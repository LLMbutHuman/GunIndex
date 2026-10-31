<?php
declare(strict_types=1);
ini_set('display_errors','0'); ini_set('log_errors','1'); date_default_timezone_set('UTC');
set_exception_handler(static function(Throwable $error):void {
 error_log('GunIndex failure: '.get_class($error).' at '.basename($error->getFile()).':'.$error->getLine());
 if(PHP_SAPI==='cli'){fwrite(STDERR,"Operation failed; inspect restricted logs.\n");exit(1);}
 http_response_code(500); header('Content-Type: text/html; charset=utf-8'); echo '<!doctype html><html lang="de"><meta charset="utf-8"><title>Fehler</title><h1>Die Anfrage konnte nicht abgeschlossen werden.</h1><p>Bitte versuche es später erneut.</p></html>';
});
if(PHP_SAPI!=='cli'){
 $postLimit=ini_get('post_max_size');
 if(is_string($postLimit)&&preg_match('/\A([0-9]+)([KMG]?)\z/i',$postLimit,$limitParts)){
  $postBytes=(int)$limitParts[1]*(match(strtoupper($limitParts[2])){'K'=>1024,'M'=>1048576,'G'=>1073741824,default=>1});
  if($postBytes>0&&(int)($_SERVER['CONTENT_LENGTH']??0)>$postBytes){http_response_code(413);exit('Die Anfrage ist zu groß. Bitte weniger oder kleinere Dateien auswählen.');}
 }
 header('Content-Type: text/html; charset=utf-8'); header('X-Frame-Options: DENY'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: strict-origin-when-cross-origin'); header('Permissions-Policy: camera=(), microphone=(), geolocation=()'); header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store');
 header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self' https://www.sandbox.paypal.com https://www.paypal.com");
 if(session_status()===PHP_SESSION_NONE){
  ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');ini_set('session.use_trans_sid','0'); session_name('gunindex_session');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>getenv('SESSION_COOKIE_SECURE')==='1'||(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'httponly'=>true,'samesite'=>'Lax']);session_start();
 }
}
