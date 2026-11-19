<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);
if (auth_requires_mfa($pdo,$user)) abort_request(404,t('user_security.unavailable'));
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','HEAD','POST'],true)) { header('Allow: GET, HEAD, POST'); abort_request(405,t('account.method')); }
$error='';
if ($method==='POST') {
    require_csrf();
    try {
        $id=filter_var($_POST['passkey']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) throw new InvalidArgumentException(t('user_security.unavailable'));
        passkey_remove($pdo,$user,$id,post_string('password',1024),post_string('factor',64));
        redirect('/user/passkeys.php');
    } catch (InvalidArgumentException|DomainException $e) { $error=$e->getMessage();http_response_code(422); }
}
$config=passkey_config();
$factor=user_totp_state($pdo,(int)$user['id'])!==null;
$q=$pdo->prepare('SELECT id,label,rp_id,created_at,last_used_at FROM market_passkeys WHERE user_id=? ORDER BY id');
$q->execute([$user['id']]);$rows=$q->fetchAll();
$pageTitle=t('user_security.passkeys');
include __DIR__.'/../includes/header.php';
?>
<main class="page"><div class="page-heading"><h1><?=h($pageTitle)?></h1>
<a href="/user/security.php"><?=h(t('user_security.title'))?></a></div>
<?php if($error!==''):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?>
<section class="panel"><h2><?=h(t('user_security.passkey_add'))?></h2>
<?php if($config):?>
<script src="/assets/js/passkeys.js" defer></script>
<form method="post" action="/auth/passkey.php" class="form-grid" data-passkey="add" data-origin="<?=h($config['origin'])?>" data-error="<?=h(t('user_security.passkey_failed'))?>">
<?=csrf_field()?>
<label class="field"><?=h(t('user_security.passkey_label'))?><input name="label" maxlength="80" required></label>
<label class="field"><?=h(t('Aktuelles Passwort'))?><input name="password" type="password" autocomplete="current-password" maxlength="1024" required></label>
<?php if($factor):?><label class="field"><?=h(t('user_security.factor'))?><input name="factor" maxlength="64" autocomplete="one-time-code" required></label><?php endif;?>
<button class="button" type="button" disabled data-passkey-start><?=h(t('user_security.passkey_add'))?></button>
<p data-passkey-error role="status"></p></form>
<?php else:?><p><?=h(t('user_security.passkey_origin'))?></p><?php endif;?></section>
<?php foreach($rows as $key):?>
<section class="panel"><h2><?=h($key['label'])?></h2>
<p><?=h($key['rp_id'].' · '.$key['created_at'])?> UTC</p>
<?php if($key['last_used_at']!==null):?><p><?=h(t('sessions.last_seen').': '.$key['last_used_at'])?> UTC</p><?php endif;?>
<form method="post" class="form-grid"><?=csrf_field()?>
<input type="hidden" name="passkey" value="<?=(int)$key['id']?>">
<label class="field"><?=h(t('Aktuelles Passwort'))?><input name="password" type="password" autocomplete="current-password" maxlength="1024" required></label>
<?php if($factor):?><label class="field"><?=h(t('user_security.factor'))?><input name="factor" maxlength="64" autocomplete="one-time-code" required></label><?php endif;?>
<button class="button secondary" type="submit"><?=h(t('user_security.passkey_remove'))?></button></form>
</section><?php endforeach; ?>
</main><?php include __DIR__.'/../includes/footer.php'; ?>
