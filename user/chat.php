<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';$user=require_login($pdo);$uid=(int)$user['id'];
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$id=is_string($_GET['listing_id']??null)?filter_var($_GET['listing_id'],FILTER_VALIDATE_INT):false;$peer=is_string($_GET['user_id']??null)?filter_var($_GET['user_id'],FILTER_VALIDATE_INT):false;
if(!$id||!$peer)abort_request(404,t('e2ee.legacy_not_found'));
try{$context=chat_context($pdo,$uid,(int)$id,(int)$peer);}catch(MarketNotFoundException $e){abort_request(404,$e->getMessage());}catch(MarketAccessException $e){abort_request(403,$e->getMessage());}
$error='';$text='';$url='/user/chat.php?listing_id='.(int)$id.'&user_id='.(int)$peer;
if($_SERVER['REQUEST_METHOD']==='POST'){
 require_csrf();try{
  $action=post_string('action');if($action==='send'){$text=post_string('message',25000);chat_send($pdo,$uid,(int)$id,(int)$peer,$text);}
  elseif($action==='read'){$pdo->prepare('UPDATE market_messages SET is_read=1 WHERE listing_id=? AND sender_id=? AND receiver_id=? AND cipher_version IN (0,1)')->execute([$id,$peer,$uid]);}
  elseif(in_array($action,['block','unblock'],true))chat_set_block($pdo,$uid,(int)$id,(int)$peer,$action==='block');
  else throw new InvalidArgumentException(t('e2ee.legacy_action_invalid'));redirect($url);
 }catch(InvalidArgumentException $e){http_response_code(422);$error=$e->getMessage();}catch(MarketAccessException $e){http_response_code(403);$error=$e->getMessage();}catch(MarketConflictException $e){http_response_code(409);$error=$e->getMessage();}catch(OverflowException $e){http_response_code(429);header('Retry-After: 60');$error=$e->getMessage();}
}
$before=is_string($_GET['before']??null)?(filter_var($_GET['before'],FILTER_VALIDATE_INT)?:0):0;
$legacyAllowed=e2ee_legacy_allowed($pdo,$uid,(int)$peer);
$messages=chat_history($pdo,$context,(int)$before);$latest=$messages?(int)end($messages)['id']:0;$oldest=$messages?(int)$messages[0]['id']:0;$pageTitle=t('e2ee.legacy_message_to',['name'=>$context['peer']['username']]);
include __DIR__.'/../includes/header.php';?>
<main class="container"><div class="page-heading"><h1><?=h($context['peer']['username'])?></h1><p><?=h($context['item']['title'])?></p><a href="/user/messages.php"><?=h(t('e2ee.legacy_inbox_title'))?></a></div>
<?php if($error):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?>
<?php include __DIR__.'/../includes/escrow-chat.php'; ?>
<?php if(e2ee_enabled()||!$legacyAllowed):?><section class="panel"><a class="button" href="/user/secure-chat.php?listing_id=<?=(int)$id?>&amp;user_id=<?=(int)$peer?>"><?=h(t('e2ee.title'))?></a><p><?=h(t($legacyAllowed?'e2ee.intro':'e2ee.legacy_blocked'))?></p></section><?php endif;?>
<section class="panel" aria-label="<?=h(t('e2ee.legacy_history'))?>"><div data-chat-messages data-chat-endpoint="<?=h('/user/chat-updates.php?listing_id='.(int)$id.'&user_id='.(int)$peer)?>" data-chat-last="<?=$latest?>" data-chat-self="<?=$uid?>" data-chat-self-label="<?=h(t('e2ee.legacy_you'))?>" data-chat-peer-label="<?=h($context['peer']['username'])?>" aria-live="polite">
<?php if(!$messages):?><p><?=h(t('e2ee.legacy_empty'))?></p><?php endif;?><?php foreach($messages as $m):?><article class="panel" data-chat-message="<?=(int)$m['id']?>"><strong><?=h((int)$m['sender_id']===$uid?t('e2ee.legacy_you'):$context['peer']['username'])?></strong><p style="white-space:pre-wrap;overflow-wrap:anywhere"><?=h($m['message_text'])?></p><small><?=h($m['created_at'])?> UTC</small><br><a href="/marketplace/report.php?type=message&amp;id=<?=(int)$m['id']?>"><?=h(t('e2ee.legacy_report'))?></a></article><?php endforeach;?></div>
<?php if(count($messages)===50):?><a class="button secondary" href="<?=h($url.'&before='.$oldest)?>"><?=h(t('e2ee.legacy_older'))?></a><?php endif;?>
<?php if($messages):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="read"><button class="button secondary" type="submit"><?=h(t('e2ee.legacy_mark_read'))?></button></form><?php endif;?></section>
<section class="panel"><h2><?=h(t('e2ee.legacy_compose'))?></h2><?php if($context['can_send']&&$legacyAllowed):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="send"><label class="field"><?=h(t('e2ee.legacy_message'))?><textarea name="message" rows="5" maxlength="5000" required><?=h($text)?></textarea></label><button class="button" type="submit"><?=h(t('e2ee.legacy_send'))?></button></form><?php else:?><p class="notice"><?=h(t('e2ee.legacy_send_paused'))?></p><?php endif;?>
<p><?=h(t('e2ee.legacy_storage'))?></p>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="<?=$context['own_block']?'unblock':'block'?>"><button class="button secondary" type="submit"><?=h(t($context['own_block']?'e2ee.legacy_unblock':'e2ee.legacy_block'))?></button></form></section></main>
<script src="/assets/js/chat.js" defer></script><?php include __DIR__.'/../includes/footer.php';?>
