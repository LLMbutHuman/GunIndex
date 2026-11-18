<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);$actor=(int)$user['id'];$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','HEAD','POST'],true)){header('Allow: GET, HEAD, POST');abort_request(405,t('account.method'));}
if($method==='POST')require_csrf();
$rawId=$method==='POST'?($_POST['id']??null):($_GET['id']??null);
if(!is_string($rawId)||!preg_match('/\A[1-9][0-9]{0,9}\z/',$rawId))abort_request(404,t('trade.unavailable'));
$id=(int)$rawId;$error='';
if($method==='POST'){
    try{
        $action=$_POST['action']??null;
        if($action==='rate'){
            if(!isset($_POST['stars'])||!is_string($_POST['stars'])||!preg_match('/\A[1-5]\z/',$_POST['stars']))throw new DomainException(t('trade.invalid'));
            if(($_POST['confirm_action']??null)!=='1')throw new DomainException(t('trade.confirm'));
            trade_rate($pdo,$actor,$id,(int)$_POST['stars'],post_string('comment',4000));
        }elseif(is_string($action)&&in_array($action,['dispatch','receive','settle','cancel_request','cancel_accept','cancel_decline','dispute'],true)){
            if(($_POST['confirm_action']??null)!=='1')throw new DomainException(t('trade.confirm'));
            trade_transition($pdo,$actor,$id,$action,$_POST);
        }else throw new DomainException(t('trade.invalid'));
        redirect('/user/trade.php?id='.$id.'&saved=1');
    }catch(MarketAccessException $e){http_response_code(403);$error=$e->getMessage();}
    catch(MarketNotFoundException $e){http_response_code(404);$error=$e->getMessage();}
    catch(MarketConflictException $e){http_response_code(409);$error=$e->getMessage();}
    catch(DomainException|InvalidArgumentException $e){http_response_code(422);$error=$e->getMessage();}
}
try{$trade=trade_get($pdo,$actor,$id);}
catch(MarketAccessException){abort_request(403,t('trade.forbidden'));}
catch(MarketNotFoundException|DomainException){abort_request(404,t('trade.unavailable'));}
$snapshot=$trade['snapshot'];$rated=false;
foreach($trade['ratings'] as $rating){if((int)$rating['sender_id']===$actor)$rated=true;}
$pageTitle=t('trade.transaction',['reference'=>$trade['reference']]);include __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/trade.css">
<main class="trade">
<div class="trade-heading"><div><h1><?=h($pageTitle)?></h1><p id="trade-status" class="trade-status" data-trade-status="<?=h($trade['status'])?>"><?=h(t('trade.status.'.$trade['status']))?></p></div><a href="/user/trades.php"><?=h(t('trade.title'))?></a></div>
<?php if($error!==''):?><p class="trade-error" role="alert"><?=h($error)?></p><?php elseif(isset($_GET['saved'])):?><p class="notice" role="status"><?=h(t('trade.saved'))?></p><?php endif;?>
<div class="trade-grid"><div class="trade-stack"><section class="panel">
<h2><?=h($snapshot['title'])?></h2><p><?=h(t((int)$trade['buyer_id']===$actor?'trade.role.buyer':'trade.role.seller'))?></p>
<p><a href="/marketplace/view-item.php?id=<?=(int)$trade['listing_id']?>"><?=h(t('trade.view_listing'))?></a></p>
<?php if($trade['status']!=='cancelled'): $peer=(int)($actor===(int)$trade['buyer_id']?$trade['seller_id']:$trade['buyer_id']);?>
<p><a href="/user/chat.php?listing_id=<?=(int)$trade['listing_id']?>&amp;user_id=<?=$peer?>"><?=h(t('Nachrichten'))?></a></p><?php endif;?>
<p class="trade-meta"><?=h(t('trade.snapshot_notice'))?></p>
<details><summary><?=h(t('trade.description'))?></summary><div class="trade-text"><?=h($snapshot['description'])?></div></details>
<?php require_once __DIR__.'/../includes/classification-fields.php'; classification_summary($snapshot['attributes']??[]); ?>
<dl class="trade-terms"><dt><?=h(t('trade.price'))?></dt><dd><?=h(money_eur((int)$trade['price_cents']))?></dd><dt><?=h(t('trade.delivery'))?></dt><dd><?=h(t('trade.delivery.'.$trade['delivery']))?></dd><dt><?=h(t('trade.shipping_cost'))?></dt><dd><?=h(money_eur((int)$trade['shipping_cents']))?></dd><dt class="trade-total"><?=h(t('trade.total'))?></dt><dd class="trade-total"><?=h(money_eur((int)$trade['total_cents']))?></dd></dl>
<p class="trade-note"><?=h(t('trade.payment_notice'))?></p>
<dl class="trade-terms"><dt><?=h(t('trade.buyer_confirmation'))?></dt><dd><?=h(t(!empty($trade['buyer_confirmed'])?'trade.confirmed':'trade.pending'))?></dd><dt><?=h(t('trade.seller_confirmation'))?></dt><dd><?=h(t(!empty($trade['seller_confirmed'])?'trade.confirmed':'trade.pending'))?></dd></dl>
<p><?=h(t('trade.completion_notice'))?></p>
</section>
<section class="panel"><h2><?=h(t('trade.history'))?></h2><ol class="trade-history">
<?php foreach($trade['events'] as $event):?><li><strong><?=h(t('trade.action.'.$event['action']))?></strong><time><?=h($event['created_at'])?> UTC</time><span><?=h(t('trade.status.'.$event['to_status']))?></span><?php if(!empty($event['details'])&&is_string($event['details'])):?><p class="trade-text"><?=h($event['details'])?></p><?php endif;?></li><?php endforeach;?>
</ol></section></div>
<div class="trade-stack"><section class="panel"><h2><?=h(t('trade.next_steps'))?></h2>
<?php if(!$trade['actions']):?><p><?=h(t('trade.no_actions'))?></p><?php endif;?>
<?php foreach($trade['actions'] as $action):if(!in_array($action,['dispatch','receive','settle','cancel_request','cancel_accept','cancel_decline','dispute'],true))continue;?>
<details><summary><?=h(t('trade.action.'.$action))?></summary><p><?=h(t('trade.help.'.$action))?></p>
<form method="post" action="/user/trade.php" data-trade-action="<?=h($action)?>">
<?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="version" value="<?=(int)$trade['version']?>"><input type="hidden" name="request_key" value="<?=h(bin2hex(random_bytes(16)))?>">
<?php if(in_array($action,['dispatch','cancel_request','dispute'],true)):?><label><?=h(t($action==='dispatch'?'trade.shipping_details':'trade.reason'))?><textarea name="details" rows="3" maxlength="1000" <?=$action==='dispute'?'required':''?>></textarea></label><p class="trade-meta"><?=h(t('trade.private_details'))?></p><?php endif;?>
<label class="check"><input type="checkbox" name="confirm_action" value="1" required><span><?=h(t('trade.confirm_action'))?></span></label>
<button class="button" type="submit" name="action" value="<?=h($action)?>"><?=h(t('trade.action.'.$action))?></button>
</form></details>
<?php endforeach;?>
</section>
<section class="panel"><h2><?=h(t('trade.ratings'))?></h2>
<?php if(!$trade['ratings']):?><p><?=h(t('trade.no_ratings'))?></p><?php endif;?>
<?php foreach($trade['ratings'] as $rating):?><article class="trade-card"><h3><?=h(t((int)$rating['sender_id']===$actor?'trade.rating.own':'trade.rating.partner'))?></h3><p><?=h(t('trade.stars',['stars'=>(int)$rating['stars']]))?></p><p class="trade-text"><?=h($rating['comment'])?></p></article><?php endforeach;?>
<?php if($trade['status']==='completed'&&!$rated):?>
<form id="trade-rating-form" method="post" action="/user/trade.php"><?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>">
<label><?=h(t('trade.rating_score'))?><select name="stars" required><option value=""><?=h(t('trade.choose'))?></option><?php for($stars=5;$stars>=1;$stars--):?><option value="<?=$stars?>"><?=h(t('trade.stars',['stars'=>$stars]))?></option><?php endfor;?></select></label>
<label><?=h(t('trade.rating_comment'))?><textarea name="comment" rows="3" maxlength="1000"></textarea></label><p><?=h(t('trade.rating_notice'))?></p>
<label class="check"><input type="checkbox" name="confirm_action" value="1" required><span><?=h(t('trade.confirm_rating'))?></span></label><button type="submit" class="button" name="action" value="rate"><?=h(t('trade.rate'))?></button>
</form>
<?php elseif($trade['status']!=='completed'):?><p><?=h(t('trade.rating_wait'))?></p><?php endif;?>
</section></div></div></main>
<?php include __DIR__.'/../includes/footer.php'; ?>
