<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$admin = require_permission($pdo,'site.manage');
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    abort_request(405, t('account.method'));
}
$pageTitle = t('admin_settings.title');
$definitions = site_setting_definitions();
$values = site_settings($pdo);
$revision = site_settings_revision($values);
$error = '';
$success = '';
if ($method === 'POST') {
    require_csrf();
    $submitted = [];
    foreach (array_keys($definitions) as $key) {
        $submitted[$key] = $_POST[$key] ?? $values[$key];
        if (is_string($submitted[$key])) $values[$key] = $submitted[$key];
    }
    $receivedRevision = $_POST['_revision'] ?? null;
    try {
        if (!is_string($receivedRevision) || !preg_match('/\A[a-f0-9]{64}\z/', $receivedRevision)) throw new InvalidArgumentException(t('admin_settings.reload_required'));
        $changed = site_settings_save($pdo, (int)$admin['id'], $submitted, $receivedRevision);
        $values = site_settings($pdo, true);
        $revision = site_settings_revision($values);
        $success = t($changed === [] ? 'admin_settings.current' : 'admin_settings.saved');
    } catch (InvalidArgumentException | DomainException $exception) {
        http_response_code($exception instanceof DomainException ? 409 : 422);
        $error = $exception->getMessage();
        if (is_string($receivedRevision)) $revision = $receivedRevision;
    } catch (Throwable) {
        http_response_code(503);
        $error = t('admin_settings.unavailable');
    }
}
include __DIR__ . '/../includes/header.php';
$groups = [
    'admin_settings.brand' => ['brand_name', 'tagline', 'hero_title', 'hero_text', 'contact_email'],
    'admin_settings.appearance' => ['accent', 'secondary', 'animation_mode'],
    'admin_settings.availability' => ['registration_enabled', 'maintenance_enabled', 'seo_indexing_enabled'],
    'admin_settings.paypal' => ['manual_paypal_enabled','manual_paypal_recipient','manual_paypal_approved','manual_tax_reviewed','manual_tax_bps'],
    'admin_settings.business' => ['business_promotions_enabled','dealer_profiles_enabled','business_reservation_minutes'],
    'admin_settings.privacy' => ['verification_enabled','verification_review_approved','verification_retention_days','privacy_contact_email','legal_publication_enabled','legal_registration_documents','legal_existing_account_required','legal_checkout_documents'],
    'admin_settings.operator' => ['finance_enabled','operator_legal_name','operator_address_line1','operator_address_line2','operator_postcode','operator_city','operator_country','operator_email','operator_vat_id','operator_tax_number','operator_registration','invoice_prefix'],
    'admin_settings.fees' => ['fee_auction_listing_cents', 'fee_bump_single_cents', 'fee_bump_pack_cents', 'fee_category_top_cents', 'fee_home_top_cents', 'fee_banner_monthly_cents'],
    'auction_billing.heading' => ['auction_fees_enabled','auction_fees_approved'],
];
?>
<main class="container admin-page">
    <div class="page-heading"><div><h1><?=h($pageTitle)?></h1></div><a href="/marketplace/index.php" class="button secondary"><?=h(t('admin_settings.view'))?> ↗</a></div>
    <div class="admin-layout"><?php include __DIR__ . '/../includes/admin-nav.php'; ?><div class="admin-content">
        <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?>
        <?php if ($success !== ''): ?><div class="notice success" role="status"><?= h($success) ?></div><?php endif; ?>
        <form method="post" action="/admin/settings.php" class="settings-form">
            <?= csrf_field() ?><input type="hidden" name="_revision" value="<?= h($revision) ?>">
            <?php foreach ($groups as $heading => $keys): ?><section class="panel"><h2><?= h(t($heading)) ?></h2>
                <?php if ($heading === 'admin_settings.fees'): ?><p class="notice"><?=h(t('admin_settings.fees_help'))?></p><?php endif; ?>
                <div class="form-grid">
                    <?php foreach ($keys as $key): $definition = $definitions[$key]; ?>
                        <div class="field<?= in_array($key, ['hero_text', 'hero_title'], true) ? ' full-width' : '' ?>">
                            <?php if ($definition['type'] === 'boolean'): ?>
                                <input type="hidden" name="<?= h($key) ?>" value="0"><label class="checkbox"><input type="checkbox" name="<?= h($key) ?>" value="1"<?= $values[$key] === '1' ? ' checked' : '' ?>> <?= h(t($definition['label'])) ?></label>
                                <?php if ($key === 'auction_fees_enabled'): ?><small><?=h(t('auction_billing.setup_help'))?></small><?php endif; ?>
                                <?php if ($key === 'maintenance_enabled'): ?><small><?=h(t('maintenance.help'))?></small><?php endif; ?>
                            <?php else: ?>
                                <label for="setting-<?= h($key) ?>"><?= h(t($definition['label'])) ?></label>
                                <?php if($definition['type']==='document_codes'):?><small><?=h(t('consent.configuration_help'))?></small><?php endif;?>
                                <?php if ($definition['type'] === 'animation'): ?><select id="setting-<?= h($key) ?>" name="<?= h($key) ?>"><?php foreach (['full', 'reduced', 'off'] as $mode): ?><option value="<?= h($mode) ?>"<?= $values[$key] === $mode ? ' selected' : '' ?>><?= h(t('admin_settings.animation_'.$mode)) ?></option><?php endforeach; ?></select><small><?=h(t('admin_settings.animation_help'))?></small>
                                <?php elseif ($definition['type'] === 'country'): ?><select id="setting-<?= h($key) ?>" name="<?= h($key) ?>"><?php foreach (geo_countries() as $code => $label): ?><option value="<?= h($code) ?>"<?= $values[$key] === $code ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select>
                                <?php elseif ($definition['type'] === 'integer'): ?><input id="setting-<?= h($key) ?>" name="<?= h($key) ?>" type="number" value="<?= h($values[$key]) ?>" min="<?= (int)($definition['min_value'] ?? 0) ?>" max="<?= (int)($definition['max_value'] ?? 10000) ?>" step="1" required><small><?= h(t($key === 'business_reservation_minutes' ? 'admin_settings.reservation_help' : 'admin_settings.retention_help')) ?></small>
                                <?php elseif ($key === 'hero_text'): ?><textarea id="setting-<?= h($key) ?>" name="<?= h($key) ?>" rows="4" maxlength="500"><?= h($values[$key]) ?></textarea>
                                <?php else: ?><input id="setting-<?= h($key) ?>" name="<?= h($key) ?>" type="<?= $definition['type'] === 'color' ? 'color' : ($definition['type'] === 'email' ? 'email' : (in_array($definition['type'], ['cents','tax','integer'], true) ? 'number' : 'text')) ?>" value="<?= h($values[$key]) ?>"<?= isset($definition['max']) ? ' maxlength="' . (int)$definition['max'] . '"' : '' ?><?= in_array($definition['type'], ['cents','tax','integer'], true) ? ' min="0" max="' . ($definition['type'] === 'tax' ? '10000' : '9999999') . '" step="1" inputmode="numeric"' : '' ?><?= ($definition['min'] ?? 0) > 0 ? ' required' : '' ?>><?php if (in_array($definition['type'], ['cents','tax','integer'], true)): ?><small><?= h(t($definition['type'] === 'tax' ? 'admin_settings.tax_help' : 'admin_settings.cents_help')) ?></small><?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section><?php endforeach; ?>
            <div class="actions"><button class="button" type="submit"><?=h(t('admin_settings.save'))?></button><a class="button secondary" href="/admin/settings.php"><?=h(t('admin_settings.reload'))?></a></div>
        </form>
    </div></div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
