<?php
declare(strict_types=1);

function dealer_subscription_terms(): array
{
    $raw = getenv('DEALER_SUBSCRIPTION_CENTS');
    $price = is_string($raw) && preg_match('/\A[1-9][0-9]{0,7}\z/', $raw) ? (int)$raw : 0;
    return [
        'service'=>'dealer_subscription','label'=>t('dealer_subscription.title'),'gross_cents'=>$price,'currency'=>'EUR',
        'term_days'=>30,'version'=>'sub-1-' . substr(hash('sha256', 'dealer_subscription:30:' . $price),0,20), 'available'=>false,
    ];
}

function dealer_subscription_available(PDO $pdo): bool
{
    if (getenv('DEALER_SUBSCRIPTION_ENABLED') !== '1' || getenv('DEALER_SUBSCRIPTION_APPROVED') !== '1'
        || !finance_enabled($pdo) || dealer_subscription_terms()['gross_cents'] < 1) return false;
    $paypal = payments_config();
    $bitcoin = bitcoin_config();
    return ($paypal['mode'] === 'live' && payments_paypal_reason($paypal) === null)
        || bank_gate($pdo) === null || ($bitcoin['chain'] === 'main' && bitcoin_gate($pdo, $bitcoin, true) === null);
}

function dealer_subscription_active(PDO $pdo, int $owner): bool
{
    $query = $pdo->prepare("SELECT 1 FROM market_dealer_subscriptions s JOIN market_invoices i ON i.id=s.invoice_id WHERE s.owner_id=? AND s.revoked_at IS NULL AND s.starts_at<=UTC_TIMESTAMP() AND s.ends_at>UTC_TIMESTAMP() AND i.status IN ('paid','partially_refunded') LIMIT 1");
    $query->execute([$owner]);
    return $query->fetchColumn() !== false;
}

function dealer_subscription_history(PDO $pdo, int $owner): array
{
    $query = $pdo->prepare('SELECT s.invoice_id,s.term_days,s.starts_at,s.ends_at,s.revoked_at,i.invoice_number,i.status,i.gross_cents,i.currency FROM market_dealer_subscriptions s JOIN market_invoices i ON i.id=s.invoice_id AND i.user_id=s.owner_id WHERE s.owner_id=? ORDER BY s.invoice_id DESC LIMIT 50');
    $query->execute([$owner]);
    return $query->fetchAll();
}

function dealer_subscription_order(PDO $pdo, int $owner, string $request, string $version, array $input): int
{
    $request = trade_hex($request);
    if (($input['confirm_subscription'] ?? null) !== '1') throw new InvalidArgumentException(t('dealer_subscription.invalid'));
    return trade_transaction($pdo, static function () use ($pdo,$owner,$request,$version,$input): int {
        dealer_team_lock_owner($pdo,$owner);
        $prior = $pdo->prepare('SELECT s.invoice_id,i.fee_version FROM market_dealer_subscriptions s JOIN market_invoices i ON i.id=s.invoice_id WHERE s.owner_id=? AND s.request_key=?');
        $prior->execute([$owner,$request]);
        if ($previous = $prior->fetch()) {
            if (!hash_equals($previous['fee_version'],$version)) throw new PaymentConflict(t('dealer_subscription.changed'));
            return (int)$previous['invoice_id'];
        }
        $terms = dealer_subscription_terms();
        if (!dealer_subscription_available($pdo)) throw new PaymentUnavailable(t('dealer_subscription.unavailable'));
        if (!hash_equals($terms['version'],$version)) throw new PaymentConflict(t('dealer_subscription.changed'));
        $pending = $pdo->prepare("SELECT 1 FROM market_dealer_subscriptions s JOIN market_invoices i ON i.id=s.invoice_id WHERE s.owner_id=? AND i.status IN ('draft','issued','pending') LIMIT 1");
        $pending->execute([$owner]);
        if ($pending->fetchColumn() !== false) throw new PaymentConflict(t('dealer_subscription.pending'));
        $recent = $pdo->prepare('SELECT COUNT(*) FROM market_dealer_subscriptions WHERE owner_id=? AND created_at>UTC_TIMESTAMP()-INTERVAL 1 HOUR');
        $recent->execute([$owner]);
        if ((int)$recent->fetchColumn() >= 10) throw new PaymentConflict(t('account.rate_limit'));

        $documents = consent_check($pdo,'checkout',$input,i18n_locale());
        if (!$documents) throw new PaymentUnavailable(t('dealer_subscription.unavailable'));
        $invoice = $pdo->prepare("INSERT INTO market_invoices(user_id,invoice_number,service_code,fee_version,gross_cents,status) VALUES (?,?,?,?,?,'issued')");
        $invoice->execute([$owner,'GI-'.strtoupper(bin2hex(random_bytes(10))),$terms['service'],$terms['version'],$terms['gross_cents']]);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO market_dealer_subscriptions(invoice_id,owner_id,request_key,term_days) VALUES (?,?,?,?)')
            ->execute([$id,$owner,$request,$terms['term_days']]);
        finance_prepare_order($pdo,$id,$owner);
        consent_record($pdo,$owner,'checkout',$id,$documents);
        audit_log($pdo,$owner,'dealer.subscription.ordered',$id,['term_days'=>$terms['term_days'],'version'=>$terms['version']]);
        return $id;
    });
}

/** Called after verified finance issuance inside the locked invoice transaction. */
function dealer_subscription_settle(PDO $pdo, array $invoice): void
{
    if ($invoice['service_code'] !== 'dealer_subscription') return;
    if (!$pdo->inTransaction() || $invoice['status'] !== 'paid') throw new LogicException('Verified subscription settlement requires a paid invoice lock.');
    $owner = (int)$invoice['user_id'];
    $lock = $pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
    $lock->execute([$owner]);
    $query = $pdo->prepare('SELECT term_days,starts_at,revoked_at FROM market_dealer_subscriptions WHERE invoice_id=? AND owner_id=? FOR UPDATE');
    $query->execute([$invoice['id'],$owner]);
    $term = $query->fetch();
    if (!$term) throw new PaymentConflict(t('dealer_subscription.unavailable'));
    if ($term['starts_at'] !== null || $term['revoked_at'] !== null) return;
    $coverage = $pdo->prepare('SELECT GREATEST(UTC_TIMESTAMP(),ends_at) FROM market_dealer_subscriptions WHERE owner_id=? AND revoked_at IS NULL AND ends_at IS NOT NULL ORDER BY ends_at DESC LIMIT 1 FOR UPDATE');
    $coverage->execute([$owner]);
    $start = $coverage->fetchColumn() ?: gmdate('Y-m-d H:i:s');
    $pdo->prepare('UPDATE market_dealer_subscriptions SET starts_at=?,ends_at=DATE_ADD(?,INTERVAL ? DAY) WHERE invoice_id=?')
        ->execute([$start,$start,$term['term_days'],$invoice['id']]);
}

function dealer_subscription_revoke(PDO $pdo, array $invoice): void
{
    if ($invoice['service_code'] !== 'dealer_subscription') return;
    if (!$pdo->inTransaction()) throw new LogicException('Subscription revocation must share the financial transaction.');
    $lock = $pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
    $lock->execute([$invoice['user_id']]);
    $pdo->prepare('UPDATE market_dealer_subscriptions SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP()) WHERE invoice_id=? AND owner_id=?')
        ->execute([$invoice['id'],$invoice['user_id']]);
}

function dealer_subscription_export(PDO $pdo, int $actor, int $owner, int $after = 0): array
{
    return trade_transaction($pdo, static function () use ($pdo,$actor,$owner,$after): array {
        dealer_team_assert($pdo,$actor,$owner,'inventory.read',true);
        if (!dealer_subscription_active($pdo,$owner)) throw new MarketAccessException(t('dealer_subscription.export_unavailable'));
        $query = $pdo->prepare('SELECT id,sku,label,quantity,updated_at FROM market_dealer_inventory WHERE owner_id=? AND id>? ORDER BY id LIMIT 1001');
        $query->execute([$owner,$after]);
        $rows = $query->fetchAll();
        $next = null;
        if (count($rows) > 1000) {
            array_pop($rows);
            $next = (int)end($rows)['id'];
        }
        return ['rows'=>$rows,'next'=>$next];
    });
}

function dealer_subscription_csv(array $rows): string
{
    $output = fopen('php://temp','w+b');
    try {
        fwrite($output,"\xEF\xBB\xBF");
        fputcsv($output,['id','sku','label','quantity','updated_at'],',','"','');
        foreach ($rows as $row) {
            fputcsv($output,array_map('business_csv_cell',array_values($row)),',','"','');
        }
        rewind($output);
        return stream_get_contents($output);
    } finally { fclose($output); }
}
