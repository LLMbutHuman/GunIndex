<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/business.php';
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','HEAD'],true))abort_request(405,'Diese Methode wird nicht unterstützt.');
$id=is_string($_GET['id']??null)?filter_var($_GET['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;
$raw=$_GET['country']??null;if(!$id||!is_string($raw))abort_request(404,'Banner nicht gefunden.');
try{$country=business_country($raw);}catch(InvalidArgumentException){abort_request(404,'Banner nicht gefunden.');}
$campaign=business_public_campaign($pdo,(int)$id,$country);if(!$campaign)abort_request(404,'Banner nicht gefunden.');
business_output_banner($campaign['storage_key'],false,static fn()=>business_record_metric($pdo,(int)$id,'impression'));
