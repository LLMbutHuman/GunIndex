<?php
declare(strict_types=1);
function chat_message_plain(string $stored,int $version=0):string {
 if($version===0)return$stored;if($version!==1)throw new RuntimeException('Unsupported message encryption version.');return crypto_decrypt('chat',$stored);
}
function chat_blocked(PDO $pdo,int $a,int $b):bool {
 $q=$pdo->prepare('SELECT 1 FROM market_user_blocks WHERE (user_id=? AND blocked_id=?) OR (user_id=? AND blocked_id=?) LIMIT 1');$q->execute([$a,$b,$b,$a]);return(bool)$q->fetchColumn();
}
function chat_context(PDO $pdo,int $user,int $listing,int $peer):array {
 if($listing<1||$peer<1||$user===$peer)throw new MarketNotFoundException('Unterhaltung nicht gefunden.');
 $q=$pdo->prepare('SELECT l.*,u.is_active AS seller_active FROM market_listings l JOIN users u ON u.id=l.seller_id WHERE l.id=?');$q->execute([$listing]);$item=$q->fetch();if(!$item)throw new MarketNotFoundException('Angebot nicht gefunden.');
 $seller=(int)$item['seller_id'];if($user!==$seller&&$peer!==$seller)throw new MarketAccessException('Diese Unterhaltung ist privat.');
 $q=$pdo->prepare('SELECT id,username,is_active,preferred_locale FROM users WHERE id=?');$q->execute([$peer]);$person=$q->fetch();if(!$person)throw new MarketNotFoundException('Unterhaltung nicht gefunden.');
 $pair=[$listing,$user,$peer,$peer,$user];$where='listing_id=? AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))';
 $q=$pdo->prepare('SELECT 1 FROM market_messages WHERE '.$where.' LIMIT 1');$q->execute($pair);$exists=(bool)$q->fetchColumn();
 $public=($item['seller_active']==1)&&catalog_category_allowed($pdo,(int)$item['category_id'])&&geo_country_enabled($pdo,$item['country_code']);
 $winner=$item['type']==='auction'&&in_array($item['status'],['ended','sold'],true)&&(int)($item['highest_bidder_id']??0)>0&&in_array((int)$item['highest_bidder_id'],[$user,$peer],true);
 if(!$exists&&!($public&&($winner||($item['status']==='active'&&$user!==$seller))))throw new MarketAccessException('Für dieses Angebot kann keine neue Unterhaltung begonnen werden.');
 $blocked=chat_blocked($pdo,$user,$peer);$q=$pdo->prepare('SELECT 1 FROM market_user_blocks WHERE user_id=? AND blocked_id=?');$q->execute([$user,$peer]);
 return ['item'=>$item,'peer'=>$person,'pair'=>$pair,'where'=>$where,'existing'=>$exists,'blocked'=>$blocked,'own_block'=>(bool)$q->fetchColumn(),'can_send'=>$public&&in_array($item['status'],['active','ended','sold'],true)&&(bool)$person['is_active']&&!$blocked];
}
function chat_send(PDO $pdo,int $user,int $listing,int $peer,string $text):int {
 if(!preg_match('//u',$text)||mb_strlen(trim($text))<1||mb_strlen($text)>5000)throw new InvalidArgumentException('Nachrichten benötigen 1 bis 5.000 gültige Zeichen.');
 $pdo->beginTransaction();try{
  $q=$pdo->prepare('SELECT id FROM market_listings WHERE id=? FOR UPDATE');$q->execute([$listing]);if(!$q->fetchColumn())throw new MarketNotFoundException('Angebot nicht gefunden.');
  $ids=[$user,$peer];sort($ids,SORT_NUMERIC);$q=$pdo->prepare('SELECT id,is_active FROM users WHERE id IN (?,?) ORDER BY id FOR UPDATE');$q->execute($ids);$people=$q->fetchAll();if(count($people)!==2||array_filter($people,static fn($p)=>(int)$p['is_active']!==1))throw new MarketAccessException('Dieses Konto nimmt keine Nachrichten an.');
  $ctx=chat_context($pdo,$user,$listing,$peer);if(!$ctx['can_send'])throw new MarketAccessException('Diese Unterhaltung nimmt aktuell keine neuen Nachrichten an.');
  if(!market_category_locked_allowed($pdo,(int)$ctx['item']['category_id'])||!geo_country_locked_enabled($pdo,$ctx['item']['country_code']))throw new MarketAccessException('Dieses Angebot ist aktuell gesperrt.');
  $q=$pdo->prepare('SELECT COUNT(*) FROM market_messages WHERE sender_id=? AND created_at>UTC_TIMESTAMP()-INTERVAL 1 MINUTE');$q->execute([$user]);if((int)$q->fetchColumn()>=20)throw new OverflowException('Bitte warte kurz vor der nächsten Nachricht.');
  $pdo->prepare('INSERT INTO market_messages(listing_id,sender_id,receiver_id,message_text,cipher_version) VALUES (?,?,?,?,1)')->execute([$listing,$user,$peer,crypto_encrypt('chat',trim($text))]);$id=(int)$pdo->lastInsertId();
  $seller=(int)$ctx['item']['seller_id'];$buyer=$user===$seller?$peer:$user;
  $pdo->prepare('INSERT INTO market_conversations(listing_id,seller_id,buyer_id,last_message_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE last_message_id=GREATEST(last_message_id,VALUES(last_message_id)),updated_at=UTC_TIMESTAMP()')->execute([$listing,$seller,$buyer,$id]);
  if(function_exists('notify_user'))notify_user($pdo,$peer,'chat:'.$id,i18n_user_text($pdo,$peer,'notification.new_chat.title'),i18n_user_text($pdo,$peer,'notification.new_chat.body',['listing'=>$ctx['item']['title']]),'/user/chat.php?listing_id='.$listing.'&user_id='.$user,true);
  $pdo->commit();return$id;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
function chat_set_block(PDO $pdo,int $user,int $listing,int $peer,bool $blocked):void {
 chat_context($pdo,$user,$listing,$peer);$pdo->beginTransaction();try{
  $ids=[$user,$peer];sort($ids,SORT_NUMERIC);$q=$pdo->prepare('SELECT id FROM users WHERE id IN (?,?) ORDER BY id FOR UPDATE');$q->execute($ids);$q->fetchAll();
  if($blocked)$pdo->prepare('INSERT IGNORE INTO market_user_blocks(user_id,blocked_id) VALUES (?,?)')->execute([$user,$peer]);
  else $pdo->prepare('DELETE FROM market_user_blocks WHERE user_id=? AND blocked_id=?')->execute([$user,$peer]);
  audit_log($pdo,$user,$blocked?'chat.user_blocked':'chat.user_unblocked',$peer,[]);$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
function chat_history(PDO $pdo,array $context,int $before=0,int $after=0,int $limit=50):array {
 $limit=max(1,min(50,$limit));$sql='SELECT id,sender_id,receiver_id,message_text,cipher_version,is_read,created_at FROM market_messages WHERE '.$context['where'];$args=$context['pair'];
 if($before>0){$sql.=' AND id<?';$args[]=$before;}if($after>0){$sql.=' AND id>?';$args[]=$after;}
 $sql.=' ORDER BY id '.($after>0?'ASC':'DESC').' LIMIT '.$limit;$q=$pdo->prepare($sql);$q->execute($args);$rows=$q->fetchAll();if($after===0)$rows=array_reverse($rows);
 foreach($rows as &$row){$row['message_text']=chat_message_plain($row['message_text'],(int)$row['cipher_version']);unset($row['cipher_version']);}unset($row);return$rows;
}
