<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$rawId = $_GET['user_id'] ?? null;
$id = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
if ($id === false) { abort_request(404, 'Dieses Profil ist nicht verfügbar.'); }
$query = $pdo->prepare('SELECT id,username,created_at FROM users WHERE id=? AND is_active=1'); $query->execute([$id]); $seller = $query->fetch();
if (!$seller) { abort_request(404, 'Dieses Profil ist nicht verfügbar.'); }
$rawPage = $_GET['page'] ?? '1';
$page = is_string($rawPage) ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]) : false;
if ($page === false) { abort_request(422, 'Bitte eine gültige Seite auswählen.'); }
$categories = market_category_ids($pdo); $items = []; $total = 0;
if ($categories !== []) {
    $condition = "l.seller_id=? AND l.status='active' AND l.category_id IN (" . implode(',', array_fill(0, count($categories), '?')) . ')';
    $parameters = array_merge([(int)$id], $categories);
    $query = $pdo->prepare('SELECT COUNT(*) FROM market_listings l WHERE ' . $condition); $query->execute($parameters); $total = (int)$query->fetchColumn();
    $query = $pdo->prepare('SELECT l.*,c.name AS category_name FROM market_listings l JOIN market_categories c ON c.id=l.category_id WHERE ' . $condition . ' ORDER BY l.created_at DESC,l.id DESC LIMIT 24 OFFSET ' . (($page - 1) * 24));
    $query->execute($parameters); $items = $query->fetchAll();
}
$query = $pdo->prepare("SELECT COUNT(*) AS count,AVG(r.stars) AS average FROM market_ratings r JOIN market_listings l ON l.id=r.listing_id WHERE r.receiver_id=? AND r.status='active' AND l.status IN ('ended','sold')");
$query->execute([$id]); $summary = $query->fetch();
$query = $pdo->prepare("SELECT r.stars,r.comment,r.created_at,s.username FROM market_ratings r JOIN users s ON s.id=r.sender_id JOIN market_listings l ON l.id=r.listing_id WHERE r.receiver_id=? AND r.status='active' AND l.status IN ('ended','sold') ORDER BY r.id DESC LIMIT 20");
$query->execute([$id]); $ratings = $query->fetchAll();
$pageTitle = (string)$seller['username']; require __DIR__ . '/../includes/header.php';
?>
<main class="page-width section"><header class="page-heading"><p class="eyebrow">Menschen hinter den Dingen</p><h1><?= h($seller['username']) ?></h1><p>Mitglied seit <?= h(substr((string)$seller['created_at'], 0, 10)) ?> · <?= $total ?> aktive Angebote</p>
<?php if ((int)$summary['count'] > 0): ?><p><?= h(number_format((float)$summary['average'], 1, ',', '.')) ?> von 5 Sternen · <?= (int)$summary['count'] ?> Bewertungen</p><?php else: ?><p>Noch keine Bewertungen aus abgeschlossenen Marktplatztransaktionen.</p><?php endif; ?></header>
<section class="section"><h2>Aktuelle Entdeckungen.</h2><?php if ($items): ?><div class="cards"><?php foreach ($items as $item) { ui_listing_card($item); } ?></div><?php else: ?><p class="panel">Aktuell keine verfügbaren Angebote.</p><?php endif; ?>
<?php if ($total > 24): ?><nav class="pagination" aria-label="Angebotsseiten"><?php if ($page > 1): ?><a class="button secondary" href="?user_id=<?= (int)$id ?>&amp;page=<?= $page - 1 ?>">Zurück</a><?php endif; ?><span>Seite <?= $page ?></span><?php if ($page * 24 < $total): ?><a class="button secondary" href="?user_id=<?= (int)$id ?>&amp;page=<?= $page + 1 ?>">Weiter</a><?php endif; ?></nav><?php endif; ?></section>
<section class="section"><h2>Erfahrungen miteinander.</h2><?php if ($ratings === []): ?><p class="panel">Hier erscheinen nachvollziehbare Bewertungen nach abgeschlossenen Transaktionen.</p><?php else: ?><?php foreach ($ratings as $rating): ?><article class="panel"><p><?= (int)$rating['stars'] ?> / 5 · <?= h($rating['username']) ?> · <?= h(substr((string)$rating['created_at'], 0, 10)) ?></p><p><?= nl2br(h($rating['comment'])) ?></p></article><?php endforeach; ?><?php endif; ?></section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
