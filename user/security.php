<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);
if (auth_requires_mfa($pdo,$user)) abort_request(404,t('user_security.unavailable'));
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','HEAD','POST'],true)) { header('Allow: GET, HEAD, POST'); abort_request(405,t('account.method')); }
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
$error='';$codes=[];
if ($method==='POST') {
    require_csrf();
    try {
        $action=post_string('action',16);
        if ($action==='start') {
            user_totp_begin($pdo,$user,post_string('password',1024));
            redirect('/user/security.php');
        }
        if ($action==='cancel') { unset($_SESSION['user_totp_setup']); redirect('/user/security.php'); }
        $codes=user_totp_change($pdo,$user,$action,post_string('password',1024),post_string('factor',64));
        if (!$codes) redirect('/user/security.php');
    } catch (InvalidArgumentException|DomainException $e) { $error=$e->getMessage();http_response_code(422); }
}
$enabled=user_totp_state($pdo,(int)$user['id'])!==null;
$pending=$enabled?null:user_totp_pending($user);
$manualKey=null;
if ($pending) {
    $secret=crypto_decrypt('user_totp',$pending['cipher']);
    try { $manualKey=auth_base32($secret); } finally { sodium_memzero($secret); }
}
$pageTitle=t('user_security.title');
include __DIR__.'/../includes/header.php';
?>
<main class="page"><div class="page-heading"><h1><?=h($pageTitle)?></h1>
<a href="/user/sessions.php"><?=h(t('sessions.title'))?></a> · <a href="/user/passkeys.php"><?=h(t('user_security.passkeys'))?></a></div>
<?php if($error!==''):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?>
<?php if($codes):?><section class="panel" id="user-recovery"><h2><?=h(t('user_security.recovery'))?></h2>
<p><?=h(t('user_security.recovery_help'))?></p><ul><?php foreach($codes as $code):?><li><code><?=h($code)?></code></li><?php endforeach;?></ul>
<a href="/user/security.php"><?=h(t('Weiter'))?></a></section><?php endif;?>
<section class="panel"><h2><?=h(t('user_security.totp'))?></h2>
<?php if($enabled):?>
<p><?=h(t('user_security.enabled'))?></p>
<form method="post" class="form-grid"><?=csrf_field()?>
<label class="field"><?=h(t('Aktuelles Passwort'))?><input name="password" type="password" autocomplete="current-password" maxlength="1024" required></label>
<label class="field"><?=h(t('user_security.factor'))?><input name="factor" autocomplete="one-time-code" maxlength="64" required></label>
<div class="button-row"><button class="button secondary" name="action" value="rotate"><?=h(t('user_security.rotate'))?></button>
<button class="button secondary" name="action" value="disable"><?=h(t('user_security.disable'))?></button></div></form>
<?php elseif($manualKey!==null):?>
<p><?=h(t('user_security.setup_help'))?></p><p><code id="user-totp-key"><?=h($manualKey)?></code></p>
<form method="post" class="form-grid"><?=csrf_field()?>
<label class="field"><?=h(t('account.mfa_code'))?><input name="factor" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required></label>
<button class="button" name="action" value="enable"><?=h(t('user_security.enable'))?></button>
<button class="button secondary" name="action" value="cancel" formnovalidate><?=h(t('Abbrechen'))?></button></form>
<?php else:?>
<p><?=h(t('user_security.off'))?></p>
<form method="post" class="form-grid"><?=csrf_field()?>
<label class="field"><?=h(t('Aktuelles Passwort'))?><input name="password" type="password" autocomplete="current-password" maxlength="1024" required></label>
<button class="button" name="action" value="start"><?=h(t('user_security.begin'))?></button></form>
<?php endif;?></section></main>
<?php include __DIR__.'/../includes/footer.php'; ?>
