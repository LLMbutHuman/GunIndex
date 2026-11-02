<?php
declare(strict_types=1);

function site_setting_definitions(): array
{
    return [
        'brand_name' => ['type' => 'text', 'default' => 'GunIndex.eu', 'label' => 'Name der Website', 'max' => 60, 'min' => 2],
        'tagline' => ['type' => 'text', 'default' => 'Gute Dinge. Neue Geschichten.', 'label' => 'Kurzer Markenclaim', 'max' => 120, 'min' => 0],
        'hero_title' => ['type' => 'text', 'default' => 'Besonderes finden. Neues möglich machen.', 'label' => 'Startseitenüberschrift', 'max' => 120, 'min' => 2],
        'hero_text' => ['type' => 'text', 'default' => 'Besondere Fundstücke, ehrliche Angebote und spannende Auktionen für deinen Alltag.', 'label' => 'Startseitentext', 'max' => 500, 'min' => 0],
        'accent' => ['type' => 'color', 'default' => '#35e6bd', 'label' => 'Akzentfarbe'],
        'secondary' => ['type' => 'color', 'default' => '#ffb86c', 'label' => 'Zweite Akzentfarbe'],
        'animation_mode' => ['type' => 'animation', 'default' => 'full', 'label' => 'Bewegung und Animationen'],
        'maintenance_enabled' => ['type' => 'boolean', 'default' => '0', 'label' => 'Wartungshinweis anzeigen'],
        'seo_indexing_enabled' => ['type' => 'boolean', 'default' => '0', 'label' => 'Öffentliche Suchmaschinenindexierung freigeben'],
        'registration_enabled' => ['type' => 'boolean', 'default' => '1', 'label' => 'Neue Registrierungen erlauben'],
        'contact_email' => ['type' => 'email', 'default' => '', 'label' => 'Öffentliche Kontakt-E-Mail'],
        'manual_paypal_enabled' => ['type'=>'boolean','default'=>'0','label'=>'Manuelle PayPal-Zahlungsaufträge anbieten'],
        'manual_paypal_recipient' => ['type'=>'email','default'=>'','label'=>'Geprüftes PayPal-Empfängerkonto'],
        'manual_paypal_approved' => ['type'=>'boolean','default'=>'0','label'=>'Geschäftliche Zahlungen und Zahlungsbutton sind für mein Konto und diesen Zweck zulässig'],
        'manual_tax_reviewed' => ['type'=>'boolean','default'=>'0','label'=>'Steuerbehandlung und Bestellbedingungen sind fachlich geprüft'],
        'manual_tax_bps' => ['type'=>'tax','default'=>'0','label'=>'Geprüfter Umsatzsteuersatz in Basispunkten (1900 entspricht 19 %)'],
        'business_promotions_enabled'=>['type'=>'boolean','default'=>'0','label'=>'Premiumplätze und Händlerwerbung anbieten'],
        'dealer_profiles_enabled'=>['type'=>'boolean','default'=>'1','label'=>'Händlerprofile erlauben'],
        'business_reservation_minutes'=>['type'=>'integer','default'=>'1440','label'=>'Reservierungsdauer für manuelle Zahlungen (Minuten)','min_value'=>15,'max_value'=>2880],
        'verification_enabled'=>['type'=>'boolean','default'=>'0','label'=>'Identitätsprüfung anbieten'],
        'verification_review_approved'=>['type'=>'boolean','default'=>'0','label'=>'Identitätsprüfung und Aufbewahrung sind fachlich freigegeben'],
        'verification_retention_days'=>['type'=>'integer','default'=>'0','label'=>'Geprüfte Aufbewahrungsdauer der Identitätsbilder (Tage)','min_value'=>0,'max_value'=>90],
        'privacy_contact_email'=>['type'=>'email','default'=>'','label'=>'Kontakt für Datenschutzanfragen'],
        'legal_publication_enabled'=>['type'=>'boolean','default'=>'0','label'=>'Explizit geprüfte Rechtsdokumente veröffentlichen'],
        'finance_enabled'=>['type'=>'boolean','default'=>'0','label'=>'Unveränderliche Rechnungsdokumente ausstellen'],
        'operator_legal_name'=>['type'=>'text','default'=>'','label'=>'Rechtlicher Betreibername','min'=>0,'max'=>150],
        'operator_address_line1'=>['type'=>'text','default'=>'','label'=>'Betreiberanschrift: Straße und Hausnummer','min'=>0,'max'=>150],
        'operator_address_line2'=>['type'=>'text','default'=>'','label'=>'Betreiberanschrift: Zusatz','min'=>0,'max'=>150],
        'operator_postcode'=>['type'=>'text','default'=>'','label'=>'Betreiberanschrift: Postleitzahl','min'=>0,'max'=>16],
        'operator_city'=>['type'=>'text','default'=>'','label'=>'Betreiberanschrift: Ort','min'=>0,'max'=>100],
        'operator_country'=>['type'=>'country','default'=>'DE','label'=>'Land des Betreibers'],
        'operator_email'=>['type'=>'email','default'=>'','label'=>'Kontakt-E-Mail des Betreibers'],
        'operator_vat_id'=>['type'=>'text','default'=>'','label'=>'Umsatzsteuer-ID (falls vorhanden)','min'=>0,'max'=>40],
        'operator_tax_number'=>['type'=>'text','default'=>'','label'=>'Steuernummer für Rechnungen (falls erforderlich)','min'=>0,'max'=>50],
        'operator_registration'=>['type'=>'text','default'=>'','label'=>'Registerangaben (falls erforderlich)','min'=>0,'max'=>150],
        'invoice_prefix'=>['type'=>'code','default'=>'GI','label'=>'Rechnungsnummern-Präfix'],
        'fee_auction_listing_cents' => ['type' => 'cents', 'default' => '50', 'label' => 'Auktion einstellen'],
        'fee_bump_single_cents' => ['type' => 'cents', 'default' => '199', 'label' => 'Einzelner Bump'],
        'fee_bump_pack_cents' => ['type' => 'cents', 'default' => '499', 'label' => 'Drei Bumps'],
        'fee_category_top_cents' => ['type' => 'cents', 'default' => '990', 'label' => 'Kategorie-Top / sieben Tage'],
        'fee_home_top_cents' => ['type' => 'cents', 'default' => '1990', 'label' => 'Startseiten-Top / sieben Tage'],
        'fee_banner_monthly_cents' => ['type' => 'cents', 'default' => '12900', 'label' => 'Händlerbanner / Monat'],
    ];
}

function site_setting_validate(string $key, mixed $value): string
{
    $definition = site_setting_definitions()[$key] ?? null;
    if ($definition === null || !is_string($value) || !preg_match('//u', $value)) {
        throw new InvalidArgumentException('Ungültiger Einstellungswert.');
    }
    $value = trim($value);
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
    if (!$valid) throw new InvalidArgumentException('Bitte prüfe „' . $definition['label'] . '“.');
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
    if ((int)$statement->fetchColumn() !== 1) throw new DomainException('Diese Änderung benötigt aktuelle Adminrechte.');
}

function site_settings_save(PDO $pdo, int $actorId, array $submitted, string $expectedRevision): array
{
    settings_assert_admin($pdo, $actorId);
    $validated = [];
    foreach (site_setting_definitions() as $key => $definition) {
        if (!array_key_exists($key, $submitted)) throw new InvalidArgumentException('Ein Einstellungsfeld fehlt. Bitte lade das Formular neu.');
        $validated[$key] = site_setting_validate($key, $submitted[$key]);
    }
    $lockName = 'gunindex.settings.' . substr(hash('sha256', (string)$pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 24);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('Die Einstellungen werden gerade bearbeitet. Bitte erneut versuchen.');
    try {
        $pdo->beginTransaction();
        settings_assert_admin($pdo, $actorId);
        $previous = site_settings($pdo, true);
        if (!hash_equals(site_settings_revision($previous), $expectedRevision)) throw new DomainException('Die Einstellungen wurden zwischenzeitlich geändert. Deine Eingaben bleiben erhalten; lade den aktuellen Stand vor erneutem Speichern neu.');
        $changed = [];
        $statement = $pdo->prepare('INSERT INTO system_settings(setting_key, setting_value, updated_at) VALUES (?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=UTC_TIMESTAMP()');
        foreach ($validated as $key => $value) {
            if ($previous[$key] !== $value) {
                $statement->execute([$key, $value]);
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
