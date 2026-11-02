<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$admin = require_permission($pdo, 'site.manage');
require_once __DIR__.'/../config/setup.php';
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET','HEAD'], true)) {
    header('Allow: GET, HEAD');
    abort_request(405, t('setup.ui.readonly'));
}
$facts = setup_collect($pdo, (int)$admin['id']);
$steps = setup_steps($facts);
$labels = setup_status_labels();
$pageTitle = t('setup.ui.title');
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page">
    <div class="page-heading"><p class="eyebrow"><?=h(t('setup.ui.eyebrow'))?></p><h1><?=h(t('setup.ui.heading'))?></h1><p><?=h(t('setup.ui.intro'))?></p></div>
    <div class="admin-layout"><?php include __DIR__.'/../includes/admin-nav.php'; ?><div class="admin-content">
        <section class="panel"><h2><?=h(t('setup.ui.current'))?></h2><p><?=h(t('setup.ui.checked_at', ['time'=>$facts['checked_at']]))?></p><p><?=h(t('setup.ui.status_help'))?></p><a class="button secondary" href="/admin/setup.php"><?=h(t('setup.ui.refresh'))?></a></section>
        <nav class="panel" aria-label="<?=h(t('setup.ui.nav'))?>"><ol><?php foreach ($steps as $step): ?><li><a href="#setup-<?=h($step['id'])?>"><?=h($step['title'])?></a> · <?=h($labels[$step['status']])?></li><?php endforeach; ?></ol></nav>
        <?php foreach ($steps as $index=>$step): ?>
            <section class="panel" id="setup-<?=h($step['id'])?>" aria-labelledby="heading-<?=h($step['id'])?>">
                <p class="eyebrow"><?=h(t('setup.ui.step', ['current'=>$index+1,'total'=>count($steps)]))?></p>
                <h2 id="heading-<?=h($step['id'])?>"><?=h($step['title'])?></h2>
                <p class="notice<?=$step['status']==='error'?' error':''?>"><strong><?=h($labels[$step['status']])?></strong></p>
                <p><?=h($step['detail'])?></p><p><strong><?=h(t('setup.ui.next'))?></strong> <?=h($step['next'])?></p>
                <?php if ($step['href'] !== null && rbac_has_permission($pdo, (int)$admin['id'], $step['permission'])): ?><a class="button secondary" href="<?=h($step['href'])?>"><?=h(t('setup.ui.settings', ['title'=>$step['title']]))?></a><?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div></div>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
