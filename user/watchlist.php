<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$user = require_login($pdo);
$rawPage = $_GET['page'] ?? '1';
$page = is_string($rawPage) ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]) : false;
if ($page === false) { abort_request(422, t('dashboard.watchlist_page_invalid')); }
$categories = market_category_ids($pdo); $items = []; $total = 0;
if ($categories !== []) {
    $listings = 'FROM market_watchlist w JOIN market_listings l ON l.id=w.listing_id
        JOIN users u ON u.id=l.seller_id JOIN market_countries mc ON mc.code=l.country_code';
    $condition = "w.user_id=? AND l.status='active' AND u.is_active=1 AND mc.is_active=1 AND l.category_id IN (" . implode(',', array_fill(0, count($categories), '?')) . ')';
    $parameters = array_merge([(int)$user['id']], $categories);
    $query = $pdo->prepare('SELECT COUNT(*) ' . $listings . ' WHERE ' . $condition);
    $query->execute($parameters); $total = (int)$query->fetchColumn();
    $query = $pdo->prepare('SELECT l.*,u.username AS seller_username,c.name AS category_name ' . $listings
        . ' JOIN market_categories c ON c.id=l.category_id WHERE ' . $condition
        . ' ORDER BY w.created_at DESC,l.id DESC LIMIT 24 OFFSET ' . (($page - 1) * 24));
    $query->execute($parameters); $items = $query->fetchAll();
}
$pageTitle = t('dashboard.watchlist'); require __DIR__ . '/../includes/header.php';
?>
<main class="page-width section"><header class="page-heading"><h1><?=h($pageTitle)?></h1><p><?=h(t('dashboard.watchlist_count',['count'=>$total]))?></p></header>
<?php if ($items === []): ?><section class="panel empty-state"><h2><?=h(t('dashboard.watchlist_empty'))?></h2><p><?=h(t('dashboard.watchlist_help'))?></p><a class="button" href="/marketplace/search.php"><?=h(t('Alle Entdeckungen'))?></a></section>
<?php else: ?><div class="cards"><?php foreach ($items as $item): ?><div><?php ui_listing_card($item); ?><form method="post" action="/user/toggle-watchlist.php"><?= csrf_field() ?><input type="hidden" name="listing_id" value="<?= (int)$item['id'] ?>"><button class="button secondary" type="submit"><?=h(t('dashboard.watchlist_remove'))?></button></form></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($total > 24): ?><nav class="pagination" aria-label="<?=h($pageTitle)?>"><?php if ($page > 1): ?><a class="button secondary" href="?page=<?= $page - 1 ?>"><?=h(t('Zurück'))?></a><?php endif; ?><span><?=h(t('Seite {page} von {pages}',['page'=>$page,'pages'=>(int)ceil($total/24)]))?></span><?php if ($page * 24 < $total): ?><a class="button secondary" href="?page=<?= $page + 1 ?>"><?=h(t('Weiter'))?></a><?php endif; ?></nav><?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
