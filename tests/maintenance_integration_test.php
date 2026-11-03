<?php
declare(strict_types=1);
/** Sequential HTTP/DB checks against the isolated Linux runtime; synthetic data only. */
if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY === 'Windows' || getenv('DB_DATABASE') !== 'gunindex_rebuild_test') {
    if (PHP_SAPI !== 'cli') http_response_code(404);
    exit(2);
}
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/maintenance.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'gunindex_rebuild_test') exit(2);

function mi_check(bool $condition): void {
    if (!$condition) throw new RuntimeException('Maintenance integration assertion failed.');
}
function mi_test(string $name, callable $run): void {
    global $passed, $currentTest;
    $currentTest = $name; $run(); ++$passed; fwrite(STDOUT, 'PASS '.$name.PHP_EOL);
}
function mi_setting(PDO $pdo, string $value): void {
    global $miActor;
    $pdo->beginTransaction();try{maintenance_transition($pdo,$miActor['id'],site_settings($pdo,true)['maintenance_enabled'],$value);$pdo->commit();}
    catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
    site_settings($pdo, true);
}
function mi_user(PDO $pdo, bool $admin = false, bool $mfa = false): array {
    $name = 'maint_'.bin2hex(random_bytes(8)); $password = 'Synthetic maintenance '.bin2hex(random_bytes(16));
    $secret = $mfa ? random_bytes(20) : null;
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,is_admin,mfa_secret) VALUES (?,?,?,'Synthetic maintenance fixture','10115','Synthetic city',?,?)")
        ->execute([$name,$name.'@example.invalid',auth_hash($password),(int)$admin,$secret === null ? null : auth_secret_encrypt($secret)]);
    return ['id'=>(int)$pdo->lastInsertId(),'name'=>$name,'password'=>$password,'secret'=>$secret];
}
function mi_browser(): CurlHandle {
    global $browsers;
    $browser = curl_init();
    curl_setopt_array($browser, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>5]);
    $browsers[] = $browser;
    return $browser;
}
function mi_request(CurlHandle $browser, string $path, string $method = 'GET', array|string|null $fields = null): array {
    mi_check(str_starts_with($path,'/') && !str_starts_with($path,'//') && !preg_match('/[\r\n]/',$path));
    $headers = [];
    curl_setopt_array($browser, [CURLOPT_URL=>'http://127.0.0.1:8081'.$path,CURLOPT_NOBODY=>false,CURLOPT_HTTPGET=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>[]]);
    curl_setopt($browser, CURLOPT_HEADERFUNCTION, static function (CurlHandle $curl, string $line) use (&$headers): int {
        if (str_contains($line, ':')) { [$name,$value] = explode(':',$line,2); $headers[strtolower(trim($name))] = trim($value); }
        return strlen($line);
    });
    if ($fields !== null) {
        curl_setopt($browser,CURLOPT_POSTFIELDS,is_array($fields) ? http_build_query($fields) : $fields);
        curl_setopt($browser,CURLOPT_HTTPHEADER,[is_array($fields) ? 'Content-Type: application/x-www-form-urlencoded' : 'Content-Type: application/json']);
    }
    if ($method === 'HEAD') curl_setopt($browser,CURLOPT_NOBODY,true);
    $body = curl_exec($browser); mi_check(is_string($body));
    return ['status'=>(int)curl_getinfo($browser,CURLINFO_RESPONSE_CODE),'body'=>$body,'headers'=>$headers];
}
function mi_csrf(array $response): string {
    mi_check(preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/',$response['body'],$matches) === 1);
    return $matches[1];
}
function mi_login(CurlHandle $browser, array $user, bool $completeMfa = false): void {
    $form = mi_request($browser,'/auth/login.php'); mi_check($form['status'] === 200);
    $response = mi_request($browser,'/auth/login.php','POST',['csrf_token'=>mi_csrf($form),'login'=>$user['name'],'password'=>$user['password']]);
    mi_check($response['status'] === 303);
    if ($completeMfa) {
        mi_check(($response['headers']['location'] ?? '') === '/auth/mfa.php' && is_string($user['secret']));
        $form = mi_request($browser,'/auth/mfa.php'); mi_check($form['status'] === 200);
        $response = mi_request($browser,'/auth/mfa.php','POST',['csrf_token'=>mi_csrf($form),'code'=>auth_totp($user['secret'],intdiv(time(),30))]);
        mi_check($response['status'] === 303);
    }
}
function mi_blocked(array $response, bool $head = false): void {
    mi_check($response['status'] === 503);
    mi_check(($response['headers']['retry-after'] ?? '') === '300');
    mi_check(str_contains($response['headers']['cache-control'] ?? '','no-store'));
    mi_check(str_contains($response['headers']['x-robots-tag'] ?? '','noindex'));
    mi_check(!isset($response['headers']['location']));
    mi_check($head ? $response['body'] === '' : str_contains($response['body'],'<main><h1>'));
}
function mi_payment_snapshot(PDO $pdo): array {
    return [
        $pdo->query('SELECT id,status,gross_cents,updated_at FROM market_invoices ORDER BY id')->fetchAll(),
        $pdo->query('SELECT id,status,capture_id,updated_at FROM market_payment_attempts ORDER BY id')->fetchAll(),
        $pdo->query('SELECT id,attempt_id,provider_event_id,payload_hash FROM market_payment_events ORDER BY id')->fetchAll(),
    ];
}

$passed = 0; $currentTest = 'fixture_setup'; $browsers = []; $error = null;
$original = $pdo->query("SELECT setting_value,updated_at FROM system_settings WHERE setting_key='maintenance_enabled'")->fetch();
$miActor=mi_user($pdo,true,true);
if($original!==false&&$original['setting_value']!=='0')throw new RuntimeException('Maintenance tests require an initially available synthetic runtime.');
try {
    mi_setting($pdo,'0');
    $anonymous = mi_browser();
    mi_test('disabled_gate_preserves_public_http_access', static function () use ($anonymous): void {
        $result = mi_request($anonymous,'/');
        mi_check($result['status'] === 200 && !isset($result['headers']['retry-after']));
    });
    $admin = mi_user($pdo,true,true); $ordinary = mi_user($pdo); $seller = mi_user($pdo);
    $finance = mi_user($pdo,false,true); $delegated = mi_user($pdo,false,true); $promoted = mi_user($pdo,false,true);
    governance_role_change($pdo,$admin['id'],$finance['id'],'finance',true);
    governance_role_change($pdo,$admin['id'],$delegated['id'],'administrator',true);
    $adminBrowser = mi_browser(); $pendingBrowser = mi_browser(); $ordinaryBrowser = mi_browser();
    $financeBrowser = mi_browser(); $delegatedBrowser = mi_browser(); $promotedBrowser = mi_browser();
    mi_login($adminBrowser,$admin,true); mi_login($pendingBrowser,$admin);
    mi_login($ordinaryBrowser,$ordinary); mi_login($financeBrowser,$finance,true);
    mi_login($delegatedBrowser,$delegated,true); mi_login($promotedBrowser,$promoted);
    // The existing ordinary session remains without MFA after a server-side role grant.
    governance_role_change($pdo,$admin['id'],$promoted['id'],'administrator',true);
    $adminCsrf = mi_csrf(mi_request($adminBrowser,'/admin/settings.php'));
    $ordinaryCsrf = mi_csrf(mi_request($ordinaryBrowser,'/user/dashboard.php'));
    $pdo->prepare("INSERT INTO market_categories(name,slug) VALUES ('Synthetic maintenance category',?)")->execute(['maint-'.bin2hex(random_bytes(8))]);
    $category = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,buyout_cents,status,zip,city,country_code,ends_at) VALUES (?,?,'Synthetic ordinary item','Synthetic general-goods maintenance fixture.','auction',1000,2500,'active','10115','Synthetic city','DE',UTC_TIMESTAMP()+INTERVAL 7 DAY)")->execute([$seller['id'],$category]);
    $listing = (int)$pdo->lastInsertId();
    // Unsigned events fail before any network call even if sandbox credentials exist.
    $event = json_encode(['id'=>'SYNTHETIC-MAINT-'.strtoupper(bin2hex(random_bytes(8))),'event_type'=>'PAYMENT.CAPTURE.COMPLETED','resource'=>['id'=>'SYNTHETIC-CAPTURE']],JSON_THROW_ON_ERROR);
    $beforeWebhook = mi_request($anonymous,'/actions/payment-webhook.php','POST',$event);
    mi_check(in_array($beforeWebhook['status'],[400,503],true) && !isset($beforeWebhook['headers']['retry-after']));
    mi_setting($pdo,'1');

    mi_test('anonymous_get_post_and_head_return_temporary_private_responses', static function () use ($anonymous,$listing): void {
        foreach (['/','/index.php','/marketplace/search.php','/marketplace/view-auction.php?id='.$listing,'/sitemap.xml','/robots.txt','/user/dashboard.php','/admin/settings.php'] as $path) {
            mi_blocked(mi_request($anonymous,$path));
        }
        mi_blocked(mi_request($anonymous,'/actions/place-bid.php','POST',['listing_id'=>(string)$listing,'max_bid'=>'20.00']));
        mi_blocked(mi_request($anonymous,'/marketplace/search.php','HEAD'),true);
    });
    mi_test('ordinary_pending_mfa_and_unprivileged_staff_cannot_enter_administration', static function () use ($pendingBrowser,$ordinaryBrowser,$financeBrowser,$promotedBrowser): void {
        foreach ([$pendingBrowser,$ordinaryBrowser,$financeBrowser,$promotedBrowser] as $browser) {
            mi_blocked(mi_request($browser,'/admin/settings.php'));
            mi_blocked(mi_request($browser,'/admin/setup.php','HEAD'),true);
            mi_blocked(mi_request($browser,'/admin/settings.php','POST',['maintenance_enabled'=>'0']));
        }
    });
    mi_test('mfa_site_administrators_reach_admin_pages_but_csrf_still_applies', static function () use ($pdo,$adminBrowser,$delegatedBrowser): void {
        foreach ([$adminBrowser,$delegatedBrowser] as $browser) {
            $page = mi_request($browser,'/admin/setup.php'); mi_check($page['status'] === 200 && str_contains($page['body'],'id="setup-'));
            $head = mi_request($browser,'/admin/settings.php','HEAD'); mi_check($head['status'] === 200 && $head['body'] === '');
            $post = mi_request($browser,'/admin/settings.php','POST',['maintenance_enabled'=>'0']);
            mi_check($post['status'] === 403);
        }
        mi_check(site_settings($pdo,true)['maintenance_enabled'] === '1');
    });
    mi_test('valid_market_posts_cannot_change_bids_listings_or_payments_even_for_admins', static function () use ($pdo,$adminBrowser,$ordinaryBrowser,$adminCsrf,$ordinaryCsrf,$listing): void {
        $read = static function () use ($pdo,$listing): array {
            $q = $pdo->prepare('SELECT * FROM market_listings WHERE id=?'); $q->execute([$listing]);
            $rows = [$q->fetch()];
            foreach (['market_bids','market_proxy_bids'] as $table) { $q = $pdo->prepare('SELECT * FROM '.$table.' WHERE listing_id=? ORDER BY user_id'); $q->execute([$listing]); $rows[] = $q->fetchAll(); }
            return [$rows,mi_payment_snapshot($pdo),(int)$pdo->query('SELECT COUNT(*) FROM market_notifications')->fetchColumn()];
        };
        $before = $read();
        foreach ([[$adminBrowser,$adminCsrf],[$ordinaryBrowser,$ordinaryCsrf]] as [$browser,$csrf]) {
            mi_blocked(mi_request($browser,'/actions/place-bid.php','POST',['csrf_token'=>$csrf,'listing_id'=>(string)$listing,'max_bid'=>'20.00']));
            mi_blocked(mi_request($browser,'/marketplace/buyout-request.php','POST',['csrf_token'=>$csrf,'listing_id'=>(string)$listing]));
            mi_blocked(mi_request($browser,'/actions/payment-checkout.php','POST',['csrf_token'=>$csrf,'listing_id'=>(string)$listing,'service_code'=>'bump_single']));
            mi_blocked(mi_request($browser,'/marketplace/view-auction.php?id='.$listing));
            mi_blocked(mi_request($browser,'/user/dashboard.php'));
        }
        mi_check($before === $read());
    });
    mi_test('authentication_exceptions_keep_method_csrf_and_route_guards', static function () use ($anonymous,$pendingBrowser): void {
        mi_check(mi_request($anonymous,'/auth/login.php')['status'] === 200);
        mi_check(mi_request($anonymous,'/auth/mfa.php')['status'] === 303);
        mi_check(mi_request($anonymous,'/auth/logout.php')['status'] === 200);
        mi_check(mi_request($pendingBrowser,'/auth/mfa.php')['status'] === 200);
        foreach (['/auth/login.php','/auth/mfa.php','/auth/logout.php'] as $path) {
            $browser = $path === '/auth/mfa.php' ? $pendingBrowser : $anonymous;
            mi_check(mi_request($browser,$path,'POST',[])['status'] === 403);
            mi_blocked(mi_request($browser,$path,'DELETE'));
        }
        foreach (['/auth/register.php','/auth/forgot-password.php','/auth/reset-password.php','/auth/verify-email.php'] as $path) mi_blocked(mi_request($anonymous,$path));
        foreach (['/auth/login.php/extra','/admin/settings.php/extra','/unknown.php'] as $path) mi_check(mi_request($anonymous,$path)['status'] === 404);
    });
    mi_test('webhook_exception_preserves_unsigned_event_rejection_and_database_state', static function () use ($pdo,$anonymous,$event,$beforeWebhook): void {
        $snapshot = mi_payment_snapshot($pdo);
        $after = mi_request($anonymous,'/actions/payment-webhook.php','POST',$event);
        mi_check($after['status'] === $beforeWebhook['status'] && $after['body'] === $beforeWebhook['body']);
        mi_check(!isset($after['headers']['retry-after']));
        mi_blocked(mi_request($anonymous,'/actions/payment-webhook.php'));
        mi_blocked(mi_request($anonymous,'/actions/payment-webhook.php','HEAD'),true);
        mi_check($snapshot === mi_payment_snapshot($pdo));
    });
    mi_test('all_eight_languages_are_served_over_http_without_a_query_language_bypass', static function () use ($pdo): void {
        foreach (i18n_locales() as $locale=>$label) {
            $browser = mi_browser(); mi_setting($pdo,'0');
            try {
                $login = mi_request($browser,'/auth/login.php'); mi_check($login['status'] === 200);
                $choice = mi_request($browser,'/user/language.php','POST',['csrf_token'=>mi_csrf($login),'locale'=>$locale,'market_country'=>'DE','return_path'=>'/']);
                mi_check($choice['status'] === 303);
            } finally { mi_setting($pdo,'1'); }
            $response = mi_request($browser,'/?locale%5B%5D=xx&bypass=1'); mi_blocked($response);
            mi_check(($response['headers']['content-language'] ?? '') === $locale && str_contains($response['body'],'<html lang="'.$locale.'">'));
            mi_check(str_contains($response['body'],htmlspecialchars(maintenance_dictionary($locale)['maintenance.heading'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')));
        }
    });
    mi_test('maintenance_response_exposes_no_configuration_or_request_secrets', static function () use ($anonymous): void {
        $sentinel = 'synthetic-private-'.bin2hex(random_bytes(16));
        $response = mi_request($anonymous,'/?token='.$sentinel.'&maintenance_enabled=0'); mi_blocked($response);
        $forbidden = [$sentinel];
        foreach (['APP_KEY','DB_PASSWORD','PAYPAL_CLIENT_SECRET'] as $name) { $value = getenv($name); if (is_string($value) && strlen($value) >= 8) $forbidden[] = $value; }
        foreach ($forbidden as $value) {
            mi_check(!str_contains($response['body'],$value));
            mi_check(!str_contains($response['body'],htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')));
        }
    });
    mi_test('authenticated_admin_can_disable_and_enable_through_the_actual_settings_form', static function () use ($pdo,$adminBrowser,$anonymous): void {
        foreach (['0','1'] as $value) {
            $form = mi_request($adminBrowser,'/admin/settings.php'); mi_check($form['status'] === 200);
            mi_check(preg_match('/name="_revision"\s+value="([a-f0-9]{64})"/',$form['body'],$match) === 1);
            $fields = site_settings($pdo,true); $fields['maintenance_enabled'] = $value;
            $fields['csrf_token'] = mi_csrf($form); $fields['_revision'] = $match[1];
            $response = mi_request($adminBrowser,'/admin/settings.php','POST',$fields);
            mi_check($response['status'] === 200 && site_settings($pdo,true)['maintenance_enabled'] === $value);
            if ($value === '0') mi_check(mi_request($anonymous,'/')['status'] === 200);
            else mi_blocked(mi_request($anonymous,'/'));
        }
    });
    mi_test('revoked_site_role_is_checked_on_the_next_request', static function () use ($pdo,$admin,$delegated,$delegatedBrowser): void {
        governance_role_change($pdo,$admin['id'],$delegated['id'],'administrator',false);
        mi_blocked(mi_request($delegatedBrowser,'/admin/settings.php'));
    });
    mi_test('removing_mfa_disables_even_an_existing_administrator_session', static function () use ($pdo,$admin,$adminBrowser): void {
        $q = $pdo->prepare('SELECT mfa_secret FROM users WHERE id=?'); $q->execute([$admin['id']]); $ciphertext = $q->fetchColumn();
        $pdo->prepare('UPDATE users SET mfa_secret=NULL WHERE id=?')->execute([$admin['id']]);
        try { mi_blocked(mi_request($adminBrowser,'/admin/settings.php')); }
        finally { $pdo->prepare('UPDATE users SET mfa_secret=? WHERE id=?')->execute([$ciphertext,$admin['id']]); }
        mi_check(mi_request($adminBrowser,'/admin/settings.php')['status'] === 200);
    });
    mi_test('logout_remains_a_real_csrf_protected_session_revocation', static function () use ($ordinaryBrowser): void {
        $form = mi_request($ordinaryBrowser,'/auth/logout.php'); mi_check($form['status'] === 200);
        $response = mi_request($ordinaryBrowser,'/auth/logout.php','POST',['csrf_token'=>mi_csrf($form)]);
        mi_check($response['status'] === 303);
        mi_check(mi_request($ordinaryBrowser,'/auth/login.php')['status'] === 200);
    });
    mi_test('inactive_accounts_and_revoked_sessions_lose_the_admin_exception', static function () use ($pdo,$admin,$adminBrowser): void {
        $pdo->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$admin['id']]);
        try { mi_blocked(mi_request($adminBrowser,'/admin/settings.php')); }
        finally { $pdo->prepare('UPDATE users SET is_active=1 WHERE id=?')->execute([$admin['id']]); }
        // Use a new synthetic admin to exercise auth_version independently of the cleared inactive session.
        $other = mi_user($pdo,true,true); $browser = mi_browser(); mi_login($browser,$other,true);
        mi_check(mi_request($browser,'/admin/settings.php')['status'] === 200);
        $pdo->prepare('UPDATE users SET auth_version=auth_version+1 WHERE id=?')->execute([$other['id']]);
        mi_blocked(mi_request($browser,'/admin/settings.php'));
    });
    fwrite(STDOUT,'Maintenance integration checks passed: '.$passed.PHP_EOL);
} catch (Throwable $caught) { $error = $caught; }
finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mi_setting($pdo,'0');
    if ($original === false) $pdo->exec("DELETE FROM system_settings WHERE setting_key='maintenance_enabled'");
    else $pdo->prepare("UPDATE system_settings SET setting_value=?,updated_at=? WHERE setting_key='maintenance_enabled'")->execute([$original['setting_value'],$original['updated_at']]);
    site_settings($pdo,true);
    foreach ($browsers as $browser) curl_close($browser);
}
if ($error !== null) {
    // Deliberately omit exception messages, HTTP bodies, SQL and fixture credentials.
    fwrite(STDERR,'FAIL '.$currentTest.' ('.get_class($error).' at '.basename($error->getFile()).':'.$error->getLine().')'.PHP_EOL);
    exit(1);
}
