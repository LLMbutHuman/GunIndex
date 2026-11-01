<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$admin = require_permission($pdo,'users.manage');
$pageTitle = 'Benutzerkonten';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 require_csrf();
 $id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
 if ($id === false || !is_string($_POST['action'] ?? null) || $_POST['action'] !== 'revoke_sessions') abort_request(422, 'Ungültige Verwaltungsaktion.');
 if ($id === (int)$admin['id']) abort_request(422, 'Nutze für dein eigenes Konto die Sitzungsverwaltung im Profil.');
 $pdo->beginTransaction();
 try {
  governance_assert_actor($pdo,(int)$admin['id'],'users.manage');
  $statement = $pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
  $statement->execute([$id]);
  if (!$statement->fetchColumn()) {
   $pdo->rollBack();
   abort_request(404, 'Benutzerkonto nicht gefunden.');
  }
  $pdo->prepare('UPDATE users SET auth_version=auth_version+1 WHERE id=?')->execute([$id]);
  audit_log($pdo, (int)$admin['id'], 'user.sessions_revoked', $id);
  $pdo->commit();
  redirect('/admin/users.php?revoked=1');
 } catch (Throwable $exception) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  throw $exception;
 }
}
if (isset($_GET['action']) || isset($_GET['delete_id'])) abort_request(405, 'Verwaltungsänderungen sind ausschließlich über geschützte POST-Formulare möglich.');
$page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]) ?: 1;
$total = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$page = min($page, max(1, (int)ceil($total / 30)));
$statement = $pdo->prepare('SELECT id, username, email, city, is_admin, created_at FROM users ORDER BY id DESC LIMIT 30 OFFSET ?');
$statement->bindValue(1, ($page - 1) * 30, PDO::PARAM_INT);
$statement->execute();
$users = $statement->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<main class="container admin-page"><div class="page-heading"><div><p class="eyebrow">Menschen hinter dem Marktplatz</p><h1>Benutzerkonten.</h1><p><?= $total ?> registrierte Konten. Zugriff auf Kontaktdaten ist auf diese geschützte Verwaltung beschränkt.</p></div></div>
<div class="admin-layout"><?php include __DIR__ . '/../includes/admin-nav.php'; ?><div class="admin-content">
<?php if (($_GET['revoked'] ?? '') === '1'): ?><p class="notice success" role="status">Die bisherigen Sitzungen des Kontos wurden widerrufen.</p><?php endif; ?>
<section class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Konto</th><th>Kontakt</th><th>Rolle</th><th>Seit (UTC)</th><th>Sicherheit</th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td><strong><?= h($user['username']) ?></strong><br><span class="muted">#<?= (int)$user['id'] ?></span></td><td><?= h($user['email']) ?><br><span class="muted"><?= h($user['city']) ?></span></td><td><?= $user['is_admin'] ? 'Administration' : 'Benutzer' ?></td><td><?= h($user['created_at']) ?></td><td><?php if ((int)$user['id'] !== (int)$admin['id']): ?><form method="post" action="/admin/users.php"><?= csrf_field() ?><input type="hidden" name="action" value="revoke_sessions"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>"><button class="button secondary small" type="submit">Sitzungen beenden</button></form><?php else: ?><a href="/user/profile.php">Eigenes Profil</a><?php endif; ?></td></tr><?php endforeach; ?><?php if ($users === []): ?><tr><td colspan="5">Noch keine Benutzerkonten.</td></tr><?php endif; ?></tbody></table></div></section>
<nav class="pagination" aria-label="Benutzerseiten"><?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">← Zurück</a><?php endif; ?><span>Seite <?= $page ?> von <?= max(1, (int)ceil($total / 30)) ?></span><?php if ($page * 30 < $total): ?><a href="?page=<?= $page + 1 ?>">Weiter →</a><?php endif; ?></nav>
<p class="muted">Keine physische Kontolöschung oder freie Rollenvergabe: Schutz vor unbeabsichtigtem Datenverlust und Rechteausweitung. Verifikationsstatus werden hier nicht ohne Prüfprozess erteilt.</p>
</div></div></main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
