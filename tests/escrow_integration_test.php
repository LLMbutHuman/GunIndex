<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'development' || getenv('DB_DATABASE') !== 'gunindex_rebuild_test') exit(2);
require_once __DIR__ . '/../config/bootstrap.php';
if (!escrow_simulation_enabled($pdo)) exit(2);

function esc_check(bool $ok): void
{
    if (!$ok) throw new RuntimeException('Escrow integration assertion failed.');
}

function esc_scalar(PDO $pdo, string $sql, array $parameters): mixed
{
    $query = $pdo->prepare($sql);
    $query->execute($parameters);
    return $query->fetchColumn();
}

function esc_reject(callable $callback): void
{
    try { $callback(); }
    catch (MarketNotFoundException|MarketAccessException|MarketConflictException|DomainException|InvalidArgumentException) { return; }
    throw new RuntimeException('Restricted operation was accepted.');
}

function esc_account(PDO $pdo, bool $staff = false): int
{
    $name = 'test_' . bin2hex(random_bytes(8));
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,is_admin) VALUES (?,?,?,'Synthetic escrow fixture','10115','Teststadt',?)")
        ->execute([$name,$name . '@example.invalid',auth_hash('Synthetic escrow fixture ' . $name),(int)$staff]);
    return (int)$pdo->lastInsertId();
}

function esc_listing(PDO $pdo, int $seller, int $category, array $buyers): int
{
    $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,shipping_cents,status,zip,city,country_code,pickup,shipping,trade_checkout_enabled) VALUES (?,?,?,'Synthetic parcel workflow only','fixed',2500,300,'active','10115','Teststadt','DE',0,1,1)")
        ->execute([$seller,$category,'Synthetic escrow item ' . bin2hex(random_bytes(8))]);
    $listing = (int)$pdo->lastInsertId();
    foreach ($buyers as $buyer) chat_send($pdo, $buyer, $listing, $seller, 'Synthetic escrow test conversation.');
    return $listing;
}

function esc_request(int $version, array $extra = []): array
{
    return ['confirm'=>'1','request_key'=>bin2hex(random_bytes(16)),'version'=>(string)$version] + $extra;
}

function esc_apply(PDO $pdo, int $actor, int $id, string $action, array $extra = [], bool $finance = false): array
{
    $request = esc_request((int)escrow_row($pdo, $id)['version'], $extra);
    escrow_apply($pdo, $actor, $id, $action, $request, $finance);
    return $request;
}

$case = 'fixture';
try {
    $seller = esc_account($pdo);
    $buyer = esc_account($pdo);
    $outsider = esc_account($pdo);
    $finance = esc_account($pdo, true);
    $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')->execute(['Synthetic test household goods','test-escrow-' . bin2hex(random_bytes(8))]);
    $category = (int)$pdo->lastInsertId();
    $listing = esc_listing($pdo, $seller, $category, [$buyer,$outsider]);
    $create = esc_request(1, ['amount'=>'25.50']);

    $case = 'strict_gate_and_participant_roles';
    putenv('ESCROW_SIMULATION=0');
    esc_reject(static fn() => escrow_create($pdo, $seller, $listing, $buyer, $create));
    putenv('ESCROW_SIMULATION=1');
    putenv('APP_ENV=production');
    esc_check(!escrow_simulation_enabled($pdo));
    putenv('APP_ENV=development');
    esc_reject(static fn() => escrow_create($pdo, $buyer, $listing, $seller, $create));
    $id = escrow_create($pdo, $seller, $listing, $buyer, $create);
    esc_check(escrow_create($pdo, $seller, $listing, $buyer, $create) === $id);
    esc_reject(static fn() => escrow_details($pdo, $outsider, $id));
    esc_reject(static fn() => escrow_details($pdo, $buyer, $id, true));
    esc_reject(static fn() => escrow_apply($pdo, $seller, $id, 'accept', esc_request(1)));
    echo "PASS $case\n";

    $case = 'negotiation_contact_confidentiality_reservation_and_cent_totals';
    $other = escrow_create($pdo, $seller, $listing, $outsider, esc_request(1, ['amount'=>'27.10']));
    $counter = esc_apply($pdo, $buyer, $id, 'counter', ['amount'=>'24.17']);
    escrow_apply($pdo, $buyer, $id, 'counter', $counter);
    esc_reject(static fn() => escrow_apply($pdo, $seller, $id, 'accept', esc_request(1)));
    esc_apply($pdo, $seller, $id, 'accept');
    esc_reject(static fn() => esc_apply($pdo, $outsider, $other, 'accept'));
    esc_check(!trade_listing_available($pdo, $listing));
    esc_reject(static fn() => esc_apply($pdo, $buyer, $id, 'contact', ['contact'=>'account@example.com']));
    esc_apply($pdo, $seller, $id, 'contact', ['contact'=>'synthetic-seller@example.invalid']);
    esc_apply($pdo, $buyer, $id, 'contact', ['contact'=>'synthetic-buyer@example.invalid']);
    $buyerCase = escrow_details($pdo, $buyer, $id);
    $financeCase = escrow_details($pdo, $finance, $id, true);
    esc_check($buyerCase['own_contact'] === 'synthetic-buyer@example.invalid' && $financeCase['own_contact'] === null);
    esc_check(!str_contains(trade_json($buyerCase), 'synthetic-seller@example.invalid') && !str_contains(trade_json($financeCase), '@example.invalid'));
    esc_check((int)$buyerCase['price_cents'] === 2417 && (int)$buyerCase['shipping_cents'] === 300);
    $receiptKey = bin2hex(random_bytes(16));
    $row = escrow_row($pdo, $id);
    $receipt = escrow_simulate_receipt($row, 'funds_confirmed', $receiptKey);
    esc_reject(static fn() => escrow_validate_simulated_receipt($row, 'funds_confirmed', $receiptKey, array_replace($receipt, ['amount_cents'=>2716])));
    esc_reject(static fn() => esc_apply($pdo, $buyer, $id, 'simulate_funds_confirmed'));
    echo "PASS $case\n";

    $case = 'failed_funding_receipt_dispatch_release_and_chargeback';
    esc_apply($pdo, $finance, $id, 'simulate_payment_failed', [], true);
    esc_apply($pdo, $finance, $id, 'simulate_retry_allowed', [], true);
    $fund = esc_apply($pdo, $finance, $id, 'simulate_funds_confirmed', [], true);
    escrow_apply($pdo, $finance, $id, 'simulate_funds_confirmed', $fund, true);
    esc_check((int)esc_scalar($pdo, "SELECT COUNT(*) FROM market_escrow_events WHERE case_id=? AND action='funds_confirmed' AND provider_amount_cents=2717 AND provider_currency='EUR'", [$id]) === 1);
    esc_reject(static fn() => esc_apply($pdo, $buyer, $id, 'received'));
    esc_apply($pdo, $seller, $id, 'dispatch', ['details'=>'SYNTHETIC-PARCEL-001']);
    esc_apply($pdo, $buyer, $id, 'received');
    esc_apply($pdo, $finance, $id, 'request_release', ['details'=>'Synthetic received parcel accepted.'], true);
    esc_apply($pdo, $finance, $id, 'simulate_payout_failed', [], true);
    esc_apply($pdo, $finance, $id, 'request_release', ['details'=>'Retry after the synthetic fixture failure.'], true);
    $payout = esc_apply($pdo, $finance, $id, 'simulate_payout_confirmed', [], true);
    escrow_apply($pdo, $finance, $id, 'simulate_payout_confirmed', $payout, true);
    esc_check(escrow_details($pdo, $seller, $id)['status'] === 'released');
    esc_check(esc_scalar($pdo, 'SELECT status FROM market_listings WHERE id=?', [$listing]) === 'sold');
    esc_check((int)esc_scalar($pdo, 'SELECT COUNT(*) FROM market_trades WHERE listing_id=?', [$listing]) === 0);
    esc_apply($pdo, $finance, $id, 'simulate_chargeback', [], true);
    esc_check(escrow_details($pdo, $buyer, $id)['status'] === 'charged_back');
    echo "PASS $case\n";

    $case = 'expiry_and_refund_restore_trade_availability';
    $listing = esc_listing($pdo, $seller, $category, [$buyer]);
    $id = escrow_create($pdo, $seller, $listing, $buyer, esc_request(1, ['amount'=>'40.00']));
    esc_apply($pdo, $buyer, $id, 'accept');
    $pdo->prepare('UPDATE market_escrow_cases SET deadline=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id=?')->execute([$id]);
    esc_reject(static fn() => esc_apply($pdo, $finance, $id, 'simulate_funds_confirmed', [], true));
    esc_check(escrow_expire($pdo) >= 1 && escrow_details($pdo, $buyer, $id)['status'] === 'expired');
    esc_check(trade_listing_available($pdo, $listing));
    $id = escrow_create($pdo, $seller, $listing, $buyer, esc_request(1, ['amount'=>'40.00']));
    esc_apply($pdo, $buyer, $id, 'accept');
    esc_apply($pdo, $seller, $id, 'contact', ['contact'=>'seller@example.invalid']);
    esc_apply($pdo, $buyer, $id, 'contact', ['contact'=>'buyer@example.invalid']);
    esc_apply($pdo, $finance, $id, 'simulate_funds_confirmed', [], true);
    esc_apply($pdo, $buyer, $id, 'dispute', ['details'=>'Synthetic fixture dispute.']);
    esc_apply($pdo, $finance, $id, 'request_refund', ['details'=>'Synthetic full refund.'], true);
    esc_apply($pdo, $finance, $id, 'simulate_refund_confirmed', [], true);
    esc_check(escrow_details($pdo, $seller, $id)['status'] === 'refunded' && trade_listing_available($pdo, $listing));
    echo "PASS $case\n";
} catch (Throwable $error) {
    fwrite(STDERR, "FAIL $case (" . get_class($error) . ' at ' . basename($error->getFile()) . ':' . $error->getLine() . ")\n");
    exit(1);
}
