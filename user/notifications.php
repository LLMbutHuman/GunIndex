<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';$user=require_login($pdo);
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    require_csrf();$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$id)abort_request(422,t('account.notification_invalid'));
    $pdo->prepare('UPDATE market_notifications SET read_at=COALESCE(read_at,UTC_TIMESTAMP()) WHERE id=? AND user_id=? AND is_visible=1')->execute([$id,$user['id']]);
    redirect('/user/notifications.php');
}
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]])?:1;
$q=$pdo->prepare('SELECT COUNT(*) FROM market_notifications WHERE user_id=? AND is_visible=1');$q->execute([$user['id']]);$total=(int)$q->fetchColumn();$page=min($page,max(1,(int)ceil($total/25)));
$q=$pdo->prepare('SELECT id,title,body,target_url,read_at,created_at FROM market_notifications WHERE user_id=? AND is_visible=1 ORDER BY id DESC LIMIT 25 OFFSET ?');$q->bindValue(1,(int)$user['id'],PDO::PARAM_INT);$q->bindValue(2,($page-1)*25,PDO::PARAM_INT);$q->execute();$items=$q->fetchAll();
$pageTitle=t('Benachrichtigungen');include __DIR__.'/../includes/header.php';?>
<main class="container"><div class="page-heading"><h1><?=h(t('account.notifications_heading'))?></h1><a class="button secondary" href="/user/notification-settings.php"><?=h(t('Benachrichtigungen einstellen'))?></a></div>
<?php if(!$items):?><section class="panel"><p><?=h(t('account.notifications_empty'))?></p></section><?php endif;?>
<?php foreach($items as $item):?><article class="panel"><p class="eyebrow"><?=h(t($item['read_at']===null?'account.notification_new':'account.notification_read'))?> · <?=h($item['created_at'])?> UTC</p><h2><?=h($item['title'])?></h2><p><?=nl2br(h($item['body']))?></p><?php if($item['target_url']!==null):?><a class="button secondary" href="<?=h($item['target_url'])?>"><?=h(t('account.notification_view'))?></a><?php endif;?><?php if($item['read_at']===null):?><form method="post"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$item['id']?>"><button class="button secondary" type="submit"><?=h(t('account.notification_mark_read'))?></button></form><?php endif;?></article><?php endforeach;?>
<nav class="pagination" aria-label="<?=h(t('account.notification_pages'))?>"><?php if($page>1):?><a href="?page=<?=$page-1?>"><?=h(t('Zurück'))?></a><?php endif;?><span><?=h(t('Seite {page} von {pages}',['page'=>$page,'pages'=>max(1,(int)ceil($total/25))]))?></span><?php if($page*25<$total):?><a href="?page=<?=$page+1?>"><?=h(t('Weiter'))?></a><?php endif;?></nav></main>
<?php include __DIR__.'/../includes/footer.php'; ?>
