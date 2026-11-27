<?php
declare(strict_types=1);

function bank_config(): array
{
    return [
        'enabled'=>getenv('BANK_TRANSFER_ENABLED')==='1',
        'approved'=>getenv('BANK_TRANSFER_APPROVED')==='1',
        'iban'=>bank_statement_iban((string)getenv('BANK_TRANSFER_IBAN'))??'',
        'beneficiary'=>trim((string)getenv('BANK_TRANSFER_BENEFICIARY')),
    ];
}

function bank_gate(PDO $pdo,?array $config=null): ?string
{
    $config??=bank_config();
    if (!$config['enabled']||!$config['approved']) return t('bank.disabled');
    if (!finance_enabled($pdo)||$config['iban']===''||$config['beneficiary']===''
        ||strlen($config['beneficiary'])>140||!preg_match('/\A[^\p{C}]+\z/u',$config['beneficiary'])) return t('bank.configuration');
    return null;
}

function bank_order(PDO $pdo,int $invoiceId): ?array
{
    $q=$pdo->prepare('SELECT * FROM market_bank_orders WHERE invoice_id=?'.($pdo->inTransaction()?' FOR SHARE':''));
    $q->execute([$invoiceId]);
    return $q->fetch() ?: null;
}

function bank_start(PDO $pdo,int $invoiceId,int $userId): void
{
    $config=bank_config();
    if ($error=bank_gate($pdo,$config)) throw new PaymentUnavailable($error);
    $pdo->beginTransaction();
    try {
        maintenance_assert_market_available($pdo);
        $invoice=payments_invoice($pdo,$invoiceId,$userId,true);
        payments_assert_invoice($invoice);
        finance_assert_invoice_snapshot($invoice,finance_snapshot($pdo,$invoiceId));
        if (bank_order($pdo,$invoiceId)) {$pdo->commit();return;}
        $q=$pdo->prepare('SELECT id FROM market_payment_attempts WHERE invoice_id=? LIMIT 1 FOR SHARE');
        $q->execute([$invoiceId]);
        if ($q->fetchColumn()) throw new PaymentConflict(t('bank.conflict'));
        if (in_array($invoice['service_code'],['category_top','home_top','banner_monthly','banner_advertising'],true)) {
            $booking=business_locked_booking($pdo,$invoiceId);
            if (!$booking||$booking['status']!=='reserved'||strtotime($booking['reserved_until'].' UTC')<=time()||business_reason($pdo)!==null) throw new PaymentConflict(t('bank.expired'));
        }
        $q=$pdo->prepare('SELECT customer_claimed_at FROM market_manual_orders WHERE invoice_id=? FOR SHARE');
        $q->execute([$invoiceId]);
        if ($q->fetchColumn()) throw new PaymentConflict(t('bank.conflict'));
        if (auction_billing_service($invoice['service_code'])) auction_billing_verify_payment($pdo,$invoice);
        $reference='GI-B-'.strtoupper(bin2hex(random_bytes(12)));
        $pdo->prepare("INSERT INTO market_payment_attempts(invoice_id,provider,status,provider_order_id,idempotency_key,mode) VALUES (?,'bank','pending',?,?,'live')")
            ->execute([$invoiceId,$reference,bin2hex(random_bytes(16))]);
        $attempt=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO market_bank_orders(invoice_id,attempt_id,reference,iban,account_hash,beneficiary,amount_cents) VALUES (?,?,?,?,?,?,?)')
            ->execute([$invoiceId,$attempt,$reference,$config['iban'],hash('sha256',$config['iban']),$config['beneficiary'],$invoice['gross_cents']]);
        $pdo->prepare("UPDATE market_invoices SET status='pending' WHERE id=?")->execute([$invoiceId]);
        audit_log($pdo,$userId,'payment.bank_ordered',$invoiceId,['attempt_id'=>$attempt]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function bank_import(PDO $pdo,int $actor,string $xml): array
{
    governance_assert_actor($pdo,$actor,'finance.manage');
    $config=bank_config();
    if ($error=bank_gate($pdo,$config)) throw new PaymentUnavailable($error);
    $parsed=bank_statement_parse($xml);
    if (count($parsed['accounts'])!==1) throw new InvalidArgumentException(t('bank.invalid_statement'));
    $account=$parsed['accounts'][0];
    $accountHash=hash('sha256',$account);
    $fileHash=hash('sha256',$xml);
    $pdo->beginTransaction();
    try {
        governance_assert_actor($pdo,$actor,'finance.manage');
        if ($account!==$config['iban']) {
            $q=$pdo->prepare('SELECT 1 FROM market_bank_orders WHERE account_hash=? AND iban=? LIMIT 1 FOR SHARE');
            $q->execute([$accountHash,$account]);
            if (!$q->fetchColumn()) throw new PaymentConflict(t('bank.conflict'));
        }
        $batch=$pdo->prepare('INSERT INTO market_bank_imports(file_hash,account_hash,imported_by,normalized_count,skipped_count) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
        $batch->execute([$fileHash,$accountHash,$actor,count($parsed['entries']),$parsed['skipped_count']]);
        $batchId=(int)$pdo->lastInsertId();
        if ($batch->rowCount()!==1) {
            $pdo->commit();
            return ['imported'=>0,'duplicates'=>count($parsed['entries']),'skipped'=>$parsed['skipped_count']];
        }
        $store=$pdo->prepare('INSERT INTO market_bank_entries(import_id,account_hash,source_reference,direction,booking_date,amount_cents,payment_reference,evidence_hash) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
        $verify=$pdo->prepare('SELECT evidence_hash FROM market_bank_entries WHERE id=? FOR SHARE');
        $result=['imported'=>0,'duplicates'=>0,'skipped'=>$parsed['skipped_count']];
        foreach ($parsed['entries'] as $entry) {
            if ($entry['iban']!==$account) throw new PaymentConflict(t('bank.conflict'));
            $evidence=hash('sha256',json_encode($entry,JSON_THROW_ON_ERROR));
            $store->execute([$batchId,$accountHash,$entry['source_reference'],$entry['direction'],$entry['booking_date'],$entry['amount_cents'],$entry['payment_reference'],$evidence]);
            if ($store->rowCount()===1) {
                $result['imported']++;
            } else {
                $verify->execute([(int)$pdo->lastInsertId()]);
                if (!hash_equals((string)$verify->fetchColumn(),$evidence)) throw new PaymentConflict(t('bank.conflict'));
                $result['duplicates']++;
            }
        }
        audit_log($pdo,$actor,'payment.bank_statement_imported',$batchId,$result);
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** Match on the frozen account and reference. Reversals always stay in review. */
function bank_review_rows(PDO $pdo,int $page,bool $all=false): array
{
    $offset=(max(1,min(10000,$page))-1)*25;
    $filter=$all?'1=1':'e.credited_invoice_id IS NULL';
    return $pdo->query("SELECT e.id,e.direction,e.source_reference,e.payment_reference,e.booking_date,e.amount_cents,o.invoice_id,
        CASE WHEN e.credited_invoice_id IS NOT NULL THEN 'booked'
             WHEN e.direction='reversal' THEN 'reversal'
             WHEN o.invoice_id IS NULL THEN 'unmatched'
             WHEN i.status<>'pending' THEN 'closed'
             WHEN o.amount_cents<>e.amount_cents THEN 'wrong_amount'
             WHEN e.booking_date<DATE(o.created_at) OR e.booking_date>UTC_DATE()+INTERVAL 1 DAY
                  OR EXISTS(SELECT 1 FROM market_bank_entries r WHERE r.account_hash=e.account_hash AND r.direction='reversal' AND (r.payment_reference=e.payment_reference OR r.source_reference=e.source_reference)) THEN 'blocked'
             ELSE 'matched' END AS state
        FROM market_bank_entries e
        LEFT JOIN market_bank_orders o ON o.reference=e.payment_reference AND o.account_hash=e.account_hash
        LEFT JOIN market_invoices i ON i.id=o.invoice_id
        WHERE $filter ORDER BY e.id DESC LIMIT 26 OFFSET $offset")->fetchAll();
}

function bank_approve(PDO $pdo,int $actor,int $entryId): int
{
    governance_assert_actor($pdo,$actor,'finance.manage');
    if ($error=bank_gate($pdo)) throw new PaymentUnavailable($error);
    $q=$pdo->prepare('SELECT o.invoice_id FROM market_bank_entries e JOIN market_bank_orders o ON o.reference=e.payment_reference AND o.account_hash=e.account_hash WHERE e.id=?');
    $q->execute([$entryId]);
    $invoiceId=(int)$q->fetchColumn();
    if (!$invoiceId) throw new PaymentConflict(t('bank.conflict'));
    $pdo->beginTransaction();
    try {
        governance_assert_actor($pdo,$actor,'finance.manage');
        $q=$pdo->prepare('SELECT * FROM market_invoices WHERE id=? FOR UPDATE');
        $q->execute([$invoiceId]);
        $invoice=$q->fetch();
        $q=$pdo->prepare('SELECT * FROM market_bank_entries WHERE id=? FOR UPDATE');
        $q->execute([$entryId]);
        $entry=$q->fetch();
        if (!$invoice||!$entry||$entry['direction']!=='credit') throw new PaymentConflict(t('bank.conflict'));
        if ((int)$entry['credited_invoice_id']===$invoiceId) {$pdo->commit();return $invoiceId;}
        $order=bank_order($pdo,$invoiceId);
        if ($entry['credited_invoice_id']!==null||$invoice['status']!=='pending'||$invoice['currency']!=='EUR'||!$order
            ||$order['reference']!==$entry['payment_reference']||$order['account_hash']!==$entry['account_hash']
            ||(int)$invoice['gross_cents']!==(int)$entry['amount_cents']||(int)$order['amount_cents']!==(int)$entry['amount_cents']
            ||$entry['booking_date']<substr($order['created_at'],0,10)||$entry['booking_date']>gmdate('Y-m-d',time()+86400)) throw new PaymentConflict(t('bank.conflict'));
        $q=$pdo->prepare("SELECT id FROM market_bank_entries WHERE account_hash=? AND direction='reversal' AND (payment_reference=? OR source_reference=?) LIMIT 1 FOR SHARE");
        $q->execute([$entry['account_hash'],$entry['payment_reference'],$entry['source_reference']]);
        if ($q->fetchColumn()) throw new PaymentConflict(t('bank.conflict'));
        $q=$pdo->prepare("SELECT * FROM market_payment_attempts WHERE id=? AND invoice_id=? AND provider='bank' AND status='pending' AND mode='live' FOR UPDATE");
        $q->execute([$order['attempt_id'],$invoiceId]);
        $attempt=$q->fetch();
        if (!$attempt||$attempt['provider_order_id']!==$entry['payment_reference']) throw new PaymentConflict(t('bank.conflict'));
        finance_assert_invoice_snapshot($invoice,finance_snapshot($pdo,$invoiceId));
        if (auction_billing_service($invoice['service_code'])) auction_billing_verify_payment($pdo,$invoice);
        $capture='BANK-'.hash('sha256',$entry['account_hash'].':'.$entry['source_reference']);
        $pdo->prepare("UPDATE market_payment_attempts SET status='paid',capture_id=? WHERE id=?")->execute([$capture,$attempt['id']]);
        $pdo->prepare("INSERT INTO market_payment_events(attempt_id,provider_event_id,kind,payload_hash) VALUES (?,?,'bank.reconciled',?)")
            ->execute([$attempt['id'],'bank-entry:'.$entryId,$entry['evidence_hash']]);
        $pdo->prepare("UPDATE market_invoices SET status='paid' WHERE id=?")->execute([$invoiceId]);
        $q=$pdo->prepare('SELECT quantity FROM market_manual_orders WHERE invoice_id=?');
        $q->execute([$invoiceId]);
        $quantity=$q->fetchColumn();
        if ($quantity!==false) manual_fulfill($pdo,$invoice+['quantity'=>$quantity]);
        finance_issue_invoice($pdo,$invoiceId,$actor);
        $pdo->prepare('UPDATE market_bank_entries SET credited_invoice_id=?,reviewed_by=?,reviewed_at=UTC_TIMESTAMP() WHERE id=?')
            ->execute([$invoiceId,$actor,$entryId]);
        audit_log($pdo,$actor,'payment.bank_reconciled',$invoiceId,['entry_id'=>$entryId,'amount_cents'=>(int)$entry['amount_cents']]);
        $seller=(int)$invoice['user_id'];
        notify_user($pdo,$seller,'payment:'.$invoiceId,
            i18n_user_text($pdo,$seller,'notification.payment_confirmed.title'),
            i18n_user_text($pdo,$seller,'notification.payment_confirmed.body',['reference'=>$invoice['invoice_number']]),
            '/user/payments.php?invoice_id='.$invoiceId,true);
        $pdo->commit();
        return $invoiceId;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof PDOException&&$error->getCode()==='23000') throw new PaymentConflict(t('bank.conflict'));
        throw $error;
    }
}
