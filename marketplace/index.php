<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$pageTitle = t('ember.home_title');
$pageDescription = t('ember.hero_intro');
$categories = array_filter(catalog_categories($pdo), static fn(array $category): bool => $category['parent_id'] === null);
$specialties = array_fill_keys(['jagd-revier','schiesssport','optik','outdoor-camping','pflege-aufbewahrung','sammlerzubehoer'], true);
usort($categories, static fn(array $left, array $right): int => isset($specialties[$right['slug']]) <=> isset($specialties[$left['slug']]));
$newest = market_search($pdo, ['sort' => 'newest'], 1, 8);
$auctions = market_search($pdo, ['type' => 'auction', 'sort' => 'ending'], 1, 4);
$publicBannerPlacement = true;
include __DIR__ . '/../includes/header.php';
?>
<main>
    <section class="hero page-width">
        <div class="hero-copy reveal"><p class="eyebrow"><?=h(t('ember.tagline'))?></p><h1><?= nl2br(h(i18n_setting($pdo, 'hero_title', "Besonderes finden.\nNeues möglich machen."))) ?></h1><p class="hero-description"><?= h(i18n_setting($pdo, 'hero_text', 'Dinge mit Charakter. Ausrüstung für neue Wege. Ein Marktplatz für alles, was seine Geschichte noch nicht zu Ende erzählt hat.')) ?></p><form class="hero-search" action="/marketplace/search.php" method="get"><label class="sr-only" for="hero-query"> <?=h(t('Was möchtest du entdecken?'))?> </label><?= ui_icon('search') ?><input id="hero-query" name="q" data-market-suggest type="search" maxlength="150" placeholder="<?=h(t('Wonach hältst du Ausschau?'))?>" autocomplete="off"><button type="submit"><?=h(t('Suchen'))?></button></form><div class="hero-links"><a href="/marketplace/search.php"><?=h(t('ember.market'))?></a><a href="<?=h(seo_localized_path('/fachwissen', i18n_locale()))?>"><?=h(t('ember.editorial_nav'))?></a><a href="/marketplace/search.php?seller_type=dealer"><?=h(t('ember.dealer_link'))?></a></div></div>
        <div class="hero-art reveal" aria-hidden="true"><div class="orbit orbit-one"></div><div class="orbit orbit-two"></div><div class="orbital-core"><div class="core-grid"></div><span class="core-glyph">↗</span></div><div class="orbit-label label-one"><?= ui_icon('spark') ?><span> <?=h(t('Etwas Besonderes.'))?> </span></div><div class="orbit-label label-two"><?= ui_icon('box') ?><span> <?=h(t('Eine neue Geschichte.'))?> </span></div><div class="art-caption"><span>THE NEXT CHAPTER</span><span>BEGINS WITH A FIND.</span></div><div class="art-coordinates"><?=h(t('01 / ENTDECKEN'))?></div></div>
    </section>
    <div class="manifesto-strip" aria-hidden="true"><div class="manifesto-track">
        <?php for ($copy=0;$copy<2;$copy++): ?>
            <div class="manifesto-copy">
                <span><?=h(t('WENIGER ALLTÄGLICH.'))?></span><span class="strip-star">✳</span>
                <span><?=h(t('MEHR MÖGLICH.'))?></span><span class="strip-star">✳</span>
                <span><?=h(t('GEMEINSAM WEITER.'))?></span>
            </div>
        <?php endfor; ?>
    </div></div>
    <?php banner_ads_render($pdo,geo_country()); ?>
    <?php ui_sponsored_cards($pdo,geo_country(),null,'home_top',2); ?>
    <section class="section page-width"><div class="section-heading reveal"><div><h2><?=h(t('ember.category_heading'))?></h2></div><a class="text-link" href="/marketplace/search.php"><?=h(t('Alles ansehen'))?><?= ui_icon('arrow') ?></a></div><div class="category-grid"><?php foreach ($categories as $i => $category): ?><a class="category-tile reveal" href="/marketplace/search.php?category_id=<?= (int)$category['id'] ?>"><span class="category-symbol"><?= ui_icon(['box','spark','grid','shield','heart','pin'][$i % 6]) ?></span><strong><?= h(i18n_category($pdo,$category)) ?></strong><span class="category-next"><?= ui_icon('arrow') ?></span></a><?php endforeach; ?></div></section>
    <section class="section page-width"><div class="section-heading reveal"><div><h2><?=h(t('ember.latest'))?></h2></div><a class="text-link" href="/marketplace/search.php?sort=newest"><?=h(t('Mehr entdecken'))?><?= ui_icon('arrow') ?></a></div><?php if ($newest['items']): ?><div class="cards"><?php foreach ($newest['items'] as $item) ui_listing_card($item); ?></div><?php else: ui_empty('Der nächste Fund beginnt mit dir.', 'Hier erscheinen neue Angebote aus dem allgemeinen Marktplatz. Sei dabei und gib einem guten Stück eine neue Geschichte.'); endif; ?></section>
    <section class="chapter-banner page-width reveal"><div class="chapter-orbit" aria-hidden="true"></div><div><p class="eyebrow"><?=h(t('Nicht nur Dinge. Möglichkeiten.'))?></p><h2><?=h(t('Dein ungenutztes Stück.'))?><br><?=h(t('Jemand anderes’ Lieblingsfund.'))?></h2><p><?=h(t('Ein Angebot erstellen, die Geschichte erzählen und direkt ins Gespräch kommen.'))?></p></div><a class="button" href="/user/create-listing.php"><?=h(t('Jetzt inserieren'))?><?= ui_icon('plus') ?></a></section>
    <section class="section page-width"><div class="section-heading reveal"><div><h2><?=h(t('ember.ending'))?></h2></div><a class="text-link" href="/marketplace/search.php?type=auction&amp;sort=ending"><?=h(t('Zu den Auktionen'))?><?= ui_icon('arrow') ?></a></div><?php if ($auctions['items']): ?><div class="cards"><?php foreach ($auctions['items'] as $item) ui_listing_card($item); ?></div><?php else: ui_empty('Noch kein Countdown in Sicht.', 'Sobald neue Auktionen veröffentlicht werden, findest du sie hier. Du kannst schon jetzt eine eigene Auktion starten.'); endif; ?></section>
    <section class="principles page-width"><div class="principle reveal"><span>01</span><?= ui_icon('account') ?><h3><?=h(t('Direkt miteinander.'))?></h3><p><?=h(t('Fragen, Details und Absprachen gehören in den geschützten Chat zwischen den Beteiligten.'))?></p></div><div class="principle reveal"><span>02</span><?= ui_icon('clock') ?><h3><?=h(t('Spannung mit Regeln.'))?></h3><p><?=h(t('Private Maximalgebote und faire Verlängerungen machen Auktionen nachvollziehbar.'))?></p></div><div class="principle reveal"><span>03</span><?= ui_icon('shield') ?><h3><?=h(t('Klar statt kompliziert.'))?></h3><p><?=h(t('Allgemeine Waren, erkennbare Zustände und transparente Hinweise zu Funktionen und Kosten.'))?></p></div></section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
