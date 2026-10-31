<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '',['GET','HEAD'],true)) abort_request(405,'Nur GET oder HEAD erlaubt.');
$id=$_GET['id'] ?? null;
$position=$_GET['position'] ?? '0';
$size=$_GET['size'] ?? 'thumb';
if (!is_string($id) || !ctype_digit($id) || !is_string($position) || !preg_match('/\A[0-4]\z/',$position) || !is_string($size) || !in_array($size,['thumb','large'],true)) abort_request(404,'Bild nicht gefunden.');
if (market_find($pdo,(int)$id)===null) abort_request(404,'Bild nicht gefunden.');
$q=$pdo->prepare('SELECT storage_key FROM market_images WHERE listing_id=? AND position=? LIMIT 1');
$q->execute([(int)$id,(int)$position]);
$key=$q->fetchColumn();
if ($key===false) {
    header('Location: /assets/img/no-image.png',true,302);
    exit;
}
if (!is_string($key) || !preg_match('/\A[a-f0-9]{48}\z/',$key)) abort_request(404,'Bild nicht gefunden.');
$root=market_image_directory();
$path=$root.'/'.$key.($size==='thumb'?'-thumb':'').'.webp';
if (is_link($path) || !is_file($path) || realpath(dirname($path))!==$root) abort_request(404,'Bild nicht gefunden.');
header('Content-Type: image/webp');
header('Content-Length: '.filesize($path));
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD']!=='HEAD') readfile($path);
