<?php
declare(strict_types=1);
ini_set('display_errors','0'); ini_set('log_errors','1'); date_default_timezone_set('UTC');

function app_error_response(string $problem): never
{
    [$status, $title, $detail] = match ($problem) {
        'request_size' => [413, 'Die Anfrage ist zu groß.', 'Wähle weniger oder kleinere Dateien aus.'],
        'setup' => [503, 'Die Anwendung ist noch nicht eingerichtet.', 'Versuche es später erneut.'],
        'database' => [503, 'Die Datenbank ist vorübergehend nicht erreichbar.', 'Versuche es später erneut.'],
        default => [500, 'Die Anfrage konnte nicht abgeschlossen werden.', 'Versuche es später erneut.'],
    };
    $locale = 'de';
    $headingKey = 'account.system_'.$problem;
    $detailKey = $status === 413 ? 'account.system_smaller_files' : 'account.system_retry';
    try {
        require_once __DIR__.'/i18n.php';
        [$translationLocale, $heading, $instruction] = [i18n_locale(), t($headingKey), t($detailKey)];
        if ($heading !== $headingKey && $instruction !== $detailKey) {
            [$locale, $title, $detail] = [$translationLocale, $heading, $instruction];
        }
    } catch (Throwable) {
        // The emergency response still works if the translation files cannot be loaded.
    }
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
        echo '<!doctype html><html lang="'.htmlspecialchars($locale,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'
            .htmlspecialchars($title,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</title><main><h1>'
            .htmlspecialchars($title,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</h1><p>'
            .htmlspecialchars($detail,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p></main></html>';
    }
    exit;
}

set_exception_handler(static function(Throwable $error):void {
 error_log('GunIndex failure: '.get_class($error).' at '.basename($error->getFile()).':'.$error->getLine());
 if(PHP_SAPI==='cli'){fwrite(STDERR,"Operation failed; inspect restricted logs.\n");exit(1);}
 app_error_response('failure');
});
if(PHP_SAPI!=='cli'){
 header('Content-Type: text/html; charset=utf-8'); header('X-Frame-Options: DENY'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: strict-origin-when-cross-origin'); header('Permissions-Policy: camera=(), microphone=(), geolocation=()'); header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store');
 header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self' https://www.sandbox.paypal.com https://www.paypal.com");
 if(session_status()===PHP_SESSION_NONE&&!defined('GUNINDEX_STATELESS_REQUEST')){
  ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');ini_set('session.use_trans_sid','0'); session_name('gunindex_session');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>getenv('SESSION_COOKIE_SECURE')==='1'||(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'httponly'=>true,'samesite'=>'Lax']);session_start();
 }
 $postLimit=ini_get('post_max_size');
 if(is_string($postLimit)&&preg_match('/\A([0-9]+)([KMG]?)\z/i',$postLimit,$limitParts)){
  $postBytes=(int)$limitParts[1]*(match(strtoupper($limitParts[2])){'K'=>1024,'M'=>1048576,'G'=>1073741824,default=>1});
  if($postBytes>0&&(int)($_SERVER['CONTENT_LENGTH']??0)>$postBytes)app_error_response('request_size');
 }
}
