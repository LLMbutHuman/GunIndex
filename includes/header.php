<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$viewer = current_user($pdo);
$brandName = site_setting($pdo, 'brand_name', 'GunIndex.eu');
$tagline = i18n_setting($pdo, 'tagline', 'Besonderes finden. Gemeinsam weitergeben.');
$accent = site_setting($pdo, 'accent', '#35e6bd');
$secondary = site_setting($pdo, 'secondary', '#ffb86c');
$accent = preg_match('/\A#[0-9a-fA-F]{6}\z/', $accent) ? $accent : '#35e6bd';
$secondary = preg_match('/\A#[0-9a-fA-F]{6}\z/', $secondary) ? $secondary : '#ffb86c';
$motion = site_setting($pdo, 'animation_mode', 'full');
$motion = in_array($motion, ['full', 'reduced', 'off'], true) ? $motion : 'reduced';
$unreadNotices=$viewer?notification_unread_count($pdo,(int)$viewer['id']):0;
$marketCountry=geo_country();$marketCountryLabels=geo_countries();
$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : t('Entdecken');
$pageDescription = isset($pageDescription) && is_string($pageDescription) ? $pageDescription : $tagline;
$seoPage = ['index'=>false, 'canonical'=>null, 'structured'=>null];
if (function_exists('seo_page') && in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET','HEAD'], true)
    && (http_response_code() === false || http_response_code() === 200)) {
    $seoPage = seo_page(site_settings($pdo), (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), $seoListing ?? null);
}
header('X-Robots-Tag: ' . ($seoPage['index'] ? 'index, follow' : 'noindex, nofollow'));
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
        if(isset($item['origin_zip'],$item['origin_country']))$url.='&origin_zip='.rawurlencode($item['origin_zip']).'&origin_country='.rawurlencode($item['origin_country']);
        $price = $auction && isset($item['current_bid_cents']) ? (int)$item['current_bid_cents'] : (int)$item['price_cents'];
        ?>
        <article class="listing-card reveal">
            <a class="listing-picture" href="<?= h($url) ?>" tabindex="-1" aria-hidden="true">
                <img src="/marketplace/image.php?id=<?= (int)$item['id'] ?>&amp;position=0&amp;size=thumb" alt="" width="480" height="360" loading="lazy" decoding="async">
                <span class="picture-grain"></span>
                <span class="badge <?= $auction ? 'accent' : 'muted' ?>"><?= !empty($item['sponsored']) ? h(t('Gesponsert')) : h($auction ? t('Auktion') : t('Festpreis')) ?></span>
                <span class="picture-arrow"><?= ui_icon('arrow') ?></span>
            </a>
            <div class="listing-copy">
                <p class="eyebrow"><?= h(i18n_category($GLOBALS['pdo'],$item)) ?></p>
                <h3><a href="<?= h($url) ?>"><?= h($item['title']) ?></a></h3>
                <div class="listing-meta"><span><?= ui_icon('pin') ?><?= h(($item['country_code']??'DE').' · '.($item['city']??'')) ?></span><span><?= h(t($item['condition_label'] ?? t('used'))) ?></span></div>
                <?php if(isset($item['origin_zip'])):?><p class="help"><?=h(geo_distance_label($item['distance_km']??null))?></p><?php endif;?><div class="listing-bottom"><strong><?= h(money_eur($price)) ?></strong><span><?= $auction ? t('Aktuelles Gebot') : t('Angebotspreis') ?></span></div>
            </div>
        </article>
        <?php
    }
    function ui_empty(string $title, string $body): void {
        ?><div class="empty-state panel reveal"><span class="empty-mark"><?= ui_icon('spark') ?></span><h3><?= h(t($title)) ?></h3><p><?= h(t($body)) ?></p><a class="button secondary" href="/user/create-listing.php">Jetzt etwas weitergeben <?= ui_icon('arrow') ?></a></div><?php
    }
}
?>
<!doctype html>
<html lang="<?=h(i18n_locale())?>" data-label-pause-motion="<?=h(t('Animationen pausieren'))?>" data-label-resume-motion="<?=h(t('Animationen fortsetzen'))?>" data-label-theme-dark="<?=h(t('Dunkles Farbschema aktivieren'))?>" data-label-theme-light="<?=h(t('Helles Farbschema aktivieren'))?>" data-label-countdown-expired="<?=h(t('Frist abgelaufen · Abschluss wird verarbeitet'))?>" data-label-days="<?=h(t('Tage'))?>" data-theme="dark" data-motion="<?= h($motion) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <title><?= h($pageTitle) ?> · <?= h($brandName) ?></title>
    <meta name="description" content="<?= h($pageDescription) ?>">
    <meta name="robots" content="<?= $seoPage['index'] ? 'index, follow' : 'noindex, nofollow' ?>">
    <?php if ($seoPage['canonical'] !== null): ?><link rel="canonical" href="<?= h($seoPage['canonical']) ?>"><meta property="og:url" content="<?= h($seoPage['canonical']) ?>"><?php endif; ?>
    <?php if ($seoPage['structured'] !== null): ?><script type="application/ld+json"><?= seo_json($seoPage['structured']) ?></script><?php endif; ?>
    <meta property="og:title" content="<?= h($pageTitle . ' · ' . $brandName) ?>">
    <meta property="og:description" content="<?= h($pageDescription) ?>">
    <meta property="og:type" content="website">
    <link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/assets/css/international.css">
    <style>:root{--accent:<?= h($accent) ?>;--secondary:<?= h($secondary) ?>}</style>
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content"> <?=h(t('Zum Inhalt'))?> </a>
<div class="ambient-field" aria-hidden="true"></div>
<header class="site-header">
    <div class="nav-shell">
        <a class="brand" href="/marketplace/index.php" aria-label="<?= h($brandName) ?> – Startseite"><span class="brand-mark" aria-hidden="true"><span></span></span><span><?= h($brandName) ?></span></a>
        <nav class="desktop-nav" aria-label="<?=h(t('Hauptnavigation'))?>"><?php if($viewer): ?><a href="/user/notifications.php"><?= h(t('Benachrichtigungen')) ?><?= $unreadNotices ? ' ('.min(99,$unreadNotices).')' : '' ?></a><?php endif; ?><a href="/marketplace/search.php"> <?=h(t('Entdecken'))?> </a><a href="/marketplace/search.php?type=auction"> <?=h(t('Auktionen'))?> </a><a href="/marketplace/policy.php"> <?=h(t('So funktioniert’s'))?> </a></nav>
        <div class="nav-actions">
            <button class="icon-button theme-toggle" type="button" aria-label="<?=h(t('Farbschema wechseln'))?>" title="<?=h(t('Farbschema wechseln'))?>" hidden><?= ui_icon('sun') ?></button>
            <a class="account-link" aria-label="<?=h($viewer ? t('Mein Konto').': '.$viewer['username'] : t('Anmelden'))?>" href="<?= $viewer ? '/user/dashboard.php' : '/auth/login.php' ?>"><?= ui_icon('account') ?><span><?= $viewer ? h($viewer['username']) : t('Anmelden') ?></span></a>
            <a class="button small sell-link" href="/user/create-listing.php"><?= ui_icon('plus') ?><?=h(t('Inserieren'))?></a>
        </div>
        <details class="mobile-menu"><summary aria-label="<?=h(t('Menü öffnen'))?>"><?= ui_icon('menu') ?></summary><nav aria-label="Mobile Navigation"><?php if($viewer): ?><a href="/user/notifications.php"><?= h(t('Benachrichtigungen')) ?> (<?= $unreadNotices ?>)</a><a href="/user/messages.php"><?= h(t('Nachrichten')) ?></a><?php endif; ?><a href="/marketplace/search.php"> <?=h(t('Entdecken'))?> </a><a href="/marketplace/search.php?type=auction"> <?=h(t('Auktionen'))?> </a><a href="/user/create-listing.php"> <?=h(t('Inserat erstellen'))?> </a><a href="<?= $viewer ? '/user/dashboard.php' : '/auth/login.php' ?>"><?= $viewer ? t('Mein Konto') : t('Anmelden') ?></a><?php if ($viewer && (!empty($viewer['is_admin']) || (function_exists('rbac_requires_mfa') && rbac_requires_mfa($pdo,(int)$viewer['id'])))): ?><a href="/admin/index.php"> <?=h(t('Administration'))?> </a><?php endif; ?><a href="/marketplace/policy.php"> <?=h(t('Plattformregeln'))?> </a></nav></details>
    </div>
    <div class="market-tools page-width"><details class="market-switcher"><summary><?=h(t('Ländermarkt'))?>: <?=h($marketCountryLabels[$marketCountry])?> · <?=h(i18n_locales()[i18n_locale()])?></summary><form action="/user/language.php" method="post"><?=csrf_field()?><input type="hidden" name="return_path" value="<?=h($_SERVER['REQUEST_URI']??'/')?>"><label class="field"><?=h(t('Sprache'))?><select name="locale"><?php foreach(i18n_locales() as $code=>$label):?><option value="<?=h($code)?>" <?=i18n_locale()===$code?'selected':''?>><?=h($label)?></option><?php endforeach;?></select></label><label class="field"><?=h(t('Ländermarkt'))?><select name="market_country"><?php foreach(geo_active_countries($pdo) as $code=>$label):?><option value="<?=h($code)?>" <?=$marketCountry===$code?'selected':''?>><?=h($label.' ('.$code.')')?></option><?php endforeach;?></select></label><button class="button small" type="submit"><?=h(t('Übernehmen'))?></button></form></details></div>
</header>
<?php if (str_ends_with((string)getenv('DB_DATABASE'), '_test')): ?><div class="development-note">Entwicklungsbetrieb mit synthetischen Testdaten · keine produktiven Zahlungen</div><?php endif; ?>
<div class="site-main" id="main-content" tabindex="-1">


<?php
if(!function_exists('ui_country_ads')){
    function ui_country_ads(PDO $pdo,string $country,?int $category=null):void {
        if(!function_exists('business_banners'))return;
        $campaigns=business_banners($pdo,$country,$category,2);if(!$campaigns)return;
        ?><section class="page-width country-advertisements" aria-label="<?=h(t('Werbung'))?>"><p class="eyebrow"><?=h(t('Werbung'))?> · <?=h(geo_countries()[$country]??$country)?></p><div class="country-banner-grid"><?php foreach($campaigns as $campaign):?><a class="country-banner" href="<?=h($campaign['click_url']??('/marketplace/banner-click.php?id='.(int)$campaign['id'].'&country='.rawurlencode($country)))?>"><img src="<?=h($campaign['image_url']??('/marketplace/banner.php?id='.(int)$campaign['id'].'&country='.rawurlencode($country)))?>" alt="<?=h($campaign['title'])?>" loading="lazy" width="1200" height="300"><span><?=h(t('Gesponsert'))?> · <?=h($campaign['title'])?></span></a><?php endforeach;?></div></section><?php
    }
    function ui_sponsored_cards(PDO $pdo,string $country,?int $category,string $kind,int $limit):void {
        if(!function_exists('business_sponsored'))return;$items=business_sponsored($pdo,$country,$category,$kind,$limit);if(!$items)return;
        ?><section class="section page-width"><div class="section-heading"><h2><?=h(t('Gesponsert'))?></h2><p class="help"><?=h(geo_countries()[$country]??$country)?></p></div><div class="cards"><?php foreach($items as $item){$item['sponsored']=true;ui_listing_card($item);}?></div></section><?php
    }
}
?>
