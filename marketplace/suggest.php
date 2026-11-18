<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');
if (($_SERVER['REQUEST_METHOD']??'')!=='GET') {
    header('Allow: GET');
    http_response_code(405);
    echo '[]';
    exit;
}
try {
    $params=[];
    foreach (['q','country_code','scope','category_id','type'] as $key) {
        if (isset($_GET[$key])) {
            if (!is_string($_GET[$key])||strlen($_GET[$key])>200) throw new InvalidArgumentException();
            $params[$key]=$_GET[$key];
        }
    }
    echo json_encode(market_suggestions($pdo,$params['q']??'',$params),JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException) {
    http_response_code(400);
    echo '[]';
}
