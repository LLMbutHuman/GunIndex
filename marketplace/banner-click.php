<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/business.php';
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','HEAD'],true))abort_request(405,t('account.method'));
$id=is_string($_GET['id']??null)?filter_var($_GET['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;$raw=$_GET['country']??null;
if(!$id||!is_string($raw))abort_request(404,t('admin_campaigns.banner_missing'));
try{$country=business_country($raw);}catch(InvalidArgumentException){abort_request(404,t('admin_campaigns.banner_missing'));}
$place=$_GET['placement']??null;
$campaign=$place===null ? business_public_campaign($pdo,(int)$id,$country)
    : (is_string($place) ? (banner_ads_public($pdo,$place,$country,null,(int)$id)[0]??null) : null);
if(!$campaign)abort_request(404,t('admin_campaigns.banner_missing'));
try{$url=$place===null?business_validate_url($campaign['target_url']):banner_ads_url($campaign['target_url']);}catch(InvalidArgumentException){abort_request(404,t('admin_campaigns.banner_missing'));}
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET')business_record_metric($pdo,(int)$id,'click');
header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');header('Location: '.$url,true,303);exit;
