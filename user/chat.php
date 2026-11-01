<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';$user=require_login($pdo);$uid=(int)$user['id'];
$id=is_string($_GET['listing_id']??null)?filter_var($_GET['listing_id'],FILTER_VALIDATE_INT):false;$peer=is_string($_GET['user_id']??null)?filter_var($_GET['user_id'],FILTER_VALIDATE_INT):false;
if(!$id||!$peer)abort_request(404,'Unterhaltung nicht gefunden.');
try{$context=chat_context($pdo,$uid,(int)$id,(int)$peer);}catch(MarketNotFoundException $e){abort_request(404,$e->getMessage());}catch(MarketAccessException $e){abort_request(403,$e->getMessage());}
$error='';$text='';$url='/user/chat.php?listing_id='.(int)$id.'&user_id='.(int)$peer;
if($_SERVER['REQUEST_METHOD']==='POST'){
 require_csrf();try{
  $action=post_string('action');if($action==='send'){$text=post_string('message',25000);chat_send($pdo,$uid,(int)$id,(int)$peer,$text);}
  elseif($action==='read'){$pdo->prepare('UPDATE market_messages SET is_read=1 WHERE listing_id=? AND sender_id=? AND receiver_id=?')->execute([$id,$peer,$uid]);}
  elseif(in_array($action,['block','unblock'],true))chat_set_block($pdo,$uid,(int)$id,(int)$peer,$action==='block');
  else throw new InvalidArgumentException('Ungültige Aktion.');redirect($url);
 }catch(InvalidArgumentException $e){http_response_code(422);$error=$e->getMessage();}catch(MarketAccessException $e){http_response_code(403);$error=$e->getMessage();}catch(MarketConflictException $e){http_response_code(409);$error=$e->getMessage();}catch(OverflowException $e){http_response_code(429);header('Retry-After: 60');$error=$e->getMessage();}
}
$before=is_string($_GET['before']??null)?(filter_var($_GET['before'],FILTER_VALIDATE_INT)?:0):0;
$messages=chat_history($pdo,$context,(int)$before);$latest=$messages?(int)end($messages)['id']:0;$oldest=$messages?(int)$messages[0]['id']:0;$pageTitle='Nachricht an '.$context['peer']['username'];
include __DIR__.'/../includes/header.php';?>
<main class="container"><div class="page-heading"><p class="eyebrow">Privater Kontakt</p><h1><?=h($context['peer']['username'])?></h1><p><?=h($context['item']['title'])?></p><a href="/user/messages.php">Alle Nachrichten</a></div>
<?php if($error):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?>
<section class="panel" aria-label="Nachrichtenverlauf"><div data-chat-messages data-chat-endpoint="<?=h('/user/chat-updates.php?listing_id='.(int)$id.'&user_id='.(int)$peer)?>" data-chat-last="<?=$latest?>" data-chat-self="<?=$uid?>" aria-live="polite">
<?php if(!$messages):?><p>Noch keine Nachrichten.</p><?php endif;?><?php foreach($messages as $m):?><article class="panel" data-chat-message="<?=(int)$m['id']?>"><strong><?=(int)$m['sender_id']===$uid?'Du':h($context['peer']['username'])?></strong><p style="white-space:pre-wrap;overflow-wrap:anywhere"><?=h($m['message_text'])?></p><small><?=h($m['created_at'])?> UTC</small><br><a href="/marketplace/report.php?type=message&amp;id=<?=(int)$m['id']?>">Nachricht melden</a></article><?php endforeach;?></div>
<?php if(count($messages)===50):?><a class="button secondary" href="<?=h($url.'&before='.$oldest)?>">Ältere Nachrichten</a><?php endif;?>
<?php if($messages):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="read"><button class="button secondary" type="submit">Unterhaltung als gelesen markieren</button></form><?php endif;?></section>
<section class="panel"><h2>Deine Nachricht</h2><?php if($context['can_send']):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="send"><label class="field">Nachricht<textarea name="message" rows="5" maxlength="5000" required><?=h($text)?></textarea></label><button class="button" type="submit">Nachricht senden</button></form><?php else:?><p class="notice">Für diese Unterhaltung sind neue Nachrichten aktuell gesperrt. Dein eigener bisheriger Verlauf bleibt lesbar.</p><?php endif;?>
<p>Nachrichten werden verschlüsselt gespeichert. Dies ist keine Ende-zu-Ende-Verschlüsselung.</p>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="<?=$context['own_block']?'unblock':'block'?>"><button class="button secondary" type="submit"><?=$context['own_block']?'Eigene Blockierung aufheben':'Benutzer blockieren'?></button></form></section></main>
<script src="/assets/js/chat.js" defer></script><?php include __DIR__.'/../includes/footer.php';?>
