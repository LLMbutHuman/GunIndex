<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$user = require_login($pdo);
$rawPage = $_GET['page'] ?? '1';
$page = is_string($rawPage) ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]) : false;
if ($page === false) { abort_request(422, 'Bitte eine gültige Seite auswählen.'); }
$categories = market_category_ids($pdo); $items = []; $total = 0;
if ($categories !== []) {
    $condition = "w.user_id=? AND l.status='active' AND u.is_active=1 AND l.category_id IN (" . implode(',', array_fill(0, count($categories), '?')) . ')';
    $parameters = array_merge([(int)$user['id']], $categories);
    $query = $pdo->prepare('SELECT COUNT(*) FROM market_watchlist w JOIN market_listings l ON l.id=w.listing_id JOIN users u ON u.id=l.seller_id WHERE ' . $condition);
    $query->execute($parameters); $total = (int)$query->fetchColumn();
    $query = $pdo->prepare('SELECT l.*,u.username AS seller_username,c.name AS category_name FROM market_watchlist w JOIN market_listings l ON l.id=w.listing_id JOIN users u ON u.id=l.seller_id JOIN market_categories c ON c.id=l.category_id WHERE ' . $condition . ' ORDER BY w.created_at DESC,l.id DESC LIMIT 24 OFFSET ' . (($page - 1) * 24));
    $query->execute($parameters); $items = $query->fetchAll();
}
$pageTitle = 'Meine Merkliste'; require __DIR__ . '/../includes/header.php';
?>
<main class="page-width section"><header class="page-heading"><p class="eyebrow">Gut aufgehoben</p><h1>Deine Entdeckungen.</h1><p><?= $total ?> verfügbare Angebote auf deiner Merkliste.</p></header>
<?php if ($items === []): ?><section class="panel empty-state"><h2>Noch Platz für einen Lieblingsfund.</h2><p>Merke dir interessante Angebote auf ihrer Detailseite.</p><a class="button" href="/marketplace/search.php">Angebote entdecken</a></section>
<?php else: ?><div class="cards"><?php foreach ($items as $item): ?><div><?php ui_listing_card($item); ?><form method="post" action="/user/toggle-watchlist.php"><?= csrf_field() ?><input type="hidden" name="listing_id" value="<?= (int)$item['id'] ?>"><button class="button secondary" type="submit">Von Merkliste entfernen</button></form></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($total > 24): ?><nav class="pagination" aria-label="Merklistenseiten"><?php if ($page > 1): ?><a class="button secondary" href="?page=<?= $page - 1 ?>">Zurück</a><?php endif; ?><span>Seite <?= $page ?></span><?php if ($page * 24 < $total): ?><a class="button secondary" href="?page=<?= $page + 1 ?>">Weiter</a><?php endif; ?></nav><?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
