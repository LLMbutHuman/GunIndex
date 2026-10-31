<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { require_csrf(); auth_clear_session(); redirect('/marketplace/index.php'); }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { abort_request(405, 'Diese Methode wird nicht unterstützt.'); }
$pageTitle = 'Abmelden'; require __DIR__ . '/../includes/header.php';
?>
<main class="auth-shell container"><section class="panel"><h1>Abmelden?</h1><form method="post"><?= csrf_field() ?><button type="submit" class="button">Jetzt abmelden</button></form><p><a href="/user/dashboard.php">Zurück zum Konto</a></p></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
