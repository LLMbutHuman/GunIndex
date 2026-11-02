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
    if (!$condition) throw new RuntimeException('Setup integration assertion failed.');
}
function setup_integration_test(string $name, callable $run): void {
    global $passed;
    try { $run(); $passed++; fwrite(STDOUT, 'PASS '.$name."\n"); }
    catch (Throwable $error) {
        // Never log fixture credentials, SQL statements, response bodies or configuration.
        fwrite(STDERR, 'FAIL '.$name.' ('.get_class($error).' at '.basename($error->getFile()).':'.$error->getLine().")\n");
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
        setup_integration_check(preg_match_all('/id="setup-[a-z]+"/', $result['body']) === 18);
    });

    setup_integration_test('mfa_finance_role_still_lacks_setup_permission', static function () use ($finance): void {
        $client = setup_integration_browser();
        try {
            setup_integration_login($client, $finance);
            setup_integration_mfa($client, $finance);
            $result = setup_integration_request($client, '/admin/setup.php');
            setup_integration_check($result['status'] === 403 && !str_contains($result['body'], 'id="setup-'));
        } finally { curl_close($client); }
    });

    setup_integration_test('setup_post_rejected_and_settings_preserved', static function () use ($pdo, $browser): void {
        $before = $pdo->query('SELECT setting_key,setting_value,updated_at FROM system_settings ORDER BY setting_key')->fetchAll();
        $result = setup_integration_request($browser, '/admin/setup.php', ['manual_paypal_enabled'=>'1', 'finance_enabled'=>'1', 'action'=>'activate']);
        setup_integration_check($result['status'] === 405 && ($result['headers']['allow'] ?? '') === 'GET, HEAD');
        setup_integration_check($before === $pdo->query('SELECT setting_key,setting_value,updated_at FROM system_settings ORDER BY setting_key')->fetchAll());
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

    setup_integration_test('revoked_administrator_session_loses_setup_access', static function () use ($pdo, $browser, $admin): void {
        $pdo->prepare('UPDATE users SET auth_version=auth_version+1 WHERE id=?')->execute([$admin['id']]);
        $result = setup_integration_request($browser, '/admin/setup.php');
        setup_integration_check($result['status'] === 303 && !str_contains($result['body'], 'id="setup-'));
    });
} finally { curl_close($browser); }
fwrite(STDOUT, 'Setup integration checks passed: '.$passed."\n");
