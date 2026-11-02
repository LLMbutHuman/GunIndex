<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$user = require_login($pdo); $error = ''; $success = '';
$values = array_intersect_key($user, array_flip(['email', 'zip', 'city', 'full_name','country_code','preferred_locale']));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $action = auth_input($_POST, 'action') ?: 'save';
        if (!in_array($action, ['save', 'revoke'], true)) { throw new InvalidArgumentException(t('account.profile_action')); }
        $currentPassword = auth_input($_POST, 'current_password', false); $newPassword = auth_input($_POST, 'new_password', false);
        if ($action === 'save') {
            foreach ($values as $key => $previous) { $values[$key] = in_array($key,['country_code','preferred_locale'],true) && !array_key_exists($key,$_POST) ? $previous : auth_input($_POST,$key); }
            if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 190) { throw new InvalidArgumentException(t('account.email_invalid')); }
            $values['country_code']=function_exists('geo_country')?geo_country($values['country_code']):'DE';
            $postalValid=function_exists('geo_validate_postcode')?geo_validate_postcode($values['country_code'],$values['zip']):(bool)preg_match('/\A[0-9]{5}\z/',$values['zip']);
            if (!$postalValid || $values['city']==='' || strlen($values['city'])>100 || strlen($values['full_name'])>150) throw new InvalidArgumentException(t('account.profile_location'));
            if (function_exists('geo_normalize_postcode')) $values['zip']=geo_normalize_postcode($values['country_code'],$values['zip']);
            $locales=function_exists('i18n_locales')?i18n_locales():['de'=>'Deutsch'];if(!isset($locales[$values['preferred_locale']]))throw new InvalidArgumentException(t('account.locale_invalid'));
            if ($newPassword !== '') {
                auth_password_check($newPassword);
                if (!hash_equals($newPassword, auth_input($_POST, 'password_confirm', false))) { throw new InvalidArgumentException(t('account.password_mismatch')); }
            }
        }
        $pdo->beginTransaction();
        $statement = $pdo->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE'); $statement->execute([$user['id']]); $locked = $statement->fetch();
        if (!$locked || (int)$locked['is_active'] !== 1 || (int)$locked['auth_version'] !== (int)$user['auth_version']) { throw new InvalidArgumentException(t('account.session_changed')); }
        $sensitive = $action === 'revoke' || $newPassword !== '' || $values['email'] !== $locked['email'];
        if ($sensitive) {
            auth_rate_limit('profile-password', auth_ip() . ':' . $user['id'], 10, 900);
            if (strlen($currentPassword) > 1024 || !password_verify($currentPassword, $locked['password_hash'])) { throw new InvalidArgumentException(t('account.current_password_invalid')); }
        }
        if ($action === 'save') {
            $hash = $newPassword !== '' ? auth_hash($newPassword) : $locked['password_hash'];
            $statement = $pdo->prepare('UPDATE users SET email=?,zip=?,city=?,full_name=?,country_code=?,preferred_locale=?,password_hash=?,auth_version=?,email_verified_at=IF(?,NULL,email_verified_at) WHERE id=?');
            $statement->execute([$values['email'], $values['zip'], $values['city'], $values['full_name'],$values['country_code'],$values['preferred_locale'],$hash,
                (int)$locked['auth_version'] + ($sensitive ? 1 : 0),(int)($values['email']!==$locked['email']),$locked['id']]);
        } else { $pdo->prepare('UPDATE users SET auth_version=auth_version+1 WHERE id=?')->execute([$locked['id']]); }
        if ($sensitive) {
            $pdo->prepare('UPDATE market_auth_tokens SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND used_at IS NULL')->execute([$locked['id']]);
            $pdo->prepare("UPDATE market_mail_queue SET status='expired',payload_ciphertext='' WHERE user_id=? AND dedupe_key LIKE 'account:%' AND status IN ('pending','failed')")->execute([$locked['id']]);
            $copy=account_mail_text((string)($locked['preferred_locale']??'de'),'security');
            mail_enqueue($pdo,(int)$locked['id'],$locked['email'],$copy['subject'],$copy['body'],'credentials:'.$locked['id'].':'.((int)$locked['auth_version']+1));
        }
        $emailChanged=$action==='save' && $values['email']!==$locked['email'];
        if ($emailChanged) {
            $pdo->prepare("UPDATE market_mail_queue SET status='cancelled',payload_ciphertext='' WHERE user_id=? AND dedupe_key LIKE 'notification:%' AND status IN ('pending','failed')")->execute([$locked['id']]);
            account_token_request($pdo,$values['email'],'verify');
        }
        if ($sensitive && function_exists('audit_log')) {
            audit_log($pdo, (int)$locked['id'], $action === 'revoke' ? 'account.sessions_revoked' : 'account.credentials_changed', (int)$locked['id']);
        }
        $pdo->commit();
        $wasMfa = ($_SESSION['admin_mfa_user_id'] ?? null) === (int)$user['id'];
        $query = $pdo->prepare('SELECT * FROM users WHERE id=?'); $query->execute([$user['id']]); $user = $query->fetch();
        if ($sensitive) { auth_issue_session($user, $wasMfa); }
        $_SESSION['locale']=(string)$user['preferred_locale'];
        $values = array_intersect_key($user, $values);
        $success = t($action === 'revoke' ? 'account.sessions_revoked' : 'account.profile_saved');
    } catch (InvalidArgumentException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = $exception->getMessage(); http_response_code(422);
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($exception->getCode() !== '23000') { throw $exception; }
        $error = t('account.email_unavailable'); http_response_code(422);
    } catch (Throwable $exception) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $exception; }
}
$pageTitle = t('account.profile_title'); require __DIR__ . '/../includes/header.php';
?>
<main class="container"><header class="page-heading"><p class="eyebrow"><?=h(t('account.account_label'))?></p><h1><?=h(t('account.profile_heading'))?></h1><p><?=h(t('Benutzername'))?>: <?= h($user['username']) ?></p></header>
<?php if ($error !== ''): ?><p class="notice error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($success !== ''): ?><p class="notice success" role="status"><?= h($success) ?></p><?php endif; ?>
<section class="panel"><h2><?=h(t('account.profile_section'))?></h2><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="save">
<label class="field"><?=h(t('E-Mail'))?><input type="email" name="email" autocomplete="email" value="<?= h($values['email']) ?>" maxlength="190" required></label>
<label class="field"><?=h(t('account.name'))?> <small><?=h(t('account.private_optional'))?></small><input name="full_name" autocomplete="name" value="<?= h($values['full_name']) ?>" maxlength="150"></label>
<label class="field"><?=h(t('Land'))?><select name="country_code"><?php foreach((function_exists('geo_countries')?geo_countries():['DE'=>'Deutschland']) as $code=>$label):?><option value="<?=h($code)?>" <?= $values['country_code']===$code?'selected':'' ?>><?=h($label)?></option><?php endforeach;?></select></label>
<label class="field"><?=h(t('Sprache'))?><select name="preferred_locale"><?php foreach((function_exists('i18n_locales')?i18n_locales():['de'=>'Deutsch']) as $code=>$label):?><option value="<?=h($code)?>" <?= $values['preferred_locale']===$code?'selected':'' ?>><?=h(is_array($label)?($label['label']??$code):$label)?></option><?php endforeach;?></select></label>
<label class="field"><?=h(t('Postleitzahl'))?><input name="zip" autocomplete="postal-code" inputmode="text" value="<?= h($values['zip']) ?>" maxlength="12" required></label>
<label class="field"><?=h(t('Ort'))?><input name="city" autocomplete="address-level2" value="<?= h($values['city']) ?>" maxlength="100" required></label>
<label class="field"><?=h(t('Neues Passwort'))?> <small><?=h(t('account.new_password_optional'))?></small><input type="password" name="new_password" autocomplete="new-password" maxlength="1024"></label>
<label class="field"><?=h(t('account.repeat_new_password'))?><input type="password" name="password_confirm" autocomplete="new-password" maxlength="1024"></label>
<label class="field"><?=h(t('Aktuelles Passwort'))?> <small><?=h(t('account.password_required_help'))?></small><input type="password" name="current_password" autocomplete="current-password" maxlength="1024"></label>
<button class="button" type="submit"><?=h(t('Änderungen speichern'))?></button></form></section>
<section class="panel"><h2><?=h(t('account.email_news'))?></h2><p><?=h(t($user['email_verified_at']!==null?'account.email_verified':'account.email_unverified'))?></p><a class="button secondary" href="/auth/verify-email.php"><?=h(t('account.verify_title'))?></a> <a class="button secondary" href="/user/notifications.php"><?=h(t('account.notifications'))?></a> <a class="button secondary" href="/user/notification-settings.php"><?=h(t('Benachrichtigungen einstellen'))?></a></section><section class="panel"><h2><?=h(t('Andere Sitzungen beenden'))?></h2><p><?=h(t('account.revoke_help'))?></p>
<form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="revoke">
<label class="field"><?=h(t('Aktuelles Passwort'))?><input type="password" name="current_password" autocomplete="current-password" maxlength="1024" required></label>
<button class="button secondary" type="submit"><?=h(t('Andere Sitzungen beenden'))?></button></form></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
