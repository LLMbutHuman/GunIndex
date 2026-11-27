<?php
declare(strict_types=1);
/** Synthetic DB/HTTP checks. Run sequentially inside the isolated Linux runtime. */
if (PHP_SAPI !== 'cli' || getenv('DB_DATABASE') !== 'gunindex_rebuild_test') {
    if (PHP_SAPI !== 'cli') http_response_code(404);
    exit(2);
}
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/setup.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'gunindex_rebuild_test') exit(2);

$passed = 0;
function setup_integration_check(bool $condition): void {
    if (!$condition) {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        throw new RuntimeException('Assertion at ' . basename($caller['file']) . ':' . $caller['line']);
    }
}
function setup_integration_test(string $name, callable $run): void {
    global $passed;
    try { $run(); $passed++; fwrite(STDOUT, 'PASS '.$name."\n"); }
    catch (Throwable $error) {
        // Never log fixture credentials, SQL statements, response bodies or configuration.
        $location = preg_match('/\AAssertion at [a-z0-9_]+\.php:\d+\z/', $error->getMessage())
            ? $error->getMessage() : 'at '.basename($error->getFile()).':'.$error->getLine();
        fwrite(STDERR, 'FAIL '.$name.' ('.get_class($error).' '.$location.")\n");
        exit(1);
    }
}
function setup_integration_user(PDO $pdo, bool $admin = false, bool $mfa = false): array {
    $name = 'test_'.bin2hex(random_bytes(8));
    $password = 'Synthetic setup '.bin2hex(random_bytes(16));
    $secret = $mfa ? random_bytes(20) : null;
    $query = $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,is_admin,mfa_secret) VALUES (?,?,?,'Setup synthetic fixture','10115','Teststadt',?,?)");
    $query->execute([$name, $name.'@example.invalid', auth_hash($password), (int)$admin, $secret === null ? null : auth_secret_encrypt($secret)]);
    return ['id'=>(int)$pdo->lastInsertId(), 'username'=>$name, 'password'=>$password, 'secret'=>$secret];
}
function setup_integration_browser(): CurlHandle {
    $browser = curl_init();
    curl_setopt_array($browser, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_COOKIEFILE=>'', CURLOPT_PROXY=>'', CURLOPT_TIMEOUT=>15, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_FOLLOWLOCATION=>false]);
    return $browser;
}
function setup_integration_request(CurlHandle $browser, string $path, ?array $fields = null): array {
    // Fixed loopback endpoint; no configurable remote host or automatic redirects.
    setup_integration_check(str_starts_with($path, '/') && !str_starts_with($path, '//'));
    curl_setopt($browser, CURLOPT_URL, 'http://127.0.0.1:8081'.$path);
    $headers = [];
    curl_setopt($browser, CURLOPT_HEADERFUNCTION, static function (CurlHandle $curl, string $line) use (&$headers): int {
        if (str_contains($line, ':')) {
            [$key, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($key))] = trim($value);
        }
        return strlen($line);
    });
    if ($fields === null) curl_setopt($browser, CURLOPT_HTTPGET, true);
    else {
        curl_setopt($browser, CURLOPT_POST, true);
        curl_setopt($browser, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $body = curl_exec($browser);
    setup_integration_check(is_string($body));
    return ['status'=>(int)curl_getinfo($browser, CURLINFO_RESPONSE_CODE), 'body'=>$body, 'headers'=>$headers];
}
function setup_integration_csrf(string $body): string {
    setup_integration_check(preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $body, $matches) === 1);
    return $matches[1];
}
function setup_integration_login(CurlHandle $browser, array $user): void {
    $login = setup_integration_request($browser, '/auth/login.php');
    setup_integration_check($login['status'] === 200);
    $result = setup_integration_request($browser, '/auth/login.php', [
        'csrf_token'=>setup_integration_csrf($login['body']), 'login'=>$user['username'], 'password'=>$user['password'],
    ]);
    setup_integration_check($result['status'] === 303);
}
function setup_integration_mfa(CurlHandle $browser, array $user): void {
    $form = setup_integration_request($browser, '/auth/mfa.php');
    setup_integration_check($form['status'] === 200 && is_string($user['secret']));
    $result = setup_integration_request($browser, '/auth/mfa.php', [
        'csrf_token'=>setup_integration_csrf($form['body']), 'code'=>auth_totp($user['secret'], intdiv(time(), 30)),
    ]);
    setup_integration_check($result['status'] === 303);
}

$admin = setup_integration_user($pdo, true, true);
$ordinary = setup_integration_user($pdo);
$finance = setup_integration_user($pdo, false, true);
governance_role_change($pdo, $admin['id'], $finance['id'], 'finance', true);
$browser = setup_integration_browser();
try {
    setup_integration_test('anonymous_setup_redirects_without_diagnostics', static function () use ($browser): void {
        $result = setup_integration_request($browser, '/admin/setup.php');
        setup_integration_check($result['status'] === 303);
        setup_integration_check(($result['headers']['location'] ?? '') === '/auth/login.php');
        setup_integration_check(!str_contains($result['body'], 'id="setup-'));
    });

    setup_integration_test('ordinary_account_denied_http_and_fact_collection', static function () use ($pdo, $ordinary): void {
        $client = setup_integration_browser();
        try {
            setup_integration_login($client, $ordinary);
            $result = setup_integration_request($client, '/admin/setup.php');
            setup_integration_check($result['status'] === 403 && !str_contains($result['body'], 'id="setup-'));
            setup_integration_check(setup_integration_request($client, '/admin/roles.php')['status'] === 403);
            try { setup_collect($pdo, $ordinary['id']); }
            catch (DomainException) { return; }
            throw new RuntimeException('Unprivileged fact collection was allowed.');
        } finally { curl_close($client); }
    });

    setup_integration_test('administrator_password_without_mfa_cannot_read_setup', static function () use ($browser, $admin): void {
        setup_integration_login($browser, $admin);
        $result = setup_integration_request($browser, '/admin/setup.php');
        setup_integration_check($result['status'] === 303 && !str_contains($result['body'], 'id="setup-'));
    });

    setup_integration_test('mfa_administrator_can_read_all_steps_with_private_headers', static function () use ($browser, $admin): void {
        setup_integration_mfa($browser, $admin);
        $result = setup_integration_request($browser, '/admin/setup.php');
        setup_integration_check($result['status'] === 200);
        setup_integration_check(str_contains($result['headers']['cache-control'] ?? '', 'no-store'));
        setup_integration_check(str_contains($result['headers']['x-robots-tag'] ?? '', 'noindex'));
        setup_integration_check(preg_match_all('/<section\b[^>]*\bid="setup-[a-z]+"/', $result['body']) === 18);
        foreach(['paypal','paypal_refund','bank','bitcoin','escrow'] as $provider){
            setup_integration_check(str_contains($result['body'],'data-provider="'.$provider.'"'));
        }
        setup_integration_check(str_contains($result['body'],'data-provider="escrow" data-status="disabled"'));
    });

    setup_integration_test('mfa_finance_role_still_lacks_setup_permission', static function () use ($finance): void {
        $client = setup_integration_browser();
        try {
            setup_integration_login($client, $finance);
            setup_integration_mfa($client, $finance);
            $result = setup_integration_request($client, '/admin/setup.php');
            setup_integration_check($result['status'] === 403 && !str_contains($result['body'], 'id="setup-'));
            setup_integration_check(setup_integration_request($client, '/admin/roles.php')['status'] === 403);
        } finally { curl_close($client); }
    });

    setup_integration_test('setup_actions_require_csrf_and_checks_leave_settings_intact', static function () use ($pdo, $browser): void {
        $before = $pdo->query('SELECT setting_key,setting_value,updated_at FROM system_settings ORDER BY setting_key')->fetchAll();
        $path = '/admin/setup.php';
        $result = setup_integration_request($browser, $path, ['action'=>'activate', 'confirm'=>'1']);
        setup_integration_check($result['status'] === 403);
        $token = setup_integration_csrf(setup_integration_request($browser, $path)['body']);
        $result = setup_integration_request($browser, $path, ['action'=>'activate', 'confirm'=>'1', 'csrf_token'=>$token]);
        setup_integration_check($result['status'] === 409);
        $result = setup_integration_request($browser, $path, ['action'=>'run_checks', 'csrf_token'=>$token, 'manual_paypal_enabled'=>'1', 'finance_enabled'=>'1']);
        setup_integration_check($result['status'] === 303 && ($result['headers']['location'] ?? '') === '/admin/setup.php?step=acceptance#installation');
        $result = setup_integration_request($browser, $path);
        setup_integration_check($result['status'] === 200 && str_contains($result['body'], 'data-installation-check="database" data-status="passed"'));
        setup_integration_check(str_contains($result['body'], 'data-installation-check="https" data-status="failed"') && !str_contains($result['body'], 'value="activate"'));
        setup_integration_check($before === $pdo->query('SELECT setting_key,setting_value,updated_at FROM system_settings ORDER BY setting_key')->fetchAll());
    });

    setup_integration_test('wizard_saves_and_resumes_an_account_scoped_step', static function () use ($pdo,$browser,$admin): void {
        $path = '/admin/setup.php';
        $token = setup_integration_csrf(setup_integration_request($browser,$path)['body']);
        setup_integration_check(setup_integration_request($browser,$path,['action'=>'save_step','step'=>'workers'])['status'] === 403);
        $fields = ['action'=>'save_step','step'=>'workers','csrf_token'=>$token];
        $result = setup_integration_request($browser,$path,$fields);
        setup_integration_check($result['status'] === 303 && ($result['headers']['location'] ?? '') === '/admin/setup.php?step=workers#setup-workers');
        $result = setup_integration_request($browser,$path);
        setup_integration_check(str_contains($result['body'],'href="/admin/setup.php?step=workers#setup-workers" aria-current="step"'));
        setup_integration_check(setup_wizard_checkpoint($pdo,$admin['id']) === 'workers');
        $fields['step'] = 'unrecognized';
        setup_integration_check(setup_integration_request($browser,$path,$fields)['status'] === 422);
        setup_integration_check(setup_wizard_checkpoint($pdo,$admin['id']) === 'workers');
    });

    setup_integration_test('role_matrix_is_mfa_protected_and_rejects_untrusted_posts', static function () use ($pdo,$browser,$finance): void {
        $path = '/admin/roles.php';
        $other = setup_integration_browser();
        try {
            setup_integration_login($other,$finance);
            // Password-only sessions have no staff access.
            setup_integration_check(setup_integration_request($other,$path)['status'] === 303);
        } finally { curl_close($other); }
        $result = setup_integration_request($browser,$path);
        setup_integration_check($result['status'] === 200 && str_contains($result['body'],'id="permission-matrix"'));
        $policy = governance_role_policy($pdo);
        $form = array_map(static fn(array $permissions): array => array_merge([''],$permissions), $policy['roles']);
        $fields = ['action'=>'save_permissions','revision'=>$policy['revision'],'roles'=>$form];
        setup_integration_check(setup_integration_request($browser,$path,$fields)['status'] === 403);
        $fields['csrf_token'] = setup_integration_csrf($result['body']);
        $fields['roles']['finance'][] = '*';
        setup_integration_check(setup_integration_request($browser,$path,$fields)['status'] === 422);
        setup_integration_check(governance_role_policy($pdo) === $policy);
    });

    setup_integration_test('mail_setup_http_mfa_csrf_settings_and_personal_test_queue', static function () use ($pdo, $browser, $admin, $ordinary): void {
        $path = '/admin/mail-setup.php';
        $unprivileged = setup_integration_browser();
        try {
            setup_integration_check(setup_integration_request($unprivileged, $path)['status'] === 303);
            setup_integration_login($unprivileged, $ordinary);
            setup_integration_check(setup_integration_request($unprivileged, $path)['status'] === 403);
        } finally { curl_close($unprivileged); }

        $saved = $pdo->query('SELECT revision,payload_ciphertext,updated_at FROM market_mail_setup WHERE id=1')->fetch();
        try {
            $form = setup_integration_request($browser, $path);
            setup_integration_check($form['status'] === 200 && str_contains($form['headers']['cache-control'] ?? '', 'no-store'));
            setup_integration_check(!str_contains($form['body'], 'name="recipient"'));
            $password = bin2hex(random_bytes(24));
            $fields = [
                'action'=>'save', 'mode'=>'smtp', 'from'=>'mail@example.com', 'host'=>'smtp.example.com', 'port'=>'465',
                'username'=>'Synthetic SMTP account', 'password'=>$password, 'selector'=>'fixture',
                'revision'=>mail_setup_view($pdo)['revision'],
            ];
            setup_integration_check(setup_integration_request($browser, $path, $fields)['status'] === 403);
            $fields['csrf_token'] = setup_integration_csrf($form['body']);
            setup_integration_check(setup_integration_request($browser, $path, $fields)['status'] === 303);
            $result = setup_integration_request($browser, $path);
            setup_integration_check($result['status'] === 200 && str_contains($result['body'], 'smtp.example.com'));
            setup_integration_check(!str_contains($result['body'], $password) && mail_setup_view($pdo)['password_set']);
            setup_integration_check(setup_integration_request($browser, $path, $fields)['status'] === 409);
            $send = ['action'=>'test', 'csrf_token'=>setup_integration_csrf($result['body'])];
            setup_integration_check(setup_integration_request($browser, $path, $send)['status'] === 422);
            $send['test_confirmed'] = '1';
            $send['recipient'] = 'ignored-recipient@example.invalid';
            setup_integration_check(setup_integration_request($browser, $path, $send)['status'] === 303);
            $query = $pdo->prepare("SELECT m.payload_ciphertext,u.email FROM market_mail_queue m JOIN users u ON u.id=m.user_id WHERE m.user_id=? AND m.dedupe_key LIKE 'mail-setup:%'");
            $query->execute([$admin['id']]);
            $queued = $query->fetch();
            $payload = json_decode(crypto_decrypt('mail', $queued['payload_ciphertext']), true, 8, JSON_THROW_ON_ERROR);
            setup_integration_check($payload['to'] === $queued['email'] && $payload['to'] !== $send['recipient']);
        } finally {
            $pdo->prepare('UPDATE market_mail_setup SET revision=?,payload_ciphertext=?,updated_at=? WHERE id=1')
                ->execute([$saved['revision'], $saved['payload_ciphertext'], $saved['updated_at']]);
            $pdo->prepare("DELETE FROM market_mail_queue WHERE user_id=? AND dedupe_key LIKE 'mail-setup:%'")->execute([$admin['id']]);
        }
    });

    setup_integration_test('diagnostics_do_not_disclose_secrets_or_private_settings', static function () use ($pdo, $browser, $admin): void {
        $sentinels = [
            'manual_paypal_recipient'=>'synthetic-'.bin2hex(random_bytes(8)).'@example.invalid',
            'operator_tax_number'=>'synthetic-tax-'.bin2hex(random_bytes(8)),
        ];
        $original = [];
        try {
            foreach ($sentinels as $key=>$value) {
                $query = $pdo->prepare('SELECT setting_value,updated_at FROM system_settings WHERE setting_key=?');
                $query->execute([$key]);
                $original[$key] = $query->fetch();
                $pdo->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key, $value]);
            }
            $facts = json_encode(setup_collect($pdo, $admin['id']), JSON_THROW_ON_ERROR);
            $result = setup_integration_request($browser, '/admin/setup.php');
            setup_integration_check($result['status'] === 200);
            $forbidden = array_values($sentinels);
            foreach (['APP_KEY','APP_KEY_PREVIOUS','DB_PASSWORD','PAYPAL_CLIENT_SECRET'] as $variable) {
                $secret = getenv($variable);
                if (is_string($secret) && strlen($secret) >= 8) $forbidden[] = $secret;
            }
            foreach ($forbidden as $secret) {
                setup_integration_check(!str_contains($facts, $secret));
                setup_integration_check(!str_contains($result['body'], $secret));
                setup_integration_check(!str_contains($result['body'], htmlspecialchars($secret, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
            }
        } finally {
            foreach ($original as $key=>$row) {
                if ($row === false) $pdo->prepare('DELETE FROM system_settings WHERE setting_key=?')->execute([$key]);
                else $pdo->prepare('UPDATE system_settings SET setting_value=?,updated_at=? WHERE setting_key=?')->execute([$row['setting_value'], $row['updated_at'], $key]);
            }
            site_settings($pdo, true);
        }
    });

    setup_integration_test('fact_queries_use_current_schema_and_observe_real_rows', static function () use ($pdo, $admin): void {
        $before = setup_collect($pdo, $admin['id']);
        setup_integration_check($before['database'] === true && $before['query_errors'] === []);
        foreach (['countries','countries_without_geo','categories','tax_countries','countries_without_tax','legal_approved','mail_failed'] as $key) {
            setup_integration_check(is_int($before[$key]) && $before[$key] >= 0);
        }
        $countries = $pdo->query('SELECT code,is_active FROM market_countries')->fetchAll();
        $active = array_filter($countries, static fn(array $country): bool => (int)$country['is_active'] === 1);
        setup_integration_check($before['countries'] === count($active));
        setup_integration_check($before['tax_countries'] === count($pdo->query('SELECT country_code FROM market_tax_rules')->fetchAll()));
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO market_categories(name,slug,is_active) VALUES ('Synthetic setup fixture',?,0)")->execute(['test-'.bin2hex(random_bytes(8))]);
            $category = (int)$pdo->lastInsertId();
            setup_integration_check(setup_collect($pdo, $admin['id'])['categories'] === $before['categories']);
            $pdo->prepare('UPDATE market_categories SET is_active=1 WHERE id=?')->execute([$category]);
            $pdo->prepare("INSERT INTO market_mail_queue(user_id,dedupe_key,payload_ciphertext,status) VALUES (?,?,?,'failed')")->execute([$admin['id'], 'setup-test:'.bin2hex(random_bytes(8)), crypto_encrypt('mail', '{"synthetic":true}')]);
            $after = setup_collect($pdo, $admin['id']);
            setup_integration_check($after['query_errors'] === []);
            setup_integration_check($after['categories'] === $before['categories'] + 1);
            setup_integration_check($after['mail_failed'] === $before['mail_failed'] + 1);
        } finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
    });

    setup_integration_test('escrow_offer_from_chat_and_private_finance_route', static function () use ($pdo, $browser, $ordinary): void {
        setup_integration_check(escrow_simulation_enabled($pdo));
        $buyer = setup_integration_user($pdo);
        $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')
            ->execute(['Synthetic HTTP escrow items','test-escrow-http-'.bin2hex(random_bytes(8))]);
        $category = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,shipping_cents,status,zip,city,country_code,pickup,shipping,trade_checkout_enabled) VALUES (?,?,?,'Synthetic HTTP escrow workflow','fixed',2500,300,'active','10115','Teststadt','DE',0,1,1)")
            ->execute([$ordinary['id'],$category,'Synthetic HTTP escrow item '.bin2hex(random_bytes(8))]);
        $listing = (int)$pdo->lastInsertId();
        chat_send($pdo, $buyer['id'], $listing, $ordinary['id'], 'Synthetic HTTP test message.');
        $seller = setup_integration_browser();
        $buyerBrowser = setup_integration_browser();
        try {
            setup_integration_login($seller, $ordinary);
            setup_integration_login($buyerBrowser, $buyer);
            $sellerPair = '?listing_id='.$listing.'&user_id='.$buyer['id'];
            $buyerPair = '?listing_id='.$listing.'&user_id='.$ordinary['id'];
            $chat = setup_integration_request($seller, '/user/chat.php'.$sellerPair);
            setup_integration_check($chat['status'] === 200 && str_contains($chat['body'], 'data-escrow-chat-offer'));
            $secure = setup_integration_request($seller, '/user/secure-chat.php'.$sellerPair);
            setup_integration_check($secure['status'] === 200 && str_contains($secure['body'], 'data-escrow-chat-offer'));
            $submit = ['action'=>'create','amount'=>'25.80','confirm'=>'1','request_key'=>bin2hex(random_bytes(16))];
            setup_integration_check(setup_integration_request($seller, '/user/escrow.php'.$sellerPair, $submit)['status'] === 403);
            $submit['csrf_token'] = setup_integration_csrf($chat['body']);
            $created = setup_integration_request($seller, '/user/escrow.php'.$sellerPair, $submit);
            setup_integration_check($created['status'] === 303 && preg_match('/[&?]case_id=([1-9][0-9]*)\z/', $created['headers']['location'] ?? '', $match) === 1);
            $case = (int)$match[1];
            $buyerRoute = '/user/escrow.php'.$buyerPair.'&case_id='.$case;
            $review = setup_integration_request($buyerBrowser, $buyerRoute);
            setup_integration_check($review['status'] === 200 && str_contains($review['body'], 'data-escrow-status="offered"'));
            $accept = ['action'=>'accept','case_id'=>(string)$case,'version'=>'1','request_key'=>bin2hex(random_bytes(16)),
                'confirm'=>'1','csrf_token'=>setup_integration_csrf($review['body'])];
            setup_integration_check(setup_integration_request($buyerBrowser, $buyerRoute, $accept)['status'] === 303);
            $queue = '/admin/escrow.php?case_id='.$case;
            setup_integration_check(setup_integration_request($buyerBrowser, $queue)['status'] === 403);
            $financePage = setup_integration_request($browser, $queue);
            setup_integration_check($financePage['status'] === 200 && str_contains($financePage['body'], 'awaiting_funding')
                && str_contains($financePage['headers']['cache-control'] ?? '', 'no-store'));
        } finally {
            curl_close($seller);
            curl_close($buyerBrowser);
        }
    });

    setup_integration_test('revoked_administrator_session_loses_setup_access', static function () use ($pdo, $browser, $admin): void {
        $pdo->prepare('UPDATE users SET auth_version=auth_version+1 WHERE id=?')->execute([$admin['id']]);
        $result = setup_integration_request($browser, '/admin/setup.php');
        setup_integration_check($result['status'] === 303 && !str_contains($result['body'], 'id="setup-'));
    });
} finally { curl_close($browser); }
fwrite(STDOUT, 'Setup integration checks passed: '.$passed."\n");
