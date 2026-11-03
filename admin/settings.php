<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$admin = require_permission($pdo,'site.manage');
$pageTitle = 'Marke & Website';
$definitions = site_setting_definitions();
$values = site_settings($pdo);
$revision = site_settings_revision($values);
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $submitted = [];
    foreach ($definitions as $key => $definition) {
        $submitted[$key] = $_POST[$key] ?? ($definition['type'] === 'boolean' ? '0' : '');
        if (is_string($submitted[$key])) $values[$key] = $submitted[$key];
    }
    $receivedRevision = $_POST['_revision'] ?? null;
    try {
        if (!is_string($receivedRevision) || !preg_match('/\A[a-f0-9]{64}\z/', $receivedRevision)) throw new InvalidArgumentException('Die Formularversion fehlt. Bitte lade die Seite neu.');
        $changed = site_settings_save($pdo, (int)$admin['id'], $submitted, $receivedRevision);
        $values = site_settings($pdo, true);
        $revision = site_settings_revision($values);
        $success = $changed === [] ? 'Die Einstellungen sind bereits aktuell.' : 'Gespeichert. Die Änderungen sind auf der Website aktiv.';
    } catch (InvalidArgumentException | DomainException $exception) {
        http_response_code($exception instanceof DomainException ? 409 : 422);
        $error = $exception->getMessage();
        if (is_string($receivedRevision)) $revision = $receivedRevision;
    } catch (Throwable) {
        http_response_code(503);
        $error = 'Die Einstellungen konnten gerade nicht gespeichert werden. Bitte erneut versuchen.';
    }
}
include __DIR__ . '/../includes/header.php';
$groups = [
    'Marke & Startseite' => ['brand_name', 'tagline', 'hero_title', 'hero_text', 'contact_email'],
    'Farben & Bewegung' => ['accent', 'secondary', 'animation_mode'],
    'Verfügbarkeit' => ['registration_enabled', 'maintenance_enabled','seo_indexing_enabled'],
    'Manuelle PayPal-Prüfung' => ['manual_paypal_enabled','manual_paypal_recipient','manual_paypal_approved','manual_tax_reviewed','manual_tax_bps'],
    'Regionale Geschäftsleistungen' => ['business_promotions_enabled','dealer_profiles_enabled','business_reservation_minutes'],
    'Datenschutz & Identitätsprüfung' => ['verification_enabled','verification_review_approved','verification_retention_days','privacy_contact_email','legal_publication_enabled','legal_registration_documents','legal_checkout_documents'],
    'Rechnungen & Betreiberangaben' => ['finance_enabled','operator_legal_name','operator_address_line1','operator_address_line2','operator_postcode','operator_city','operator_country','operator_email','operator_vat_id','operator_tax_number','operator_registration','invoice_prefix'],
    'Geplante Plattformpreise' => ['fee_auction_listing_cents', 'fee_bump_single_cents', 'fee_bump_pack_cents', 'fee_category_top_cents', 'fee_home_top_cents', 'fee_banner_monthly_cents'],
    t('auction_billing.heading') => ['auction_fees_enabled','auction_fees_approved'],
];
?>
<main class="container admin-page">
    <div class="page-heading"><div><p class="eyebrow">Einfach anpassen</p><h1>Deine Marke, bis ins Detail.</h1><p>Alle Felder werden geprüft. Es kann kein ausführbarer HTML- oder JavaScriptcode hinterlegt werden.</p></div><a href="/marketplace/index.php" class="button secondary">Website ansehen ↗</a></div>
    <div class="admin-layout"><?php include __DIR__ . '/../includes/admin-nav.php'; ?><div class="admin-content">
        <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?>
        <?php if ($success !== ''): ?><div class="notice success" role="status"><?= h($success) ?></div><?php endif; ?>
        <form method="post" action="/admin/settings.php" class="settings-form">
            <?= csrf_field() ?><input type="hidden" name="_revision" value="<?= h($revision) ?>">
            <?php foreach ($groups as $heading => $keys): ?><section class="panel"><h2><?= h($heading) ?></h2>
                <?php if ($heading === 'Geplante Plattformpreise'): ?><p class="notice">Die Beträge werden in Cent gespeichert. Das Ändern dieser Vorschau aktiviert keine kostenpflichtige Leistung. Bereits verbindlich gebuchte Leistungen dürfen nicht rückwirkend verändert werden.</p><?php endif; ?>
                <div class="form-grid">
                    <?php foreach ($keys as $key): $definition = $definitions[$key]; ?>
                        <div class="field<?= in_array($key, ['hero_text', 'hero_title'], true) ? ' full-width' : '' ?>">
                            <?php if ($definition['type'] === 'boolean'): ?>
                                <input type="hidden" name="<?= h($key) ?>" value="0"><label class="checkbox"><input type="checkbox" name="<?= h($key) ?>" value="1"<?= $values[$key] === '1' ? ' checked' : '' ?>> <?= h(t($definition['label'])) ?></label>
                                <?php if ($key === 'auction_fees_enabled'): ?><small><?=h(t('auction_billing.setup_help'))?></small><?php endif; ?>
                                <?php if ($key === 'maintenance_enabled'): ?><small>Zeigt einen Hinweis. Laufende Sitzungen oder Auktionen werden nicht automatisch unterbrochen.</small><?php endif; ?>
                            <?php else: ?>
                                <label for="setting-<?= h($key) ?>"><?= h(t($definition['label'])) ?></label>
                                <?php if($definition['type']==='document_codes'):?><small><?=h(t('consent.configuration_help'))?></small><?php endif;?>
                                <?php if ($definition['type'] === 'animation'): ?><select id="setting-<?= h($key) ?>" name="<?= h($key) ?>"><?php foreach (['full' => 'Lebendig', 'reduced' => 'Dezent', 'off' => 'Aus'] as $mode => $label): ?><option value="<?= h($mode) ?>"<?= $values[$key] === $mode ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select><small>Die Systemeinstellung „Bewegung reduzieren“ hat immer Vorrang.</small>
                                <?php elseif ($definition['type'] === 'country'): ?><select id="setting-<?= h($key) ?>" name="<?= h($key) ?>"><?php foreach (geo_countries() as $code => $label): ?><option value="<?= h($code) ?>"<?= $values[$key] === $code ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select>
                                <?php elseif ($definition['type'] === 'integer'): ?><input id="setting-<?= h($key) ?>" name="<?= h($key) ?>" type="number" value="<?= h($values[$key]) ?>" min="<?= (int)($definition['min_value'] ?? 0) ?>" max="<?= (int)($definition['max_value'] ?? 10000) ?>" step="1" required><small><?= $key === 'business_reservation_minutes' ? 'Reservierungsdauer in Minuten. Danach wird ein unbezahlter Platz wieder frei.' : 'Aufbewahrungsdauer in Tagen. Die Identitätsprüfung bleibt ohne geprüfte Aufbewahrungsregeln deaktiviert.' ?></small>
                                <?php elseif ($key === 'hero_text'): ?><textarea id="setting-<?= h($key) ?>" name="<?= h($key) ?>" rows="4" maxlength="500"><?= h($values[$key]) ?></textarea>
                                <?php else: ?><input id="setting-<?= h($key) ?>" name="<?= h($key) ?>" type="<?= $definition['type'] === 'color' ? 'color' : ($definition['type'] === 'email' ? 'email' : (in_array($definition['type'], ['cents','tax','integer'], true) ? 'number' : 'text')) ?>" value="<?= h($values[$key]) ?>"<?= isset($definition['max']) ? ' maxlength="' . (int)$definition['max'] . '"' : '' ?><?= in_array($definition['type'], ['cents','tax','integer'], true) ? ' min="0" max="' . ($definition['type'] === 'tax' ? '10000' : '9999999') . '" step="1" inputmode="numeric"' : '' ?><?= ($definition['min'] ?? 0) > 0 ? ' required' : '' ?>><?php if (in_array($definition['type'], ['cents','tax','integer'], true)): ?><small><?= $definition['type'] === 'tax' ? 'Basispunkte: 1900 entspricht 19 %. Nur den tatsächlich geprüften Steuersatz verwenden.' : 'Integer-Cent, beispielsweise 199 für 1,99 €.' ?></small><?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section><?php endforeach; ?>
            <div class="actions"><button class="button" type="submit">Änderungen speichern</button><a class="button secondary" href="/admin/settings.php">Aktuellen Stand neu laden</a></div>
        </form>
    </div></div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
