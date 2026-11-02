<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';$user=require_login($pdo);$success=false;$error='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    require_csrf();
    try{
        $inapp=post_string('inapp_enabled');$email=post_string('mail_enabled');
        if(!in_array($inapp,['0','1'],true)||!in_array($email,['0','1'],true))throw new InvalidArgumentException(t('account.preferences_invalid'));
        $pdo->prepare('INSERT INTO market_notification_preferences(user_id,inapp_enabled,mail_enabled) VALUES (?,?,?) ON DUPLICATE KEY UPDATE inapp_enabled=VALUES(inapp_enabled),mail_enabled=VALUES(mail_enabled)')->execute([$user['id'],(int)$inapp,(int)$email]);$success=true;
    }catch(InvalidArgumentException $exception){http_response_code(422);$error=$exception->getMessage();}
}
$preferences=notification_preferences($pdo,(int)$user['id']);$pageTitle=t('Benachrichtigungen einstellen');include __DIR__.'/../includes/header.php';?>
<main class="container"><section class="panel"><h1><?=h(t('account.preferences_heading'))?></h1><?php if($error):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?><?php if($success):?><p class="notice success" role="status"><?=h(t('account.preferences_saved'))?></p><?php endif;?>
<form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="inapp_enabled" value="0"><label><input type="checkbox" name="inapp_enabled" value="1" <?= $preferences['inapp_enabled']?'checked':'' ?>> <?=h(t('account.inapp_notifications'))?></label><input type="hidden" name="mail_enabled" value="0"><label><input type="checkbox" name="mail_enabled" value="1" <?= $preferences['mail_enabled']?'checked':'' ?>> <?=h(t('account.email_notifications'))?></label><p><?=h(t('account.notification_preferences_help'))?></p><button class="button" type="submit"><?=h(t('Einstellungen speichern'))?></button></form><a href="/user/notifications.php"><?=h(t('account.notifications_link'))?></a></section></main>
<?php include __DIR__.'/../includes/footer.php'; ?>
