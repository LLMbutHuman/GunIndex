<?php
declare(strict_types=1);
function h(mixed $text):string{return htmlspecialchars(is_scalar($text)?(string)$text:'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function abort_request(int $status,string $message):never{http_response_code($status);header('Content-Type: text/html; charset=utf-8');exit('<!doctype html><html lang="de"><meta charset="utf-8"><title>Anfrage</title><h1>'.h($message).'</h1><p><a href="/">Zur Startseite</a></p></html>');}
function redirect(string $path):never{if(!str_starts_with($path,'/')||str_starts_with($path,'//')||str_contains($path,'\\')||str_contains($path,"\r")||str_contains($path,"\n"))throw new InvalidArgumentException('Invalid redirect');header('Location: '.$path,true,303);exit;}
function require_post():void{if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');abort_request(405,'Diese Aktion benötigt ein Formular.');}}
function csrf_token():string{if(!isset($_SESSION['csrf_token'])||!is_string($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(32));return $_SESSION['csrf_token'];}
function csrf_field():string{return '<input type="hidden" name="csrf_token" value="'.h(csrf_token()).'">';}
function require_csrf():void{require_post();$token=$_POST['csrf_token']??null;if(!is_string($token)||!isset($_SESSION['csrf_token'])||!is_string($_SESSION['csrf_token'])||!hash_equals($_SESSION['csrf_token'],$token))abort_request(403,'Das Formular ist abgelaufen. Bitte lade die Seite erneut.');}
function post_string(string $key,int $max=10000):string{$value=$_POST[$key]??'';if(!is_string($value)||strlen($value)>$max)throw new InvalidArgumentException('Ungültige Formulareingabe.');return $value;}
