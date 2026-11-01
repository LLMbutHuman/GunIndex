<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
header('Referrer-Policy: no-referrer');
$token=is_string($_GET['token']??null)?$_GET['token']:'';$error='';$success=false;$requested=false;$viewer=current_user($pdo);
if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
    require_csrf();auth_rate_limit('verification-ip',auth_ip(),30,900);
    try {
        if (post_string('action')==='request') {
            $viewer=require_login($pdo);auth_rate_limit('verification-user',(string)$viewer['id'],5,3600);
            account_token_request($pdo,$viewer['email'],'verify');$requested=true;
        } else {
            $token=post_string('token',64);
            if (!account_token_consume($pdo,$token,'verify')) {http_response_code(422);$error='Der Link ist ungültig, abgelaufen oder bereits verwendet.';}
            else $success=true;
        }
    }catch(InvalidArgumentException $exception){http_response_code(422);$error=$exception->getMessage();}
    catch(Throwable){http_response_code(503);$error='Die Bestätigung ist gerade nicht möglich. Bitte später erneut versuchen.';}
}
$pageTitle='E-Mail bestätigen';include __DIR__.'/../includes/header.php';?>
<main class="auth-shell container"><section class="panel"><h1>Deine E-Mail bestätigen.</h1>
<?php if($error):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?>
<?php if($success):?><p class="notice success">Die E-Mail-Adresse wurde bestätigt. Dies bestätigt keine Identität oder rechtliche Erwerbsberechtigung.</p><a class="button" href="/user/profile.php">Zum Profil</a>
<?php elseif(preg_match('/\A[a-f0-9]{64}\z/',$token)):?><p>Bestätige den Link ausdrücklich. Das bloße Öffnen dieser Seite verändert dein Konto nicht.</p><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="confirm"><input type="hidden" name="token" value="<?=h($token)?>"><button class="button"><?=h(t('E-Mail-Adresse bestätigen'))?></button></form>
<?php elseif($viewer):?><p><?= $viewer['email_verified_at']!==null?'Deine aktuelle E-Mail-Adresse ist bestätigt.':'Deine aktuelle E-Mail-Adresse ist noch nicht bestätigt.' ?></p><?php if($requested):?><p class="notice">Wenn der Versand eingerichtet ist, wurde ein neuer Link vorbereitet.</p><?php endif;?><?php if($viewer['email_verified_at']===null):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="request"><button class="button"><?=h(t('Bestätigungslink erneut anfordern'))?></button></form><?php endif;?>
<?php else:?><a class="button" href="/auth/login.php">Anmelden und neuen Link anfordern</a><?php endif;?></section></main>
<?php include __DIR__.'/../includes/footer.php'; ?>
