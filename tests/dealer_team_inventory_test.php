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

function dti_check(bool $condition, string $failure): void
{
    if (!$condition) {
        throw new RuntimeException($failure);
    }
}

function dti_reject(callable $operation, string $scenario, string ...$expected): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        if (in_array(get_class($error), $expected, true)) {
            return;
        }
        throw $error;
    }
    throw new RuntimeException('Unauthorized or invalid operation was accepted: ' . $scenario);
}

function dti_deny(callable $operation, string $scenario): void
{
    dti_reject($operation, $scenario, MarketAccessException::class, MarketNotFoundException::class, DomainException::class);
}

function dti_account(PDO $pdo, string $name, bool $admin = false): int
{
    $query = $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code,preferred_locale,is_admin)
        VALUES (?,?,?,'Synthetic dealer integration account','10115','Synthetic town','DE','de',?)");
    $query->execute([$name, $name . '@example.invalid', auth_hash('Synthetic dealer fixture ' . $name), (int)$admin]);
    return (int)$pdo->lastInsertId();
}

function dti_dealer(PDO $pdo, int $id, string $name, string $status = 'published'): void
{
    $query = $pdo->prepare("INSERT INTO market_dealer_profiles(user_id,company_name,address_text,country_code,contact_email,legal_notice,status)
        VALUES (?,?,'Synthetic dealer location','DE',?,'Synthetic dealer profile for integration tests.',?)");
    $query->execute([$id, $name . ' synthetic company', $name . '@example.invalid', $status]);
}

function dti_order(PDO $pdo, int $seller, int $buyer, int $category): string
{
    $query = $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,shipping_cents,status,zip,city,country_code,pickup,shipping,trade_checkout_enabled)
        VALUES (?,?,'Synthetic office table','Synthetic listing for dealer operations.','fixed',4000,500,'active','10115','Synthetic town','DE',0,1,1)");
    $query->execute([$seller, $category]);
    $listing = (int)$pdo->lastInsertId();
    $quote = trade_quote($pdo, $buyer, $listing, 'shipping');
    $id = trade_purchase($pdo, $buyer, $listing, ['delivery' => 'shipping', 'quote_hash' => $quote['hash'],
        'request_key' => bin2hex(random_bytes(16)), 'confirm_purchase' => '1', 'legal_confirm' => '1',
        'legal_language' => $quote['locale'], 'legal_documents' => array_column($quote['documents'], 'content_hash', 'id')]);
    return trade_get($pdo, $seller, $id)['reference'];
}

function dti_request(CurlHandle $client, string $path, ?array $form = null): array
{
    curl_setopt_array($client, [CURLOPT_URL=>'http://127.0.0.1:8081'.$path, CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>false, CURLOPT_TIMEOUT=>15, CURLOPT_PROXY=>'']);
    if ($form === null) curl_setopt($client, CURLOPT_HTTPGET, true);
    else {
        curl_setopt($client, CURLOPT_POST, true);
        curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $body = curl_exec($client);
    dti_check(is_string($body), 'Synthetic HTTP request failed.');
    return [(int)curl_getinfo($client, CURLINFO_RESPONSE_CODE),$body];
}

function dti_csrf(string $body): string
{
    dti_check(preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $body, $match) === 1, 'Dealer form CSRF field missing.');
    return $match[1];
}

function dti_client(string $username): CurlHandle
{
    $handle = curl_init();
    curl_setopt($handle, CURLOPT_COOKIEFILE, '');
    [$status,$body] = dti_request($handle, '/auth/login.php');
    dti_check($status === 200, 'Synthetic login form unavailable.');
    [$status] = dti_request($handle, '/auth/login.php', ['csrf_token'=>dti_csrf($body),
        'login'=>$username,'password'=>'Synthetic dealer fixture '.$username]);
    dti_check($status === 303, 'Synthetic HTTP login failed.');
    return $handle;
}

$stage = 'fixtures';
try {
    $nonce = 'dti_' . bin2hex(random_bytes(8)) . '_';
    $owner = dti_account($pdo, $nonce . 'owner');
    $otherOwner = dti_account($pdo, $nonce . 'other');
    $pending = dti_account($pdo, $nonce . 'pending');
    $viewer = dti_account($pdo, $nonce . 'viewer');
    $manager = dti_account($pdo, $nonce . 'inventory');
    $operations = dti_account($pdo, $nonce . 'operations');
    $buyer = dti_account($pdo, $nonce . 'buyer');
    $admin = dti_account($pdo, $nonce . 'admin', true);
    dti_dealer($pdo, $owner, $nonce . 'owner');
    dti_dealer($pdo, $otherOwner, $nonce . 'other');
    dti_dealer($pdo, $pending, $nonce . 'pending', 'pending');

    $stage = 'invite_and_membership';
    foreach (['viewer' => $viewer, 'inventory' => $manager, 'operations' => $operations] as $role => $member) {
        dealer_team_invite($pdo, $owner, $nonce . $role, $role);
        $members = array_column(dealer_team_view($pdo, $owner)['members'], null, 'member_id');
        $invited = $members[$member] ?? [];
        $inviteVersion = (int)($invited['version'] ?? 0);
        dti_check($inviteVersion > 0 && ($invited['role'] ?? null) === $role && $invited['status'] === 'invited',
            'Owner invitation must record the requested member and role.');
        $memberView = dealer_team_view($pdo, $member);
        $teams = array_column($memberView['teams'], null, 'owner_id');
        dti_check(!$memberView['owned'] && ($teams[$owner]['status'] ?? null) === 'invited', 'Invitee cannot see own pending membership.');
        dti_deny(fn() => dealer_team_respond($pdo, $buyer, $owner, $inviteVersion, true), 'another member accepts invitation');
        dti_deny(fn() => dealer_inventory_list($pdo, $member, $owner), 'pending invitation reads stock');
        dealer_team_respond($pdo, $member, $owner, $inviteVersion, true);
        $teams = array_column(dealer_team_view($pdo, $member)['teams'], null, 'owner_id');
        dti_check(($teams[$owner]['status'] ?? null) === 'active' && $teams[$owner]['role'] === $role
            && (int)$teams[$owner]['version'] === $inviteVersion + 1, 'Acceptance must activate only the invitee at the next revision.');
    }
    $ownerView = dealer_team_view($pdo, $owner);
    $members = array_column($ownerView['members'], null, 'member_id');
    dti_check((bool)$ownerView['owned'] && count($members) === 3 && dealer_team_view($pdo, $buyer)['teams'] === [],
        'Invitation changed unrelated membership or owner visibility.');
    dti_deny(fn() => dealer_team_invite($pdo, $manager, $nonce . 'buyer', 'viewer'), 'inventory manager creates invitation');
    dti_deny(fn() => dealer_team_invite($pdo, $pending, $nonce . 'buyer', 'viewer'), 'unpublished dealer creates invitation');
    echo "PASS dealer invitation ownership and activation\n";

    $stage = 'stock_permissions_and_revisions';
    $id = dealer_inventory_create($pdo, $manager, $owner, ' stock-01 ', 'Synthetic office chair');
    dti_check(dealer_inventory_create($pdo, $manager, $owner, 'STOCK-01', 'Synthetic office chair') === $id,
        'An exact SKU creation retry must return the existing item.');
    dti_reject(fn() => dealer_inventory_create($pdo, $manager, $owner, 'STOCK-01', 'Mismatched label'),
        'conflicting SKU retry', MarketConflictException::class);
    $list = dealer_inventory_list($pdo, $viewer, $owner);
    $initial = dealer_inventory_details($pdo, $viewer, $owner, $id);
    dti_check(count($list['items']) === 1 && (int)$list['items'][0]['id'] === $id && $list['next'] === null
        && $initial['sku'] === 'STOCK-01' && (int)$initial['quantity'] === 0 && (int)$initial['version'] === 1
        && $initial['movements'] === [], 'New stock item must start at exactly zero with no movements.');
    dti_deny(fn() => dealer_inventory_create($pdo, $viewer, $owner, 'STOCK-02', 'Synthetic chair'), 'viewer creates stock');
    dti_deny(fn() => dealer_inventory_rename($pdo, $viewer, $owner, $id, 1, 'Changed label'), 'viewer renames stock');
    dti_deny(fn() => dealer_inventory_adjust($pdo, $viewer, $owner, $id, 1, bin2hex(random_bytes(16)), '1', 'Synthetic receipt'),
        'viewer adjusts stock');
    $request = bin2hex(random_bytes(16));
    dealer_inventory_adjust($pdo, $manager, $owner, $id, 1, $request, '7', 'Synthetic receipt');
    dealer_inventory_adjust($pdo, $manager, $owner, $id, 1, $request, '7', 'Synthetic receipt');
    dti_reject(fn() => dealer_inventory_adjust($pdo, $manager, $owner, $id, 1, $request, '8', 'Synthetic receipt'),
        'request key reused with different quantity', MarketConflictException::class);
    dti_reject(fn() => dealer_inventory_adjust($pdo, $manager, $owner, $id, 1, bin2hex(random_bytes(16)), '1', 'Synthetic stale change'),
        'stale stock version', MarketConflictException::class);
    dti_reject(fn() => dealer_inventory_adjust($pdo, $manager, $owner, $id, 2, bin2hex(random_bytes(16)), '-8', 'Synthetic underflow'),
        'change drives stock negative', DomainException::class);
    dealer_inventory_adjust($pdo, $manager, $owner, $id, 2, bin2hex(random_bytes(16)), '-2', 'Synthetic withdrawal');
    $item = dealer_inventory_details($pdo, $viewer, $owner, $id);
    $movements = array_map(static fn(array $row): array => [(int)$row['delta'], (int)$row['quantity_after'], (int)$row['actor_id'], $row['reason']], $item['movements']);
    dti_check((int)$item['quantity'] === 5 && (int)$item['version'] === 3 && $movements === [
        [-2, 5, $manager, 'Synthetic withdrawal'], [7, 7, $manager, 'Synthetic receipt'],
    ], 'Only successful distinct adjustments may change quantity, version and ledger.');
    dti_reject(fn() => dealer_inventory_rename($pdo, $manager, $owner, $id, 2, 'Stale label'),
        'stale rename revision', MarketConflictException::class);
    dealer_inventory_rename($pdo, $manager, $owner, $id, 3, 'Synthetic renovated office chair');
    $renamed = dealer_inventory_details($pdo, $viewer, $owner, $id);
    dti_check($renamed['label'] === 'Synthetic renovated office chair' && (int)$renamed['quantity'] === 5
        && (int)$renamed['version'] === 4 && $renamed['movements'] === $item['movements'],
        'Rename must advance revision without creating a stock movement.');
    echo "PASS inventory role, exact ledger, retries and revisions\n";

    $stage = 'order_privacy_and_tenant_scope';
    $otherStock = dealer_inventory_create($pdo, $otherOwner, $otherOwner, 'STOCK-01', 'Synthetic lamp');
    dti_check($otherStock !== $id, 'A SKU must be unique per dealer.');
    dti_deny(fn() => dealer_inventory_list($pdo, $manager, $otherOwner), 'manager reads another dealer stock');
    dti_reject(fn() => dealer_inventory_details($pdo, $manager, $owner, $otherStock), 'foreign item ID under authorized owner', MarketNotFoundException::class);
    dti_reject(fn() => dealer_inventory_adjust($pdo, $manager, $owner, $otherStock, 1, bin2hex(random_bytes(16)), '1', 'Synthetic foreign update'),
        'adjusts foreign item ID under authorized owner', MarketNotFoundException::class);
    $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')
        ->execute(['Synthetic office furniture', 'dealer-test-' . bin2hex(random_bytes(8))]);
    $category = (int)$pdo->lastInsertId();
    $ownOrder = dti_order($pdo, $owner, $buyer, $category);
    $foreignOrder = dti_order($pdo, $otherOwner, $buyer, $category);
    $orders = dealer_order_list($pdo, $operations, $owner);
    dti_check(count($orders) === 1 && $orders[0]['reference'] === $ownOrder && $orders[0]['reference'] !== $foreignOrder
        && $orders[0]['status'] === 'agreed' && $orders[0]['delivery'] === 'shipping' && (int)$orders[0]['total_cents'] === 4500,
        'Operations must see only their own dealer order with exact totals.');
    $fields = array_keys($orders[0]);
    sort($fields);
    dti_check($fields === ['created_at', 'delivery', 'reference', 'status', 'total_cents', 'updated_at']
        && !str_contains(json_encode($orders, JSON_THROW_ON_ERROR), $nonce . 'buyer'), 'Order summary exposes private buyer or contract fields.');
    dti_deny(fn() => dealer_order_list($pdo, $viewer, $owner), 'stock viewer reads orders');
    dti_deny(fn() => dealer_order_list($pdo, $operations, $otherOwner), 'operations reads another dealer orders');
    dti_deny(fn() => dealer_order_list($pdo, $admin, $owner), 'site administrator reads order without membership');
    dti_deny(fn() => dealer_inventory_list($pdo, $admin, $owner), 'site administrator reads stock without membership');
    echo "PASS dealer order summaries and tenant privacy\n";

    $stage = 'protected_team_and_stock_http';
    $ownerClient = dti_client($nonce . 'owner');
    $viewerClient = dti_client($nonce . 'viewer');
    try {
        [$teamStatus,$teamBody] = dti_request($ownerClient, '/user/dealer-team.php');
        dti_check($teamStatus === 200 && str_contains($teamBody, $nonce . 'inventory'), 'Owner cannot open the team page.');
        $stockUrl = '/user/dealer-stock.php?owner_id=' . $owner;
        [$viewStatus,$viewBody] = dti_request($viewerClient, $stockUrl . '&item_id=' . $id);
        dti_check($viewStatus === 200 && str_contains($viewBody, 'STOCK-01') && !str_contains($viewBody, 'name="action" value="adjust"'),
            'Read-only account does not get the scoped stock view.');
        $createFields = ['action'=>'create','sku'=>'STOCK-HTTP','label'=>'Synthetic HTTP shelf'];
        [$forbidden] = dti_request($viewerClient, $stockUrl, $createFields + ['csrf_token'=>dti_csrf($viewBody)]);
        dti_check($forbidden === 403, 'Viewer was not denied stock creation over HTTP.');
        [$ownerStatus,$ownerBody] = dti_request($ownerClient, $stockUrl);
        dti_check($ownerStatus === 200, 'Owner stock list unavailable.');
        [$noCsrf] = dti_request($ownerClient, $stockUrl, $createFields);
        [$created] = dti_request($ownerClient, $stockUrl, $createFields + ['csrf_token'=>dti_csrf($ownerBody)]);
        dti_check($noCsrf === 403 && $created === 303, 'Stock creation does not require CSRF or failed for the owner.');
    } finally {
        curl_close($ownerClient);
        curl_close($viewerClient);
    }
    echo "PASS dealer team and inventory HTTP permissions and CSRF\n";

    $stage = 'revocation_and_suspension';
    dealer_team_owner_change($pdo, $owner, $manager, (int)$members[$manager]['version'], 'revoke');
    dti_deny(fn() => dealer_inventory_list($pdo, $manager, $owner), 'revoked manager reads stock');
    dti_deny(fn() => dealer_inventory_create($pdo, $manager, $owner, 'STOCK-02', 'Synthetic chair'), 'revoked manager creates stock');
    $pdo->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$operations]);
    dti_deny(fn() => dealer_order_list($pdo, $operations, $owner), 'suspended operations account reads orders');
    $pdo->prepare("UPDATE market_dealer_profiles SET status='pending' WHERE user_id=?")->execute([$otherOwner]);
    dti_deny(fn() => dealer_inventory_list($pdo, $otherOwner, $otherOwner), 'unpublished dealer owns an existing stock item');
    echo "PASS membership revocation, account suspension and dealer publication gate\n";
} catch (Throwable $error) {
    $detail = $error->getFile() === __FILE__ ? ' ' . $error->getMessage() : '';
    fwrite(STDERR, "FAIL dealer $stage " . get_class($error) . ' at ' . basename($error->getFile()) . ':' . $error->getLine() . $detail . PHP_EOL);
    exit(1);
}
