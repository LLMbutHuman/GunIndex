<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('DB_DATABASE')!=='gunindex_rebuild_test'||getenv('MAIL_TRANSPORT')!=='test') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='gunindex_rebuild_test') exit(2);

function alert_check(bool $valid,string $failure): void { if(!$valid) throw new RuntimeException($failure); }
function alert_row(PDO $pdo,string $sql,array $parameters): array
{
    $q=$pdo->prepare($sql);$q->execute($parameters);return $q->fetch()?:[];
}
function alert_drain(PDO $pdo,int $recipients=100): void
{
    for($i=0;$i<20;$i++)if(listing_alert_deliver($pdo,100,$recipients)===0)return;
    throw new RuntimeException('Unexpected outbox backlog in isolated fixture.');
}

$tag=bin2hex(random_bytes(8));
$users=[];
foreach(['seller','first','second','muted','late'] as $role){
    $username='alert_'.$role.'_'.$tag;
    $q=$pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code,preferred_locale,email_verified_at)"
        ." VALUES (?,?,?,'Synthetic alert test','10115','Berlin','DE',? ,UTC_TIMESTAMP())");
    $q->execute([$username,$username.'@example.invalid',auth_hash('Synthetic sample '.bin2hex(random_bytes(12))),$role==='first'?'en':'de']);
    $users[$role]=(int)$pdo->lastInsertId();
}
$pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')->execute(['Synthetic desks '.$tag,'alert-desks-'.$tag]);
$category=(int)$pdo->lastInsertId();$seller=$users['seller'];
$values=['title'=>'Synthetic watched desk '.$tag,'description'=>'A synthetic desk for expiry and price change notifications.','category_id'=>(string)$category,
    'type'=>'fixed','status'=>'active','price'=>'30.00','zip'=>'10115','city'=>'Berlin','country_code'=>'DE','condition_label'=>'used','pickup'=>'1','accept_rules'=>'1'];
$id=market_save_listing($pdo,$seller,$values);
$watch=$pdo->prepare('INSERT INTO market_watchlist(user_id,listing_id,created_at) VALUES (?,?,?)');
foreach(['first','second','muted'] as $role)$watch->execute([$users[$role],$id,gmdate('Y-m-d H:i:s',time()-3600)]);
$pdo->prepare('INSERT INTO market_notification_preferences(user_id,inapp_enabled,mail_enabled,listing_alerts_enabled) VALUES (?,1,1,1),(?,1,1,0)')
    ->execute([$users['first'],$users['muted']]);
market_save_listing($pdo,$seller,array_replace($values,['price'=>'25.00']),[],$id);
$latest=array_replace($values,['price'=>'20.00']);
market_save_listing($pdo,$seller,$latest,[],$id);
market_save_listing($pdo,$seller,$latest,[],$id);
$watch->execute([$users['late'],$id,gmdate('Y-m-d H:i:s',time()+60)]);
$price=alert_row($pdo,"SELECT * FROM market_listing_notice_events WHERE listing_id=? AND kind='price' ORDER BY id DESC LIMIT 1",[$id]);
alert_check((int)$price['old_price']===2500&&(int)$price['new_price']===2000,'Price precision or unchanged-save deduplication failed.');
listing_alert_deliver($pdo,100,1);
$after=alert_row($pdo,'SELECT * FROM market_listing_notice_events WHERE id=?',[$price['id']]);
alert_check((int)$after['last_user_id']===$users['first']&&$after['finished_at']===null,'Work did not advance through one recipient.');
alert_drain($pdo,1);
$key='listing-alert:'.$price['id'];
$notice=alert_row($pdo,'SELECT title,body FROM market_notifications WHERE user_id=? AND event_key=?',[$users['first'],$key]);
alert_check(($notice['title']??null)===i18n_dictionary('en')['listing_alert.price.title']&&str_contains($notice['body'],'20,00'),'Wrong language or amount in enqueued price notice.');
foreach(['seller','muted','late'] as $role)alert_check(!alert_row($pdo,'SELECT id FROM market_notifications WHERE user_id=? AND event_key=?',[$users[$role],$key]),'Price notice delivered outside the subscribed recipient set.');
alert_check((bool)alert_row($pdo,'SELECT id FROM market_notifications WHERE user_id=? AND event_key=?',[$users['second'],$key]),'Resumed recipient was skipped.');
$count=alert_row($pdo,"SELECT COUNT(*) AS total FROM market_notifications WHERE user_id=? AND event_key LIKE 'listing-alert:%'",[$users['first']]);
alert_check((int)$count['total']===1,'Superseded or repeated edit generated a duplicate notice.');

$dedupe='notification:'.$users['first'].':'.$key;
alert_check((alert_row($pdo,'SELECT status FROM market_mail_queue WHERE dedupe_key=?',[$dedupe])['status']??null)==='pending','Verified user email was not queued.');
$pdo->prepare('UPDATE market_notification_preferences SET listing_alerts_enabled=0 WHERE user_id=?')->execute([$users['first']]);
mail_worker_once($pdo,100);
$mail=alert_row($pdo,'SELECT status,payload_ciphertext FROM market_mail_queue WHERE dedupe_key=?',[$dedupe]);
alert_check($mail['status']==='cancelled'&&$mail['payload_ciphertext']==='','An opt-out after enqueue still handed email to the transport.');

$pdo->prepare('UPDATE market_listings SET ends_at=UTC_TIMESTAMP()+INTERVAL 2 HOUR WHERE id=?')->execute([$id]);
listing_alert_due($pdo,1000);listing_alert_due($pdo,1000);alert_drain($pdo);
$ending=alert_row($pdo,"SELECT id,deadline FROM market_listing_notice_events WHERE listing_id=? AND kind='ending'",[$id]);
alert_check((bool)alert_row($pdo,'SELECT id FROM market_notifications WHERE user_id=? AND event_key=?',[$seller,'listing-alert:'.$ending['id']]),'Seller deadline warning missing.');
$pdo->prepare('UPDATE market_listings SET ends_at=ends_at+INTERVAL 2 MINUTE WHERE id=?')->execute([$id]);
listing_alert_due($pdo,1000);
alert_check((int)alert_row($pdo,"SELECT COUNT(*) AS total FROM market_listing_notice_events WHERE listing_id=? AND kind='ending'",[$id])['total']===1,'Minor auction extension generated another warning.');
$pdo->prepare('UPDATE market_listings SET ends_at=UTC_TIMESTAMP()-INTERVAL 1 MINUTE WHERE id=?')->execute([$id]);
trade_close_due($pdo,1000);alert_drain($pdo);
$ended=alert_row($pdo,"SELECT id FROM market_listing_notice_events WHERE listing_id=? AND kind='ended'",[$id]);
alert_check($ended&&alert_row($pdo,'SELECT id FROM market_notifications WHERE user_id=? AND event_key=?',[$users['second'],'listing-alert:'.$ended['id']]),'A watched listing ended without a notice.');
echo "Listing alert database checks passed: price, preferences, queue cancellation, deadline and replay.\n";
