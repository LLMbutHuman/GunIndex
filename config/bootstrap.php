<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
require_once __DIR__.'/functions.php';
require_once __DIR__.'/classification.php';
require_once __DIR__.'/listing_drafts.php';
require_once __DIR__.'/listing_alerts.php';
require_once __DIR__.'/user_security.php';
require_once __DIR__.'/passkeys.php';
require_once __DIR__.'/profile_images.php';
require_once __DIR__.'/auction_fee_corrections.php';
require_once __DIR__.'/paypal_refunds.php';
require_once __DIR__.'/bank_statement.php';
require_once __DIR__.'/bank_transfers.php';
require_once __DIR__.'/bitcoin_rpc.php';
require_once __DIR__.'/bitcoin_payments.php';
require_once __DIR__.'/mail_smtp.php';
require_once __DIR__.'/mail_dns.php';
require_once __DIR__.'/mail_setup.php';
require_once __DIR__.'/escrow_workflow.php';
require_once __DIR__.'/escrow.php';
require_once __DIR__.'/dealer_team.php';
require_once __DIR__.'/dealer_inventory.php';
require_once __DIR__.'/dealer_api.php';
require_once __DIR__.'/dealer_checkout_stock.php';
require_once __DIR__.'/dealer_subscriptions.php';
require_once __DIR__.'/market_search_text.php';
foreach(['settings','crypto','geo','i18n','catalog','money','governance','consent','auth','mail','account_tokens','images','image_order','market','discovery','business','fees','payments','finance','manual_payments','chat','privacy','setup','seo','personal','auction_billing','maintenance','relisting','e2ee','trade'] as $module){
 $path=__DIR__.'/'.$module.'.php';
 if(is_file($path)) require_once $path;
}
