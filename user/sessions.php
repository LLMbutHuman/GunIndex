<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','HEAD','POST'],true)) { header('Allow: GET, HEAD, POST'); abort_request(405,t('account.method')); }
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
$owner=(int)$user['id'];
$hash=auth_session_key($_SESSION['auth_session_key']??null);
if ($hash===null) redirect('/auth/login.php');
if ($method==='POST') {
    require_csrf();
    $target=filter_var($_POST['session']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!$target||($_POST['action']??null)!=='revoke') abort_request(422,t('sessions.unavailable'));
    $current=auth_session_revoke($pdo,$owner,$target,$hash);
    if ($current===null) abort_request(404,t('sessions.unavailable'));
    if ($current) { auth_clear_session(); redirect('/auth/login.php'); }
    redirect('/user/sessions.php?saved=1');
}
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]]);
if (!$page) abort_request(400,t('sessions.unavailable'));
$sessions=auth_session_list($pdo,$owner,(int)$user['auth_version'],$hash,$page);
$pageTitle=t('sessions.title');
include __DIR__.'/../includes/header.php';
?>
<main class="page"><div class="page-heading"><h1><?=h($pageTitle)?></h1><p><?=h(t('sessions.help'))?></p>
<a href="/user/dashboard.php"><?=h(t('Zum Dashboard'))?></a><?php if(!auth_requires_mfa($pdo,$user)):?> · <a href="/user/security.php"><?=h(t('user_security.title'))?></a><?php endif;?></div>
<?php if(($_GET['saved']??null)==='1'):?><p role="status" class="notice success"><?=h(t('sessions.revoked'))?></p><?php endif;?>
<?php if(!$sessions):?><p><?=h(t('sessions.empty'))?></p><?php endif;?>
<?php foreach($sessions as $session):?>
<section class="panel" data-session-id="<?=(int)$session['id']?>" <?=$session['current_session']?'data-current-session':''?>>
<h2><?=h($session['browser']==='unknown'?t('sessions.unknown'):$session['browser'])?> · <?=h($session['platform']==='unknown'?t('sessions.unknown'):$session['platform'])?></h2>
<?php if($session['current_session']):?><p><strong><?=h(t('sessions.current'))?></strong></p><?php endif;?>
<dl class="detail-facts"><div><dt><?=h(t('sessions.started'))?></dt><dd><?=h($session['started_at'])?> UTC</dd></div>
<div><dt><?=h(t('sessions.last_seen'))?></dt><dd><?=h($session['last_seen_at'])?> UTC</dd></div></dl>
<form method="post"><?=csrf_field()?><input type="hidden" name="session" value="<?=(int)$session['id']?>">
<button class="button secondary" type="submit" name="action" value="revoke"><?=h(t($session['current_session']?'sessions.sign_out':'sessions.revoke'))?></button></form>
</section>
<?php endforeach;?>
<nav class="button-row" aria-label="<?=h(t('sessions.title'))?>">
<?php if($page>1):?><a class="button secondary" href="?page=<?=$page-1?>"><?=h(t('Zurück'))?></a><?php endif;?>
<?php if(count($sessions)===25&&$page<10000):?><a class="button secondary" href="?page=<?=$page+1?>"><?=h(t('Weiter'))?></a><?php endif;?>
</nav>
</main><?php include __DIR__.'/../includes/footer.php'; ?>
