<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('GUNINDEX_CI_FIXTURE') !== 'french-postcodes' || getenv('DB_DATABASE') !== 'gunindex') exit(2);
require_once __DIR__ . '/../config/bootstrap.php';
if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) exit(2);

function french_postcodes_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$source = $pdo->query("SELECT id,record_count FROM geo_imports WHERE country_code='FR'")->fetchAll();
french_postcodes_check(count($source) === 1 && (int)$source[0]['record_count'] === 6051
    && (int)$pdo->query("SELECT COUNT(*) FROM geo_locations WHERE country_code='FR'")->fetchColumn() === 6051
    && geo_uses_french_seed($pdo), 'Expected the complete attributed French release dataset.');
$paris = geo_postcode($pdo, 'FR', '75001');
$corsica = geo_postcode($pdo, 'FR', '20000');
$ain = geo_postcode($pdo, 'FR', '01000');
french_postcodes_check($paris['latitude'] === '48.8620049' && $paris['longitude'] === '2.3359443'
    && $corsica['city'] === 'AJACCIO' && $ain['postal_code'] === '01000', 'Expected real Paris, Corsica and leading-zero locations.');
french_postcodes_check(geo_postcode($pdo, 'FR', '98000') === null && geo_postcode($pdo, 'FR', '97100') === null,
    'The metropolitan seed included Monaco or an overseas postcode.');
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE geo_imports SET is_active=0 WHERE id=?')->execute([$source[0]['id']]);
    french_postcodes_check(!geo_uses_french_seed($pdo) && geo_postcode($pdo, 'FR', '75001') === null,
        'Deactivation did not remove both distance points and attribution.');
} finally {
    $pdo->rollBack();
}
fwrite(STDOUT, "Verified French dataset coverage, actual location coordinates and attribution state.\n");
