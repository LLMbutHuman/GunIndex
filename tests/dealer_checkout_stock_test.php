<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(2);
}
if (PHP_OS_FAMILY !== 'Linux' || getenv('APP_ENV') !== 'development'
    || getenv('DB_DATABASE') !== 'gunindex_rebuild_test' || getenv('DB_USERNAME') !== 'gunindex_test'
    || getenv('DB_SOCKET') !== '/var/run/mysqld/mysqld.sock') {
    exit(2);
}
require_once __DIR__ . '/../config/bootstrap.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'gunindex_rebuild_test') {
    exit(2);
}
$_SESSION['locale'] = 'de';

const DCS_MARK = 'Synthetic shared dealer stock fixture';

function dcs_check(bool $condition, string $failure): void
{
    if (!$condition) throw new RuntimeException($failure);
}

function dcs_account(PDO $pdo, string $name): int
{
    $query = $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code,preferred_locale)
        VALUES (?,?,?,?,'10115','Synthetic town','DE','de')");
    $query->execute([$name, $name . '@example.invalid', auth_hash(bin2hex(random_bytes(24))), DCS_MARK]);
    return (int)$pdo->lastInsertId();
}

function dcs_quote(PDO $pdo, int $buyer, int $listing): array
{
    $quote = trade_quote($pdo, $buyer, $listing, 'shipping');
    return ['delivery'=>'shipping','quote_hash'=>$quote['hash'],'request_key'=>bin2hex(random_bytes(16)),
        'confirm_purchase'=>'1','legal_confirm'=>'1','legal_language'=>$quote['locale'],
        'legal_documents'=>array_column($quote['documents'], 'content_hash', 'id')];
}

function dcs_transition(PDO $pdo, int $actor, int $trade, string $action): array
{
    $input = ['version'=>(string)trade_row($pdo, $trade)['version'],'request_key'=>bin2hex(random_bytes(16)),
        'confirm_action'=>'1','details'=>'Synthetic shipment only.'];
    trade_transition($pdo, $actor, $trade, $action, $input);
    return $input;
}

function dcs_ledger(PDO $pdo, int $owner, int $item, array $expected): void
{
    $details = dealer_inventory_details($pdo, $owner, $owner, $item);
    $actual = array_map(static fn(array $row): array => [$row['movement_kind'],(int)$row['delta'],(int)$row['quantity_after']],
        array_reverse($details['movements']));
    dcs_check($actual === $expected && (int)$details['quantity'] === end($expected)[2]
        && (int)$details['version'] === count($expected) + 1, 'Quantity, revision or movement ledger differs from expected history.');
}

function dcs_trade_stock(PDO $pdo, int $trade, int $owner, int $item, string $status): void
{
    $query = $pdo->prepare('SELECT status FROM market_dealer_trade_stock WHERE trade_id=? AND owner_id=? AND item_id=?');
    $query->execute([$trade,$owner,$item]);
    dcs_check($query->fetchColumn() === $status, 'Order stock status differs from expected state.');
}

function dcs_read($pipe): string
{
    $read = [$pipe];
    $write = $except = null;
    dcs_check(stream_select($read, $write, $except, 15) === 1, 'Checkout child response timed out.');
    $line = fgets($pipe, 128);
    dcs_check($line !== false && str_ends_with($line, "\n"), 'Checkout child response incomplete.');
    return rtrim($line, "\n");
}

function dcs_child(PDO $pdo, array $args): void
{
    [$run,$role,$item,$listing,$buyer] = array_pad($args, 5, '');
    dcs_check(preg_match('/\A[a-f0-9]{16}\z/', $run) === 1 && in_array($role, ['a','b'], true)
        && ctype_digit($item) && ctype_digit($listing) && ctype_digit($buyer), 'Invalid checkout child arguments.');
    $ownerName = 'dcs_' . $run . '_owner';
    $buyerName = 'dcs_' . $run . '_' . $role;
    $query = $pdo->prepare("SELECT 1 FROM market_dealer_stock_bindings x
        JOIN market_listings l ON l.id=x.listing_id AND l.seller_id=x.owner_id
        JOIN market_dealer_inventory i ON i.id=x.item_id AND i.owner_id=x.owner_id
        JOIN users s ON s.id=x.owner_id JOIN users b ON b.id=?
        WHERE l.id=? AND i.id=? AND l.title=? AND l.description=? AND l.status='active' AND i.quantity=1
        AND s.username=? AND s.email=? AND s.full_name=? AND s.is_admin=0
        AND b.username=? AND b.email=? AND b.full_name=? AND b.is_admin=0");
    $query->execute([(int)$buyer,(int)$listing,(int)$item,'Synthetic office lamp ' . $run . ' ' . $role,DCS_MARK,
        $ownerName,$ownerName . '@example.invalid',DCS_MARK,$buyerName,$buyerName . '@example.invalid',DCS_MARK]);
    dcs_check((bool)$query->fetchColumn(), 'Child refused non-fixture stock and listing.');
    $raw = fgets(STDIN, 16385);
    dcs_check(is_string($raw) && str_ends_with($raw, "\n"), 'Missing checkout fixture quote.');
    $input = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    dcs_check(is_array($input), 'Invalid checkout fixture quote.');
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=5');
    fwrite(STDOUT, "ready\n");
    dcs_check(fgets(STDIN, 8) === "race\n", 'Missing checkout race start signal.');
    fwrite(STDOUT, "attempting\n");
    try {
        $result = trade_purchase($pdo, (int)$buyer, (int)$listing, $input);
    } catch (DomainException $error) {
        dcs_check($error->getMessage() === t('dealer_checkout.sold_out'), 'Checkout rejected for an unrelated reason.');
        $result = 'sold_out';
    }
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR) . "\n");
}

function dcs_race(PDO $pdo, string $run, int $owner, int $item, array $cases): array
{
    $children = [];
    try {
        foreach ($cases as $role => [$buyer,$listing,$input]) {
            $process = proc_open([PHP_BINARY,__FILE__,'--race',$run,$role,(string)$item,(string)$listing,(string)$buyer],
                [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
            dcs_check(is_resource($process), 'Checkout child did not start.');
            $children[$role] = [$process,$pipes];
            $request = json_encode($input, JSON_THROW_ON_ERROR) . "\n";
            dcs_check(fwrite($pipes[0], $request) === strlen($request), 'Checkout fixture quote was not sent.');
        }
        foreach ($children as [, $pipes]) {
            dcs_check(dcs_read($pipes[1]) === 'ready', 'Checkout child failed fixture preparation.');
        }

        // Both purchases use separate listing rows but must contend for this inventory row.
        $pdo->beginTransaction();
        $query = $pdo->prepare('SELECT quantity FROM market_dealer_inventory WHERE id=? AND owner_id=? FOR UPDATE');
        $query->execute([$item,$owner]);
        dcs_check((int)$query->fetchColumn() === 1, 'Race requires the last stock unit.');
        foreach ($children as [, $pipes]) {
            dcs_check(fwrite($pipes[0], "race\n") === 5, 'Checkout race start signal failed.');
            fclose($pipes[0]);
        }
        foreach ($children as [, $pipes]) {
            dcs_check(dcs_read($pipes[1]) === 'attempting', 'Child did not attempt checkout.');
        }
        $pdo->commit();

        $results = [];
        foreach ($children as $role => [$process,$pipes]) {
            $results[$role] = json_decode(dcs_read($pipes[1]), true, 8, JSON_THROW_ON_ERROR);
            fclose($pipes[1]);
            $errors = stream_get_contents($pipes[2], 1);
            fclose($pipes[2]);
            $exit = proc_close($process);
            unset($children[$role]);
            dcs_check($exit === 0 && $errors === '', 'Checkout child exited unsuccessfully.');
        }
        return $results;
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($children as [$process,$pipes]) {
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            proc_terminate($process);
            proc_close($process);
        }
    }
}

if (($argv[1] ?? '') === '--race') {
    dcs_child($pdo, array_slice($argv, 2));
    exit(0);
}

$stage = 'fixture';
try {
    $run = bin2hex(random_bytes(8));
    $prefix = 'dcs_' . $run . '_';
    $owner = dcs_account($pdo, $prefix . 'owner');
    $query = $pdo->prepare("INSERT INTO market_dealer_profiles(user_id,company_name,address_text,country_code,contact_email,legal_notice,status)
        VALUES (?,'Synthetic lamp dealer','Synthetic dealer location','DE',?,?,'published')");
    $query->execute([$owner,$prefix . 'owner@example.invalid',DCS_MARK]);
    $item = dealer_inventory_create($pdo, $owner, $owner, 'LAMP-01', 'Synthetic office lamp');
    dealer_inventory_adjust($pdo, $owner, $owner, $item, 1, bin2hex(random_bytes(16)), '1', 'Synthetic opening count');
    $history = [['manual',1,1]];
    dcs_ledger($pdo, $owner, $item, $history);
    $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')->execute(['Synthetic home office fixtures','stock-race-' . $run]);
    $category = (int)$pdo->lastInsertId();
    $query = $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,shipping_cents,status,zip,city,country_code,pickup,shipping,trade_checkout_enabled)
        VALUES (?,?,?,?,'fixed',4500,300,'active','10115','Synthetic town','DE',0,1,1)");
    foreach (['a','b'] as $role) {
        $buyer = dcs_account($pdo, $prefix . $role);
        $query->execute([$owner,$category,'Synthetic office lamp ' . $run . ' ' . $role,DCS_MARK]);
        $listing = (int)$pdo->lastInsertId();
        dealer_checkout_change_binding($pdo, $owner, $owner, $item, $listing, null);
        $cases[$role] = [$buyer,$listing,dcs_quote($pdo, $buyer, $listing)];
    }

    $stage = 'binding_revision';
    [$buyer,$listing,$stale] = $cases['a'];
    $bound = dealer_checkout_stock_row($pdo, $owner, $listing);
    dealer_checkout_change_binding($pdo, $owner, $owner, $item, $listing, $bound['revision']);
    dealer_checkout_change_binding($pdo, $owner, $owner, $item, $listing, null);
    try {
        trade_purchase($pdo, $buyer, $listing, $stale);
        throw new RuntimeException('An old binding quote was accepted.');
    } catch (DomainException $error) {
        dcs_check($error->getMessage() === t('trade.changed'), 'Stale quote rejected for the wrong reason.');
    }
    $cases['a'][2] = dcs_quote($pdo, $buyer, $listing);
    dcs_ledger($pdo, $owner, $item, $history);

    $stage = 'race_and_replay';
    $results = dcs_race($pdo, $run, $owner, $item, $cases);
    $won = array_filter($results, is_int(...));
    $lost = array_keys($results, 'sold_out', true);
    dcs_check(count($won) === 1 && count($lost) === 1, 'Expected exactly one accepted order and one sold-out response.');
    $winner = array_key_first($won);
    $trade = $won[$winner];
    [$buyer,$listing,$input] = $cases[$winner];
    [$nextBuyer,$nextListing,$nextInput] = $cases[$lost[0]];
    dcs_check(trade_purchase($pdo, $buyer, $listing, $input) === $trade, 'Purchase replay did not return the original trade.');
    $query = $pdo->prepare('SELECT listing_id,buyer_id,status FROM market_trades WHERE listing_id IN (?,?)');
    $query->execute([$listing,$nextListing]);
    $rows = $query->fetchAll();
    dcs_check(count($rows) === 1 && (int)$rows[0]['listing_id'] === $listing
        && (int)$rows[0]['buyer_id'] === $buyer && $rows[0]['status'] === 'agreed', 'Checkout race created inconsistent trade records.');
    dcs_trade_stock($pdo, $trade, $owner, $item, 'reserved');
    $history[] = ['order_sale',-1,0];
    dcs_ledger($pdo, $owner, $item, $history);
    echo "PASS dealer shared stock binding revision, race and exact purchase retry\n";

    $stage = 'cancel_before_dispatch';
    dcs_transition($pdo, $buyer, $trade, 'cancel_request');
    dcs_ledger($pdo, $owner, $item, $history);
    $accepted = dcs_transition($pdo, $owner, $trade, 'cancel_accept');
    trade_transition($pdo, $owner, $trade, 'cancel_accept', $accepted);
    dcs_check(trade_row($pdo, $trade)['status'] === 'cancelled', 'First trade was not cancelled.');
    dcs_trade_stock($pdo, $trade, $owner, $item, 'restored');
    $history[] = ['order_cancel',1,1];
    dcs_ledger($pdo, $owner, $item, $history);
    echo "PASS cancellation before dispatch restores one unit exactly once\n";

    $stage = 'cancel_after_dispatch_and_return';
    $second = trade_purchase($pdo, $nextBuyer, $nextListing, $nextInput);
    dcs_check($second !== $trade, 'Remaining listing did not create a separate purchase.');
    $history[] = ['order_sale',-1,0];
    dcs_ledger($pdo, $owner, $item, $history);
    dcs_transition($pdo, $owner, $second, 'dispatch');
    dcs_transition($pdo, $nextBuyer, $second, 'cancel_request');
    dcs_transition($pdo, $owner, $second, 'cancel_accept');
    dcs_check(trade_row($pdo, $second)['status'] === 'cancelled', 'Second trade was not cancelled.');
    dcs_trade_stock($pdo, $second, $owner, $item, 'return_required');
    $reviews = dealer_checkout_bindings($pdo, $owner, $owner, $item)['reviews'];
    dcs_check(count($reviews) === 1 && (int)$reviews[0]['trade_id'] === $second, 'Sent order missing from the return review queue.');
    try {
        dealer_checkout_review($pdo, $nextBuyer, $owner, $item, $second, true);
        throw new RuntimeException('Buyer can confirm their own return.');
    } catch (MarketAccessException) {
        dcs_ledger($pdo, $owner, $item, $history);
    }
    dealer_checkout_review($pdo, $owner, $owner, $item, $second, true);
    dealer_checkout_review($pdo, $owner, $owner, $item, $second, true);
    dcs_trade_stock($pdo, $second, $owner, $item, 'restored');
    dcs_check(dealer_checkout_bindings($pdo, $owner, $owner, $item)['reviews'] === [], 'Restored order remains in return queue.');
    $history[] = ['order_cancel',1,1];
    dcs_ledger($pdo, $owner, $item, $history);
    echo "PASS dispatched cancellation needs an authorized return confirmation to restore stock\n";
} catch (Throwable $error) {
    $detail = $error->getFile() === __FILE__ ? ' ' . $error->getMessage() : '';
    fwrite(STDERR, "FAIL dealer checkout stock $stage " . get_class($error) . ' at ' . basename($error->getFile())
        . ':' . $error->getLine() . $detail . PHP_EOL);
    exit(1);
}
