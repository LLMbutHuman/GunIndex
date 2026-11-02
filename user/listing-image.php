<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);
if (!in_array($_SERVER['REQUEST_METHOD']??'', ['GET','HEAD'],true)) abort_request(405,'Method not allowed.');
$id=$_GET['id']??null;$position=$_GET['position']??null;
if (!is_string($id)||!ctype_digit($id)||!is_string($position)||!preg_match('/\A[0-4]\z/',$position)) abort_request(404,'Not found.');
$query=$pdo->prepare('SELECT i.storage_key FROM market_images i JOIN market_listings l ON l.id=i.listing_id WHERE l.id=? AND l.seller_id=? AND i.position=?');
$query->execute([(int)$id,(int)$user['id'],(int)$position]);$key=$query->fetchColumn();
if (!is_string($key)||!preg_match('/\A[a-f0-9]{48}\z/',$key)) abort_request(404,'Not found.');
$directory=market_image_directory();$path=$directory.'/'.$key.'-thumb.webp';
if (is_link($path)||!is_file($path)||realpath(dirname($path))!==$directory) abort_request(404,'Not found.');
header('Content-Type: image/webp');header('Content-Length: '.filesize($path));
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD']!=='HEAD') readfile($path);
