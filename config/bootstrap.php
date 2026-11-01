<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
require_once __DIR__.'/functions.php';
foreach(['settings','crypto','geo','i18n','catalog','money','governance','auth','mail','account_tokens','images','market','discovery','business','fees','payments','finance','manual_payments','chat','privacy'] as $module){
 $path=__DIR__.'/'.$module.'.php';
 if(is_file($path)) require_once $path;
}
