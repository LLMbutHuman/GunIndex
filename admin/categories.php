<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$admin = require_permission($pdo,'categories.manage');
$pageTitle = t('classification.category_title');
$error = '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    abort_request(405, t('account.method'));
}
$saved = $method === 'GET' && ($_SESSION['category_saved'] ?? false) === true;
if ($saved) unset($_SESSION['category_saved']);
$removalId = null;
$values = ['id' => '0', 'name' => '', 'slug' => '', 'parent_id' => '0', 'sort_order' => '0', 'is_active' => '1'];
if ($method === 'POST') {
    require_csrf();
    foreach ($values as $key => $default) if (isset($_POST[$key]) && is_string($_POST[$key])) $values[$key] = $_POST[$key];
    try {
        $action = $_POST['action'] ?? 'save';
        if (in_array($action,['deactivate','delete'],true)) {
            $removalId = filter_var($_POST['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: null;
            if ($removalId === null || ($_POST['confirm_removal'] ?? null) !== '1') {
                throw new InvalidArgumentException(t('refine.category_confirm_required'));
            }
            catalog_remove_category($pdo,(int)$admin['id'],$removalId,$action==='delete');
        } elseif ($action === 'save') {
            catalog_save_category($pdo, (int)$admin['id'], $_POST);
        } else {
            throw new InvalidArgumentException(t('account.invalid_input'));
        }
        $_SESSION['category_saved'] = true;
        redirect('/admin/categories.php');
    } catch (InvalidArgumentException | DomainException $exception) {
        http_response_code(422);
        $error = $exception->getMessage();
    } catch (Throwable) {
        http_response_code(503);
        $error = t('classification.category_unavailable');
    }
} elseif (isset($_GET['delete'])) {
    $removalId = filter_var($_GET['delete'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: null;
    if ($removalId === null) abort_request(404,t('admin_geography.category_missing'));
} elseif (isset($_GET['edit'])) {
    $edit = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $rows = catalog_rows($pdo);
    if ($edit === false || !isset($rows[$edit])) abort_request(404, t('admin_geography.category_missing'));
    foreach ($values as $key => $default) $values[$key] = $key === 'parent_id' ? (string)($rows[$edit][$key] ?? 0) : (string)$rows[$edit][$key];
}
$categories = catalog_categories($pdo, false);
$removal = $removalId === null ? null : (catalog_rows($pdo)[$removalId] ?? null);
if ($removalId !== null && $removal === null) abort_request(404,t('admin_geography.category_missing'));
$dependencies = $removal === null ? [] : catalog_category_dependencies($pdo,$removalId);
include __DIR__ . '/../includes/header.php';
?>
<main class="container admin-page">
    <div class="page-heading"><h1><?=h($pageTitle)?></h1><p><?=h(t('classification.category_intro'))?></p></div>
    <div class="admin-layout"><?php include __DIR__ . '/../includes/admin-nav.php'; ?><div class="admin-content">
        <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?>
        <?php if ($saved): ?><div class="notice success" role="status"><?=h(t('classification.category_saved'))?></div><?php endif; ?>
        <?php if ($removal !== null): ?>
            <section class="panel" id="category-removal">
                <h2><?=h(t('refine.category_removal',['name'=>i18n_category($pdo,$removal)]))?></h2>
                <p><?=h(t($dependencies ? 'refine.category_blocked' : 'refine.category_delete_help'))?></p>
                <?php if ($dependencies): ?><ul>
                    <?php foreach ($dependencies as $kind=>$count): ?>
                        <li><?=h(t('refine.category_ref_'.$kind))?> · <?=h((string)$count)?></li>
                    <?php endforeach; ?>
                </ul><?php endif; ?>
                <p><?=h(t('refine.category_deactivate_help'))?></p>
                <?php if ((int)$removal['is_active']===1 || !$dependencies): ?>
                <form method="post" action="/admin/categories.php#category-removal" class="form-grid">
                    <?=csrf_field()?><input type="hidden" name="id" value="<?=$removalId?>">
                    <label><input type="checkbox" name="confirm_removal" value="1" required> <?=h(t('refine.category_confirm'))?></label>
                    <div class="button-row">
                        <?php if ((int)$removal['is_active']===1): ?><button class="button secondary" name="action" value="deactivate"><?=h(t('refine.category_deactivate'))?></button><?php endif; ?>
                        <?php if (!$dependencies): ?><button class="button danger" name="action" value="delete"><?=h(t('refine.category_delete'))?></button><?php endif; ?>
                        <a class="button secondary" href="/admin/categories.php"><?=h(t('Abbrechen'))?></a>
                    </div>
                </form>
                <?php else: ?><a class="button secondary" href="/admin/categories.php?edit=<?=$removalId?>"><?=h(t('Bearbeiten'))?></a><?php endif; ?>
            </section>
        <?php endif; ?>
        <section class="panel"><h2><?=h(t($values['id'] === '0' ? 'classification.category_new' : 'classification.category_edit'))?></h2>
            <form method="post" action="/admin/categories.php"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h($values['id']) ?>">
                <div class="form-grid">
                    <div class="field"><label for="category-name"><?=h(t('classification.category_name'))?></label><input id="category-name" name="name" required minlength="2" maxlength="100" value="<?= h($values['name']) ?>"></div>
                    <div class="field"><label for="category-slug"><?=h(t('classification.category_slug'))?></label><input id="category-slug" name="slug" required maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="outdoor-camping" value="<?= h($values['slug']) ?>"><small><?=h(t('classification.category_slug_help'))?></small></div>
                    <div class="field"><label for="category-parent"><?=h(t('classification.category_parent'))?></label><select id="category-parent" name="parent_id"><option value="0"><?=h(t('classification.category_root'))?></option><?php foreach ($categories as $category): if ((string)$category['id'] === $values['id']) continue; ?><option value="<?= (int)$category['id'] ?>"<?= (string)$category['id'] === $values['parent_id'] ? ' selected' : '' ?>><?= h(str_repeat('— ', min(20, (int)$category['depth'])) . i18n_category($pdo,$category)) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="category-sort"><?=h(t('Sortierung'))?></label><input id="category-sort" name="sort_order" type="number" min="-10000" max="10000" value="<?= h($values['sort_order']) ?>" required><small><?=h(t('classification.category_sort_help'))?></small></div>
                    <div class="field"><label for="category-active"><?=h(t('classification.category_visibility'))?></label><select id="category-active" name="is_active"><option value="1"<?= $values['is_active'] === '1' ? ' selected' : '' ?>><?=h(t('Aktiv'))?></option><option value="0"<?= $values['is_active'] === '0' ? ' selected' : '' ?>><?=h(t('admin_geography.inactive'))?></option></select></div>
                </div><div class="actions"><button class="button" type="submit"><?=h(t('admin_settings.save'))?></button><?php if ($values['id'] !== '0'): ?><a class="button secondary" href="/admin/categories.php"><?=h(t('classification.category_new'))?></a><?php endif; ?></div>
            </form>
        </section>
        <section class="panel"><h2><?=h(t('classification.category_tree'))?> <span class="muted">· <?= count($categories) ?></span></h2><div class="table-wrap"><table class="data-table"><thead><tr><th><?=h(t('Kategorie'))?></th><th><?=h(t('classification.category_slug'))?></th><th><?=h(t('Status'))?></th><th><?=h(t('dashboard.actions'))?></th></tr></thead><tbody>
            <?php foreach ($categories as $category): ?><tr><td><?= h(str_repeat('— ', min(20, (int)$category['depth'])) . i18n_category($pdo,$category)) ?></td><td><?= h($category['slug']) ?></td><td><span class="status"><?=h(t($category['available'] ? 'classification.category_available' : ($category['is_active'] ? 'classification.category_parent_blocked' : 'admin_geography.inactive')))?></span></td><td><div class="button-row"><a href="/admin/categories.php?edit=<?= (int)$category['id'] ?>"><?=h(t('Bearbeiten'))?> ↗</a><a href="/admin/category-attributes.php?category_id=<?=(int)$category['id']?>"><?=h(t('classification.admin'))?></a><a class="button secondary small" href="/admin/categories.php?delete=<?=(int)$category['id']?>#category-removal"><?=h(t('refine.category_manage_removal'))?></a></div></td></tr><?php endforeach; ?>
            <?php if ($categories === []): ?><tr><td colspan="4"><?=h(t('classification.category_empty'))?></td></tr><?php endif; ?>
        </tbody></table></div></section>
        <p class="muted"><?=h(t('classification.category_rules'))?></p>
    </div></div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
