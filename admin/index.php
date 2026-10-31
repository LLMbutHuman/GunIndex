<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$admin = require_admin($pdo);
$pageTitle = 'Administration';
$users = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$listings = (int)$pdo->query("SELECT COUNT(*) FROM market_listings WHERE status='active'")->fetchColumn();
$auctions = (int)$pdo->query("SELECT COUNT(*) FROM market_listings WHERE status='active' AND type='auction'")->fetchColumn();
$categories = (int)$pdo->query('SELECT COUNT(*) FROM market_categories')->fetchColumn();
$reviews = (int)$pdo->query("SELECT COUNT(*) FROM market_listings WHERE status='review'")->fetchColumn();
$recent = $pdo->query('SELECT action_type, target_id, created_at FROM admin_logs ORDER BY id DESC LIMIT 8')->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<main class="container admin-page">
 <div class="page-heading"><div><p class="eyebrow">Deine Plattform. Deine Kontrolle.</p><h1>Alles im Blick.</h1><p>Inhalte organisieren, den Auftritt gestalten und Änderungen nachvollziehen.</p></div><a class="button" href="/admin/settings.php">Website gestalten ↗</a></div>
 <div class="admin-layout"><?php include __DIR__ . '/../includes/admin-nav.php'; ?><div class="admin-content">
  <section class="stats-grid" aria-label="Aktuelle Datenbankzahlen"><?php foreach ([[$users, 'Benutzerkonten'], [$listings, 'Aktive Inserate'], [$auctions, 'Aktive Auktionen'], [$categories, 'Kategorien']] as [$count, $label]): ?><article class="stat-card panel"><span class="eyebrow"><?= h($label) ?></span><strong><?= h((string)$count) ?></strong><span class="muted">Aktueller Datenbestand</span></article><?php endforeach; ?></section>
  <?php if ($reviews > 0): ?><section class="notice error"><h2><?= $reviews ?> Auktion<?= $reviews === 1 ? '' : 'en' ?> benötigt Prüfung</h2><p>Ein Abschlusskonflikt wurde isoliert. Bestehende Gebote bleiben erhalten; andere Auktionen können weiter abgeschlossen werden. Prüfe die protokollierten Auktionsereignisse vor einer Korrektur.</p><a href="/admin/listings.php?status=review">Zur Prüfwarteschlange ↗</a></section><?php endif; ?>
  <section class="panel"><p class="eyebrow">Schneller gestalten</p><h2>Ein Auftritt mit eigener Handschrift.</h2><p>Website-Namen, Startseitentexte, Akzentfarben und die Intensität der Animationen kannst du direkt hier ändern. Kategorien lassen sich ohne Quellcode ergänzen und verschieben.</p><div class="actions"><a class="button" href="/admin/settings.php">Marke & Website</a><a class="button secondary" href="/admin/categories.php">Kategorien organisieren</a></div></section>
  <section class="panel"><h2>Betriebsstatus</h2><div class="status-grid"><p><span class="status">Adminzugriff geschützt</span><br>Aktuelle Berechtigung und verpflichtende MFA werden geprüft.</p><p><span class="status">Zahlungsbetrieb deaktiviert</span><br>Es werden keine unbestätigten Zahlungen als bezahlt verbucht.</p><p><span class="status">Identitätsdokumente deaktiviert</span><br>Die Sammlung sensibler Dokumente bleibt gesperrt.</p></div><p class="muted">Ein technischer Status ersetzt keine rechtliche, steuerliche oder produktive Freigabe.</p></section>
  <section class="panel"><div class="section-heading"><h2>Zuletzt geändert</h2><a href="/admin/logs.php">Protokoll öffnen ↗</a></div><?php if ($recent === []): ?><p class="empty-state">Noch keine administrativen Änderungen protokolliert.</p><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Aktion</th><th>Bezug</th><th>Zeitpunkt (UTC)</th></tr></thead><tbody><?php foreach ($recent as $event): ?><tr><td><?= h($event['action_type']) ?></td><td><?= $event['target_id'] === null ? 'Website' : '#' . h((string)$event['target_id']) ?></td><td><?= h($event['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
 </div></div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
