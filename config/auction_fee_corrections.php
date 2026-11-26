<?php
declare(strict_types=1);

/** The caller holds its invoice lock before taking the common assessment lock. */
function auction_fee_lock_charge(PDO $pdo,int $invoiceId): ?array
{
    if (!$pdo->inTransaction()) throw new LogicException('Auction fee lock requires a transaction.');
    $q=$pdo->prepare('SELECT * FROM market_auction_fee_charges WHERE invoice_id=? FOR UPDATE');
    $q->execute([$invoiceId]);
    if ($charge=$q->fetch()) return $charge+['payable_cents'=>(int)$charge['gross_cents']];
    $q=$pdo->prepare('SELECT charge_id,remaining_cents FROM market_auction_fee_corrections WHERE replacement_invoice_id=? FOR SHARE');
    $q->execute([$invoiceId]);
    if (!$correction=$q->fetch()) return null;
    $q=$pdo->prepare('SELECT * FROM market_auction_fee_charges WHERE id=? FOR UPDATE');
    $q->execute([$correction['charge_id']]);
    return ($charge=$q->fetch()) ? $charge+['payable_cents'=>(int)$correction['remaining_cents']] : null;
}

function auction_fee_replacement(PDO $pdo,int $invoiceId,bool $locked=false): ?array
{
    $q=$pdo->prepare('SELECT * FROM market_auction_fee_corrections WHERE previous_invoice_id=?'.($locked?' FOR SHARE':''));
    $q->execute([$invoiceId]);
    return $q->fetch() ?: null;
}

function auction_fee_has_open_late_receipts(PDO $pdo,int $chargeId,bool $locked=false): bool
{
    $q=$pdo->prepare('SELECT l.attempt_id FROM market_auction_late_receipts l JOIN market_auction_fee_corrections c ON c.previous_invoice_id=l.previous_invoice_id WHERE c.charge_id=? AND l.refund_id IS NULL LIMIT 1'.($locked?' FOR SHARE':''));
    $q->execute([$chargeId]);
    return (bool)$q->fetchColumn();
}

function auction_fee_assert_late_receipts_resolved(PDO $pdo,int $chargeId): void
{
    if (auction_fee_has_open_late_receipts($pdo,$chargeId,true)) throw new PaymentConflict(t('auction_correction.unresolved'));
}

function auction_fee_assert_revisable(PDO $pdo,array $invoice): void
{
    if (!in_array($invoice['status'],['issued','pending','manual_rejected'],true)
        || ($invoice['customer_claimed_at']!==null&&$invoice['status']!=='manual_rejected')) {
        throw new PaymentConflict(t('auction_correction.ineligible'));
    }
    $q=$pdo->prepare('SELECT id FROM market_payment_attempts WHERE invoice_id=? LIMIT 1 FOR SHARE');
    $q->execute([$invoice['id']]);
    if ($q->fetchColumn()) throw new PaymentConflict(t('auction_correction.ineligible'));
    $q=$pdo->prepare('SELECT id FROM market_invoice_documents WHERE invoice_id=? LIMIT 1 FOR SHARE');
    $q->execute([$invoice['id']]);
    if ($q->fetchColumn()) throw new PaymentConflict(t('auction_correction.ineligible'));
}

function auction_fee_correct(PDO $pdo,int $actor,int $invoiceId,int $reduction,string $reason): int
{
    governance_assert_actor($pdo,$actor,'finance.manage');
    $reason=trim($reason);
    if ($reduction<1||mb_strlen($reason)<10||mb_strlen($reason)>2000) throw new InvalidArgumentException(t('auction_correction.invalid_amount'));
    $pdo->beginTransaction();
    try {
        governance_assert_actor($pdo,$actor,'finance.manage');
        $q=$pdo->prepare('SELECT i.*,o.reference,o.recipient,o.listing_id,o.terms_hash,o.terms_text,o.terms_language,o.customer_claimed_at FROM market_invoices i JOIN market_manual_orders o ON o.invoice_id=i.id WHERE i.id=? FOR UPDATE');
        $q->execute([$invoiceId]);
        $invoice=$q->fetch();
        if (!$invoice||!auction_billing_service($invoice['service_code'])||$invoice['currency']!=='EUR') throw new PaymentConflict(t('auction_correction.ineligible'));
        if ($previous=auction_fee_replacement($pdo,$invoiceId,true)) {
            if ((int)$previous['previous_cents']-(int)$previous['remaining_cents']!==$reduction||$previous['reason']!==$reason) {
                throw new PaymentConflict(t('auction_correction.ineligible'));
            }
            $pdo->commit();
            return (int)$previous['replacement_invoice_id'];
        }
        $charge=auction_fee_lock_charge($pdo,$invoiceId);
        if (!$charge||$charge['waived_at']!==null||(int)$charge['listing_id']!==(int)$invoice['listing_id']
            ||$charge['payable_cents']!==(int)$invoice['gross_cents']||$invoice['invoice_number']!==$invoice['reference']) {
            throw new PaymentConflict(t('auction_correction.ineligible'));
        }
        auction_fee_assert_revisable($pdo,$invoice);
        auction_fee_assert_late_receipts_resolved($pdo,(int)$charge['id']);
        $gross=(int)$invoice['gross_cents'];
        if ($reduction>=$gross) throw new InvalidArgumentException(t('auction_correction.invalid_amount'));
        $snapshot=finance_snapshot($pdo,$invoiceId);
        finance_assert_invoice_snapshot($invoice,$snapshot);
        $remaining=$gross-$reduction;
        $reference='GI-'.strtoupper(bin2hex(random_bytes(10)));
        $seller=(int)$invoice['user_id'];
        $pdo->prepare("INSERT INTO market_invoices(user_id,invoice_number,service_code,fee_version,gross_cents,currency,status) VALUES (?,?,?,?,?,'EUR','issued')")
            ->execute([$seller,$reference,$invoice['service_code'],$invoice['fee_version'],$remaining]);
        $replacement=(int)$pdo->lastInsertId();
        $q=$pdo->prepare('SELECT created_at FROM market_invoices WHERE id=?');
        $q->execute([$replacement]);
        $tax=fees_tax_split($remaining,(int)$snapshot['tax_bps'],true);
        $snapshot=array_replace($snapshot,$tax,[
            'order_reference'=>$reference,
            'ordered_at'=>$q->fetchColumn(),
            'previous_order_reference'=>$invoice['reference'],
        ]);
        $json=auction_billing_json($snapshot);
        $pdo->prepare('INSERT INTO market_invoice_snapshots(invoice_id,user_id,payload_ciphertext,payload_hash) VALUES (?,?,?,?)')
            ->execute([$replacement,$seller,crypto_encrypt('finance',$json),hash('sha256',$json)]);
        $pdo->prepare('INSERT INTO market_manual_orders(invoice_id,user_id,listing_id,reference,recipient,quantity,net_cents,tax_cents,tax_bps,terms_hash,terms_text,terms_language,request_key) VALUES (?,?,?,?,?,1,?,?,?,?,?,?,?)')
            ->execute([$replacement,$seller,$charge['listing_id'],$reference,$invoice['recipient'],$tax['net_cents'],$tax['tax_cents'],$snapshot['tax_bps'],$invoice['terms_hash'],$invoice['terms_text'],$invoice['terms_language'],hash('sha256','auction-correction:'.$invoiceId)]);
        // The accepted documents and their original timestamps remain the historical evidence.
        $pdo->prepare("INSERT INTO market_contract_receipts(user_id,scope,context_key,invoice_id,document_id,content_hash,created_at) SELECT user_id,scope,?,?,document_id,content_hash,created_at FROM market_contract_receipts WHERE invoice_id=? AND user_id=? AND scope='checkout'")
            ->execute(['invoice:'.$replacement,$replacement,$invoiceId,$seller]);
        $pdo->prepare('INSERT INTO market_auction_fee_corrections(previous_invoice_id,replacement_invoice_id,charge_id,previous_cents,remaining_cents,actor_id,reason) VALUES (?,?,?,?,?,?,?)')
            ->execute([$invoiceId,$replacement,$charge['id'],$gross,$remaining,$actor,$reason]);
        $pdo->prepare("UPDATE market_invoices SET status='void' WHERE id=?")->execute([$invoiceId]);
        $event=$pdo->prepare('INSERT INTO market_finance_events(invoice_id,actor_id,kind,amount_cents,reference) VALUES (?,?,?,?,?)');
        $event->execute([$invoiceId,$actor,'fee_replaced',-$gross,'auction-replaced:'.$invoiceId]);
        $event->execute([$replacement,$actor,'fee_due',$remaining,'auction-corrected:'.$replacement]);
        audit_log($pdo,$actor,'auction.fee_corrected',(int)$charge['listing_id'],['old_invoice_id'=>$invoiceId,'new_invoice_id'=>$replacement,'reduction_cents'=>$reduction,'reason'=>$reason]);
        notify_user($pdo,$seller,'auction-fee-corrected:'.$invoiceId,
            i18n_user_text($pdo,$seller,'auction_correction.title'),
            i18n_user_text($pdo,$seller,'auction_correction.notice',['amount'=>money_eur($remaining),'reference'=>$reference]),
            '/user/billing.php?order='.$replacement,true);
        $pdo->commit();
        return $replacement;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** Store a verified payment to an obsolete reference as unallocated cash. */
function auction_fee_record_late_receipt(PDO $pdo,int $actor,int $invoiceId,string $reference,string $transaction,int $amount,string $reason): int
{
    governance_assert_actor($pdo,$actor,'finance.manage');
    $transaction=strtoupper(trim($transaction));
    $reason=trim($reason);
    if ($amount<1||$amount>9999999999||!preg_match('/\A[A-Z0-9]{10,64}\z/',$transaction)||mb_strlen($reason)<10||mb_strlen($reason)>1000) {
        throw new InvalidArgumentException(t('auction_correction.invalid_receipt'));
    }
    $pdo->beginTransaction();
    try {
        governance_assert_actor($pdo,$actor,'finance.manage');
        $q=$pdo->prepare('SELECT i.*,o.reference,o.recipient FROM market_invoices i JOIN market_manual_orders o ON o.invoice_id=i.id WHERE i.id=? FOR UPDATE');
        $q->execute([$invoiceId]);
        $invoice=$q->fetch();
        if (!$invoice||$invoice['status']!=='void'||$invoice['currency']!=='EUR'||!hash_equals($invoice['reference'],$reference)
            ||!auction_fee_replacement($pdo,$invoiceId,true)||!auction_fee_lock_charge($pdo,$invoiceId)) {
            throw new PaymentConflict(t('auction_correction.invalid_receipt'));
        }
        $q=$pdo->prepare('SELECT a.id,a.invoice_id,l.amount_cents FROM market_payment_attempts a LEFT JOIN market_auction_late_receipts l ON l.attempt_id=a.id WHERE a.capture_id=?');
        $q->execute([$transaction]);
        if ($previous=$q->fetch()) {
            if ((int)$previous['invoice_id']!==$invoiceId||(int)$previous['amount_cents']!==$amount) throw new PaymentConflict(t('auction_correction.invalid_receipt'));
            $pdo->commit();
            return (int)$previous['id'];
        }
        $q=$pdo->prepare('SELECT id FROM market_refunds WHERE transaction_id=?');
        $q->execute([$transaction]);
        if ($q->fetchColumn()) throw new PaymentConflict(t('auction_correction.invalid_receipt'));
        $pdo->prepare("INSERT INTO market_payment_attempts(invoice_id,provider,status,capture_id,idempotency_key,mode) VALUES (?,'paypal_manual','manual_late_review',?,?,'live')")
            ->execute([$invoiceId,$transaction,bin2hex(random_bytes(16))]);
        $attempt=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO market_payment_events(attempt_id,provider_event_id,kind,payload_hash) VALUES (?,?,'manual_late_receipt',?)")
            ->execute([$attempt,'manual:'.$transaction,hash('sha256',$reference.':'.$amount.':'.$invoice['recipient'])]);
        $pdo->prepare('INSERT INTO market_auction_late_receipts(attempt_id,previous_invoice_id,amount_cents,reviewed_by,reason) VALUES (?,?,?,?,?)')
            ->execute([$attempt,$invoiceId,$amount,$actor,$reason]);
        $pdo->prepare("INSERT INTO market_finance_events(invoice_id,actor_id,kind,amount_cents,reference) VALUES (?,?,'cash_in',?,?)")
            ->execute([$invoiceId,$actor,$amount,'auction-late-receipt:'.$attempt]);
        audit_log($pdo,$actor,'auction.late_receipt_recorded',$invoiceId,['attempt_id'=>$attempt,'amount_cents'=>$amount]);
        $seller=(int)$invoice['user_id'];
        notify_user($pdo,$seller,'auction-late-receipt:'.$attempt,
            i18n_user_text($pdo,$seller,'auction_correction.late_title'),
            i18n_user_text($pdo,$seller,'auction_correction.late_notice',['reference'=>$reference]),
            '/user/billing.php?order='.$invoiceId,true);
        $pdo->commit();
        return $attempt;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof PDOException&&$error->getCode()==='23000') throw new PaymentConflict(t('auction_correction.invalid_receipt'));
        throw $error;
    }
}

/** Record the completed full return verified at the provider. */
function auction_fee_record_late_refund(PDO $pdo,int $actor,int $attempt,int $amount,string $transaction,string $reason): int
{
    governance_assert_actor($pdo,$actor,'finance.manage');
    $transaction=strtoupper(trim($transaction));
    $reason=trim($reason);
    if ($amount<1||!preg_match('/\A[A-Z0-9]{10,64}\z/',$transaction)||mb_strlen($reason)<10||mb_strlen($reason)>1000) {
        throw new InvalidArgumentException(t('auction_correction.invalid_refund'));
    }
    $pdo->beginTransaction();
    try {
        governance_assert_actor($pdo,$actor,'finance.manage');
        $q=$pdo->prepare('SELECT previous_invoice_id FROM market_auction_late_receipts WHERE attempt_id=?');
        $q->execute([$attempt]);
        $invoiceId=(int)$q->fetchColumn();
        $q=$pdo->prepare('SELECT id,user_id,invoice_number FROM market_invoices WHERE id=? AND status=\'void\' FOR UPDATE');
        $q->execute([$invoiceId]);
        $invoice=$q->fetch();
        if (!$invoice||!auction_fee_lock_charge($pdo,$invoiceId)) throw new PaymentConflict(t('auction_correction.invalid_refund'));
        $q=$pdo->prepare('SELECT l.*,r.transaction_id FROM market_auction_late_receipts l LEFT JOIN market_refunds r ON r.id=l.refund_id WHERE l.attempt_id=? AND l.previous_invoice_id=? FOR UPDATE');
        $q->execute([$attempt,$invoiceId]);
        $receipt=$q->fetch();
        if (!$receipt||(int)$receipt['amount_cents']!==$amount) throw new PaymentConflict(t('auction_correction.invalid_refund'));
        if ($receipt['refund_id']!==null) {
            if ($receipt['transaction_id']!==$transaction) throw new PaymentConflict(t('auction_correction.invalid_refund'));
            $pdo->commit();
            return (int)$receipt['refund_id'];
        }
        $q=$pdo->prepare('SELECT id FROM market_payment_attempts WHERE capture_id=?');
        $q->execute([$transaction]);
        if ($q->fetchColumn()) throw new PaymentConflict(t('auction_correction.invalid_refund'));
        $pdo->prepare('INSERT INTO market_refunds(invoice_id,transaction_id,amount_cents,verified_by,reason) VALUES (?,?,?,?,?)')
            ->execute([$invoiceId,$transaction,$amount,$actor,$reason]);
        $refund=(int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE market_auction_late_receipts SET refund_id=? WHERE attempt_id=?')->execute([$refund,$attempt]);
        $pdo->prepare("UPDATE market_payment_attempts SET status='manual_late_returned' WHERE id=?")->execute([$attempt]);
        $pdo->prepare("INSERT INTO market_finance_events(invoice_id,actor_id,kind,amount_cents,reference) VALUES (?,?,'cash_refund',?,?)")
            ->execute([$invoiceId,$actor,-$amount,'refund:'.$transaction]);
        audit_log($pdo,$actor,'auction.late_receipt_returned',$invoiceId,['attempt_id'=>$attempt,'refund_id'=>$refund]);
        $seller=(int)$invoice['user_id'];
        notify_user($pdo,$seller,'refund:'.$refund,i18n_user_text($pdo,$seller,'notification.refund_recorded.title'),
            i18n_user_text($pdo,$seller,'notification.refund_recorded.body',['reference'=>$invoice['invoice_number']]),
            '/user/billing.php?order='.$invoiceId,true);
        $pdo->commit();
        return $refund;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof PDOException&&$error->getCode()==='23000') throw new PaymentConflict(t('auction_correction.invalid_refund'));
        throw $error;
    }
}
