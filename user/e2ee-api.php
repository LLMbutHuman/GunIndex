<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Cross-Origin-Resource-Policy: same-origin');

$respond = static function(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
};
$readInteger = static function(mixed $value, int $minimum = 1): int {
    if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D',$value)) throw new InvalidArgumentException('Invalid query integer.');
    return e2ee_integer((int)$value,$minimum);
};

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method,['GET','POST'],true)) { header('Allow: GET, POST'); $respond(['error'=>'method_not_allowed'],405); }
    $user = current_user($pdo);
    if ($user === null) $respond(['error'=>'authentication_required'],401);
    e2ee_require_enabled();
    $userId = (int)$user['id'];

    // No CORS and no Origin:null exception. APP_URL handles a TLS reverse proxy.
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = $_SERVER['HTTP_ORIGIN'];
        $configured = (string)(getenv('APP_URL') ?: '');
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
        $base = $configured !== '' ? $configured : $scheme.($_SERVER['HTTP_HOST'] ?? '');
        $normalizeOrigin = static function(string $value): ?string {
            $parts = parse_url($value);
            if (!is_array($parts) || !isset($parts['scheme'],$parts['host']) || !in_array(strtolower($parts['scheme']),['http','https'],true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !in_array($parts['path'] ?? '',['','/'],true)) return null;
            $scheme = strtolower($parts['scheme']); $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
            return $scheme.'://'.strtolower($parts['host']).':'.$port;
        };
        $expectedOrigin = $normalizeOrigin($base); $givenOrigin = is_string($origin) ? $normalizeOrigin($origin) : null;
        if ($expectedOrigin === null || $givenOrigin === null || !hash_equals($expectedOrigin,$givenOrigin)) $respond(['error'=>'origin_forbidden'],403);
    }
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') $respond(['error'=>'origin_forbidden'],403);

    if ($method === 'GET') {
        $action = $_GET['action'] ?? null;
        if (!is_string($action)) throw new InvalidArgumentException('Missing action.');
        switch ($action) {
            case 'account':
                e2ee_fields($_GET,['action']);
                $respond(['account'=>e2ee_account($pdo,$userId)]);
            case 'uploads':
                e2ee_fields($_GET,['action']);
                $respond(['attachments'=>e2ee_uploads($pdo,$userId)]);
            case 'context':
                e2ee_fields($_GET,['action','listing_id','peer_id']);
                $respond(e2ee_context($pdo,$userId,$readInteger($_GET['listing_id']),$readInteger($_GET['peer_id'])));
            case 'history':
                e2ee_fields($_GET,isset($_GET['after']) ? ['action','listing_id','peer_id','after'] : ['action','listing_id','peer_id']);
                $respond(['messages'=>e2ee_history($pdo,$userId,$readInteger($_GET['listing_id']),$readInteger($_GET['peer_id']),isset($_GET['after']) ? $readInteger($_GET['after'],0) : 0)]);
            case 'download':
                e2ee_fields($_GET,['action','id']);
                $download = e2ee_download($pdo,$userId,e2ee_hex($_GET['id']));
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="encrypted-attachment.bin"');
                header('Content-Length: '.$download['bytes']);
                header('Content-Security-Policy: sandbox; default-src \'none\'');
                session_write_close();
                try { fpassthru($download['stream']); } finally { fclose($download['stream']); }
                exit;
            default: $respond(['error'=>'not_found'],404);
        }
    }

    if ($_GET !== []) throw new InvalidArgumentException('Unexpected query fields.');
    if (!preg_match('/\Aapplication\/json(?:\s*;\s*charset=utf-8)?\s*\z/i',$_SERVER['CONTENT_TYPE'] ?? '')) $respond(['error'=>'json_required'],415);
    $input = fopen('php://input','rb');
    if ($input === false) throw new InvalidArgumentException('Request unavailable.');
    try { $raw = stream_get_contents($input,7340033); } finally { fclose($input); }
    if ($raw === false || strlen($raw) > 7340032) $respond(['error'=>'request_too_large'],413);
    try { $request = json_decode($raw,true,32,JSON_THROW_ON_ERROR); }
    catch (JsonException) { throw new InvalidArgumentException('Invalid JSON.'); }
    if (!is_array($request) || array_is_list($request)) throw new InvalidArgumentException('JSON object required.');
    $token = $request['csrf_token'] ?? null;
    if (!is_string($token) || !isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'],$token)) $respond(['error'=>'csrf_invalid'],403);
    $action = $request['action'] ?? null;
    if (!is_string($action)) throw new InvalidArgumentException('Missing action.');
    unset($request['action'],$request['csrf_token']);
    switch ($action) {
        case 'account': $respond(['account'=>e2ee_account_save($pdo,$userId,$request)]);
        case 'upload': $respond(['attachment'=>e2ee_upload($pdo,$userId,$request)]);
        case 'send': $respond(['id'=>e2ee_send($pdo,$userId,$request)]);
        case 'read':
            e2ee_fields($request,['listing_id','peer_id','through']);
            $respond(['read'=>e2ee_read($pdo,$userId,e2ee_integer($request['listing_id']),e2ee_integer($request['peer_id']),e2ee_integer($request['through']))]);
        case 'discard':
            e2ee_fields($request,['id']); e2ee_discard($pdo,$userId,e2ee_hex($request['id']));
            $respond(['discarded'=>true]);
        default: $respond(['error'=>'not_found'],404);
    }
} catch (E2EEConflictException) { $respond(['error'=>'state_conflict'],409);
} catch (InvalidArgumentException) { $respond(['error'=>'invalid_request'],400);
} catch (MarketAccessException) { $respond(['error'=>'access_forbidden'],403);
} catch (MarketNotFoundException) { $respond(['error'=>'not_found'],404);
} catch (OverflowException) { header('Retry-After: 60'); $respond(['error'=>'rate_or_storage_limit'],429);
} catch (E2EEUnavailableException|MaintenancePaused|MaintenanceStateConflict) { header('Retry-After: 60'); $respond(['error'=>'temporarily_unavailable'],503);
} catch (Throwable $error) {
    error_log('GunIndex E2EE failure: '.get_class($error).' at '.basename($error->getFile()).':'.$error->getLine());
    $respond(['error'=>'request_failed'],500);
}
