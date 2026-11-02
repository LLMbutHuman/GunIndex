<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    abort_request(405, 'Nur GET oder HEAD erlaubt.');
}
if (!seo_enabled($pdo)) abort_request(404, 'Nicht gefunden.');
try {
    $page = seo_sitemap_page($_GET['page'] ?? null);
    $data = seo_sitemap_rows($pdo, $page);
} catch (InvalidArgumentException | OutOfBoundsException) {
    abort_request(404, 'Nicht gefunden.');
}
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') echo seo_sitemap_document(seo_origin(), $page, $data);
