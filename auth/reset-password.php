<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
header('Referrer-Policy: no-referrer');
$token = is_string($_GET['token']??null) ? $_GET['token'] : '';
$error='';$success=false;
if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
    require_csrf();auth_rate_limit('reset-ip',auth_ip(),30,900);
    try {
        $token=post_string('token',64);auth_rate_limit('reset-capability',hash('sha256',$token),10,900);
        $password=post_string('password',1024);$confirmation=post_string('password_confirm',1024);
        if (!hash_equals($password,$confirmation)) throw new InvalidArgumentException(t('account.password_mismatch'));
        if (!account_token_consume($pdo,$token,'reset',$password)) { http_response_code(422);$error=t('account.token_invalid'); }
        else {auth_clear_session();$success=true;}
    } catch (InvalidArgumentException $exception) {http_response_code(422);$error=$exception->getMessage();}
    catch (Throwable) {http_response_code(503);$error=t('account.change_unavailable');}
}
$pageTitle=t('account.reset_title'); include __DIR__.'/../includes/header.php'; ?>
<main class="auth-shell container"><section class="panel"><h1><?=h(t('account.reset_heading'))?></h1>
<?php if($success):?><p class="notice success" role="status"><?=h(t('account.reset_success'))?></p><a class="button" href="/auth/login.php"><?=h(t('account.sign_in_again'))?></a>
<?php elseif(!preg_match('/\A[a-f0-9]{64}\z/',$token)):?><p class="notice"><?=h(t('account.request_valid_link'))?></p><a href="/auth/forgot-password.php"><?=h(t('account.request_new_link'))?></a>
<?php else:?><?php if($error):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="token" value="<?=h($token)?>"><label class="field"><?=h(t('Neues Passwort'))?><input type="password" name="password" minlength="12" maxlength="1024" autocomplete="new-password" required></label><label class="field"><?=h(t('Passwort wiederholen'))?><input type="password" name="password_confirm" minlength="12" maxlength="1024" autocomplete="new-password" required></label><button class="button" type="submit"><?=h(t('Passwort ändern'))?></button></form><?php endif;?></section></main>
<?php include __DIR__.'/../includes/footer.php'; ?>
