<?php
declare(strict_types=1);
require_once __DIR__.'/security.php';
$database=getenv('DB_DATABASE');$username=getenv('DB_USERNAME');$password=getenv('DB_PASSWORD');
if(!is_string($database)||!preg_match('/\A[a-zA-Z0-9_]+\z/',$database)||!is_string($username)||$username===''||!is_string($password)||$password===''){
 if(PHP_SAPI==='cli'){fwrite(STDERR,"Database environment is incomplete.\n");exit(1);}http_response_code(503);exit('Die Anwendung ist noch nicht eingerichtet.');
}
$socket=getenv('DB_SOCKET');
$dsn=is_string($socket)&&$socket!==''?'mysql:unix_socket='.$socket:'mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';port='.(getenv('DB_PORT')?:'3306');
try{$pdo=new PDO($dsn.';dbname='.$database.';charset=utf8mb4',$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);$pdo->exec("SET time_zone='+00:00'");}
catch(PDOException $error){error_log('GunIndex database unavailable');if(PHP_SAPI==='cli'){fwrite(STDERR,"Database unavailable.\n");exit(1);}http_response_code(503);exit('Die Datenbank ist vorübergehend nicht erreichbar.');}
