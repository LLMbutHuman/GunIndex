<?php
declare(strict_types=1);

function bitcoin_config(): array
{
    return [
        'enabled'=>getenv('BITCOIN_ENABLED')==='1',
        'live_approved'=>getenv('BITCOIN_LIVE_APPROVED')==='1',
        'chain'=>getenv('BITCOIN_NETWORK')?:'regtest',
        'rpc_url'=>(string)getenv('BITCOIN_RPC_URL'),
        'rpc_user'=>(string)getenv('BITCOIN_RPC_USER'),
        'rpc_password'=>(string)getenv('BITCOIN_RPC_PASSWORD'),
        'wallet'=>(string)getenv('BITCOIN_WALLET'),
        'rate_cents'=>(string)getenv('BITCOIN_RATE_CENTS'),
        'rate_until'=>(string)getenv('BITCOIN_RATE_UNTIL'),
        'confirmations'=>getenv('BITCOIN_CONFIRMATIONS')?:'6',
    ];
}

function bitcoin_quote_expiry(string $timestamp): ?int
{
    $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$timestamp,new DateTimeZone('UTC'));
    if (!$date||$date->format('Y-m-d\TH:i:s\Z')!==$timestamp) return null;
    return $date->getTimestamp();
}

function bitcoin_gate(PDO $pdo,?array $config=null,bool $requireQuote=false): ?string
{
    $config??=bitcoin_config();
    if (!$config['enabled']||($config['chain']==='main'&&!$config['live_approved'])) return t('bitcoin.disabled');
    if (($config['chain']==='regtest'&&getenv('APP_ENV')!=='development')||!finance_enabled($pdo)
        ||!preg_match('/\A(?:[1-9][0-9]?|100)\z/',$config['confirmations'])) return t('bitcoin.configuration');
    try {new MarketBitcoin($config);} catch (InvalidArgumentException|PaymentUnavailable) {return t('bitcoin.configuration');}
    if ($requireQuote&&(!preg_match('/\A[1-9][0-9]{0,11}\z/',$config['rate_cents'])||(bitcoin_quote_expiry($config['rate_until'])??0)<=time())) {
        return t('bitcoin.quote_unavailable');
    }
    return null;
}

function bitcoin_decimal(int $satoshis): string
{
    return intdiv($satoshis,100000000).'.'.str_pad((string)($satoshis%100000000),8,'0',STR_PAD_LEFT);
}

function bitcoin_quote_sats(int $cents,int $rate): int
{
    if ($cents<1||$cents>9999999999||$rate<1||$rate>999999999999) throw new InvalidArgumentException(t('bitcoin.quote_unavailable'));
    $satoshis=intdiv($cents*100000000+$rate-1,$rate);
    if ($satoshis>2100000000000000) throw new InvalidArgumentException(t('bitcoin.quote_unavailable'));
    return $satoshis;
}

function bitcoin_context(array $config): string
{
    return hash('sha256',json_encode([$config['chain'],rtrim($config['rpc_url'],'/'),$config['wallet']],JSON_THROW_ON_ERROR));
}

function bitcoin_order(PDO $pdo,int $invoiceId): ?array
{
    $q=$pdo->prepare('SELECT * FROM market_bitcoin_orders WHERE invoice_id=?'.($pdo->inTransaction()?' FOR SHARE':''));
    $q->execute([$invoiceId]);
    return $q->fetch() ?: null;
}

function bitcoin_start(PDO $pdo,int $invoiceId,int $userId): void
{
    $config=bitcoin_config();
    if ($error=bitcoin_gate($pdo,$config,true)) throw new PaymentUnavailable($error);
    $pdo->beginTransaction();
    try {
        maintenance_assert_market_available($pdo);
        $invoice=payments_invoice($pdo,$invoiceId,$userId,true);
        payments_assert_invoice($invoice);
        if ($invoice['service_code']==='dealer_subscription'&&$config['chain']!=='main') throw new PaymentUnavailable(t('dealer_subscription.unavailable'));
        finance_assert_invoice_snapshot($invoice,finance_snapshot($pdo,$invoiceId));
        if (bitcoin_order($pdo,$invoiceId)) {$pdo->commit();return;}
        $q=$pdo->prepare('SELECT id FROM market_payment_attempts WHERE invoice_id=? LIMIT 1 FOR SHARE');
        $q->execute([$invoiceId]);
        if ($q->fetchColumn()) throw new PaymentConflict(t('bitcoin.conflict'));
        $q=$pdo->prepare('SELECT customer_claimed_at FROM market_manual_orders WHERE invoice_id=? FOR SHARE');
        $q->execute([$invoiceId]);
        if ($q->fetchColumn()) throw new PaymentConflict(t('bitcoin.conflict'));
        if (in_array($invoice['service_code'],['category_top','home_top','banner_monthly','banner_advertising'],true)) {
            $booking=business_locked_booking($pdo,$invoiceId);
            if (!$booking||$booking['status']!=='reserved'||strtotime($booking['reserved_until'].' UTC')<=time()||business_reason($pdo)!==null) throw new PaymentConflict(t('bitcoin.conflict'));
        }
        if (auction_billing_service($invoice['service_code'])) auction_billing_verify_payment($pdo,$invoice);
        $now=time();
        $expiry=min($now+1800,bitcoin_quote_expiry($config['rate_until'])??0);
        if ($expiry<=$now) throw new PaymentUnavailable(t('bitcoin.quote_unavailable'));
        $satoshis=bitcoin_quote_sats((int)$invoice['gross_cents'],(int)$config['rate_cents']);
        $reference='GI-BTC-'.strtoupper(bin2hex(random_bytes(12)));
        $mode=$config['chain']==='regtest'?'sandbox':'live';
        $pdo->prepare("INSERT INTO market_payment_attempts(invoice_id,provider,status,provider_order_id,idempotency_key,mode) VALUES (?,'bitcoin','pending',?,?,?)")
            ->execute([$invoiceId,$reference,bin2hex(random_bytes(16)),$mode]);
        $attempt=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO market_bitcoin_orders(invoice_id,attempt_id,reference,chain,context_hash,amount_sats,rate_cents_per_btc,required_confirmations,expires_at) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$invoiceId,$attempt,$reference,$config['chain'],bitcoin_context($config),$satoshis,(int)$config['rate_cents'],(int)$config['confirmations'],gmdate('Y-m-d H:i:s',$expiry)]);
        $pdo->prepare("UPDATE market_invoices SET status='pending' WHERE id=?")->execute([$invoiceId]);
        audit_log($pdo,$userId,'payment.bitcoin_ordered',$invoiceId,['attempt_id'=>$attempt,'network'=>$config['chain'],'satoshis'=>$satoshis]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function bitcoin_review_rows(PDO $pdo,int $page,bool $all=false): array
{
    $offset=(max(1,min(10000,$page))-1)*25;
    $filter=$all?'1=1':"status IN ('review','pending','allocating')";
    return $pdo->query("SELECT id,invoice_id,reference,address,amount_sats,status,chain,confirmations,txid,vout,expires_at,updated_at,review_reason FROM market_bitcoin_orders WHERE $filter ORDER BY id DESC LIMIT 26 OFFSET $offset")->fetchAll();
}

/** Capture only one exact output bound to an unexpired quote and the approved chain. */
function bitcoin_apply_receipts(PDO $pdo,array $order,array $receipts,string $lease): void
{
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT * FROM market_invoices WHERE id=? FOR UPDATE');
        $q->execute([$order['invoice_id']]);
        $invoice=$q->fetch();
        $current=bitcoin_order($pdo,(int)$order['invoice_id']);
        if (!$invoice||!$current||$current['lease_token']!==$lease) {$pdo->commit();return;}
        $one=count($receipts)===1?$receipts[0]:null;
        $status=$current['status'];$reason=null;$next=30;
        $expiry=strtotime($current['expires_at'].' UTC');
        if ($status==='confirmed') {
            if (!$one||$one['txid']!==$current['txid']||$one['vout']!==(int)$current['vout']
                ||$one['satoshis']!==(int)$current['amount_sats']||$one['block_hash']!==$current['block_hash']
                ||$one['confirmations']<(int)$current['required_confirmations']) {
                $status='review';$reason='reorg';
                if ($current['chain']==='main') {
                    $pdo->prepare('UPDATE market_bump_credits SET remaining=0 WHERE invoice_id=?')->execute([$invoice['id']]);
                    business_refund_invoice($pdo,(int)$invoice['id']);
                    dealer_subscription_revoke($pdo,$invoice);
                }
            }
            $next=900;
        } elseif ($receipts===[]) {
            $status=time()>=$expiry?'expired':'pending';$next=$status==='expired'?3600:30;
        } elseif ($invoice['status']!=='pending') {
            $status='review';$reason='cancelled';
        } elseif (!$one) {
            $status='review';$reason='multiple';
        } elseif ($one['satoshis']!==(int)$current['amount_sats']) {
            $status='review';$reason='amount';
        } elseif ($one['received_at']>$expiry) {
            $status='review';$reason='late';
        } elseif ($one['confirmations']>=(int)$current['required_confirmations']&&$one['block_hash']!==null) {
            bitcoin_book($pdo,$invoice,$current,$one);
            $status='confirmed';$next=900;
        } else {
            $status='pending';
        }
        $saveReceipt=$status!=='review'||$reason!=='reorg';
        $q=$pdo->prepare('UPDATE market_bitcoin_orders SET status=?,review_reason=?,txid=?,vout=?,block_hash=?,confirmations=?,lease_token=NULL,lease_until=NULL,next_check_at=TIMESTAMPADD(SECOND,?,UTC_TIMESTAMP()) WHERE id=?');
        $q->execute([$status,$reason,$saveReceipt?($one['txid']??null):$current['txid'],$saveReceipt?($one['vout']??null):$current['vout'],
            $saveReceipt?($one['block_hash']??null):$current['block_hash'],$one['confirmations']??null,$next,$current['id']]);
        if ($status==='review') audit_log($pdo,(int)$invoice['user_id'],'payment.bitcoin_review',(int)$invoice['id'],['reason'=>$reason,'source'=>'node']);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof PDOException&&$error->getCode()==='23000') throw new PaymentConflict(t('bitcoin.conflict'));
        throw $error;
    }
}

function bitcoin_book(PDO $pdo,array $invoice,array $order,array $receipt): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Bitcoin booking requires the invoice lock.');
    $mode=$order['chain']==='main'?'live':'sandbox';
    $q=$pdo->prepare("UPDATE market_payment_attempts SET status='paid',capture_id=? WHERE id=? AND invoice_id=? AND provider='bitcoin' AND status='pending' AND mode=? AND provider_order_id=?");
    $q->execute(['BTC-'.$receipt['txid'].':'.$receipt['vout'],$order['attempt_id'],$invoice['id'],$mode,$order['reference']]);
    if ($q->rowCount()!==1) throw new PaymentConflict(t('bitcoin.conflict'));
    $pdo->prepare("INSERT INTO market_payment_events(attempt_id,provider_event_id,kind,payload_hash) VALUES (?,?,'bitcoin.confirmed',?)")
        ->execute([$order['attempt_id'],'bitcoin-confirmed:'.$order['id'],hash('sha256',json_encode($receipt,JSON_THROW_ON_ERROR))]);
    $pdo->prepare("UPDATE market_invoices SET status='paid' WHERE id=?")->execute([$invoice['id']]);
    if ($mode==='sandbox') return;
    finance_assert_invoice_snapshot($invoice,finance_snapshot($pdo,(int)$invoice['id']));
    if (auction_billing_service($invoice['service_code'])) auction_billing_verify_payment($pdo,$invoice);
    $q=$pdo->prepare('SELECT quantity FROM market_manual_orders WHERE invoice_id=?');
    $q->execute([$invoice['id']]);
    $quantity=$q->fetchColumn();
    if ($quantity!==false) manual_fulfill($pdo,$invoice+['quantity'=>$quantity]);
    finance_issue_invoice($pdo,(int)$invoice['id']);
}

function bitcoin_next(PDO $pdo,array $config,?MarketBitcoin $node=null): bool
{
    if (bitcoin_gate($pdo,$config)!==null) return false;
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare("SELECT * FROM market_bitcoin_orders WHERE context_hash=? AND (status IN ('allocating','pending','confirmed') OR (status='expired' AND address IS NOT NULL)) AND next_check_at<=UTC_TIMESTAMP() AND (lease_until IS NULL OR lease_until<UTC_TIMESTAMP()) ORDER BY next_check_at,id LIMIT 1 FOR UPDATE SKIP LOCKED");
        $q->execute([bitcoin_context($config)]);
        $order=$q->fetch();
        if (!$order) {$pdo->commit();return false;}
        $lease=bin2hex(random_bytes(16));
        $pdo->prepare('UPDATE market_bitcoin_orders SET lease_token=?,lease_until=UTC_TIMESTAMP()+INTERVAL 4 MINUTE WHERE id=?')->execute([$lease,$order['id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    $node??=new MarketBitcoin($config);
    try {
        if ($order['address']===null) {
            $q=$pdo->prepare('SELECT status FROM market_invoices WHERE id=?');
            $q->execute([$order['invoice_id']]);
            $expired=strtotime($order['expires_at'].' UTC')<=time()||$q->fetchColumn()!=='pending';
            $address=$expired?null:$node->address($order['reference']);
            $q=$pdo->prepare('UPDATE market_bitcoin_orders SET status=?,address=?,lease_token=NULL,lease_until=NULL,next_check_at=UTC_TIMESTAMP() WHERE id=? AND lease_token=?');
            $q->execute([$expired?'expired':'pending',$address,$order['id'],$lease]);
        } else {
            $knownTx=$order['status']==='confirmed'?$order['txid']:null;
            bitcoin_apply_receipts($pdo,$order,$node->receipts($order['address'],$knownTx),$lease);
        }
    } catch (PaymentConflict|PaymentUnavailable $error) {
        $q=$pdo->prepare("UPDATE market_bitcoin_orders SET status=IF(?=1,'review',status),review_reason=IF(?=1,'conflict',review_reason),lease_token=NULL,lease_until=NULL,next_check_at=UTC_TIMESTAMP()+INTERVAL 2 MINUTE WHERE id=? AND lease_token=?");
        $conflict=(int)($error instanceof PaymentConflict);
        $q->execute([$conflict,$conflict,$order['id'],$lease]);
    }
    return true;
}
