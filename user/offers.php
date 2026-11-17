<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);$actor=(int)$user['id'];$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','HEAD','POST'],true)){header('Allow: GET, HEAD, POST');abort_request(405,t('account.method'));}
if($method==='POST')require_csrf();
$input=$method==='POST'?$_POST:$_GET;$rawId=$input['listing_id']??null;
if(!is_string($rawId)||!preg_match('/\A[1-9][0-9]{0,9}\z/',$rawId))abort_request(404,t('trade.unavailable'));
$listingId=(int)$rawId;$error='';
if($method==='POST'){
    try{
        $action=$_POST['action']??null;
        if($action==='create_offer'){
            $offerId=trade_offer_create($pdo,$actor,$listingId,$_POST);
        }elseif(in_array($action,['reject','withdraw'],true)){
            $rawOffer=$_POST['offer_id']??null;
            if(!is_string($rawOffer)||!preg_match('/\A[1-9][0-9]{0,9}\z/',$rawOffer)||($_POST['confirm_action']??null)!=='1')throw new DomainException(t('trade.confirm'));
            $offerId=(int)$rawOffer;
            // The service authorizes the offer's parties; the controller also binds the return context.
            $context=trade_offer_context($pdo,$actor,$listingId);
            if(!array_filter($context['offers'],static fn(array $row):bool=>(int)$row['id']===$offerId))throw new MarketAccessException(t('trade.forbidden'));
            trade_offer_action($pdo,$actor,$offerId,$action);
        }else throw new DomainException(t('trade.invalid'));
        redirect('/user/offers.php?listing_id='.$listingId.'&saved=1#offer-'.$offerId);
    }catch(MarketAccessException $e){http_response_code(403);$error=$e->getMessage();}
    catch(MarketNotFoundException $e){http_response_code(404);$error=$e->getMessage();}
    catch(MarketConflictException $e){http_response_code(409);$error=$e->getMessage();}
    catch(DomainException|InvalidArgumentException $e){http_response_code(422);$error=$e->getMessage();}
}
try{$context=trade_offer_context($pdo,$actor,$listingId);}
catch(MarketAccessException){abort_request(403,t('trade.forbidden'));}
catch(MarketNotFoundException|MarketConflictException|DomainException){abort_request(404,t('trade.unavailable'));}
$listing=$context['listing'];$parent=null;$hasOpen=false;$parentInput=$input['parent_id']??'';
if(!is_string($parentInput)||($parentInput!==''&&!preg_match('/\A[1-9][0-9]{0,9}\z/',$parentInput)))abort_request(400,t('trade.invalid'));
foreach($context['offers'] as $offer){
    if(!empty($offer['actionable']))$hasOpen=true;
    if($parentInput!==''&&(int)$offer['id']===(int)$parentInput&&!empty($offer['actionable'])&&(int)$offer['proposer_id']!==$actor)$parent=$offer;
}
if($parentInput!==''&&!$parent){http_response_code(409);$error=t('trade.changed');}
$canCreate=(int)$listing['trade_checkout_enabled']===1&&$listing['status']==='active'
    &&($listing['ends_at']===null||strtotime($listing['ends_at'].' UTC')>time())
    &&($parent!==null||((int)$listing['seller_id']!==$actor&&!$hasOpen&&$parentInput===''));
$requestKey=isset($_POST['request_key'])&&is_string($_POST['request_key'])&&preg_match('/\A[a-f0-9]{32}\z/',$_POST['request_key'])?$_POST['request_key']:bin2hex(random_bytes(16));
$amount=$method==='POST'&&isset($_POST['amount'])&&is_string($_POST['amount'])?substr($_POST['amount'],0,30):($parent!==null?number_format((int)$parent['price_cents']/100,2,'.',''):'');
$delivery=$method==='POST'&&isset($_POST['delivery'])&&is_string($_POST['delivery'])?$_POST['delivery']:($parent['delivery']??(!empty($listing['pickup'])?'pickup':'shipping'));
$pageTitle=t('trade.offers');include __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/trade.css">
<main class="trade">
<div class="trade-heading"><div><h1><?=h(t('trade.offers'))?></h1><h2><?=h($listing['title'])?></h2></div><a href="/user/trades.php"><?=h(t('trade.title'))?></a></div>
<p><a href="/marketplace/view-item.php?id=<?=$listingId?>"><?=h(t('trade.view_listing'))?></a></p>
<p><?=h(t('trade.offers_help'))?></p><p class="trade-note"><?=h(t('trade.payment_notice'))?></p>
<?php if($error!==''):?><p class="trade-error" role="alert"><?=h($error)?></p><?php elseif(isset($_GET['saved'])):?><p class="notice" role="status"><?=h(t('trade.saved'))?></p><?php endif;?>
<div class="trade-grid"><section class="panel"><h2><?=h(t('trade.offer_history'))?></h2>
<?php if(!$context['offers']):?><p><?=h(t('trade.no_offers'))?></p><?php endif;?>
<div class="trade-stack">
<?php foreach($context['offers'] as $offer):$own=(int)$offer['proposer_id']===$actor;?>
<article id="offer-<?=(int)$offer['id']?>" class="panel trade-card" data-offer-id="<?=(int)$offer['id']?>" data-offer-status="<?=h($offer['status'])?>">
<h3><?=h(t($own?'trade.offer.sent':'trade.offer.received'))?> #<?=(int)$offer['id']?></h3>
<p><span class="trade-status"><?=h(t('trade.offer_status.'.$offer['status']))?></span></p>
<dl class="trade-terms"><dt><?=h(t('trade.price'))?></dt><dd><?=h(money_eur((int)$offer['price_cents']))?></dd><dt><?=h(t('trade.delivery'))?></dt><dd><?=h(t('trade.delivery.'.$offer['delivery']))?></dd><dt><?=h(t('trade.shipping_cost'))?></dt><dd><?=h(money_eur((int)$offer['shipping_cents']))?></dd><dt><?=h(t('trade.total'))?></dt><dd><?=h(money_eur((int)$offer['total_cents']))?></dd></dl>
<p class="trade-meta"><?=h(t('trade.expires_at'))?>: <time><?=h($offer['expires_at'])?> UTC</time></p>
<?php if(!empty($offer['actionable'])):?>
<div class="trade-actions">
<?php if(!$own):?><a class="button" href="/user/checkout.php?listing_id=<?=$listingId?>&amp;offer_id=<?=(int)$offer['id']?>&amp;delivery=<?=h($offer['delivery'])?>"><?=h(t('trade.review_accept'))?></a><a class="button secondary" href="/user/offers.php?listing_id=<?=$listingId?>&amp;parent_id=<?=(int)$offer['id']?>#trade-offer-form"><?=h(t('trade.counter_offer'))?></a><?php endif;?>
<form method="post" action="/user/offers.php" data-offer-action="<?=$own?'withdraw':'reject'?>"><?=csrf_field()?><input type="hidden" name="listing_id" value="<?=$listingId?>"><input type="hidden" name="offer_id" value="<?=(int)$offer['id']?>"><label class="check"><input type="checkbox" name="confirm_action" value="1" required><span><?=h(t('trade.confirm_action'))?></span></label><button class="button secondary" name="action" value="<?=$own?'withdraw':'reject'?>" type="submit"><?=h(t($own?'trade.withdraw_offer':'trade.reject_offer'))?></button></form>
</div>
<?php elseif($offer['status']==='open'):?><p><?=h(t('trade.offer_unavailable'))?></p><?php endif;?>
</article>
<?php endforeach;?></div></section>
<section class="panel"><h2><?=h(t($parent!==null?'trade.counter_offer':'trade.create_offer'))?></h2>
<?php if($canCreate):?>
<p><?=h(t('trade.offer_binding'))?></p>
<form id="trade-offer-form" method="post" action="/user/offers.php">
<?=csrf_field()?><input type="hidden" name="listing_id" value="<?=$listingId?>"><input type="hidden" name="listing_hash" value="<?=h($context['hash'])?>"><input type="hidden" name="request_key" value="<?=h($requestKey)?>">
<?php if($parent!==null):?><input type="hidden" name="parent_id" value="<?=(int)$parent['id']?>"><p><?=h(t('trade.counter_for',['id'=>(int)$parent['id']]))?></p><?php endif;?>
<label><?=h(t('trade.offer_amount'))?><input name="amount" inputmode="decimal" type="text" maxlength="12" pattern="(?:0|[1-9][0-9]{0,7})(?:[.,][0-9]{1,2})?" value="<?=h($amount)?>" required></label>
<label><?=h(t('trade.delivery'))?><select name="delivery" required>
<?php if(!empty($listing['pickup'])):?><option value="pickup" <?=$delivery==='pickup'?'selected':''?>><?=h(t('trade.delivery.pickup'))?></option><?php endif;?>
<?php if(!empty($listing['shipping'])):?><option value="shipping" <?=$delivery==='shipping'?'selected':''?>><?=h(t('trade.delivery.shipping'))?> (+ <?=h(money_eur((int)$listing['shipping_cents']))?>)</option><?php endif;?>
</select></label>
<p class="trade-meta"><?=h(t('trade.shipping_added'))?></p>
<label><?=h(t('trade.offer_hours'))?><input type="number" name="hours" min="1" max="168" value="48" required></label>
<?php consent_form($context['documents'],$context['locale']);?>
<label class="check"><input type="checkbox" name="confirm_offer" value="1" required><span><?=h(t('trade.confirm_offer'))?></span></label>
<button class="button" type="submit" name="action" value="create_offer"><?=h(t($parent!==null?'trade.send_counter':'trade.send_offer'))?></button>
</form>
<?php else:?><p><?=h(t('trade.no_new_offer'))?></p><?php endif;?>
</section></div></main>
<?php include __DIR__.'/../includes/footer.php'; ?>
