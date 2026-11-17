<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);$actor=(int)$user['id'];
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','HEAD','POST'],true)){header('Allow: GET, HEAD, POST');abort_request(405,t('account.method'));}
if($method==='POST')require_csrf();
$input=$method==='POST'?$_POST:$_GET;
$rawId=$input['listing_id']??null;
if(!is_string($rawId)||!preg_match('/\A[1-9][0-9]{0,9}\z/',$rawId))abort_request(404,t('trade.unavailable'));
$listingId=(int)$rawId;$offerId=null;
if(isset($input['offer_id'])&&$input['offer_id']!==''){
    if(!is_string($input['offer_id'])||!preg_match('/\A[1-9][0-9]{0,9}\z/',$input['offer_id']))abort_request(404,t('trade.unavailable'));
    $offerId=(int)$input['offer_id'];
}
$error='';$quote=null;
if($method==='POST'){
    try{
        if(($_POST['action']??null)!=='purchase')throw new DomainException(t('trade.invalid'));
        $tradeId=trade_purchase($pdo,$actor,$listingId,$_POST,$offerId);
        redirect('/user/trade.php?id='.$tradeId.'&saved=1');
    }catch(MarketAccessException $e){http_response_code(403);$error=$e->getMessage();}
    catch(MarketNotFoundException $e){http_response_code(404);$error=$e->getMessage();}
    catch(MarketConflictException $e){http_response_code(409);$error=$e->getMessage();}
    catch(DomainException|InvalidArgumentException $e){http_response_code(422);$error=$e->getMessage();}
}
$delivery=$input['delivery']??null;
if($delivery===null){$availableListing=market_find($pdo,$listingId,true);$delivery=!empty($availableListing['pickup'])?'pickup':'shipping';}
try{
    if(!is_string($delivery)||!in_array($delivery,['pickup','shipping'],true))throw new DomainException(t('trade.invalid'));
    $quote=trade_quote($pdo,$actor,$listingId,$delivery,$offerId);
}catch(MarketAccessException $e){http_response_code(403);$error=$e->getMessage();}
catch(MarketNotFoundException $e){http_response_code(404);$error=$e->getMessage();}
catch(MarketConflictException $e){http_response_code(409);$error=$e->getMessage();}
catch(DomainException|InvalidArgumentException $e){http_response_code(422);$error=$e->getMessage();}
$requestKey=isset($_POST['request_key'])&&is_string($_POST['request_key'])&&preg_match('/\A[a-f0-9]{32}\z/',$_POST['request_key'])?$_POST['request_key']:bin2hex(random_bytes(16));
$pageTitle=t('trade.checkout');include __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/trade.css">
<main class="trade">
<div class="trade-heading"><h1><?=h(t('trade.checkout'))?></h1><a href="/user/trades.php"><?=h(t('trade.title'))?></a></div>
<?php if($error!==''):?><p class="trade-error" role="alert"><?=h($error)?></p><?php endif;?>
<?php if($quote): $listing=$quote['listing']; ?>
<div class="trade-grid"><section class="panel">
<h2><?=h($listing['title'])?></h2>
<p><a href="/marketplace/view-item.php?id=<?=$listingId?>"><?=h(t('trade.view_listing'))?></a></p>
<details><summary><?=h(t('trade.description'))?></summary><div class="trade-text"><?=h($listing['description'])?></div></details>
<?php if($offerId===null&& !empty($listing['pickup'])&& !empty($listing['shipping'])):?>
<form method="get" action="/user/checkout.php"><input type="hidden" name="listing_id" value="<?=$listingId?>">
<label><?=h(t('trade.delivery'))?><select name="delivery"><option value="pickup" <?=$quote['delivery']==='pickup'?'selected':''?>><?=h(t('trade.delivery.pickup'))?></option><option value="shipping" <?=$quote['delivery']==='shipping'?'selected':''?>><?=h(t('trade.delivery.shipping'))?></option></select></label>
<button class="button secondary" type="submit"><?=h(t('trade.update_total'))?></button></form>
<?php endif;?>
<dl class="trade-terms"><dt><?=h(t('trade.price'))?></dt><dd><?=h(money_eur((int)$quote['price_cents']))?></dd><dt><?=h(t('trade.delivery'))?></dt><dd><?=h(t('trade.delivery.'.$quote['delivery']))?></dd><dt><?=h(t('trade.shipping_cost'))?></dt><dd><?=h(money_eur((int)$quote['shipping_cents']))?></dd><dt class="trade-total"><?=h(t('trade.total'))?></dt><dd class="trade-total"><?=h(money_eur((int)$quote['total_cents']))?></dd></dl>
<p class="trade-note"><?=h(t('trade.payment_notice'))?></p><p><?=h(t('trade.purchase_notice'))?></p>
</section><section class="panel">
<h2><?=h(t($offerId===null?'trade.confirm_order':'trade.accept_offer'))?></h2>
<form id="trade-checkout-form" method="post" action="/user/checkout.php">
<?=csrf_field()?><input type="hidden" name="listing_id" value="<?=$listingId?>"><input type="hidden" name="delivery" value="<?=h($quote['delivery'])?>"><input type="hidden" name="quote_hash" value="<?=h($quote['hash'])?>"><input type="hidden" name="request_key" value="<?=h($requestKey)?>">
<?php if($offerId!==null):?><input type="hidden" name="offer_id" value="<?=$offerId?>"><?php endif;?>
<?php consent_form($quote['documents'],$quote['locale']); ?>
<label class="check"><input type="checkbox" name="confirm_purchase" value="1" required> <span><?=h(t('trade.confirm_purchase',['total'=>money_eur((int)$quote['total_cents'])]))?></span></label>
<button class="button" type="submit" name="action" value="purchase"><?=h(t($offerId===null?'trade.purchase':'trade.accept_offer'))?></button>
</form></section></div>
<?php else:?><p><a href="/marketplace/view-item.php?id=<?=$listingId?>"><?=h(t('trade.view_listing'))?></a></p><?php endif;?>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
