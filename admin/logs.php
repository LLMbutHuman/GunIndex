<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$admin = require_permission($pdo,'site.manage');
$pageTitle = t('admin_nav.audit');
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET','HEAD'], true)) {
    header('Allow: GET, HEAD');
    abort_request(405, t('admin_audit.readonly'));
}
$page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]) ?: 1;
$total = (int)$pdo->query('SELECT COUNT(*) FROM admin_logs')->fetchColumn();
$page = min($page, max(1, (int)ceil($total / 40)));
$statement = $pdo->prepare('SELECT l.id, l.action_type, l.target_id, l.details, l.created_at, u.username FROM admin_logs l LEFT JOIN users u ON u.id=l.admin_id ORDER BY l.id DESC LIMIT 40 OFFSET ?');
$statement->bindValue(1, ($page - 1) * 40, PDO::PARAM_INT);
$statement->execute();
$logs = $statement->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<main class="container admin-page">
    <div class="page-heading"><h1><?=h($pageTitle)?></h1></div>
    <div class="admin-layout"><?php include __DIR__ . '/../includes/admin-nav.php'; ?>
        <div class="admin-content">
            <section class="panel"><div class="table-wrap"><table class="data-table">
                <thead><tr><th><?=h(t('admin_audit.timestamp'))?></th><th><?=h(t('admin_audit.actor'))?></th><th><?=h(t('admin_audit.action'))?></th><th><?=h(t('admin_audit.details'))?></th></tr></thead>
                <tbody>
                <?php foreach ($logs as $event): ?><tr>
                    <td><?=h($event['created_at'])?></td><td><?=h($event['username']??t('admin_audit.system'))?></td><td><?=h($event['action_type'])?></td>
                    <td><?=$event['target_id']===null?h(t('admin_audit.site')):'#'.(int)$event['target_id']?><br><code><?=h($event['details'])?></code></td>
                </tr><?php endforeach; ?>
                <?php if ($logs === []): ?><tr><td colspan="4"><?=h(t('admin_audit.empty'))?></td></tr><?php endif; ?>
                </tbody>
            </table></div></section>
            <nav class="pagination" aria-label="<?=h(t('admin_audit.pages'))?>">
                <?php if ($page > 1): ?><a href="?page=<?=$page-1?>"><?=h(t('Zurück'))?></a><?php endif; ?>
                <span><?=h(t('admin_audit.page',['page'=>$page,'total'=>max(1,(int)ceil($total/40))]))?></span>
                <?php if ($page*40 < $total): ?><a href="?page=<?=$page+1?>"><?=h(t('Weiter'))?></a><?php endif; ?>
            </nav>
            <p class="muted"><?=h(t('admin_audit.retention'))?></p>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
