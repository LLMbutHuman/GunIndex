<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$admin = require_permission($pdo,'site.manage');
$pageTitle = 'Änderungsprotokoll';
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') abort_request(405, 'Dieses Protokoll ist ausschließlich lesbar.');
$page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]) ?: 1;
$total = (int)$pdo->query('SELECT COUNT(*) FROM admin_logs')->fetchColumn();
$page = min($page, max(1, (int)ceil($total / 40)));
$statement = $pdo->prepare('SELECT l.id, l.action_type, l.target_id, l.details, l.created_at, u.username FROM admin_logs l JOIN users u ON u.id=l.admin_id ORDER BY l.id DESC LIMIT 40 OFFSET ?');
$statement->bindValue(1, ($page - 1) * 40, PDO::PARAM_INT);
$statement->execute();
$logs = $statement->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<main class="container admin-page"><div class="page-heading"><div><p class="eyebrow">Nachvollziehbar handeln</p><h1>Was sich verändert hat.</h1><p>Website-, Kategorie- und Kontosicherheitsänderungen mit verantwortlichem Konto und Zeitpunkt.</p></div></div><div class="admin-layout"><?php include __DIR__ . '/../includes/admin-nav.php'; ?><div class="admin-content"><section class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Zeitpunkt (UTC)</th><th>Verantwortlich</th><th>Aktion</th><th>Bezug & Details</th></tr></thead><tbody><?php foreach ($logs as $event): ?><tr><td><?= h($event['created_at']) ?></td><td><?= h($event['username']) ?></td><td><?= h($event['action_type']) ?></td><td><?= $event['target_id'] === null ? 'Website' : '#' . h((string)$event['target_id']) ?><br><code><?= h($event['details']) ?></code></td></tr><?php endforeach; ?><?php if ($logs === []): ?><tr><td colspan="4">Noch keine Änderungen protokolliert.</td></tr><?php endif; ?></tbody></table></div></section><nav class="pagination" aria-label="Protokollseiten"><?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">← Zurück</a><?php endif; ?><span>Seite <?= $page ?> von <?= max(1, (int)ceil($total / 40)) ?></span><?php if ($page * 40 < $total): ?><a href="?page=<?= $page + 1 ?>">Weiter →</a><?php endif; ?></nav><p class="muted">Die Oberfläche bietet keine Löschung oder Bearbeitung dieser Einträge. Für manipulationserschwerende Aufbewahrung sind zusätzlich getrennte Betriebslogs und passende Datenbankrechte nötig.</p></div></div></main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
