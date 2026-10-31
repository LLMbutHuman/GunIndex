<?php
declare(strict_types=1);
/** Synthetic CLI fixtures only. No HTTP access, arbitrary SQL or historical data. */
if (PHP_SAPI !== 'cli' || getenv('DB_DATABASE') !== 'gunindex_rebuild_test') {
    if (PHP_SAPI !== 'cli') http_response_code(404);
    exit(2);
}
require_once __DIR__ . '/../config/bootstrap.php';

function fixture_name(mixed $value): string
{
    if (!is_string($value) || !preg_match('/\Atest_[a-f0-9]{16}\z/', $value)) throw new InvalidArgumentException('Synthetic identity required.');
    return $value;
}

function fixture_user(PDO $pdo, mixed $name): array
{
    $name = fixture_name($name);
    $q = $pdo->prepare("SELECT * FROM users WHERE username=? AND email=CONCAT(username,'@example.invalid') AND full_name='Synthetic test fixture'");
    $q->execute([$name]); $row = $q->fetch();
    if (!$row) throw new InvalidArgumentException('Synthetic account not found.');
    return $row;
}

function fixture_title(mixed $title): string
{
    if (!is_string($title) || !preg_match('/\AFixture [a-f0-9]{16}(?: |$)/', $title) || strlen($title) > 150) throw new InvalidArgumentException('Synthetic title required.');
    return $title;
}

function fixture_listing(PDO $pdo, mixed $id): array
{
    if (!is_int($id) || $id < 1) throw new InvalidArgumentException('Invalid listing identifier.');
    $q = $pdo->prepare("SELECT l.* FROM market_listings l JOIN users u ON u.id=l.seller_id WHERE l.id=? AND u.username REGEXP '^test_[a-f0-9]{16}$' AND u.email=CONCAT(u.username,'@example.invalid') AND u.full_name='Synthetic test fixture'");
    $q->execute([$id]); $row = $q->fetch();
    if (!$row) throw new InvalidArgumentException('Synthetic listing not found.');
    fixture_title($row['title']);
    return $row;
}

try {
    $raw = stream_get_contents(STDIN, 20001);
    if ($raw === false || strlen($raw) > 20000) throw new InvalidArgumentException('Fixture input limit.');
    $request = json_decode($raw, true, 24, JSON_THROW_ON_ERROR);
    if (!is_array($request) || !is_string($request['operation'] ?? null)) throw new InvalidArgumentException('Invalid fixture operation.');
    $operation = $request['operation'];
    $out = null;
    if ($operation === 'reset_limits') {
        $directory = getenv('AUTH_RATE_LIMIT_DIR');
        if ($directory !== '/var/lib/gunindex/limits' || is_link($directory)) throw new RuntimeException('Unexpected test limiter directory.');
        if (is_dir($directory)) foreach (glob($directory . '/*.json') ?: [] as $file) {
            if (is_link($file) || !preg_match('/\A[a-f0-9]{64}\.json\z/', basename($file))) throw new RuntimeException('Unexpected limiter file.');
            $handle = fopen($file, 'r+b');
            if ($handle === false || !flock($handle, LOCK_EX)) throw new RuntimeException('Test limiter lock unavailable.');
            try { if (!ftruncate($handle, 0) || !fflush($handle)) throw new RuntimeException('Limiter reset failed.'); }
            finally { flock($handle, LOCK_UN); fclose($handle); }
        }
        $out = ['ok' => true];
    } elseif ($operation === 'seed') {
        $name = fixture_name($request['username'] ?? null);
        $password = $request['password'] ?? null; $admin = $request['admin'] ?? false; $mfa = $request['mfa'] ?? true;
        if (!is_string($password) || !is_bool($admin) || !is_bool($mfa)) throw new InvalidArgumentException('Invalid synthetic account.');
        $secret = $admin && $mfa ? auth_secret_encrypt(random_bytes(20)) : null;
        $q = $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,is_admin,mfa_secret) VALUES (?,?,?,'Synthetic test fixture','10115','Teststadt',?,?)");
        $q->execute([$name, $name . '@example.invalid', auth_hash($password), (int)$admin, $secret]);
        $out = ['id' => (int)$pdo->lastInsertId()];
    } elseif ($operation === 'code') {
        $user = fixture_user($pdo, $request['username'] ?? null);
        if (!$user['is_admin'] || !$user['mfa_secret']) throw new InvalidArgumentException('MFA fixture required.');
        $counter = max(intdiv(time(), 30), (int)($user['mfa_last_counter'] ?? -1) + 1);
        if ($counter > intdiv(time(), 30) + 1) throw new RuntimeException('Wait for a fresh authenticator window.');
        $out = ['code' => auth_totp(auth_secret_decrypt($user['mfa_secret']), $counter)];
    } elseif ($operation === 'account') {
        $user = fixture_user($pdo, $request['username'] ?? null);
        $password = $request['password'] ?? '';
        if (!is_string($password) || strlen($password) > 1024) throw new InvalidArgumentException('Invalid password probe.');
        $out = ['id' => (int)$user['id'], 'auth_version' => (int)$user['auth_version'], 'password_matches' => password_verify($password, $user['password_hash'])];
    } elseif ($operation === 'categories') {
        $out = ['categories' => catalog_categories($pdo)];
    } elseif ($operation === 'category') {
        $slug = $request['slug'] ?? null;
        if (!is_string($slug) || !preg_match('/\Atest-[a-f0-9]{16}\z/', $slug)) throw new InvalidArgumentException('Synthetic category required.');
        $q = $pdo->prepare('SELECT id,parent_id,name,slug,is_active FROM market_categories WHERE slug=?'); $q->execute([$slug]); $row = $q->fetch();
        $out = $row ? $row + ['available' => catalog_category_allowed($pdo, (int)$row['id'])] : ['id' => null];
    } elseif ($operation === 'seed_listing') {
        $seller = fixture_user($pdo, $request['username'] ?? null); $title = fixture_title($request['title'] ?? null);
        $category = $request['category_id'] ?? null; $type = $request['type'] ?? 'auction';
        $price = $request['price_cents'] ?? 1000; $buyout = $request['buyout_cents'] ?? null; $seconds = $request['ends_in'] ?? 86400;
        if (!is_int($category) || !catalog_category_allowed($pdo, $category) || !in_array($type, ['fixed', 'auction'], true)
            || !is_int($price) || $price < 1 || $price > 9999999999 || ($buyout !== null && (!is_int($buyout) || $buyout < $price || $buyout > 9999999999))
            || !is_int($seconds) || $seconds < -3600 || $seconds > 1209600) throw new InvalidArgumentException('Invalid synthetic listing.');
        $q = $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,buyout_cents,status,ends_at,zip,city,pickup,shipping,condition_label) VALUES (?,?,?,'Synthetic ordinary product used only for isolated regression tests.',?,?,?,'active',?,'10115','Teststadt',1,1,'used')");
        $q->execute([$seller['id'], $category, $title, $type, $price, $buyout, $type === 'auction' ? gmdate('Y-m-d H:i:s', time() + $seconds) : null]);
        $out = ['id' => (int)$pdo->lastInsertId()];
    } elseif ($operation === 'find_listing') {
        $user = fixture_user($pdo, $request['username'] ?? null); $title = fixture_title($request['title'] ?? null);
        $q = $pdo->prepare('SELECT id,status FROM market_listings WHERE seller_id=? AND title=? ORDER BY id DESC LIMIT 1'); $q->execute([$user['id'], $title]);
        $out = $q->fetch() ?: ['id' => null];
    } elseif (in_array($operation, ['state', 'expire', 'inconsistent', 'bid'], true)) {
        $listing = fixture_listing($pdo, $request['listing_id'] ?? null); $id = (int)$listing['id'];
        if ($operation === 'expire') {
            $pdo->prepare('UPDATE market_listings SET ends_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id=?')->execute([$id]); $out = ['ok' => true];
        } elseif ($operation === 'inconsistent') {
            if (!market_has_bids($pdo, $id)) throw new InvalidArgumentException('Bid fixture required.');
            $pdo->prepare('UPDATE market_listings SET current_bid_cents=9999999999 WHERE id=?')->execute([$id]); $out = ['ok' => true];
        } elseif ($operation === 'bid') {
            $user = fixture_user($pdo, $request['username'] ?? null); $maximum = $request['maximum'] ?? null; $start = $request['start_at_ms'] ?? null;
            if (!is_int($maximum) || $maximum < 1 || $maximum > 9999999999) throw new InvalidArgumentException('Invalid synthetic maximum.');
            if ($start !== null) {
                $now = (int)floor(microtime(true) * 1000);
                if (!is_int($start) || $start > $now + 5000 || $start < $now - 5000) throw new InvalidArgumentException('Invalid synthetic barrier.');
                while ((int)floor(microtime(true) * 1000) < $start) usleep(2000);
            }
            try { $result = market_bid($pdo, $id, (int)$user['id'], $maximum); $out = ['ok' => true, 'price_cents' => $result['price_cents']]; }
            catch (MarketAccessException | MarketConflictException | MarketNotFoundException | InvalidArgumentException $error) { $out = ['ok' => false, 'kind' => get_class($error)]; }
        } else {
            $q = $pdo->prepare('SELECT COUNT(*) FROM market_bids WHERE listing_id=?'); $q->execute([$id]); $events = (int)$q->fetchColumn();
            $q = $pdo->prepare('SELECT COUNT(*) FROM market_images WHERE listing_id=?'); $q->execute([$id]); $images = (int)$q->fetchColumn();
            $out = ['id' => $id, 'status' => $listing['status'], 'type' => $listing['type'], 'price_cents' => (int)$listing['price_cents'],
                'current_bid_cents' => $listing['current_bid_cents'] === null ? null : (int)$listing['current_bid_cents'], 'highest_bidder_id' => $listing['highest_bidder_id'] === null ? null : (int)$listing['highest_bidder_id'],
                'ends_at' => $listing['ends_at'], 'events' => $events, 'images' => $images];
        }
    } elseif ($operation === 'rating_state') {
        $user=fixture_user($pdo,$request['username']??null);$listing=fixture_listing($pdo,$request['listing_id']??null);$q=$pdo->prepare('SELECT id,status FROM market_ratings WHERE listing_id=? AND sender_id=?');$q->execute([$listing['id'],$user['id']]);$out=$q->fetch();
    } elseif ($operation === 'automatic_guard') {
        $user=fixture_user($pdo,$request['username']??null);$id=$request['invoice_id']??null;if(!is_int($id))throw new InvalidArgumentException();payments_invoice($pdo,$id,(int)$user['id']);try{payments_assert_automatic($pdo,$id);$out=['allowed'=>true];}catch(PaymentConflict){$out=['allowed'=>false];}
    } elseif ($operation === 'manual_state') {
        $user=fixture_user($pdo,$request['username']??null);$id=$request['invoice_id']??null;if(!is_int($id))throw new InvalidArgumentException();
        $q=$pdo->prepare('SELECT i.id,i.status,i.gross_cents,o.reference,o.recipient,o.quantity,o.customer_claimed_at,(SELECT COALESCE(SUM(remaining),0) FROM market_bump_credits c WHERE c.invoice_id=i.id) AS credits,(SELECT COUNT(*) FROM market_payment_attempts a WHERE a.invoice_id=i.id AND status=\'manual_verified\') AS approvals FROM market_invoices i JOIN market_manual_orders o ON o.invoice_id=i.id WHERE i.id=? AND i.user_id=?');$q->execute([$id,$user['id']]);$out=$q->fetch();if(!$out)throw new InvalidArgumentException();
    } elseif ($operation === 'bump_state') {
        $listing=fixture_listing($pdo,$request['listing_id']??null);$q=$pdo->prepare('SELECT COUNT(*) FROM market_bump_events WHERE listing_id=?');$q->execute([$listing['id']]);$out=['created_at'=>$listing['created_at'],'ends_at'=>$listing['ends_at'],'bumped_at'=>$listing['bumped_at'],'current_bid_cents'=>$listing['current_bid_cents'],'bumps'=>(int)$q->fetchColumn()];
    } elseif ($operation === 'invoice') {
        $user = fixture_user($pdo, $request['username'] ?? null);
        $q = $pdo->prepare("INSERT INTO market_invoices(user_id,service_code,fee_version,gross_cents,status) VALUES (?,'bump_single','test-1.0',199,'draft')"); $q->execute([$user['id']]);
        $out = ['id' => (int)$pdo->lastInsertId()];
    } else throw new InvalidArgumentException('Unsupported fixture operation.');
    fwrite(STDOUT, json_encode($out, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
} catch (Throwable $error) {
    // Never expose submitted passwords, MFA secrets, DB values or SQL errors.
    fwrite(STDERR, 'Synthetic fixture operation failed.' . PHP_EOL);
    exit(2);
}
