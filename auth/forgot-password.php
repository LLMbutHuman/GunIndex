<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
header('Referrer-Policy: no-referrer');
$complete = false; $email = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $started = hrtime(true);
    require_csrf(); auth_rate_limit('recovery-ip',auth_ip(),12,900);
    try {
        $email = trim(post_string('email',190));
        auth_rate_limit('recovery-address',hash('sha256',strtolower($email)),5,3600);
        account_token_request($pdo,$email,'reset');
    } catch (Throwable $error) {
        // Do not disclose whether an account or delivery configuration exists.
        error_log('Account recovery request could not be queued.');
    }
    $complete = true;
    $elapsed = (int)((hrtime(true)-$started)/1000);
    usleep(max(0,300000-$elapsed)+random_int(0,30000));
}
$pageTitle=t('Passwort vergessen?'); include __DIR__.'/../includes/header.php';
?>
<main class="auth-shell container"><section class="panel"><p class="eyebrow"><?=h(t('account.recovery_label'))?></p><h1><?=h(t('Passwort vergessen?'))?></h1>
<?php if ($complete): ?><p class="notice" role="status"><?=h(t('account.recovery_notice'))?></p><?php else: ?><p><?=h(t('account.recovery_help'))?></p><?php endif; ?>
<form method="post" class="form-grid"><?=csrf_field()?><label class="field"><?=h(t('E-Mail'))?><input type="email" name="email" maxlength="190" autocomplete="email" required value="<?=h($email)?>"></label><button class="button" type="submit"><?=h(t('Wiederherstellungslink anfordern'))?></button></form><p><a href="/auth/login.php"><?=h(t('Zur Anmeldung'))?></a></p></section></main>
<?php include __DIR__.'/../includes/footer.php'; ?>
