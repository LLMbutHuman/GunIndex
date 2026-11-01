<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';require_csrf();
$locale=$_POST['locale']??null;$country=$_POST['market_country']??($_POST['country_code']??null);$return=$_POST['return_path']??'/';
if(!is_string($locale)||!isset(i18n_locales()[$locale])||!is_string($country)||!is_string($return))abort_request(422,t('Bitte Sprache und Land prüfen.'));
try{$country=geo_country($country);if(!geo_country_enabled($pdo,$country))throw new InvalidArgumentException();}catch(InvalidArgumentException){abort_request(422,t('Dieser Ländermarkt ist derzeit nicht verfügbar.'));}
$decoded=rawurldecode($return);
if(!str_starts_with($return,'/')||str_starts_with($return,'//')||str_starts_with($decoded,'//')||preg_match('/[\x00-\x20\\\\]/',$return)||preg_match('/[\x00-\x1f\x7f\\\\]/',$decoded))$return='/';
$user=current_user($pdo);
$_SESSION['locale']=$locale;$_SESSION['market_country']=$country;
if($user)$pdo->prepare('UPDATE users SET preferred_locale=? WHERE id=?')->execute([$locale,$user['id']]);
redirect($return);
