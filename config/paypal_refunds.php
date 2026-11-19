<?php
declare(strict_types=1);

function paypal_refund_gate(PDO $pdo,array $config): ?string
{
    if (empty($config['refunds_enabled'])) return t('paypal_refunds.disabled');
    if ($reason=payments_paypal_reason($config)) return $reason;
    if ($config['mode']==='live'&&(empty($config['refunds_live_approved'])||!finance_enabled($pdo))) {
        return t('paypal_refunds.live_gate');
    }
    return null;
}

function paypal_refund_context(array $config): string
{
    return hash('sha256',json_encode([$config['mode'],$config['merchant_id'],$config['client_id']],JSON_THROW_ON_ERROR));
}

function paypal_refund_queue(PDO $pdo,int $actor,int $requestId,int $amount,string $reason): int
{
    $config=payments_config();
    governance_assert_actor($pdo,$actor,'finance.manage');
    if ($error=paypal_refund_gate($pdo,$config)) throw new PaymentUnavailable($error);
    $reason=trim($reason);
    if ($amount<1||mb_strlen($reason)<10||mb_strlen($reason)>2000) throw new InvalidArgumentException(t('paypal_refunds.ineligible'));
    $q=$pdo->prepare('SELECT invoice_id FROM market_refund_requests WHERE id=?');
    $q->execute([$requestId]);
    $invoiceId=(int)$q->fetchColumn();
    $pdo->beginTransaction();
    try {
        governance_assert_actor($pdo,$actor,'finance.manage');
        maintenance_assert_market_available($pdo);
        $q=$pdo->prepare('SELECT * FROM market_invoices WHERE id=? FOR UPDATE');
        $q->execute([$invoiceId]);
        $invoice=$q->fetch();
        $q=$pdo->prepare('SELECT * FROM market_paypal_refund_jobs WHERE request_id=? FOR UPDATE');
        $q->execute([$requestId]);
        if ($existing=$q->fetch()) {
            if ((int)$existing['amount_cents']!==$amount||$existing['reason']!==$reason
                ||$existing['context_hash']!==paypal_refund_context($config)) throw new PaymentConflict(t('paypal_refunds.ineligible'));
            $pdo->commit();
            return (int)$existing['id'];
        }
        $q=$pdo->prepare("SELECT * FROM market_refund_requests WHERE id=? AND invoice_id=? AND status='requested' FOR UPDATE");
        $q->execute([$requestId,$invoiceId]);
        $request=$q->fetch();
        if (!$invoice||!$request||(int)$invoice['user_id']!==(int)$request['user_id']||$invoice['currency']!=='EUR'
            ||!in_array($invoice['status'],['paid','partially_refunded'],true)||$amount>(int)$request['requested_cents']
            ||$amount>(int)$invoice['gross_cents']-finance_refunded_cents($pdo,$invoiceId)) throw new PaymentConflict(t('paypal_refunds.ineligible'));
        payments_assert_automatic($pdo,$invoiceId);
        $q=$pdo->prepare("SELECT id,capture_id,provider_order_id FROM market_payment_attempts WHERE invoice_id=? AND provider='paypal' AND mode=? AND status='paid' LIMIT 2 FOR UPDATE");
        $q->execute([$invoiceId,$config['mode']]);
        $attempts=$q->fetchAll();
        if (count($attempts)!==1||empty($attempts[0]['capture_id'])||empty($attempts[0]['provider_order_id'])) throw new PaymentConflict(t('paypal_refunds.ineligible'));
        if ($config['mode']==='live') {
            finance_assert_invoice_snapshot($invoice,finance_snapshot($pdo,$invoiceId));
            $q=$pdo->prepare('SELECT id FROM market_invoice_documents WHERE origin_invoice_id=?');
            $q->execute([$invoiceId]);
            if (!$q->fetchColumn()) throw new PaymentConflict(t('paypal_refunds.ineligible'));
        }
        $q=$pdo->prepare("SELECT id FROM market_paypal_refund_jobs WHERE invoice_id=? AND status IN ('queued','pending','review') LIMIT 1 FOR SHARE");
        $q->execute([$invoiceId]);
        if ($q->fetchColumn()) throw new PaymentConflict(t('paypal_refunds.in_progress'));
        $pdo->prepare('INSERT INTO market_paypal_refund_jobs(request_id,invoice_id,attempt_id,approved_by,amount_cents,reason,mode,context_hash,reference) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$requestId,$invoiceId,$attempts[0]['id'],$actor,$amount,$reason,$config['mode'],paypal_refund_context($config),'GI-R-'.bin2hex(random_bytes(16))]);
        $id=(int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE market_refund_requests SET status='processing' WHERE id=?")->execute([$requestId]);
        audit_log($pdo,$actor,'finance.paypal_refund_queued',$invoiceId,['job_id'=>$id,'amount_cents'=>$amount,'mode'=>$config['mode']]);
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function paypal_refund_find(PDO $pdo,int $id,bool $locked=false): ?array
{
    $q=$pdo->prepare('SELECT j.*,a.capture_id,a.provider_order_id FROM market_paypal_refund_jobs j JOIN market_payment_attempts a ON a.id=j.attempt_id AND a.invoice_id=j.invoice_id WHERE j.id=?'.($locked?' FOR UPDATE':''));
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

function paypal_refund_verify(array $job,array $resource): array
{
    $id=$resource['id']??null;
    $status=$resource['status']??null;
    $hostPattern=$job['mode']==='live'?'api(?:-m)?\.paypal\.com':'api(?:-m)?\.sandbox\.paypal\.com';
    $parent='~\Ahttps://'.$hostPattern.'/v2/payments/captures/'.preg_quote($job['capture_id'],'~').'\z~';
    $links=$resource['links']??null;
    $parentLinks=is_array($links)?array_values(array_filter($links,static fn($link)=>is_array($link)&&($link['rel']??null)==='up')):[];
    if (!is_string($id)||!preg_match('/\A[A-Z0-9]{6,64}\z/',$id)||$id===$job['capture_id']
        ||($job['provider_refund_id']!==null&&$job['provider_refund_id']!==$id)
        ||($resource['custom_id']??null)!==$job['reference']
        ||payments_api_cents($resource['amount']??null)!==(int)$job['amount_cents']
        ||!in_array($status,['COMPLETED','PENDING','FAILED','CANCELLED'],true)
        ||count($parentLinks)!==1||($parentLinks[0]['method']??null)!=='GET'
        ||!is_string($parentLinks[0]['href']??null)||!preg_match($parent,$parentLinks[0]['href'])) {
        throw new PaymentConflict(t('paypal_refunds.mismatch'));
    }
    return [$id,strtolower($status)];
}

/** Final status changes and accounting commit together after network I/O. */
function paypal_refund_apply(PDO $pdo,array $job,array $resource,?string $lease=null): void
{
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT * FROM market_invoices WHERE id=? FOR UPDATE');
        $q->execute([$job['invoice_id']]);
        $invoice=$q->fetch();
        $current=paypal_refund_find($pdo,(int)$job['id'],true);
        if (!$invoice||!$current||$current['first_dispatch_at']===null) throw new PaymentConflict(t('paypal_refunds.ineligible'));
        if (($lease!==null&&$current['lease_token']!==$lease)||in_array($current['status'],['completed','failed','cancelled'],true)) {
            $pdo->commit();
            return;
        }
        [$id,$status]=paypal_refund_verify($current,$resource);
        $actor=(int)$current['approved_by'];
        if ($status==='completed') {
            if ($current['mode']==='live') {
                finance_book_verified_refund($pdo,$invoice,$actor,(int)$current['amount_cents'],$id,$current['reason'],(int)$current['request_id'],false);
            } else {
                $total=finance_refunded_cents($pdo,(int)$invoice['id'])+(int)$current['amount_cents'];
                if ($total>(int)$invoice['gross_cents']||!in_array($invoice['status'],['paid','partially_refunded'],true)) throw new PaymentConflict(t('paypal_refunds.ineligible'));
                $pdo->prepare('UPDATE market_invoices SET status=? WHERE id=?')->execute([$total===(int)$invoice['gross_cents']?'refunded':'partially_refunded',$invoice['id']]);
                $pdo->prepare("UPDATE market_refund_requests SET status='sandbox_recorded',decision_reason=? WHERE id=?")->execute([$current['reason'],$current['request_id']]);
            }
        } elseif (in_array($status,['failed','cancelled'],true)) {
            $pdo->prepare("UPDATE market_refund_requests SET status='requested' WHERE id=? AND status='processing'")->execute([$current['request_id']]);
        }
        $pdo->prepare('UPDATE market_paypal_refund_jobs SET status=?,provider_refund_id=?,lease_token=NULL,lease_until=NULL,next_check_at=UTC_TIMESTAMP()+INTERVAL 2 MINUTE WHERE id=?')
            ->execute([$status,$id,$current['id']]);
        if ($current['status']!==$status) audit_log($pdo,$actor,'finance.paypal_refund_'.$status,(int)$invoice['id'],['job_id'=>(int)$current['id'],'mode'=>$current['mode']]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof PDOException&&$error->getCode()==='23000') throw new PaymentConflict(t('paypal_refunds.mismatch'));
        throw $error;
    }
}

/** Reserve one network operation across concurrent workers and process crashes. */
function paypal_refund_next(PDO $pdo,array $config,?MarketPayPal $client=null): bool
{
    if (paypal_refund_gate($pdo,$config)!==null) return false;
    $context=paypal_refund_context($config);
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare("SELECT id FROM market_paypal_refund_jobs WHERE context_hash=? AND status IN ('queued','pending') AND next_check_at<=UTC_TIMESTAMP() AND (lease_until IS NULL OR lease_until<UTC_TIMESTAMP()) ORDER BY next_check_at,id LIMIT 1 FOR UPDATE SKIP LOCKED");
        $q->execute([$context]);
        $id=(int)$q->fetchColumn();
        if (!$id) {$pdo->commit();return false;}
        $job=paypal_refund_find($pdo,$id,true);
        if ($job['provider_refund_id']===null&&$job['first_dispatch_at']!==null
            &&strtotime($job['first_dispatch_at'].' UTC')<=time()-3600) {
            $pdo->prepare("UPDATE market_paypal_refund_jobs SET status='review',lease_token=NULL,lease_until=NULL WHERE id=?")->execute([$id]);
            audit_log($pdo,(int)$job['approved_by'],'finance.paypal_refund_review',(int)$job['invoice_id'],['job_id'=>$id,'reason'=>'dispatch_window_elapsed']);
            $pdo->commit();
            return true;
        }
        $lease=bin2hex(random_bytes(16));
        $pdo->prepare('UPDATE market_paypal_refund_jobs SET lease_token=?,lease_until=UTC_TIMESTAMP()+INTERVAL 2 MINUTE,first_dispatch_at=COALESCE(first_dispatch_at,UTC_TIMESTAMP()) WHERE id=?')->execute([$lease,$id]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    $client??=new MarketPayPal($config);
    try {
        if ($job['provider_refund_id']!==null) {
            $response=$client->refundDetails($job['provider_refund_id']);
        } else {
            $q=$pdo->prepare('SELECT * FROM market_invoices WHERE id=?');
            $q->execute([$job['invoice_id']]);
            $invoice=$q->fetch();
            $order=$client->order($job['provider_order_id']);
            if (!$invoice||$client->validate($order,$invoice,$job['provider_order_id'],true,true)!==$job['capture_id']) throw new PaymentConflict(t('paypal_refunds.mismatch'));
            $response=$client->refund($job['capture_id'],(int)$job['amount_cents'],$job['reference'],$job['reference']);
        }
        paypal_refund_apply($pdo,$job,$response,$lease);
    } catch (PaymentUnavailable|PaymentConflict $error) {
        $q=$pdo->prepare("UPDATE market_paypal_refund_jobs SET status=IF(?=1,'review',status),lease_token=NULL,lease_until=NULL,next_check_at=UTC_TIMESTAMP()+INTERVAL 2 MINUTE WHERE id=? AND lease_token=?");
        $q->execute([(int)($error instanceof PaymentConflict),$id,$lease]);
    }
    return true;
}

function paypal_refund_reconcile(PDO $pdo,int $actor,int $id,string $providerId): void
{
    governance_assert_actor($pdo,$actor,'finance.manage');
    $config=payments_config();
    if ($error=paypal_refund_gate($pdo,$config)) throw new PaymentUnavailable($error);
    $job=paypal_refund_find($pdo,$id);
    if (!$job||$job['context_hash']!==paypal_refund_context($config)||$job['first_dispatch_at']===null
        ||!in_array($job['status'],['queued','pending','review'],true)) throw new PaymentConflict(t('paypal_refunds.ineligible'));
    $providerId=$providerId!==''?trim($providerId):($job['provider_refund_id']??'');
    $resource=(new MarketPayPal($config))->refundDetails($providerId);
    if (($resource['id']??null)!==$providerId) throw new PaymentConflict(t('paypal_refunds.mismatch'));
    paypal_refund_apply($pdo,$job,$resource);
}

/** A verified webhook only identifies a candidate. The validated GET decides its status. */
function paypal_refund_webhook(PDO $pdo,array $config,MarketPayPal $client,array $event): void
{
    if (paypal_refund_gate($pdo,$config)!==null) return;
    $id=$event['resource']['id']??null;
    $reference=$event['resource']['custom_id']??null;
    if (!is_string($id)||!preg_match('/\A[A-Z0-9]{6,64}\z/',$id)) return;
    $q=$pdo->prepare('SELECT id FROM market_paypal_refund_jobs WHERE context_hash=? AND (provider_refund_id=? OR reference=?)');
    $q->execute([paypal_refund_context($config),$id,is_string($reference)&&strlen($reference)===37?$reference:'']);
    $ids=$q->fetchAll(PDO::FETCH_COLUMN);
    if (count($ids)>1) throw new PaymentConflict(t('paypal_refunds.mismatch'));
    if (!$ids) return;
    $job=paypal_refund_find($pdo,(int)$ids[0]);
    if ($job['first_dispatch_at']===null||in_array($job['status'],['completed','failed','cancelled'],true)) return;
    $resource=$client->refundDetails($id);
    if (($resource['id']??null)!==$id) throw new PaymentConflict(t('paypal_refunds.mismatch'));
    paypal_refund_apply($pdo,$job,$resource);
}
