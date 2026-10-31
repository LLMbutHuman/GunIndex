<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
if (site_setting($pdo,'registration_enabled','1') !== '1') { abort_request(503,'Neue Registrierungen sind vorübergehend deaktiviert.'); }
if (current_user($pdo)) { redirect('/user/dashboard.php'); }
$error = ''; $values = ['username' => '', 'email' => '', 'zip' => '', 'city' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf(); auth_rate_limit('register-ip', auth_ip(), 12, 3600);
    try {
        if ($_FILES !== []) { throw new InvalidArgumentException('Bei der Registrierung werden keine Dokumente angenommen.'); }
        foreach ($values as $key => $_) { $values[$key] = auth_input($_POST, $key); }
        $password = auth_input($_POST, 'password', false); $confirmation = auth_input($_POST, 'password_confirm', false);
        if (!preg_match('/\A[A-Za-z0-9_\-]{3,50}\z/', $values['username'])) { throw new InvalidArgumentException('Der Benutzername benötigt 3–50 Buchstaben, Zahlen, Unterstriche oder Bindestriche.'); }
        if (strlen($values['email']) > 190 || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException('Bitte eine gültige E-Mail-Adresse eingeben.'); }
        if (!preg_match('/\A[0-9]{5}\z/', $values['zip']) || $values['city'] === '' || strlen($values['city']) > 100) { throw new InvalidArgumentException('Bitte eine fünfstellige Postleitzahl und einen Ort eingeben.'); }
        if (!hash_equals($password, $confirmation)) { throw new InvalidArgumentException('Die Passwörter stimmen nicht überein.'); }
        $hash = auth_hash($password);
        $query = $pdo->prepare('INSERT INTO users (username,email,password_hash,zip,city) VALUES (?,?,?,?,?)');
        $query->execute([$values['username'], $values['email'], $hash, $values['zip'], $values['city']]);
        $query = $pdo->prepare('SELECT * FROM users WHERE id = ?'); $query->execute([(int)$pdo->lastInsertId()]);
        auth_issue_session($query->fetch()); redirect('/user/dashboard.php');
    } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); http_response_code(422); }
    catch (PDOException $exception) {
        if ($exception->getCode() !== '23000') { throw $exception; }
        $error = 'Mit diesen Angaben kann kein Konto erstellt werden. Bitte andere Angaben wählen oder anmelden.'; http_response_code(422);
    }
}
$pageTitle = 'Registrieren'; require __DIR__ . '/../includes/header.php';
?>
<main class="auth-shell container"><section class="panel"><p class="eyebrow">Dabei sein</p><h1>Ein Konto. Neue Möglichkeiten.</h1>
<p>Für die Registrierung benötigen wir keine Identitätsdokumente.</p>
<?php if ($error !== ''): ?><p class="notice error" role="alert"><?= h($error) ?></p><?php endif; ?>
<form method="post" class="form-grid"><?= csrf_field() ?>
<label class="field">Benutzername<input name="username" value="<?= h($values['username']) ?>" autocomplete="username" minlength="3" maxlength="50" required></label>
<label class="field">E-Mail<input type="email" name="email" value="<?= h($values['email']) ?>" autocomplete="email" maxlength="190" required></label>
<label class="field">Postleitzahl<input name="zip" value="<?= h($values['zip']) ?>" autocomplete="postal-code" inputmode="numeric" pattern="[0-9]{5}" required></label>
<label class="field">Ort<input name="city" value="<?= h($values['city']) ?>" autocomplete="address-level2" maxlength="100" required></label>
<label class="field">Passwort<input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="1024" required><small>Mindestens zwölf Zeichen. Ein langer, eigener Passwortsatz ist geeignet.</small></label>
<label class="field">Passwort wiederholen<input type="password" name="password_confirm" autocomplete="new-password" minlength="12" maxlength="1024" required></label>
<button class="button" type="submit">Konto erstellen</button></form><p>Bereits registriert? <a href="/auth/login.php">Anmelden</a></p></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
