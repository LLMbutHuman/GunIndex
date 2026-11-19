<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
require_once __DIR__.'/functions.php';
require_once __DIR__.'/classification.php';
require_once __DIR__.'/listing_drafts.php';
require_once __DIR__.'/listing_alerts.php';
require_once __DIR__.'/user_security.php';
require_once __DIR__.'/passkeys.php';
require_once __DIR__.'/market_search_text.php';
foreach(['settings','crypto','geo','i18n','catalog','money','governance','consent','auth','mail','account_tokens','images','image_order','market','discovery','business','fees','payments','finance','manual_payments','chat','privacy','setup','seo','personal','auction_billing','maintenance','relisting','e2ee','trade'] as $module){
 $path=__DIR__.'/'.$module.'.php';
 if(is_file($path)) require_once $path;
}
