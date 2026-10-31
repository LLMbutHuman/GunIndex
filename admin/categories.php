<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$admin = require_admin($pdo);
$pageTitle = 'Kategorien verwalten';
$error = '';
$values = ['id' => '0', 'name' => '', 'slug' => '', 'parent_id' => '0', 'sort_order' => '0', 'is_active' => '1'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach ($values as $key => $default) if (isset($_POST[$key]) && is_string($_POST[$key])) $values[$key] = $_POST[$key];
    try {
        catalog_save_category($pdo, (int)$admin['id'], $_POST);
        redirect('/admin/categories.php?saved=1');
    } catch (InvalidArgumentException | DomainException $exception) {
        http_response_code(422);
        $error = $exception->getMessage();
    } catch (Throwable) {
        http_response_code(503);
        $error = 'Die Kategorie konnte gerade nicht gespeichert werden. Bitte erneut versuchen.';
    }
} elseif (isset($_GET['edit'])) {
    $edit = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $rows = catalog_rows($pdo);
    if ($edit === false || !isset($rows[$edit])) abort_request(404, 'Kategorie nicht gefunden.');
    foreach ($values as $key => $default) $values[$key] = $key === 'parent_id' ? (string)($rows[$edit][$key] ?? 0) : (string)$rows[$edit][$key];
}
$categories = catalog_categories($pdo, false);
include __DIR__ . '/../includes/header.php';
?>
<main class="container admin-page">
    <div class="page-heading"><div><p class="eyebrow">Struktur schafft Orientierung</p><h1>Raum für neue Fundstücke.</h1><p>Unterkategorien hinzufügen, sortieren und verschieben. Die Deaktivierung einer Oberkategorie sperrt auch ihre Nachfahren.</p></div></div>
    <div class="admin-layout"><?php include __DIR__ . '/../includes/admin-nav.php'; ?><div class="admin-content">
        <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?>
        <?php if (($_GET['saved'] ?? '') === '1'): ?><div class="notice success" role="status">Kategorie gespeichert.</div><?php endif; ?>
        <section class="panel"><h2><?= $values['id'] === '0' ? 'Neue Kategorie' : 'Kategorie bearbeiten' ?></h2>
            <form method="post" action="/admin/categories.php"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h($values['id']) ?>">
                <div class="form-grid">
                    <div class="field"><label for="category-name">Name</label><input id="category-name" name="name" required minlength="2" maxlength="100" value="<?= h($values['name']) ?>"></div>
                    <div class="field"><label for="category-slug">Eindeutige Kennung</label><input id="category-slug" name="slug" required maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="outdoor-camping" value="<?= h($values['slug']) ?>"><small>Kleinbuchstaben, Zahlen und Bindestriche.</small></div>
                    <div class="field"><label for="category-parent">Oberkategorie</label><select id="category-parent" name="parent_id"><option value="0">Keine · Hauptkategorie</option><?php foreach ($categories as $category): if ((string)$category['id'] === $values['id']) continue; ?><option value="<?= (int)$category['id'] ?>"<?= (string)$category['id'] === $values['parent_id'] ? ' selected' : '' ?>><?= h(str_repeat('— ', min(20, (int)$category['depth'])) . $category['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="category-sort">Sortierung</label><input id="category-sort" name="sort_order" type="number" min="-10000" max="10000" value="<?= h($values['sort_order']) ?>" required><small>Kleinere Werte erscheinen zuerst.</small></div>
                    <div class="field"><label for="category-active">Sichtbarkeit</label><select id="category-active" name="is_active"><option value="1"<?= $values['is_active'] === '1' ? ' selected' : '' ?>>Aktiv</option><option value="0"<?= $values['is_active'] === '0' ? ' selected' : '' ?>>Deaktiviert</option></select></div>
                </div><div class="actions"><button class="button" type="submit">Kategorie speichern</button><?php if ($values['id'] !== '0'): ?><a class="button secondary" href="/admin/categories.php">Neue Kategorie</a><?php endif; ?></div>
            </form>
        </section>
        <section class="panel"><h2>Kategoriebaum <span class="muted">· <?= count($categories) ?></span></h2><div class="table-wrap"><table class="data-table"><thead><tr><th>Kategorie</th><th>Kennung</th><th>Status</th><th>Aktion</th></tr></thead><tbody>
            <?php foreach ($categories as $category): ?><tr><td><?= h(str_repeat('— ', min(20, (int)$category['depth'])) . $category['name']) ?></td><td><?= h($category['slug']) ?></td><td><span class="status"><?= $category['available'] ? 'Verfügbar' : ($category['is_active'] ? 'Durch Vorfahr gesperrt' : 'Deaktiviert') ?></span></td><td><a href="/admin/categories.php?edit=<?= (int)$category['id'] ?>">Bearbeiten ↗</a></td></tr><?php endforeach; ?>
            <?php if ($categories === []): ?><tr><td colspan="4">Noch keine Kategorien. Lege die erste Hauptkategorie an.</td></tr><?php endif; ?>
        </tbody></table></div></section>
        <p class="muted">Eine Kategorie erteilt keine rechtliche Produktfreigabe. Regulierte Waren bleiben vom allgemeinen Handel ausgeschlossen. Historische Angebote werden nicht automatisch übernommen.</p>
    </div></div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
