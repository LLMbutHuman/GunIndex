<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';require_once __DIR__.'/../config/governance.php';require_once __DIR__.'/../config/privacy.php';
$actor=require_permission($pdo,'identity.review');require_csrf();
try{$plain=verification_open_document($pdo,(int)$actor['id'],post_string('token',64));}
catch(InvalidArgumentException|DomainException){abort_request(403,'Dieser Dokumentenzugriff ist nicht verfügbar.');}
header('Content-Type: image/webp');header('Content-Disposition: inline; filename="verification.webp"');header('Content-Length: '.strlen($plain));header('Cache-Control: private, no-store');header('Pragma: no-cache');header('Referrer-Policy: no-referrer');header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; sandbox");header('X-Content-Type-Options: nosniff');echo$plain;
