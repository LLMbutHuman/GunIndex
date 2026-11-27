<?php
declare(strict_types=1);
require_once __DIR__ . '/i18n.php';

function site_setting_definitions(): array
{
    return [
        'brand_name' => ['type' => 'text', 'default' => 'GunIndex.eu', 'label' => 'admin_settings.field.brand_name', 'max' => 60, 'min' => 2],
        'tagline' => ['type' => 'text', 'default' => 'Ausrüstung und Fachwissen für Jagd, Schießsport und Outdoor.', 'translation'=>'ember.tagline', 'label' => 'admin_settings.field.tagline', 'max' => 120, 'min' => 0],
        'hero_title' => ['type' => 'text', 'default' => 'Gut ausgerüstet. Klar informiert.', 'translation'=>'ember.hero', 'label' => 'admin_settings.field.hero_title', 'max' => 120, 'min' => 2],
        'hero_text' => ['type' => 'text', 'default' => 'Finde Ausrüstung für Jagd, Schießsport und Outdoor, Optik und Sammlerbedarf. Lies Fachinformationen zum Waffenrecht.', 'translation'=>'ember.hero_intro', 'label' => 'admin_settings.field.hero_text', 'max' => 500, 'min' => 0],
        'accent' => ['type' => 'color', 'default' => '#ff9955', 'label' => 'admin_settings.field.accent'],
        'secondary' => ['type' => 'color', 'default' => '#8faebb', 'label' => 'admin_settings.field.secondary'],
        'animation_mode' => ['type' => 'animation', 'default' => 'full', 'label' => 'admin_settings.field.animation_mode'],
        'maintenance_enabled' => ['type' => 'boolean', 'default' => '0', 'label' => 'maintenance.enable'],
        'seo_indexing_enabled' => ['type' => 'boolean', 'default' => '0', 'label' => 'admin_settings.field.seo_indexing_enabled'],
        'registration_enabled' => ['type' => 'boolean', 'default' => '1', 'label' => 'admin_settings.field.registration_enabled'],
        'contact_email' => ['type' => 'email', 'default' => '', 'label' => 'admin_settings.field.contact_email'],
        'manual_paypal_enabled' => ['type'=>'boolean','default'=>'0','label'=>'admin_settings.field.manual_paypal_enabled'],
        'manual_paypal_recipient' => ['type'=>'email','default'=>'','label'=>'admin_settings.field.manual_paypal_recipient'],
        'manual_paypal_approved' => ['type'=>'boolean','default'=>'0','label'=>'admin_settings.field.manual_paypal_approved'],
        'manual_tax_reviewed' => ['type'=>'boolean','default'=>'0','label'=>'admin_settings.field.manual_tax_reviewed'],
        'manual_tax_bps' => ['type'=>'tax','default'=>'0','label'=>'admin_settings.field.manual_tax_bps'],
        'business_promotions_enabled'=>['type'=>'boolean','default'=>'0','label'=>'admin_settings.field.business_promotions_enabled'],
        'dealer_profiles_enabled'=>['type'=>'boolean','default'=>'1','label'=>'admin_settings.field.dealer_profiles_enabled'],
        'business_reservation_minutes'=>['type'=>'integer','default'=>'1440','label'=>'admin_settings.field.business_reservation_minutes','min_value'=>15,'max_value'=>2880],
        'verification_enabled'=>['type'=>'boolean','default'=>'0','label'=>'admin_settings.field.verification_enabled'],
        'verification_review_approved'=>['type'=>'boolean','default'=>'0','label'=>'admin_settings.field.verification_review_approved'],
        'verification_retention_days'=>['type'=>'integer','default'=>'0','label'=>'admin_settings.field.verification_retention_days','min_value'=>0,'max_value'=>90],
        'privacy_contact_email'=>['type'=>'email','default'=>'','label'=>'admin_settings.field.privacy_contact_email'],
        'legal_publication_enabled'=>['type'=>'boolean','default'=>'0','label'=>'admin_settings.field.legal_publication_enabled'],
        'legal_registration_documents'=>['type'=>'document_codes','default'=>'','label'=>'consent.registration_codes','max'=>809],
        'legal_existing_account_required'=>['type'=>'boolean','default'=>'0','label'=>'account_consent.required_setting'],
        'legal_checkout_documents'=>['type'=>'document_codes','default'=>'','label'=>'consent.checkout_codes','max'=>809],
        'finance_enabled'=>['type'=>'boolean','default'=>'0','label'=>'admin_settings.field.finance_enabled'],
        'auction_fees_enabled'=>['type'=>'boolean','default'=>'0','label'=>'auction_billing.enabled'],
        'auction_fees_approved'=>['type'=>'boolean','default'=>'0','label'=>'auction_billing.approved'],
        'operator_legal_name'=>['type'=>'text','default'=>'','label'=>'admin_settings.field.operator_legal_name','min'=>0,'max'=>150],
        'operator_address_line1'=>['type'=>'text','default'=>'','label'=>'admin_settings.field.operator_address_line1','min'=>0,'max'=>150],
        'operator_address_line2'=>['type'=>'text','default'=>'','label'=>'admin_settings.field.operator_address_line2','min'=>0,'max'=>150],
        'operator_postcode'=>['type'=>'text','default'=>'','label'=>'admin_settings.field.operator_postcode','min'=>0,'max'=>16],
        'operator_city'=>['type'=>'text','default'=>'','label'=>'admin_settings.field.operator_city','min'=>0,'max'=>100],
        'operator_country'=>['type'=>'country','default'=>'DE','label'=>'admin_settings.field.operator_country'],
        'operator_email'=>['type'=>'email','default'=>'','label'=>'admin_settings.field.operator_email'],
        'operator_vat_id'=>['type'=>'text','default'=>'','label'=>'admin_settings.field.operator_vat_id','min'=>0,'max'=>40],
        'operator_tax_number'=>['type'=>'text','default'=>'','label'=>'admin_settings.field.operator_tax_number','min'=>0,'max'=>50],
        'operator_registration'=>['type'=>'text','default'=>'','label'=>'admin_settings.field.operator_registration','min'=>0,'max'=>150],
        'invoice_prefix'=>['type'=>'code','default'=>'GI','label'=>'admin_settings.field.invoice_prefix'],
        'fee_auction_listing_cents' => ['type' => 'cents', 'default' => '50', 'label' => 'admin_settings.field.fee_auction_listing_cents'],
        'fee_bump_single_cents' => ['type' => 'cents', 'default' => '199', 'label' => 'admin_settings.field.fee_bump_single_cents'],
        'fee_bump_pack_cents' => ['type' => 'cents', 'default' => '499', 'label' => 'admin_settings.field.fee_bump_pack_cents'],
        'fee_category_top_cents' => ['type' => 'cents', 'default' => '990', 'label' => 'admin_settings.field.fee_category_top_cents'],
        'fee_home_top_cents' => ['type' => 'cents', 'default' => '1990', 'label' => 'admin_settings.field.fee_home_top_cents'],
        'fee_banner_monthly_cents' => ['type' => 'cents', 'default' => '12900', 'label' => 'admin_settings.field.fee_banner_monthly_cents'],
    ];
}

function site_setting_validate(string $key, mixed $value): string
{
    $definition = site_setting_definitions()[$key] ?? null;
    if ($definition === null || !is_string($value) || !preg_match('//u', $value)) {
        throw new InvalidArgumentException(t('admin_settings.invalid_value'));
    }
    $value = trim($value);
    if($definition['type']==='document_codes'){
        require_once __DIR__.'/consent.php';
        return implode(',',consent_codes($value));
    }
    $valid = match ($definition['type']) {
        'boolean' => in_array($value, ['0', '1'], true),
        'animation' => in_array($value, ['full', 'reduced', 'off'], true),
        'color' => (bool)preg_match('/\A#[a-fA-F0-9]{6}\z/', $value),
        'email' => $value === '' || (strlen($value) <= 190 && !preg_match('/[\r\n]/', $value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false),
        'integer' => (bool)preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/', $value) && (int)$value >= $definition['min_value'] && (int)$value <= $definition['max_value'],
        'country' => in_array($value,['DE','AT','CH','FR','BE','NL','LU','DK','PL','CZ','LI'],true),
        'code' => (bool)preg_match('/\A[A-Z0-9][A-Z0-9-]{0,9}\z/',$value),
        'tax' => (bool)preg_match('/\A(?:0|[1-9][0-9]{0,4})\z/', $value) && (int)$value <= 10000,
        'cents' => (bool)preg_match('/\A(?:0|[1-9][0-9]{0,6})\z/', $value),
        'text' => !preg_match('/[\x00-\x1F\x7F]/u', $value)
            && preg_match('/\A.{' . $definition['min'] . ',' . $definition['max'] . '}\z/us', $value) === 1,
        default => false,
    };
    if (!$valid) throw new InvalidArgumentException(t('admin_settings.invalid_field', ['field'=>t($definition['label'])]));
    return $definition['type'] === 'color' ? strtolower($value) : $value;
}

function site_settings(PDO $pdo, bool $refresh = false): array
{
    static $cache = null;
    $cache ??= new WeakMap();
    if (!$refresh && isset($cache[$pdo])) return $cache[$pdo];
    $definitions = site_setting_definitions();
    $values = array_map(static fn(array $definition): string => $definition['default'], $definitions);
    foreach ($pdo->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll() as $row) {
        if (isset($definitions[$row['setting_key']])) {
            try {
                $values[$row['setting_key']] = site_setting_validate($row['setting_key'], $row['setting_value']);
            } catch (InvalidArgumentException) {
                // Invalid old values cannot inject CSS or bypass availability controls.
            }
        }
    }
    return $cache[$pdo] = $values;
}

function site_setting(PDO $pdo, string $key, string $default = ''): string
{
    return site_settings($pdo)[$key] ?? $default;
}

function site_settings_revision(array $settings): string
{
    ksort($settings);
    return hash('sha256', json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
}

function audit_log(PDO $pdo, int $adminId, string $action, ?int $targetId = null, array $details = []): void
{
    if ($adminId < 1 || !preg_match('/\A[a-z0-9_.-]{1,100}\z/', $action)) throw new InvalidArgumentException('Ungültiger Protokolleintrag.');
    $encoded = json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    if (strlen($encoded) > 8000) throw new InvalidArgumentException('Protokolleintrag ist zu groß.');
    $statement = $pdo->prepare('INSERT INTO admin_logs(admin_id, action_type, target_id, details, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())');
    $statement->execute([$adminId, $action, $targetId, $encoded]);
}

function settings_assert_admin(PDO $pdo, int $actorId): void
{
    if(function_exists('governance_assert_actor')){governance_assert_actor($pdo,$actorId,'site.manage');return;}
    $statement = $pdo->prepare('SELECT is_admin FROM users WHERE id = ? AND is_active=1');
    $statement->execute([$actorId]);
    if ((int)$statement->fetchColumn() !== 1) throw new DomainException(t('admin_settings.permission'));
}

function site_settings_save(PDO $pdo, int $actorId, array $submitted, string $expectedRevision): array
{
    settings_assert_admin($pdo, $actorId);
    $validated = [];
    foreach (site_setting_definitions() as $key => $definition) {
        if (!array_key_exists($key, $submitted)) throw new InvalidArgumentException(t('admin_settings.reload_required'));
        $validated[$key] = site_setting_validate($key, $submitted[$key]);
    }
    $lockName = 'gunindex.settings.' . substr(hash('sha256', (string)$pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 24);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('Die Einstellungen werden gerade bearbeitet. Bitte erneut versuchen.');
    try {
        $pdo->beginTransaction();
        // Acquire the global write barrier before any actor/settings row locks.
        if(function_exists('maintenance_state'))maintenance_state($pdo,true);
        settings_assert_admin($pdo, $actorId);
        $previous = site_settings($pdo, true);
        if (!hash_equals(site_settings_revision($previous), $expectedRevision)) throw new DomainException(t('admin_settings.stale'));
        if(function_exists('maintenance_transition'))maintenance_transition($pdo,$actorId,$previous['maintenance_enabled'],$validated['maintenance_enabled']);
        $changed = [];
        $statement = $pdo->prepare('INSERT INTO system_settings(setting_key, setting_value, updated_at) VALUES (?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=UTC_TIMESTAMP()');
        foreach ($validated as $key => $value) {
            if ($previous[$key] !== $value) {
                if($key!=='maintenance_enabled'||!function_exists('maintenance_transition'))$statement->execute([$key, $value]);
                $changed[] = $key;
            }
        }
        if ($changed !== []) audit_log($pdo, $actorId, 'settings.updated', null, ['keys' => $changed]);
        $pdo->commit();
        site_settings($pdo, true);
        return $changed;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        site_settings($pdo, true);
        throw $error;
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}
