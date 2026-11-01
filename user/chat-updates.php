<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';$user=require_login($pdo);
$read=static fn($name)=>is_string($_GET[$name]??null)?(filter_var($_GET[$name],FILTER_VALIDATE_INT)?:0):0;
try{$context=chat_context($pdo,(int)$user['id'],$read('listing_id'),$read('user_id'));$rows=chat_history($pdo,$context,0,max(0,$read('after')),25);}catch(MarketAccessException){abort_request(403,'Diese Unterhaltung ist privat.');}catch(MarketNotFoundException){abort_request(404,'Unterhaltung nicht gefunden.');}
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');echo json_encode(['messages'=>$rows,'can_send'=>$context['can_send']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
