<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
require_once __DIR__.'/functions.php';
foreach(['settings','crypto','geo','i18n','catalog','money','governance','consent','auth','mail','account_tokens','images','image_order','market','discovery','business','fees','payments','finance','manual_payments','chat','privacy','setup','seo','personal','auction_billing','maintenance'] as $module){
 $path=__DIR__.'/'.$module.'.php';
 if(is_file($path)) require_once $path;
}
