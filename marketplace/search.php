<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$filters = [];
foreach (['q','category_id','type','min_price','max_price','sort'] as $key) {
    if (isset($_GET[$key]) && !is_string($_GET[$key])) abort_request(400, 'Ungültiger Suchfilter.');
    $filters[$key] = isset($_GET[$key]) ? trim($_GET[$key]) : '';
}
$filters['q'] = substr($filters['q'], 0, 150);
$filters['sort'] = in_array($filters['sort'], ['newest','price_asc','price_desc','ending'], true) ? $filters['sort'] : 'newest';
$page = isset($_GET['page']) && is_string($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>10000]]) : 1;
if (!$page) abort_request(400, 'Ungültige Ergebnisseite.');
$categories = catalog_categories($pdo);
$error = '';
try { $results = market_search($pdo, $filters, (int)$page, 24); }
catch (InvalidArgumentException $exception) { http_response_code(422); $error = 'Bitte prüfe Kategorie, Angebotstyp und Preisfilter.'; $results = ['items'=>[], 'total'=>0]; }
$pageTitle = $filters['q'] ? 'Suche: ' . $filters['q'] : 'Entdecken';
include __DIR__ . '/../includes/header.php';
?>
<main class="page-width section search-page"><div class="page-heading reveal"><p class="eyebrow">Deine nächste Geschichte</p><h1><?= $filters['q'] ? 'Auf der Suche nach „' . h($filters['q']) . '“' : 'Entdeckungen mit Charakter.' ?></h1><p>Ein guter Fund ist manchmal nur einen Filter entfernt.</p></div>
<form action="/marketplace/search.php" method="get" class="search-toolbar panel"><div class="search-input"><label class="sr-only" for="query">Suchbegriff</label><?= ui_icon('search') ?><input id="query" name="q" type="search" value="<?= h($filters['q']) ?>" maxlength="150" placeholder="Was möchtest du entdecken?"></div><div class="search-filter-grid"><label class="field">Kategorie<select name="category_id"><option value="">Alle Kategorien</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (string)$category['id'] === $filters['category_id'] ? 'selected' : '' ?>><?= h(str_repeat('— ', min((int)($category['depth'] ?? 0), 12)) . $category['name']) ?></option><?php endforeach; ?></select></label><label class="field">Angebotstyp<select name="type"><option value="">Alle Angebote</option><option value="fixed" <?= $filters['type'] === 'fixed' ? 'selected' : '' ?>>Festpreis</option><option value="auction" <?= $filters['type'] === 'auction' ? 'selected' : '' ?>>Auktion</option></select></label><label class="field">Preis ab (€)<input name="min_price" value="<?= h($filters['min_price']) ?>" inputmode="decimal" placeholder="0,00" maxlength="14"></label><label class="field">Preis bis (€)<input name="max_price" value="<?= h($filters['max_price']) ?>" inputmode="decimal" placeholder="Offen" maxlength="14"></label><label class="field">Sortierung<select name="sort"><?php foreach (['newest'=>'Neueste zuerst','price_asc'=>'Preis aufsteigend','price_desc'=>'Preis absteigend','ending'=>'Bald endende Auktionen'] as $value=>$label): ?><option value="<?= h($value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label><button class="button" type="submit">Anzeigen <?= ui_icon('arrow') ?></button></div></form>
<?php if ($error): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?><div class="results-heading"><p><strong><?= (int)$results['total'] ?></strong> <?= (int)$results['total'] === 1 ? 'Entdeckung' : 'Entdeckungen' ?></p><?php if (array_filter($filters, static fn($v) => $v !== '' && $v !== 'newest')): ?><a class="text-link" href="/marketplace/search.php">Filter zurücksetzen</a><?php endif; ?></div>
<?php if ($results['items']): ?><div class="cards"><?php foreach ($results['items'] as $item) ui_listing_card($item); ?></div><?php else: ui_empty('Noch kein passender Fund.', 'Probiere einen anderen Suchbegriff oder lockere die Filter. Neue Angebote können jederzeit dazukommen.'); endif; ?>
<?php $pages = max(1, (int)ceil((int)$results['total'] / 24)); if ($pages > 1): ?><nav class="pagination" aria-label="Ergebnisseiten"><?php if ($page > 1): ?><a class="button secondary" href="?<?= h(http_build_query(array_merge($filters,['page'=>$page-1]))) ?>">Zurück</a><?php endif; ?><span>Seite <?= (int)$page ?> von <?= $pages ?></span><?php if ($page < $pages): ?><a class="button secondary" href="?<?= h(http_build_query(array_merge($filters,['page'=>$page+1]))) ?>">Weiter <?= ui_icon('arrow') ?></a><?php endif; ?></nav><?php endif; ?></main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
