<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','HEAD'],true)) { header('Allow: GET, HEAD'); abort_request(405,t('account.method')); }
$revision=$_GET['revision']??null;
try {
    $editor=listing_draft_id($_GET['editor']??null);
    $position=listing_draft_id($_GET['position']??null);
} catch (InvalidArgumentException) { abort_request(404,t('draft.unavailable')); }
if ($position>4||!is_string($revision)||!preg_match('/\A[a-f0-9]{32}\z/',$revision)) abort_request(404,t('draft.unavailable'));
$column=($_GET['size']??null)==='thumb'?'thumb_webp':'large_webp';
$q=$pdo->prepare('SELECT i.'.$column.' FROM market_listing_draft_images i JOIN market_listing_drafts d USING(seller_id,editor_id)'
    .' WHERE d.seller_id=? AND d.editor_id=? AND d.revision=? AND i.position=? AND d.payload IS NOT NULL');
$q->execute([(int)$user['id'],$editor,$revision,$position]);
$bytes=$q->fetchColumn();
if (!is_string($bytes)||$bytes==='') abort_request(404,t('draft.unavailable'));
header('Content-Type: image/webp');
header('Content-Length: '.strlen($bytes));
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');
if ($method==='GET') echo $bytes;
