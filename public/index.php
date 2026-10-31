<?php
declare(strict_types=1);
$root=dirname(__DIR__);$path=rawurldecode(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
if(str_contains($path,"\0")||str_contains($path,'\\')||preg_match('~(?:^|/)\.\.?(/|$)~',$path)){http_response_code(404);exit('Nicht gefunden.');}
$allowed=['marketplace/index.php','marketplace/search.php','marketplace/view-item.php','marketplace/view-auction.php','marketplace/image.php','marketplace/profile.php','marketplace/policy.php','marketplace/buyout-request.php','auth/login.php','auth/register.php','auth/logout.php','auth/mfa.php','user/watchlist.php','user/toggle-watchlist.php','user/messages.php','user/chat.php','user/rate-user.php','admin/ratings.php','admin/listings.php','user/dashboard.php','user/profile.php','user/create-listing.php','user/edit-listing.php','user/delete-listing.php','user/payments.php','user/billing.php','admin/payments.php','actions/place-bid.php','actions/manage-auction.php','actions/payment-checkout.php','actions/payment-return.php','actions/payment-webhook.php','admin/index.php','admin/settings.php','admin/categories.php','admin/users.php','admin/logs.php'];
$route=$path==='/'||$path==='/index.php'?'marketplace/index.php':ltrim($path,'/');
$static=['/assets/css/app.css'=>'text/css; charset=utf-8','/assets/js/app.js'=>'application/javascript; charset=utf-8','/assets/img/no-image.png'=>'image/png'];
if(isset($static[$path])&&is_file($root.$path)){header('Content-Type: '.$static[$path]);header('X-Content-Type-Options: nosniff');if(($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')readfile($root.$path);exit;}
if(!in_array($route,$allowed,true)||!is_file($root.'/'.$route)){http_response_code(404);header('X-Robots-Tag: noindex');exit('Nicht gefunden.');}require $root.'/'.$route;
