<?php
declare(strict_types=1);
require_once __DIR__.'/i18n.php';

final class MaintenancePaused extends DomainException {}
final class MaintenanceStateConflict extends RuntimeException {}

/** Always a current read; a raw setting change cannot silently bypass a pause. */
function maintenance_state(PDO $pdo,bool $exclusive=false): array {
    if(!$pdo->inTransaction())throw new LogicException('Maintenance state requires a transaction.');
    $row=$pdo->query('SELECT * FROM market_maintenance_state WHERE id=1'.($exclusive?' FOR UPDATE':' FOR SHARE'))->fetch();
    $value=$pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='maintenance_enabled' FOR SHARE")->fetchColumn();
    $value=$value===false?'0':$value;
    if(!$row||!in_array($value,['0','1'],true)||(int)$row['active']!==(int)$value)
        throw new MaintenanceStateConflict('Maintenance state and configuration disagree.');
    return $row;
}

/** Call immediately after beginning a market transaction, before other row locks. */
function maintenance_assert_market_available(PDO $pdo): void {
    if(!$pdo->inTransaction())throw new LogicException('Market maintenance guard requires a transaction.');
    // An HTTP/worker lease already holds the barrier on a separate connection.
    // Re-locking here could block behind a waiting administrator on another connection.
    $lease=$GLOBALS['maintenance_runtime_lease']??null;
    if($lease instanceof PDO&&$lease->inTransaction())return;
    if((int)maintenance_state($pdo)['active']===1)throw new MaintenancePaused(maintenance_dictionary(i18n_locale())['maintenance.message']);
}

/** Independent read transaction spans the entire request/worker despite nested application transactions. */
function maintenance_acquire_lease(): ?PDO {
    $database=getenv('DB_DATABASE');$username=getenv('DB_USERNAME');$password=getenv('DB_PASSWORD');$socket=getenv('DB_SOCKET');
    if(!is_string($database)||!preg_match('/\A[a-zA-Z0-9_]+\z/',$database)||!is_string($username)||$username===''||!is_string($password)||$password==='')throw new MaintenanceStateConflict('Maintenance database configuration unavailable.');
    $dsn=is_string($socket)&&$socket!==''?'mysql:unix_socket='.$socket:'mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';port='.(getenv('DB_PORT')?:'3306');
    $lease=new PDO($dsn.';dbname='.$database.';charset=utf8mb4',$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $lease->exec("SET time_zone='+00:00'");$lease->beginTransaction();
    try{
        if((int)maintenance_state($lease)['active']===1){$lease->rollBack();return null;}
        $GLOBALS['maintenance_runtime_lease']=$lease;
        register_shutdown_function(static function()use($lease):void{if($lease->inTransaction())$lease->rollBack();});
        return $lease;
    }catch(Throwable $error){if($lease->inTransaction())$lease->rollBack();throw $error;}
}

/** Worker process holds its lease until exit; activation waits for a running batch. */
function maintenance_worker_gate(): void {
    if(PHP_SAPI!=='cli')throw new LogicException('Worker gate requires CLI.');
    if(maintenance_acquire_lease()===null){fwrite(STDOUT,"Worker deferred: maintenance is active.\n");exit(0);}
}

/** Settings transaction owns the barrier exclusively before updating any settings. */
function maintenance_transition(PDO $pdo,int $actor,string $old,string $new): void {
    if(!in_array($old,['0','1'],true)||!in_array($new,['0','1'],true))throw new InvalidArgumentException('Invalid maintenance state.');
    if(!$pdo->inTransaction())throw new LogicException('Maintenance transition requires a transaction.');
    $state=maintenance_state($pdo,true);
    if((string)$state['active']!==$old)throw new MaintenanceStateConflict('Maintenance state changed.');
    if($old===$new)return;
    governance_assert_actor($pdo,$actor,'site.manage');
    if($new==='1'){
        $pdo->prepare('INSERT INTO market_maintenance_windows(activation_key,started_at,activated_by) VALUES (?,UTC_TIMESTAMP(6),?)')->execute([bin2hex(random_bytes(24)),$actor]);
        $window=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO market_maintenance_auctions(window_id,listing_id,original_ends_at) SELECT ?,l.id,l.ends_at FROM market_listings l JOIN market_maintenance_windows w ON w.id=? WHERE l.type='auction' AND l.status IN ('active','paused') AND l.ends_at>w.started_at")->execute([$window,$window]);
        $pdo->prepare('UPDATE market_maintenance_state SET active=1,window_id=? WHERE id=1')->execute([$window]);
        audit_log($pdo,$actor,'maintenance.started',$window,[]);
    }else{
        $window=(int)$state['window_id'];
        $q=$pdo->prepare('SELECT *,GREATEST(0,CEIL(TIMESTAMPDIFF(MICROSECOND,started_at,UTC_TIMESTAMP(6))/1000000)) AS elapsed FROM market_maintenance_windows WHERE id=? FOR UPDATE');$q->execute([$window]);$period=$q->fetch();
        if(!$period||$period['ended_at']!==null)throw new MaintenanceStateConflict('Open maintenance window missing.');
        $seconds=(int)$period['elapsed'];
        $q=$pdo->prepare("UPDATE market_listings l JOIN market_maintenance_auctions a ON a.listing_id=l.id SET l.ends_at=TIMESTAMPADD(SECOND,?,a.original_ends_at),l.updated_at=UTC_TIMESTAMP() WHERE a.window_id=? AND l.type='auction' AND l.status IN ('active','paused') AND l.ends_at=a.original_ends_at");$q->execute([$seconds,$window]);$extended=$q->rowCount();
        $pdo->prepare('UPDATE market_maintenance_windows SET ended_at=UTC_TIMESTAMP(6),duration_seconds=?,deactivated_by=? WHERE id=?')->execute([$seconds,$actor,$window]);
        $pdo->exec('UPDATE market_maintenance_state SET active=0 WHERE id=1');
        $q=$pdo->prepare('SELECT COUNT(*) FROM market_maintenance_auctions WHERE window_id=?');$q->execute([$window]);$captured=(int)$q->fetchColumn();
        audit_log($pdo,$actor,'maintenance.ended',$window,['duration_seconds'=>$seconds,'auctions_extended'=>$extended,'auctions_unchanged_or_removed'=>$captured-$extended]);
    }
    $pdo->prepare("INSERT INTO system_settings(setting_key,setting_value) VALUES ('maintenance_enabled',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=UTC_TIMESTAMP()")->execute([$new]);
}

/** The persisted setting is validated as a string by site_settings(). */
function maintenance_enabled(array $settings): bool
{
    return ($settings['maintenance_enabled'] ?? '0') === '1';
}

/**
 * Pure defence-in-depth check after current_user() and a fresh RBAC lookup.
 * Neither an is_admin flag nor a remembered MFA marker alone grants access.
 */
function maintenance_admin_session(?array $user, array $session, bool $canManageSite, int $now): bool
{
    if ($user === null || !$canManageSite) return false;
    $id = $user['id'] ?? null;
    $version = $user['auth_version'] ?? null;
    if ((!is_int($id) && !(is_string($id) && preg_match('/\A[1-9][0-9]{0,17}\z/', $id)))
        || (!is_int($version) && !(is_string($version) && preg_match('/\A[0-9]{1,18}\z/', $version)))) return false;
    $id = (int)$id;
    $started = $session['auth_started_at'] ?? null;
    $seen = $session['auth_last_seen'] ?? null;
    return $id > 0
        && in_array($user['is_active'] ?? null, [1, '1'], true)
        && is_string($user['mfa_secret'] ?? null) && $user['mfa_secret'] !== ''
        && ($session['user_id'] ?? null) === $id
        && ($session['auth_version'] ?? null) === (int)$version
        && ($session['admin_mfa_user_id'] ?? null) === $id
        && is_int($started) && $started > 0 && $started <= $now && $now - $started <= 43200
        && is_int($seen) && $seen >= $started && $seen <= $now && $now - $seen <= 1800;
}

/** Accept only the router's canonical relative route, never a raw request URI. */
function maintenance_allows_request(array $settings, string $route, string $method, bool $administrator = false): bool
{
    if (!maintenance_enabled($settings)) return true;
    // Existing login/MFA/CSRF controls still run. This does not grant marketplace access.
    if (in_array($route, ['auth/login.php', 'auth/mfa.php', 'auth/logout.php'], true)) {
        return in_array($method, ['GET', 'HEAD', 'POST'], true);
    }
    // Continue verified reconciliation of existing payments; the endpoint still
    // verifies provider activation, request size and the PayPal event signature.
    if ($route === 'actions/payment-webhook.php') return $method === 'POST';
    return $administrator && in_array($method, ['GET', 'HEAD', 'POST'], true)
        && preg_match('/\Aadmin\/[a-z][a-z0-9-]*\.php\z/', $route) === 1;
}

/** Explicit allowlisted locale files; standalone rendering also works before i18n integration. */
function maintenance_dictionary(mixed $locale): array
{
    $locale = is_string($locale) && isset(i18n_locales()[$locale]) ? $locale : 'de';
    static $cache = [];
    return $cache[$locale] ??= require dirname(__DIR__).'/locales/maintenance/'.$locale.'.php';
}

/** No database, user data, request target or operator secrets enter this page. */
function maintenance_response(mixed $locale, string $method = 'GET'): array
{
    $locale = is_string($locale) && isset(i18n_locales()[$locale]) ? $locale : 'de';
    $dictionary = maintenance_dictionary($locale);
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $text = static fn(string $key): string => $escape($dictionary['maintenance.'.$key]);
    $body = '<!doctype html><html lang="'.$locale.'"><head><meta charset="utf-8">'
        .'<meta name="viewport" content="width=device-width, initial-scale=1"><title>'.$text('title').'</title>'
        .'<style>body{font-family:system-ui,sans-serif;margin:0;background:#f5f6f8;color:#17202a}'
        .'main{box-sizing:border-box;max-width:42rem;margin:10vh auto;padding:2rem;overflow-wrap:anywhere}'
        .'h1{font-size:2rem;line-height:1.2}p{line-height:1.6}a{color:#124d8c}a:focus-visible{outline:3px solid #124d8c;outline-offset:4px}'
        .'</style></head><body><main><h1>'.$text('heading').'</h1><p>'.$text('message').'</p><p>'.$text('retry').'</p>'
        .'<p><a href="/auth/login.php">'.$text('login').'</a></p>'
        .'<p><a href="/admin/settings.php">'.$text('administration').'</a></p></main></body></html>';
    return ['status'=>503, 'headers'=>[
        'Content-Type'=>'text/html; charset=utf-8',
        'Content-Language'=>$locale,
        'Retry-After'=>'300',
        'Cache-Control'=>'no-store, max-age=0',
        'X-Robots-Tag'=>'noindex, nofollow',
        'X-Content-Type-Options'=>'nosniff',
        'X-Frame-Options'=>'DENY',
        'Content-Security-Policy'=>"default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
    ], 'body'=>$method === 'HEAD' ? '' : $body];
}

/**
 * Router hook: call after bootstrap and canonical-route validation, before the
 * destination script executes. The lease also drains in-flight public requests.
 */
function maintenance_enforce_http(PDO $pdo, string $route): void
{
    if (PHP_SAPI === 'cli') return;
    $method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : '';
    // Exceptions do not retain a public lease: settings must be able to take the
    // exclusive barrier, and authenticated payment reconciliation stays available.
    $exception=in_array($route,['auth/login.php','auth/mfa.php','auth/logout.php','actions/payment-webhook.php'],true)||str_starts_with($route,'admin/');
    try{
        $lease=maintenance_acquire_lease();
        if($lease!==null){
            if($exception){$lease->rollBack();unset($GLOBALS['maintenance_runtime_lease']);}
            return;
        }
    }catch(Throwable $error){
        error_log('Maintenance barrier unavailable: '.get_class($error));
        $response=maintenance_response(i18n_locale(),$method);
        http_response_code($response['status']);foreach($response['headers'] as $name=>$value)header($name.': '.$value);echo $response['body'];exit;
    }
    $settings=['maintenance_enabled'=>'1'];
    if (maintenance_allows_request($settings, $route, $method)) return;
    $administrator = false;
    if (preg_match('/\Aadmin\/[a-z][a-z0-9-]*\.php\z/', $route) === 1) {
        $user = current_user($pdo);
        $canManage = $user !== null && rbac_has_permission($pdo, (int)$user['id'], 'site.manage');
        $administrator = maintenance_admin_session($user, $_SESSION ?? [], $canManage, time());
    }
    if (maintenance_allows_request($settings, $route, $method, $administrator)) return;
    $response = maintenance_response(i18n_locale(), $method);
    http_response_code($response['status']);
    foreach ($response['headers'] as $name=>$value) header($name.': '.$value);
    echo $response['body'];
    exit;
}
