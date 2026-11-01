<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
if (current_user($pdo)) { redirect('/user/dashboard.php'); }
$error = ''; $login = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $login = auth_input($_POST, 'login');
        $password = auth_input($_POST, 'password', false);
        $user = auth_authenticate($pdo, $login, $password);
        if ($user === null) {
            $error = 'Anmeldung fehlgeschlagen. Bitte prüfe deine Zugangsdaten.'; http_response_code(422);
        } elseif (auth_requires_mfa($pdo,$user)) {
            if (empty($user['mfa_secret'])) {
                $error = 'Der Administrationszugang muss zuerst lokal mit einem zweiten Faktor eingerichtet werden.'; http_response_code(503);
            } else {
                auth_clear_session();
                $_SESSION['pending_admin_id'] = (int)$user['id'];
                $_SESSION['pending_admin_version'] = (int)$user['auth_version'];
                $_SESSION['pending_admin_started'] = time();
                redirect('/auth/mfa.php');
            }
        } else { auth_issue_session($user); redirect('/user/dashboard.php'); }
    } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); http_response_code(422); }
}
$pageTitle = 'Anmelden'; require __DIR__ . '/../includes/header.php';
?>
<main class="auth-shell container"><section class="panel">
<p class="eyebrow">Dein Zugang</p><h1>Willkommen zurück.</h1><p>Entdecken, anbieten und mit anderen in Verbindung bleiben.</p>
<?php if ($error !== ''): ?><p class="notice error" role="alert"><?= h($error) ?></p><?php endif; ?>
<form method="post" class="form-grid"><?= csrf_field() ?>
<label class="field">Benutzername oder E-Mail<input name="login" value="<?= h($login) ?>" maxlength="190" autocomplete="username" required></label>
<label class="field"><?=h(t('Passwort'))?><input type="password" name="password" autocomplete="current-password" maxlength="1024" required></label>
<button class="button" type="submit"><?=h(t('Anmelden'))?></button></form>
<p><a href="/auth/forgot-password.php"><?=h(t('Passwort vergessen?'))?></a></p>
<p>Noch kein Konto? <a href="/auth/register.php">Kostenlos registrieren</a></p></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
