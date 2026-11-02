<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    abort_request(405, 'Nur GET oder HEAD erlaubt.');
}
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') echo seo_robots(seo_enabled($pdo), seo_origin());
