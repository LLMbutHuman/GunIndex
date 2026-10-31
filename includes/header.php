<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$viewer = current_user($pdo);
$brandName = site_setting($pdo, 'brand_name', 'GunIndex.eu');
$tagline = site_setting($pdo, 'tagline', 'Besonderes finden. Gemeinsam weitergeben.');
$accent = site_setting($pdo, 'accent', '#35e6bd');
$secondary = site_setting($pdo, 'secondary', '#ffb86c');
$accent = preg_match('/\A#[0-9a-fA-F]{6}\z/', $accent) ? $accent : '#35e6bd';
$secondary = preg_match('/\A#[0-9a-fA-F]{6}\z/', $secondary) ? $secondary : '#ffb86c';
$motion = site_setting($pdo, 'animation_mode', 'full');
$motion = in_array($motion, ['full', 'reduced', 'off'], true) ? $motion : 'reduced';
$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : 'Entdecken';
$pageDescription = isset($pageDescription) && is_string($pageDescription) ? $pageDescription : $tagline;
if (!function_exists('ui_icon')) {
    function ui_icon(string $name, string $class = ''): string {
        $paths = [
            'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/>',
            'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/>',
            'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'account' => '<circle cx="12" cy="8" r="4"/><path d="M4 22v-2a8 8 0 0 1 16 0v2"/>',
            'pin' => '<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
            'heart' => '<path d="m12 21-8-8C-1 8 6 1 12 7c6-6 13 1 8 6Z"/>',
            'shield' => '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z"/><path d="m8 12 3 3 5-6"/>',
            'spark' => '<path d="m12 2 2.7 7.3L22 12l-7.3 2.7L12 22l-2.7-7.3L2 12l7.3-2.7Z"/>',
            'box' => '<path d="m12 3 9 5-9 5-9-5 9-5Zm-9 5v9l9 5 9-5V8M12 13v9M7.5 5.5l9 5"/>',
            'chevron' => '<path d="m9 5 7 7-7 7"/>',
            'check' => '<path d="m5 12 4 4L19 6"/>',
        ];
        return '<svg class="icon ' . h($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['box']) . '</svg>';
    }
    function ui_listing_card(array $item): void {
        $auction = ($item['type'] ?? '') === 'auction';
        $url = '/marketplace/' . ($auction ? 'view-auction' : 'view-item') . '.php?id=' . (int)$item['id'];
        $price = $auction && isset($item['current_bid_cents']) ? (int)$item['current_bid_cents'] : (int)$item['price_cents'];
        ?>
        <article class="listing-card reveal">
            <a class="listing-picture" href="<?= h($url) ?>" tabindex="-1" aria-hidden="true">
                <img src="/marketplace/image.php?id=<?= (int)$item['id'] ?>&amp;position=0&amp;size=thumb" alt="" width="480" height="360" loading="lazy" decoding="async">
                <span class="picture-grain"></span>
                <span class="badge <?= $auction ? 'accent' : 'muted' ?>"><?= $auction ? 'Auktion' : 'Festpreis' ?></span>
                <span class="picture-arrow"><?= ui_icon('arrow') ?></span>
            </a>
            <div class="listing-copy">
                <p class="eyebrow"><?= h($item['category_name'] ?? 'Entdeckung') ?></p>
                <h3><a href="<?= h($url) ?>"><?= h($item['title']) ?></a></h3>
                <div class="listing-meta"><span><?= ui_icon('pin') ?><?= h($item['city'] ?? '') ?></span><span><?= h($item['condition_label'] ?? 'gebraucht') ?></span></div>
                <div class="listing-bottom"><strong><?= h(money_eur($price)) ?></strong><span><?= $auction ? 'Aktuelles Gebot' : 'Angebotspreis' ?></span></div>
            </div>
        </article>
        <?php
    }
    function ui_empty(string $title, string $body): void {
        ?><div class="empty-state panel reveal"><span class="empty-mark"><?= ui_icon('spark') ?></span><h3><?= h($title) ?></h3><p><?= h($body) ?></p><a class="button secondary" href="/user/create-listing.php">Jetzt etwas weitergeben <?= ui_icon('arrow') ?></a></div><?php
    }
}
?>
<!doctype html>
<html lang="de" data-theme="dark" data-motion="<?= h($motion) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <title><?= h($pageTitle) ?> · <?= h($brandName) ?></title>
    <meta name="description" content="<?= h($pageDescription) ?>">
    <meta property="og:title" content="<?= h($pageTitle . ' · ' . $brandName) ?>">
    <meta property="og:description" content="<?= h($pageDescription) ?>">
    <meta property="og:type" content="website">
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>:root{--accent:<?= h($accent) ?>;--secondary:<?= h($secondary) ?>}</style>
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content">Zum Inhalt</a>
<div class="ambient-field" aria-hidden="true"></div>
<header class="site-header">
    <div class="nav-shell">
        <a class="brand" href="/marketplace/index.php" aria-label="<?= h($brandName) ?> – Startseite"><span class="brand-mark" aria-hidden="true"><span></span></span><span><?= h($brandName) ?></span></a>
        <nav class="desktop-nav" aria-label="Hauptnavigation"><a href="/marketplace/search.php">Entdecken</a><a href="/marketplace/search.php?type=auction">Auktionen</a><a href="/marketplace/policy.php">So funktioniert’s</a></nav>
        <div class="nav-actions">
            <button class="icon-button theme-toggle" type="button" aria-label="Farbschema wechseln" title="Farbschema wechseln" hidden><?= ui_icon('sun') ?></button>
            <a class="account-link" href="<?= $viewer ? '/user/dashboard.php' : '/auth/login.php' ?>"><?= ui_icon('account') ?><span><?= $viewer ? h($viewer['username']) : 'Anmelden' ?></span></a>
            <a class="button small sell-link" href="/user/create-listing.php"><?= ui_icon('plus') ?>Inserieren</a>
        </div>
        <details class="mobile-menu"><summary aria-label="Menü öffnen"><?= ui_icon('menu') ?></summary><nav aria-label="Mobile Navigation"><a href="/marketplace/search.php">Entdecken</a><a href="/marketplace/search.php?type=auction">Auktionen</a><a href="/user/create-listing.php">Inserat erstellen</a><a href="<?= $viewer ? '/user/dashboard.php' : '/auth/login.php' ?>"><?= $viewer ? 'Mein Konto' : 'Anmelden' ?></a><?php if ($viewer && !empty($viewer['is_admin'])): ?><a href="/admin/index.php">Administration</a><?php endif; ?><a href="/marketplace/policy.php">Plattformregeln</a></nav></details>
    </div>
</header>
<?php if (str_ends_with((string)getenv('DB_DATABASE'), '_test')): ?><div class="development-note">Entwicklungsbetrieb mit synthetischen Testdaten · keine produktiven Zahlungen</div><?php endif; ?>
<div class="site-main" id="main-content" tabindex="-1">
