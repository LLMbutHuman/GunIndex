<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
header('Cache-Control: private, no-store, max-age=0');
$method=$_SERVER['REQUEST_METHOD']??'GET';
if ($method === 'POST') { require_csrf(); auth_logout($pdo); redirect('/marketplace/index.php'); }
if (!in_array($method, ['GET', 'HEAD'], true)) { header('Allow: GET, HEAD, POST'); abort_request(405, t('account.method')); }
$pageTitle = t('Abmelden'); require __DIR__ . '/../includes/header.php';
?>
<main class="auth-shell container"><section class="panel"><h1><?=h(t('account.logout_prompt'))?></h1><form method="post"><?= csrf_field() ?><button type="submit" class="button"><?=h($pageTitle)?></button></form><p><a href="/user/dashboard.php"><?=h(t('account.logout_back'))?></a></p></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
