<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
if (!isset($_SESSION['pending_admin_id']) || time() - (int)($_SESSION['pending_admin_started'] ?? 0) > 600) { redirect('/auth/login.php'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $code = auth_input($_POST, 'code');
        if (($verified=auth_verify_pending_mfa($pdo, $code)) !== null) { redirect((int)$verified['is_admin']===1 ? '/admin/index.php' : '/user/dashboard.php'); }
        $error = 'Der Code ist ungültig, abgelaufen oder bereits verwendet. Bitte einen neuen Code versuchen.'; http_response_code(422);
    } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); http_response_code(422); }
}
$pageTitle = 'Zweiter Faktor'; require __DIR__ . '/../includes/header.php';
?>
<main class="auth-shell container"><section class="panel"><p class="eyebrow">Geschützter Verwaltungszugang</p><h1>Ein zweiter Schritt.</h1>
<p>Gib den aktuellen sechsstelligen Code deiner Authenticator-App ein.</p>
<?php if ($error !== ''): ?><p class="notice error" role="alert"><?= h($error) ?></p><?php endif; ?>
<form method="post" class="form-grid"><?= csrf_field() ?><label class="field">Einmalcode<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus></label><button type="submit" class="button">Sicher anmelden</button></form>
<p><a href="/auth/login.php">Zur Anmeldung</a></p></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
