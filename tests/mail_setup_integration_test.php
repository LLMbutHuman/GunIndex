<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'development' || getenv('DB_DATABASE') !== 'gunindex_rebuild_test') exit(2);
require_once __DIR__ . '/../config/bootstrap.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'gunindex_rebuild_test') exit(2);

function mail_setup_check(bool $condition): void
{
    if (!$condition) throw new RuntimeException('Mail setup assertion failed.');
}

function mail_setup_fixture(PDO $pdo, bool $admin): array
{
    $name = 'test_' . bin2hex(random_bytes(8));
    $email = $name . '@example.invalid';
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,is_admin) VALUES (?,?,?,'Mail setup fixture','10115','Teststadt',?)")
        ->execute([$name, $email, auth_hash('Synthetic fixture password ' . $name), (int)$admin]);
    return [(int)$pdo->lastInsertId(), $email];
}

$saved = $pdo->query('SELECT revision,payload_ciphertext,updated_at FROM market_mail_setup WHERE id=1')->fetch();
$previousEnv = getenv('MAIL_TRANSPORT');
putenv('MAIL_TRANSPORT=test');
$case = 'fixture';
try {
    [$admin, $email] = mail_setup_fixture($pdo, true);
    [$ordinary] = mail_setup_fixture($pdo, false);
    $input = [
        'mode' => 'smtp', 'host' => 'smtp.example.com', 'port' => '587', 'from' => 'no-reply@example.com',
        'username' => 'synthetic-user', 'password' => bin2hex(random_bytes(24)), 'selector' => 'Synthetic',
        'revision' => mail_setup_view($pdo)['revision'],
    ];

    $case = 'configuration_requires_site_manage';
    foreach ([static fn() => mail_setup_save($pdo, $ordinary, $input), static fn() => mail_setup_test($pdo, $ordinary)] as $request) {
        try { $request(); throw new RuntimeException('Missing staff authorization.'); }
        catch (DomainException) {}
    }
    echo "PASS $case\n";

    $case = 'configuration_encrypted_redacted_revision_and_password_binding';
    mail_setup_save($pdo, $admin, $input);
    $view = mail_setup_view($pdo);
    mail_setup_check($view['password_set'] === true && !array_key_exists('password', $view) && $view['selector'] === 'synthetic');
    $cipher = (string)$pdo->query('SELECT payload_ciphertext FROM market_mail_setup WHERE id=1')->fetchColumn();
    mail_setup_check(str_starts_with($cipher, 'gx1:') && !str_contains($cipher, $input['password']) && !str_contains($cipher, $input['host']));
    mail_setup_check(mail_setup_config($pdo)['password'] === $input['password']);
    try { mail_setup_save($pdo, $admin, $input); throw new RuntimeException('Stale revision accepted.'); }
    catch (DomainException $error) { mail_setup_check($error->getMessage() === t('mail_setup.stale')); }
    $patch = array_replace($input, ['password' => '', 'revision' => $view['revision']]);
    try { mail_setup_save($pdo, $admin, array_replace($patch, ['host' => 'other.example.com'])); throw new RuntimeException('Secret reused on a different host.'); }
    catch (InvalidArgumentException) {}
    mail_setup_save($pdo, $admin, $patch);
    mail_setup_check(mail_setup_config($pdo)['password'] === $input['password']);
    echo "PASS $case\n";

    $case = 'test_mail_own_recipient_revision_and_handoff_lifecycle';
    mail_setup_test($pdo, $admin);
    $testQueue = $pdo->prepare("SELECT id,payload_ciphertext,status FROM market_mail_queue WHERE user_id=? AND dedupe_key LIKE 'mail-setup:%' ORDER BY id DESC LIMIT 1");
    $testQueue->execute([$admin]);
    $pending = $testQueue->fetch();
    $message = json_decode(crypto_decrypt('mail', $pending['payload_ciphertext']), true, 8, JSON_THROW_ON_ERROR);
    mail_setup_check($message['to'] === $email && !str_contains($pending['payload_ciphertext'], $email));
    mail_setup_check(mail_setup_test_status($pdo, $admin)['status'] === 'pending');
    $patch['revision'] = mail_setup_view($pdo)['revision'];
    mail_setup_save($pdo, $admin, $patch);
    mail_setup_check(mail_setup_test_status($pdo, $admin) === null);
    mail_setup_test($pdo, $admin);
    $counts = mail_worker_once($pdo, 100);
    $status = mail_setup_test_status($pdo, $admin);
    mail_setup_check($counts['delivered'] >= 1 && $status['status'] === 'delivered' && $status['synthetic'] === true);
    $query = $pdo->prepare('SELECT payload_ciphertext,status FROM market_mail_queue WHERE id=?');
    $query->execute([$pending['id']]);
    $old = $query->fetch();
    mail_setup_check($old['status'] === 'cancelled' && $old['payload_ciphertext'] === '');
    echo "PASS $case\n";

    $case = 'account_test_limit_and_transport_off_clears_credential';
    mail_setup_test($pdo, $admin);
    try { mail_setup_test($pdo, $admin); throw new RuntimeException('Test limit missing.'); }
    catch (DomainException $error) { mail_setup_check($error->getMessage() === t('account.rate_limit')); }
    mail_setup_save($pdo, $admin, array_replace($patch, ['mode' => 'off', 'revision' => mail_setup_view($pdo)['revision']]));
    putenv('MAIL_TRANSPORT=sendmail');
    mail_setup_check(mail_runtime_config($pdo)['mode'] === 'off' && !mail_setup_view($pdo)['password_set']);
    mail_setup_check(mail_transport_reason($pdo) !== null);
    $audit = $pdo->prepare("SELECT details FROM admin_logs WHERE admin_id=? AND action_type LIKE 'mail.setup.%'");
    $audit->execute([$admin]);
    foreach ($audit->fetchAll(PDO::FETCH_COLUMN) as $details) {
        mail_setup_check(!str_contains($details, $input['password']) && !str_contains($details, $email));
    }
    echo "PASS $case\n";
} catch (Throwable $error) {
    fwrite(STDERR, "FAIL $case (" . get_class($error) . ' at ' . basename($error->getFile()) . ':' . $error->getLine() . ")\n");
    $failed = true;
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $pdo->prepare('UPDATE market_mail_setup SET revision=?,payload_ciphertext=?,updated_at=? WHERE id=1')
        ->execute([$saved['revision'], $saved['payload_ciphertext'], $saved['updated_at']]);
    if (isset($admin)) $pdo->prepare("DELETE FROM market_mail_queue WHERE user_id=? AND dedupe_key LIKE 'mail-setup:%'")->execute([$admin]);
    putenv($previousEnv === false ? 'MAIL_TRANSPORT' : 'MAIL_TRANSPORT=' . $previousEnv);
}
if (!empty($failed)) exit(1);
