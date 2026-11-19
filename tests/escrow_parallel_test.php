<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(2);
}
if (PHP_OS_FAMILY !== 'Linux' || getenv('APP_ENV') !== 'development' || getenv('ESCROW_SIMULATION') !== '1'
    || getenv('DB_DATABASE') !== 'gunindex_rebuild_test' || getenv('DB_USERNAME') !== 'gunindex_test'
    || getenv('DB_SOCKET') !== '/var/run/mysqld/mysqld.sock') {
    exit(2);
}
require_once __DIR__ . '/../config/bootstrap.php';
if (!escrow_simulation_enabled($pdo)) {
    exit(2);
}
$_SESSION['locale'] = 'de';

const ESCROW_RACE_USER = 'Synthetic escrow reservation race';
const ESCROW_RACE_ITEM = 'Synthetic escrow wooden table ';

function escrow_race_check(bool $condition, string $failure): void
{
    if (!$condition) {
        throw new RuntimeException($failure);
    }
}

function escrow_race_user(PDO $pdo, string $username): int
{
    $query = $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code,preferred_locale) VALUES (?,?,?,?,'10115','Synthetic town','DE','de')");
    $query->execute([$username, $username . '@example.invalid', auth_hash(bin2hex(random_bytes(24))), ESCROW_RACE_USER]);
    return (int)$pdo->lastInsertId();
}

function escrow_race_request(): array
{
    return ['version' => '1', 'request_key' => bin2hex(random_bytes(16)), 'confirm' => '1'];
}

function escrow_race_child(PDO $pdo, array $arguments): void
{
    [$run, $role, $listing, $id, $buyer] = array_pad($arguments, 5, '');
    escrow_race_check(preg_match('/\A[a-f0-9]{16}\z/', $run) === 1 && in_array($role, ['a', 'b'], true)
        && ctype_digit($listing) && ctype_digit($id) && ctype_digit($buyer), 'Invalid race child arguments.');
    $buyerName = 'escrow_race_' . $run . '_' . $role;
    $sellerName = 'escrow_race_' . $run . '_seller';
    $query = $pdo->prepare("SELECT 1 FROM market_escrow_cases e
        JOIN market_listings l ON l.id=e.listing_id AND l.seller_id=e.seller_id
        JOIN users b ON b.id=e.buyer_id JOIN users s ON s.id=e.seller_id
        WHERE e.id=? AND l.id=? AND b.id=? AND l.title=? AND e.version=1 AND e.status='offered'
        AND e.proposer_id=s.id AND b.is_admin=0 AND s.is_admin=0
        AND b.username=? AND b.email=? AND b.full_name=?
        AND s.username=? AND s.email=? AND s.full_name=?");
    $query->execute([(int)$id, (int)$listing, (int)$buyer, ESCROW_RACE_ITEM . $run,
        $buyerName, $buyerName . '@example.invalid', ESCROW_RACE_USER,
        $sellerName, $sellerName . '@example.invalid', ESCROW_RACE_USER]);
    escrow_race_check((bool)$query->fetchColumn(), 'Child refused non-fixture escrow offer.');
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=5');
    $request = escrow_race_request();
    fwrite(STDOUT, "ready\n");
    escrow_race_check(fgets(STDIN, 8) === "race\n", 'Invalid race start signal.');
    fwrite(STDOUT, "attempting\n");
    try {
        escrow_apply($pdo, (int)$buyer, (int)$id, 'accept', $request);
        fwrite(STDOUT, "accepted\n");
    } catch (MarketConflictException $error) {
        escrow_race_check($error->getMessage() === t('escrow.changed'), 'Unexpected race conflict.');
        fwrite(STDOUT, "conflict\n");
    }
}

function escrow_race_read($pipe): string
{
    $read = [$pipe];
    $write = $except = null;
    escrow_race_check(stream_select($read, $write, $except, 15) === 1, 'Race child response timed out.');
    $line = fgets($pipe, 32);
    escrow_race_check($line !== false && str_ends_with($line, "\n"), 'Race child response incomplete.');
    return rtrim($line, "\n");
}

function escrow_race_run(PDO $pdo, string $run, int $listing, array $cases): array
{
    $children = [];
    try {
        foreach ($cases as $role => [$id, $buyer]) {
            $process = proc_open([PHP_BINARY, __FILE__, '--race', $run, $role, (string)$listing, (string)$id, (string)$buyer],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            escrow_race_check(is_resource($process), 'Race child could not start.');
            $children[$id] = [$process, $pipes];
        }
        foreach ($children as [, $pipes]) {
            escrow_race_check(escrow_race_read($pipes[1]) === 'ready', 'Race child failed fixture verification.');
        }

        // Both connections enter the acceptance path behind the same listing lock.
        $pdo->beginTransaction();
        market_lock_listing($pdo, $listing);
        foreach ($children as [, $pipes]) {
            escrow_race_check(fwrite($pipes[0], "race\n") === 5, 'Race start signal failed.');
            fclose($pipes[0]);
        }
        foreach ($children as [, $pipes]) {
            escrow_race_check(escrow_race_read($pipes[1]) === 'attempting', 'Race child did not attempt acceptance.');
        }
        $pdo->commit();

        $results = [];
        foreach ($children as $id => [$process, $pipes]) {
            $results[$id] = escrow_race_read($pipes[1]);
            fclose($pipes[1]);
            $errors = stream_get_contents($pipes[2], 1);
            fclose($pipes[2]);
            $exit = proc_close($process);
            unset($children[$id]);
            escrow_race_check($exit === 0 && $errors === '', 'Race child failed after start.');
        }
        return $results;
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($children as [$process, $pipes]) {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) fclose($pipe);
            }
            proc_terminate($process);
            proc_close($process);
        }
    }
}

if (($argv[1] ?? '') === '--race') {
    escrow_race_child($pdo, array_slice($argv, 2));
    exit(0);
}

try {
    $run = bin2hex(random_bytes(8));
    $seller = escrow_race_user($pdo, 'escrow_race_' . $run . '_seller');
    $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')
        ->execute(['Synthetic escrow furniture', 'escrow-race-' . $run]);
    $category = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,shipping_cents,status,zip,city,country_code,pickup,shipping,trade_checkout_enabled)
        VALUES (?,?,?,'Synthetic isolated shipment race.','fixed',4500,300,'active','10115','Synthetic town','DE',0,1,1)")
        ->execute([$seller, $category, ESCROW_RACE_ITEM . $run]);
    $listing = (int)$pdo->lastInsertId();

    $cases = [];
    foreach (['a', 'b'] as $role) {
        $buyer = escrow_race_user($pdo, 'escrow_race_' . $run . '_' . $role);
        chat_send($pdo, $buyer, $listing, $seller, 'Synthetic escrow reservation race.');
        $id = escrow_create($pdo, $seller, $listing, $buyer, escrow_race_request() + ['amount' => '45.00']);
        $cases[$role] = [$id, $buyer];
    }
    $buyer = $cases['a'][1];
    $quote = trade_quote($pdo, $buyer, $listing, 'shipping');
    $checkout = ['delivery' => 'shipping', 'quote_hash' => $quote['hash'], 'request_key' => bin2hex(random_bytes(16)),
        'confirm_purchase' => '1', 'legal_confirm' => '1', 'legal_language' => $quote['locale'],
        'legal_documents' => array_column($quote['documents'], 'content_hash', 'id')];
    escrow_race_check(trade_listing_available($pdo, $listing), 'Unaccepted offers already reserved the listing.');

    $results = escrow_race_run($pdo, $run, $listing, $cases);
    escrow_race_check(count($results) === 2 && in_array('accepted', $results, true)
        && in_array('conflict', $results, true), 'Exactly one buyer must win the race.');
    foreach ($cases as [$id, $actor]) {
        $row = escrow_row($pdo, $id);
        $won = $results[$id] === 'accepted';
        escrow_race_check($row['status'] === ($won ? 'awaiting_funding' : 'offered')
            && (int)$row['version'] === ($won ? 2 : 1), 'Offer state or version inconsistent with race result.');
        if ($won) {
            $winner = [$id, $actor];
        }
    }
    $audit = $pdo->prepare("SELECT e.case_id,e.actor_id,e.from_status,e.to_status,e.version_after
        FROM market_escrow_events e JOIN market_escrow_cases c ON c.id=e.case_id WHERE c.listing_id=? AND e.action='accept'");
    $audit->execute([$listing]);
    $events = $audit->fetchAll();
    escrow_race_check(count($events) === 1 && [(int)$events[0]['case_id'], (int)$events[0]['actor_id']] === $winner
        && $events[0]['from_status'] === 'offered' && $events[0]['to_status'] === 'awaiting_funding'
        && (int)$events[0]['version_after'] === 2, 'Expected exactly one acceptance event by the winning buyer.');

    $query = $pdo->prepare('SELECT status FROM market_listings WHERE id=?');
    $query->execute([$listing]);
    escrow_race_check($query->fetchColumn() === 'active' && !trade_listing_available($pdo, $listing),
        'A reservation must block ordinary checkout while the listing remains active.');
    try {
        trade_purchase($pdo, $buyer, $listing, $checkout);
        throw new RuntimeException('Reserved listing was purchased via ordinary checkout.');
    } catch (DomainException $error) {
        escrow_race_check($error->getMessage() === t('trade.unavailable'), 'Ordinary checkout failed for an unrelated reason.');
    }
    $query = $pdo->prepare('SELECT COUNT(*) FROM market_trades WHERE listing_id=?');
    $query->execute([$listing]);
    escrow_race_check((int)$query->fetchColumn() === 0, 'Rejected ordinary checkout created a trade.');
    fwrite(STDOUT, "PASS escrow parallel accept reserves exactly once and blocks ordinary checkout\n");
} catch (Throwable $error) {
    $detail = $error->getFile() === __FILE__ ? ' ' . $error->getMessage() : '';
    fwrite(STDERR, 'FAIL escrow race ' . get_class($error) . ' at ' . basename($error->getFile()) . ':' . $error->getLine() . $detail . PHP_EOL);
    exit(1);
}
