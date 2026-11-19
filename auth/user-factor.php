<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
if (current_user($pdo)) redirect('/user/dashboard.php');
if (!is_array($_SESSION['pending_user_factor']??null)||$_SESSION['pending_user_factor']['until']<=time()) redirect('/auth/login.php');
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','HEAD','POST'],true)) { header('Allow: GET, HEAD, POST'); abort_request(405,t('account.method')); }
header('Cache-Control: private, no-store');
$error='';
if ($method==='POST') {
    require_csrf();
    try {
        user_security_complete($pdo,post_string('factor',64));
        redirect('/user/dashboard.php');
    } catch (InvalidArgumentException|DomainException $e) { $error=$e->getMessage(); http_response_code(422); }
}
$pageTitle=t('user_security.factor_title');
include __DIR__.'/../includes/header.php';
?>
<main class="auth-shell container"><section class="panel">
<h1><?=h($pageTitle)?></h1>
<?php if($error!==''):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?>
<form method="post" class="form-grid"><?=csrf_field()?>
<label class="field"><?=h(t('user_security.factor'))?><input name="factor" maxlength="64" autocomplete="one-time-code" required autofocus></label>
<button class="button" type="submit"><?=h(t('account.mfa_submit'))?></button></form>
<a href="/auth/login.php"><?=h(t('Zur Anmeldung'))?></a></section></main>
<?php include __DIR__.'/../includes/footer.php'; ?>
