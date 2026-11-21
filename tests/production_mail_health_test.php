<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('GUNINDEX_CI_FIXTURE') !== 'mail-health' || getenv('DB_DATABASE') !== 'gunindex') exit(2);
define('GUNINDEX_HEALTH_LIBRARY', true);
require_once __DIR__ . '/../ops/health.php';

if (getenv('MAIL_TRANSPORT') !== 'none' || !in_array('mail_not_configured', ops_health($pdo)['warnings'], true)) exit(1);
$pdo->beginTransaction();
try {
    $config = mail_setup_config($pdo);
    $config['mode'] = 'sendmail';
    $config['from'] = 'synthetic@example.invalid';
    $update = $pdo->prepare('UPDATE market_mail_setup SET revision=?,payload_ciphertext=? WHERE id=1');
    $update->execute([$config['revision'], crypto_encrypt('mail_setup', json_encode($config, JSON_THROW_ON_ERROR))]);
    if (mail_transport_reason($pdo) !== null || array_intersect(['mail_not_configured', 'mail_configuration_invalid'], ops_health($pdo)['warnings'])) {
        throw new RuntimeException('Health check ignored the stored mail transport.');
    }
    $update->execute([$config['revision'], 'invalid-test-ciphertext']);
    if (!in_array('mail_configuration_invalid', ops_health($pdo)['warnings'], true)) {
        throw new RuntimeException('Health check ignored a corrupt stored mail configuration.');
    }
} finally {
    $pdo->rollBack();
}
fwrite(STDOUT, "Verified mail diagnostics against the encrypted persisted configuration without submitting messages.\n");
