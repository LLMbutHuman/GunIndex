<?php
declare(strict_types=1);
$adminLinks = [
    '/admin/index.php' => ['Übersicht', '01'],
    '/admin/settings.php' => ['Marke & Website', '02'],
    '/admin/categories.php' => ['Kategorien', '03'],
    '/admin/users.php' => ['Benutzerkonten', '04'],
    '/admin/listings.php' => ['Inserate & Auktionen', '05'],
    '/admin/ratings.php' => ['Bewertungen', '06'],
    '/admin/payments.php' => ['Zahlungseingänge', '07'],
    '/admin/logs.php' => ['Änderungsprotokoll', '07'],
];
$adminPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
?>
<aside class="admin-nav panel" aria-label="Administration">
    <p class="eyebrow">Control room</p>
    <nav aria-label="Verwaltungsbereiche"><?php foreach ($adminLinks as $path => [$label, $number]): ?><a href="<?= h($path) ?>"<?= $path === $adminPath ? ' aria-current="page"' : '' ?>><span aria-hidden="true"><?= h($number) ?></span> <?= h($label) ?></a><?php endforeach; ?></nav>
    <p class="muted">Änderungen werden serverseitig geprüft und protokolliert.</p>
    <a href="/marketplace/index.php">Zur Website ↗</a>
</aside>
