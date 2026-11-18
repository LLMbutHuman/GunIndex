<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../includes/classification-fields.php';
if (($_SERVER['REQUEST_METHOD']??'')!=='GET') {
    header('Allow: GET');
    abort_request(405,t('account.method'));
}
$category = filter_var($_GET['category_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>4294967295]]);
$mode = $_GET['mode']??null;
if (!$category||!in_array($mode,['listing','search'],true)||!catalog_category_allowed($pdo,$category)) {
    abort_request(404,t('classification.invalid'));
}
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');
classification_fields(classification_definitions($pdo,$category),[],$mode==='search');
