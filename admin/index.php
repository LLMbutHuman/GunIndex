<?php
declare(strict_types=1);

require_once __DIR__.'/../config/bootstrap.php';
$admin = require_staff($pdo);
header('Cache-Control: private, no-store');
$pageTitle = t('admin_nav.dashboard');
$overviews = rbac_permissions($pdo, (int)$admin['id']);
$can = static fn(string $permission): bool => in_array('*', $overviews, true) || in_array($permission, $overviews, true);

$metrics = [
    'admin_overview.active' => (int)$pdo->query("SELECT COUNT(*) FROM market_listings WHERE status='active'")->fetchColumn(),
    'admin_overview.auctions' => (int)$pdo->query("SELECT COUNT(*) FROM market_listings WHERE status='active' AND type='auction'")->fetchColumn(),
    'admin_nav.categories' => (int)$pdo->query('SELECT COUNT(*) FROM market_categories')->fetchColumn(),
];
if ($can('users.manage')) {
    $metrics['admin_nav.users'] = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
}
if ($can('finance.manage')) {
    $net = (int)$pdo->query("SELECT COALESCE(SUM(amount_cents),0) FROM market_finance_events WHERE kind IN ('cash_in','cash_refund')")->fetchColumn();
    $metrics['admin_overview.cash'] = money_eur($net);
}
$reviews = $can('content.moderate')
    ? (int)$pdo->query("SELECT COUNT(*) FROM market_listings WHERE status='review'")->fetchColumn()
    : 0;
$recent = $can('site.manage')
    ? $pdo->query('SELECT action_type,created_at FROM admin_logs ORDER BY id DESC LIMIT 8')->fetchAll()
    : [];
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page">
    <div class="page-heading"><h1><?=h($pageTitle)?></h1><p><?=h(t('admin_nav.access'))?></p></div>
    <div class="admin-layout">
        <?php include __DIR__.'/../includes/admin-nav.php';?>
        <div class="admin-content">
            <section class="stats-grid" aria-label="<?=h(t('admin_overview.metrics'))?>">
                <?php foreach ($metrics as $metricKey => $metricValue):?>
                    <article class="stat-card panel"><span class="eyebrow"><?=h(t($metricKey))?></span><strong><?=h((string)$metricValue)?></strong></article>
                <?php endforeach;?>
            </section>
            <?php if ($reviews > 0):?>
                <section class="notice error">
                    <h2><?=h(t('admin_overview.auction_review', ['count'=>$reviews]))?></h2>
                    <a href="/admin/listings.php?status=review"><?=h(t('admin_overview.review_queue'))?></a>
                </section>
            <?php endif;?>
            <?php if ($can('site.manage')):?>
                <section class="panel">
                    <h2><?=h(t('admin_overview.site'))?></h2>
                    <div class="button-row">
                        <a class="button" href="/admin/settings.php"><?=h(t('admin_nav.settings'))?></a>
                        <a class="button secondary" href="/admin/geography.php"><?=h(t('admin_nav.geography'))?></a>
                    </div>
                </section>
            <?php endif;?>
            <?php if ($can('finance.manage')):?>
                <section class="panel">
                    <h2><?=h(t('finance_admin.payments'))?></h2>
                    <p><?=h(manual_payment_reason($pdo) ?? t('admin_overview.payment_available'))?></p>
                    <a class="button secondary" href="/admin/payments.php"><?=h(t('admin_overview.review_payments'))?></a>
                    <a class="button secondary" href="/admin/finance.php"><?=h(t('admin_nav.finance'))?></a>
                </section>
            <?php endif;?>
            <?php if ($can('identity.review')):?>
                <section class="panel">
                    <h2><?=h(t('admin_nav.identity'))?></h2>
                    <p><?=h(verification_reason($pdo) ?? t('admin_overview.identity_available'))?></p>
                    <a class="button secondary" href="/admin/verifications.php"><?=h(t('admin_overview.review_identity'))?></a>
                </section>
            <?php endif;?>
            <?php if ($can('site.manage')):?>
                <section class="panel">
                    <h2><?=h(t('admin_overview.recent'))?></h2>
                    <?php foreach ($recent as $event):?><p><?=h($event['action_type'])?> · <?=h($event['created_at'])?> UTC</p><?php endforeach;?>
                    <?php if ($recent === []):?><p><?=h(t('admin_audit.empty'))?></p><?php endif;?>
                </section>
            <?php endif;?>
        </div>
    </div>
</main>
<?php include __DIR__.'/../includes/footer.php';?>
