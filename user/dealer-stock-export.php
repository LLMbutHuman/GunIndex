<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';

$actor = (int)require_login($pdo)['id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
if (!in_array($method, ['GET','HEAD'], true)) {
    header('Allow: GET, HEAD');
    abort_request(405, t('account.method'));
}
try {
    $owner = trade_integer($_GET['owner_id'] ?? null);
    $after = isset($_GET['after']) ? trade_integer($_GET['after']) : 0;
    $export = dealer_subscription_export($pdo,$actor,$owner,$after);
} catch (InvalidArgumentException) {
    abort_request(404, t('dealer_stock.unavailable'));
} catch (MarketAccessException) {
    abort_request(403, t('dealer_subscription.export_unavailable'));
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="gunindex-stock-' . $after . '.csv"');
if ($export['next'] !== null) {
    header('Link: </user/dealer-stock-export.php?owner_id=' . $owner . '&after=' . $export['next'] . '>; rel="next"');
}
if ($method !== 'HEAD') echo dealer_subscription_csv($export['rows']);
