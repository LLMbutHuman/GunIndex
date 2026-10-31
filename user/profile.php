<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$user = require_login($pdo); $error = ''; $success = '';
$values = array_intersect_key($user, array_flip(['email', 'zip', 'city', 'full_name']));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $action = auth_input($_POST, 'action') ?: 'save';
        if (!in_array($action, ['save', 'revoke'], true)) { throw new InvalidArgumentException('Unbekannte Profilaktion.'); }
        $currentPassword = auth_input($_POST, 'current_password', false); $newPassword = auth_input($_POST, 'new_password', false);
        if ($action === 'save') {
            foreach ($values as $key => $_) { $values[$key] = auth_input($_POST, $key); }
            if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 190) { throw new InvalidArgumentException('Bitte eine gültige E-Mail-Adresse eingeben.'); }
            if (!preg_match('/\A[0-9]{5}\z/', $values['zip']) || $values['city'] === '' || strlen($values['city']) > 100 || strlen($values['full_name']) > 150) { throw new InvalidArgumentException('Bitte Postleitzahl, Ort und Namenslänge prüfen.'); }
            if ($newPassword !== '') {
                auth_password_check($newPassword);
                if (!hash_equals($newPassword, auth_input($_POST, 'password_confirm', false))) { throw new InvalidArgumentException('Die neuen Passwörter stimmen nicht überein.'); }
            }
        }
        $pdo->beginTransaction();
        $statement = $pdo->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE'); $statement->execute([$user['id']]); $locked = $statement->fetch();
        if (!$locked || (int)$locked['is_active'] !== 1 || (int)$locked['auth_version'] !== (int)$user['auth_version']) { throw new InvalidArgumentException('Deine Sitzung hat sich geändert. Bitte erneut anmelden.'); }
        $sensitive = $action === 'revoke' || $newPassword !== '' || $values['email'] !== $locked['email'];
        if ($sensitive) {
            auth_rate_limit('profile-password', auth_ip() . ':' . $user['id'], 10, 900);
            if (strlen($currentPassword) > 1024 || !password_verify($currentPassword, $locked['password_hash'])) { throw new InvalidArgumentException('Bitte dein aktuelles Passwort korrekt eingeben.'); }
        }
        if ($action === 'save') {
            $hash = $newPassword !== '' ? auth_hash($newPassword) : $locked['password_hash'];
            $statement = $pdo->prepare('UPDATE users SET email=?,zip=?,city=?,full_name=?,password_hash=?,auth_version=? WHERE id=?');
            $statement->execute([$values['email'], $values['zip'], $values['city'], $values['full_name'], $hash,
                (int)$locked['auth_version'] + ($sensitive ? 1 : 0), $locked['id']]);
        } else { $pdo->prepare('UPDATE users SET auth_version=auth_version+1 WHERE id=?')->execute([$locked['id']]); }
        if ($sensitive && function_exists('audit_log')) {
            audit_log($pdo, (int)$locked['id'], $action === 'revoke' ? 'account.sessions_revoked' : 'account.credentials_changed', (int)$locked['id']);
        }
        $pdo->commit();
        $wasMfa = ($_SESSION['admin_mfa_user_id'] ?? null) === (int)$user['id'];
        $query = $pdo->prepare('SELECT * FROM users WHERE id=?'); $query->execute([$user['id']]); $user = $query->fetch();
        if ($sensitive) { auth_issue_session($user, $wasMfa); }
        $values = array_intersect_key($user, $values);
        $success = $action === 'revoke' ? 'Andere Sitzungen wurden beendet.' : 'Dein Profil wurde gespeichert.';
    } catch (InvalidArgumentException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = $exception->getMessage(); http_response_code(422);
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($exception->getCode() !== '23000') { throw $exception; }
        $error = 'Diese E-Mail-Adresse kann nicht verwendet werden.'; http_response_code(422);
    } catch (Throwable $exception) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $exception; }
}
$pageTitle = 'Mein Profil'; require __DIR__ . '/../includes/header.php';
?>
<main class="container"><header class="page-heading"><p class="eyebrow">Dein Konto</p><h1>Alles an seinem Platz.</h1><p>Benutzername: <?= h($user['username']) ?></p></header>
<?php if ($error !== ''): ?><p class="notice error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($success !== ''): ?><p class="notice success" role="status"><?= h($success) ?></p><?php endif; ?>
<section class="panel"><h2>Profil &amp; Passwort</h2><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="save">
<label class="field">E-Mail<input type="email" name="email" autocomplete="email" value="<?= h($values['email']) ?>" maxlength="190" required></label>
<label class="field">Name <small>Optional, nicht öffentlich</small><input name="full_name" autocomplete="name" value="<?= h($values['full_name']) ?>" maxlength="150"></label>
<label class="field">Postleitzahl<input name="zip" autocomplete="postal-code" inputmode="numeric" value="<?= h($values['zip']) ?>" pattern="[0-9]{5}" required></label>
<label class="field">Ort<input name="city" autocomplete="address-level2" value="<?= h($values['city']) ?>" maxlength="100" required></label>
<label class="field">Neues Passwort <small>Optional, mindestens zwölf Zeichen</small><input type="password" name="new_password" autocomplete="new-password" maxlength="1024"></label>
<label class="field">Neues Passwort wiederholen<input type="password" name="password_confirm" autocomplete="new-password" maxlength="1024"></label>
<label class="field">Aktuelles Passwort <small>Für Änderungen von E-Mail oder Passwort erforderlich</small><input type="password" name="current_password" autocomplete="current-password" maxlength="1024"></label>
<button class="button" type="submit">Änderungen speichern</button></form></section>
<section class="panel"><h2>Andere Sitzungen beenden</h2><p>Deine aktuelle Sitzung bleibt erhalten. Andere angemeldete Geräte müssen sich bei ihrer nächsten Anfrage neu anmelden.</p>
<form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="revoke">
<label class="field">Aktuelles Passwort<input type="password" name="current_password" autocomplete="current-password" maxlength="1024" required></label>
<button class="button secondary" type="submit">Andere Sitzungen beenden</button></form></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
