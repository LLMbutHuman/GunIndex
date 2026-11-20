<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/bootstrap.php';

$dealerApiMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

try {
    if (!dealer_api_available()) throw new DealerApiError('not_found', 404);
    $testLoopback = getenv('APP_ENV') === 'development' && getenv('DB_DATABASE') === 'gunindex_rebuild_test'
        && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)
        && $pdo->query('SELECT DATABASE()')->fetchColumn() === 'gunindex_rebuild_test';
    if (($_SERVER['HTTPS'] ?? '') !== 'on' && !$testLoopback) throw new DealerApiError('https_required', 403);

    $resource = $_GET['resource'] ?? null;
    if (!is_string($resource) || !in_array($resource, ['inventory','orders','stock_movement'], true)) {
        throw new DealerApiError('not_found', 404);
    }
    $methods = $resource === 'stock_movement' ? ['POST'] : ['GET','HEAD'];
    if (!in_array($dealerApiMethod, $methods, true)) {
        header('Allow: '.implode(', ', $methods));
        throw new DealerApiError('method_not_allowed', 405);
    }
    $queryKeys = $resource === 'inventory' ? ['resource','id','after'] : ['resource'];
    if (array_diff(array_keys($_GET), $queryKeys) || (isset($_GET['id']) && isset($_GET['after']))) {
        throw new DealerApiError('invalid_request', 400);
    }
    $input = $_GET;
    if ($dealerApiMethod === 'POST') {
        $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '', 2)[0]));
        if ($contentType !== 'application/json') throw new DealerApiError('json_required', 415);
        $rawBody = file_get_contents('php://input', false, null, 0, 4097);
        if ($rawBody === false || strlen($rawBody) > 4096) throw new DealerApiError('request_too_large', 413);
        $input = json_decode($rawBody, true, 8, JSON_THROW_ON_ERROR);
        $keys = ['id','version','request_key','amount','reason'];
        if (!is_array($input) || count($input) !== count($keys) || array_diff(array_keys($input), $keys)) {
            throw new DealerApiError('invalid_request', 400);
        }
    }
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $response = dealer_api_execute($pdo, is_string($authorization) ? $authorization : '', $resource, $input);
} catch (DealerApiError $error) {
    http_response_code($error->getCode());
    if ($error->getCode() === 401) header('WWW-Authenticate: Bearer realm="GunIndex Dealer API"');
    $response = ['error'=>$error->getMessage()];
} catch (MarketNotFoundException) {
    http_response_code(404);
    $response = ['error'=>'not_found'];
} catch (MarketConflictException) {
    http_response_code(409);
    $response = ['error'=>'conflict'];
} catch (DomainException|InvalidArgumentException|JsonException) {
    http_response_code(422);
    $response = ['error'=>'invalid_request'];
} catch (Throwable $error) {
    error_log('Dealer API interrupted ('.get_class($error).')');
    http_response_code(503);
    $response = ['error'=>'unavailable'];
}
if ($dealerApiMethod !== 'HEAD') echo json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
