<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(2);
}
if (PHP_OS_FAMILY !== 'Linux' || getenv('APP_ENV') !== 'development' || getenv('DEALER_API_ENABLED') !== '1'
    || getenv('DB_DATABASE') !== 'gunindex_rebuild_test' || getenv('DB_USERNAME') !== 'gunindex_test'
    || getenv('DB_SOCKET') !== '/var/run/mysqld/mysqld.sock' || !extension_loaded('curl')) {
    exit(2);
}
require_once __DIR__ . '/../config/bootstrap.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'gunindex_rebuild_test') {
    exit(2);
}
$_SESSION['locale'] = 'de';

function dai_check(bool $condition, string $failure): void
{
    if (!$condition) {
        throw new RuntimeException($failure);
    }
}

function dai_reject(callable $operation, string $scenario): void
{
    try {
        $operation();
    } catch (DomainException) {
        return;
    }
    throw new RuntimeException('Invalid key operation was accepted in ' . $scenario . '.');
}

function dai_account(PDO $pdo, string $name, #[SensitiveParameter] string $password, bool $owner = false): int
{
    $query = $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code,preferred_locale)
        VALUES (?,?,?,'Synthetic dealer API account','10115','Synthetic town','DE','de')");
    $query->execute([$name, $name . '@example.invalid', auth_hash($password)]);
    $id = (int)$pdo->lastInsertId();
    if ($owner) {
        $query = $pdo->prepare("INSERT INTO market_dealer_profiles(user_id,company_name,address_text,country_code,contact_email,legal_notice,status)
            VALUES (?,?,'Synthetic dealer location','DE',?,'Synthetic dealer profile for API integration tests.','published')");
        $query->execute([$id, $name . ' synthetic company', $name . '@example.invalid']);
    }
    return $id;
}

function dai_order(PDO $pdo, int $seller, int $buyer, int $category): string
{
    $query = $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,shipping_cents,status,zip,city,country_code,pickup,shipping,trade_checkout_enabled)
        VALUES (?,?,'Synthetic office lamp','Synthetic listed product for dealer API tests.','fixed',3200,550,'active','10115','Synthetic town','DE',0,1,1)");
    $query->execute([$seller, $category]);
    $listing = (int)$pdo->lastInsertId();
    $quote = trade_quote($pdo, $buyer, $listing, 'shipping');
    $id = trade_purchase($pdo, $buyer, $listing, ['delivery' => 'shipping', 'quote_hash' => $quote['hash'],
        'request_key' => bin2hex(random_bytes(16)), 'confirm_purchase' => '1', 'legal_confirm' => '1',
        'legal_language' => $quote['locale'], 'legal_documents' => array_column($quote['documents'], 'content_hash', 'id')]);
    return trade_get($pdo, $seller, $id)['reference'];
}

function dai_request(string $path, string $method = 'GET', #[SensitiveParameter] array $headers = [],
    #[SensitiveParameter] ?string $payload = null, ?CurlHandle $client = null): array
{
    $client ??= curl_init();
    $responseHeaders = [];
    curl_setopt_array($client, [CURLOPT_URL => 'http://127.0.0.1:8081' . $path, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROXY => '', CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 15,
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => array_merge(['Connection: close'], $headers),
        CURLOPT_HEADERFUNCTION => static function ($client, string $line) use (&$responseHeaders): int {
            if (str_contains($line, ':')) {
                [$field, $value] = explode(':', $line, 2);
                $responseHeaders[strtolower($field)] = trim($value);
            }
            return strlen($line);
        }]);
    if ($method === 'POST') {
        curl_setopt_array($client, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload]);
    } else {
        // Keep body processing enabled for HEAD so forbidden response bytes would fail the assertion.
        curl_setopt($client, CURLOPT_HTTPGET, true);
    }
    $body = curl_exec($client);
    dai_check(is_string($body), 'Local HTTP request failed.');
    return [(int)curl_getinfo($client, CURLINFO_RESPONSE_CODE), $responseHeaders, $body];
}

function dai_session(string $username, #[SensitiveParameter] string $password): CurlHandle
{
    $client = curl_init();
    curl_setopt($client, CURLOPT_COOKIEFILE, '');
    [$status, , $body] = dai_request('/auth/login.php', client: $client);
    dai_check($status === 200 && preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $body, $parts) === 1,
        'Synthetic account login form unavailable.');
    [$status] = dai_request('/auth/login.php', 'POST', ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query(['csrf_token' => $parts[1], 'login' => $username, 'password' => $password]), $client);
    dai_check($status === 303, 'Synthetic owner could not authenticate in the browser.');
    [$status] = dai_request('/user/dealer.php', client: $client);
    dai_check($status === 200, 'Synthetic owner has no usable browser session.');
    return $client;
}

function dai_api(array $query, int $expected, #[SensitiveParameter] ?string $token = null, ?array $payload = null,
    bool $head = false, ?CurlHandle $client = null): array
{
    $headers = $token === null ? [] : ['Authorization: Bearer ' . $token];
    if ($payload !== null) $headers[] = 'Content-Type: application/json';
    [$status, $responseHeaders, $body] = dai_request('/api/dealer/v1.php?' . http_build_query($query),
        $head ? 'HEAD' : ($payload === null ? 'GET' : 'POST'), $headers,
        $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR), $client);
    dai_check($status === $expected, 'API status ' . $status . ' instead of ' . $expected . '.');
    dai_check(!isset($responseHeaders['set-cookie']) && ($responseHeaders['cache-control'] ?? '') === 'no-store'
        && str_starts_with($responseHeaders['content-type'] ?? '', 'application/json'), 'API response violated stateless/private JSON contract.');
    if ($status === 401) {
        dai_check(str_starts_with($responseHeaders['www-authenticate'] ?? '', 'Bearer'), 'Missing bearer authentication challenge.');
    }
    if ($head) {
        dai_check($body === '', 'HEAD response contains a body.');
        return [];
    }
    $decoded = json_decode($body, true, 12, JSON_THROW_ON_ERROR);
    dai_check(is_array($decoded), 'API did not return a JSON object.');
    return $decoded;
}

$stage = 'fixture';
try {
    $nonce = 'dai_' . bin2hex(random_bytes(8)) . '_';
    $ownerName = $nonce . 'owner';
    $ownerPassword = bin2hex(random_bytes(24));
    $owner = dai_account($pdo, $ownerName, $ownerPassword, true);
    $otherOwner = dai_account($pdo, $nonce . 'other', bin2hex(random_bytes(24)), true);
    $buyer = dai_account($pdo, $nonce . 'buyer', bin2hex(random_bytes(24)));
    $id = dealer_inventory_create($pdo, $owner, $owner, 'API-01', 'Synthetic office lamp');
    $foreignId = dealer_inventory_create($pdo, $otherOwner, $otherOwner, 'API-01', 'Synthetic side table');
    $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')
        ->execute(['Synthetic home office furniture', 'dealer-api-' . bin2hex(random_bytes(8))]);
    $category = (int)$pdo->lastInsertId();
    $ownOrder = dai_order($pdo, $owner, $buyer, $category);
    $foreignOrder = dai_order($pdo, $otherOwner, $buyer, $category);
    $inventory = ['resource'=>'inventory'];
    $stock = ['resource'=>'stock_movement'];
    $orders = ['resource'=>'orders'];

    $stage = 'issue_and_redaction';
    $scopes = ['inventory.read','inventory.write','orders.read'];
    $issueRequest = bin2hex(random_bytes(16));
    dai_reject(fn() => dealer_api_issue($pdo, $owner, 'Synthetic authorized client', $scopes, 'incorrect password', $issueRequest), 'password confirmation');
    $token = dealer_api_issue($pdo, $owner, 'Synthetic authorized client', $scopes, $ownerPassword, $issueRequest);
    dai_check(preg_match('/\Agxdk_([a-f0-9]{24})_([a-f0-9]{64})\z/', $token, $parts) === 1, 'Key has incorrect format.');
    dai_reject(fn() => dealer_api_issue($pdo, $owner, 'Synthetic authorized client', $scopes, $ownerPassword, $issueRequest), 'issuance replay');
    $keys = dealer_api_keys($pdo, $owner);
    dai_check(count($keys) === 1 && $keys[0]['scopes'] === $scopes && $keys[0]['revoked_at'] === null
        && dealer_api_keys($pdo, $otherOwner) === [], 'Issuance replay or foreign key visibility.');
    $keyId = (int)$keys[0]['id'];
    $metadata = array_keys($keys[0]);
    sort($metadata);
    dai_check($metadata === ['created_at','expires_at','id','label','revoked_at','scopes'], 'Key listing exposes credential data.');
    $query = $pdo->prepare('SELECT token_hash FROM market_dealer_api_keys WHERE id=? AND owner_id=?');
    $query->execute([$keyId, $owner]);
    $hashed = $query->fetchColumn();
    dai_check(is_string($hashed) && hash_equals(hash('sha256', $parts[2]), $hashed), 'Key storage does not contain the expected one-way hash.');
    unset($parts, $hashed);
    dai_reject(fn() => dealer_api_revoke($pdo, $otherOwner, $keyId), 'foreign key revocation');
    echo "PASS dealer API token issuance, redacted listing and tenant ownership\n";

    $stage = 'http_stateless_and_tenant_reads';
    dai_check(dai_api($inventory, 401) === ['error'=>'invalid_token'], 'Missing authorization was not rejected.');
    $falseSecret = substr($token, 0, -1) . (str_ends_with($token, 'f') ? 'e' : 'f');
    dai_check(dai_api($inventory, 401, $falseSecret) === ['error'=>'invalid_token'], 'A false secret authenticated.');
    dai_api($inventory + ['token'=>'synthetic-never-issued-query-key'], 400);
    dai_check(dai_api($inventory, 401, client: dai_session($ownerName, $ownerPassword)) === ['error'=>'invalid_token'],
        'An authenticated browser cookie granted API access.');
    $list = dai_api($inventory, 200, $token);
    dai_check(count($list['items']) === 1 && (int)$list['items'][0]['id'] === $id, 'Inventory list escaped dealer scope.');
    dai_api($inventory + ['id'=>$id], 200, $token, head: true);
    dai_api($inventory + ['id'=>$foreignId], 404, $token);
    dai_api($inventory + ['owner_id'=>$otherOwner], 400, $token);
    dai_api($orders + ['owner_id'=>$otherOwner], 400, $token);
    $list = dai_api($orders, 200, $token)['orders'];
    dai_check(count($list) === 1 && $list[0]['reference'] === $ownOrder && $list[0]['reference'] !== $foreignOrder
        && (int)$list[0]['total_cents'] === 3750 && $list[0]['status'] === 'agreed' && $list[0]['delivery'] === 'shipping',
        'Order list escaped dealer scope or altered order totals.');
    $orderFields = array_keys($list[0]);
    sort($orderFields);
    dai_check($orderFields === ['created_at','delivery','reference','status','total_cents','updated_at'], 'Order API exposes private fields.');
    echo "PASS dealer API bearer-only authentication and private inventory/order reads\n";

    $stage = 'http_stock_write_and_retry';
    $movement = ['id'=>$id,'version'=>1,'request_key'=>bin2hex(random_bytes(16)),'amount'=>7,'reason'=>'Synthetic stock addition'];
    dai_api($stock, 404, $token, array_replace($movement, ['id'=>$foreignId]));
    dai_api($stock, 400, $token, $movement + ['owner_id'=>$otherOwner]);
    dai_api($stock, 422, $token, array_replace($movement, ['id'=>(string)$id]));
    dai_api($stock, 422, $token, array_replace($movement, ['version'=>'1']));
    dai_api($stock, 422, $token, array_replace($movement, ['amount'=>'7']));
    $first = dai_api($stock, 200, $token, $movement);
    dai_check($first === ['id'=>$id,'recorded'=>true] && dai_api($stock, 200, $token, $movement) === $first,
        'An exact stock request retry did not return the existing result.');
    dai_api($stock, 409, $token, array_replace($movement, ['amount'=>8]));
    $item = dai_api($inventory + ['id'=>$id], 200, $token)['item'];
    dai_check((int)$item['quantity'] === 7 && (int)$item['version'] === 2 && count($item['movements']) === 1
        && (int)$item['movements'][0]['delta'] === 7 && (int)$item['movements'][0]['quantity_after'] === 7,
        'Retries or denied API writes changed stock more than once.');
    $audit = $pdo->prepare("SELECT COUNT(*) FROM admin_logs WHERE admin_id=? AND action_type='dealer.api.stock_movement' AND target_id=?");
    $audit->execute([$owner,$id]);
    dai_check((int)$audit->fetchColumn() === 1, 'A stock retry or denied request duplicated the audit event.');
    $foreign = dealer_inventory_details($pdo, $otherOwner, $otherOwner, $foreignId);
    dai_check((int)$foreign['quantity'] === 0 && $foreign['movements'] === [], 'Foreign stock was modified.');
    echo "PASS strict JSON stock modification, cross-tenant denial and exact retry\n";

    $stage = 'http_live_authorization';
    // Remove the scope in the isolated fixture, then prove the next call uses the persisted scope.
    $pdo->prepare('UPDATE market_dealer_api_keys SET scopes=1 WHERE id=? AND owner_id=?')->execute([$keyId,$owner]);
    dai_api($inventory, 200, $token);
    dai_check(dai_api($orders, 403, $token) === ['error'=>'insufficient_scope'], 'Withdrawn read scope still grants order access.');
    $next = array_replace($movement, ['version'=>2,'request_key'=>bin2hex(random_bytes(16))]);
    dai_check(dai_api($stock, 403, $token, $next) === ['error'=>'insufficient_scope'], 'Withdrawn write scope still grants stock access.');
    dealer_api_revoke($pdo, $owner, $keyId);
    dai_check(dai_api($inventory, 401, $token) === ['error'=>'invalid_token'], 'Revocation did not take effect on the next HTTP request.');

    $shortLived = dealer_api_issue($pdo, $owner, 'Synthetic short-lived client', ['inventory.read'], $ownerPassword, bin2hex(random_bytes(16)));
    $active = dealer_api_keys($pdo, $owner)[0];
    dai_check($active['revoked_at'] === null && (int)$active['id'] !== $keyId, 'Fresh fixture key unavailable for expiry checks.');
    dai_api($inventory, 200, $shortLived);
    $pdo->prepare("UPDATE market_dealer_profiles SET status='pending' WHERE user_id=?")->execute([$owner]);
    dai_api($inventory, 401, $shortLived);
    $pdo->prepare("UPDATE market_dealer_profiles SET status='published' WHERE user_id=?")->execute([$owner]);
    dai_api($inventory, 200, $shortLived);
    $pdo->prepare('UPDATE market_dealer_api_keys SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id=? AND owner_id=?')
        ->execute([(int)$active['id'],$owner]);
    dai_api($inventory, 401, $shortLived);
    echo "PASS immediate scope withdrawal, key revocation, expiry and publication checks\n";
} catch (Throwable $error) {
    $detail = $error->getFile() === __FILE__ && $error instanceof RuntimeException && !$error instanceof JsonException
        ? ' ' . $error->getMessage() : '';
    fwrite(STDERR, "FAIL dealer API $stage " . get_class($error) . ' at ' . basename($error->getFile()) . ':' . $error->getLine() . $detail . PHP_EOL);
    exit(1);
}
