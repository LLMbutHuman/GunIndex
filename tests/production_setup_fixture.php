<?php
declare(strict_types=1);

/** Populate synthetic, inert setup data on the hosted installer-test stack only. */
if (PHP_SAPI !== 'cli' || getenv('GUNINDEX_CI_FIXTURE') !== 'installer-activation'
    || getenv('DB_DATABASE') !== 'gunindex' || getenv('APP_URL') !== 'https://installer.gunindex-ci.internal') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'gunindex' || setup_installation_state($pdo) !== 'pending') exit(2);
$admin = $pdo->query("SELECT id FROM users WHERE username='installer_test_admin' AND is_admin=1 AND is_active=1")->fetchColumn();
if (!$admin || (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 1) exit(2);

$values = ['contact_email'=>'operator@example.invalid', 'operator_legal_name'=>'Synthetic operator',
    'operator_address_line1'=>'Synthetic Street 1', 'operator_postcode'=>'10115', 'operator_city'=>'Synthetic city',
    'operator_country'=>'DE', 'operator_email'=>'operator@example.invalid', 'operator_tax_number'=>'Synthetic fixture',
    'legal_publication_enabled'=>'1', 'legal_registration_documents'=>'fixture-terms', 'legal_checkout_documents'=>'fixture-terms'];
site_settings_save($pdo, (int)$admin, array_replace(site_settings($pdo), $values), site_settings_revision(site_settings($pdo)));

$body = 'Synthetic legal text for an isolated workflow check.';
$insert = $pdo->prepare("INSERT INTO market_legal_documents(document_code,version,language,title,body_text,content_hash,status,approved_by,approved_at,effective_at)"
    ." VALUES ('fixture-terms','test-1',?,'Synthetic fixture',?,?,'approved',?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
foreach (array_keys(i18n_locales()) as $locale) $insert->execute([$locale, $body, hash('sha256', $body), $admin]);

// Check local transport readiness without queueing delivery or contacting any provider.
mail_setup_save($pdo, (int)$admin, ['mode'=>'sendmail', 'from'=>'operator@example.com', 'host'=>'',
    'port'=>'587', 'username'=>'', 'password'=>'', 'selector'=>'',
    'revision'=>mail_setup_view($pdo)['revision']]);
