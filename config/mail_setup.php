<?php
declare(strict_types=1);

function mail_setup_config(?PDO $pdo = null, bool $lock = false): array
{
    $defaults = [
        'mode' => getenv('MAIL_TRANSPORT') === 'sendmail' ? 'sendmail' : 'off',
        'from' => (string)getenv('MAIL_FROM'), 'host' => '', 'port' => 587,
        'username' => '', 'password' => '', 'selector' => '',
    ];
    $defaults['revision'] = hash('sha256', json_encode($defaults, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
    if ($pdo === null) return $defaults;

    $row = $pdo->query('SELECT revision,payload_ciphertext FROM market_mail_setup WHERE id=1' . ($lock ? ' FOR SHARE' : ''))->fetch();
    if (!$row) throw new RuntimeException('Mail configuration storage is unavailable.');
    if ($row['payload_ciphertext'] === null) return $defaults;
    $config = json_decode(crypto_decrypt('mail_setup', $row['payload_ciphertext']), true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($config) || !hash_equals($row['revision'], (string)($config['revision'] ?? ''))) {
        throw new RuntimeException('Mail configuration storage is invalid.');
    }
    return $config;
}

function mail_setup_view(PDO $pdo): array
{
    $config = mail_setup_config($pdo);
    $config['password_set'] = $config['password'] !== '';
    unset($config['password']);
    return $config;
}

function mail_setup_save(PDO $pdo, int $actor, array $input): void
{
    if ($pdo->inTransaction()) throw new LogicException('Mail setup requires its own transaction.');
    $pdo->beginTransaction();
    try {
        $account = $pdo->prepare('SELECT id FROM users WHERE id=? FOR SHARE');
        $account->execute([$actor]);
        governance_assert_actor($pdo, $actor, 'site.manage');
        // Always lock the sentinel, including the first write on a fresh installation.
        $pdo->query('SELECT id FROM market_mail_setup WHERE id=1 FOR UPDATE')->fetchColumn();
        $previous = mail_setup_config($pdo, true);
        $revision = $input['revision'] ?? null;
        if (!is_string($revision) || !hash_equals($previous['revision'], $revision)) {
            throw new DomainException(t('mail_setup.stale'));
        }

        $fields = ['mode', 'from', 'host', 'port', 'username', 'password', 'selector'];
        foreach ($fields as $field) {
            if (!is_string($input[$field] ?? null)) throw new InvalidArgumentException(t('mail_setup.invalid_config'));
        }
        $mode = $input['mode'];
        $from = trim($input['from']);
        $selector = strtolower(trim($input['selector']));
        $validFrom = mail_address_valid($from) && mail_smtp_domain(substr(strrchr($from, '@'), 1)) !== null;
        if (!in_array($mode, ['off', 'sendmail', 'smtp'], true) || (!$validFrom && ($mode !== 'off' || $from !== ''))
            || ($selector !== '' && !preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/', $selector))) {
            throw new InvalidArgumentException(t('mail_setup.invalid_config'));
        }

        $config = [
            'mode' => $mode, 'from' => $from, 'host' => '', 'port' => 587, 'username' => '',
            'password' => '', 'selector' => $selector, 'revision' => bin2hex(random_bytes(32)),
        ];
        if ($mode === 'smtp') {
            $host = mail_smtp_domain(trim($input['host']));
            $port = $input['port'];
            $username = trim($input['username']);
            $password = $input['password'];
            if ($host === null || !in_array($port, ['465', '587'], true) || $username === '' || strlen($username) > 190
                || !preg_match('/\A[^\x00-\x1f\x7f]+\z/u', $username)) {
                throw new InvalidArgumentException(t('mail_setup.invalid_config'));
            }
            $port = (int)$port;
            if ($password === '' && $previous['mode'] === 'smtp' && $previous['host'] === $host
                && $previous['port'] === $port && $previous['username'] === $username) {
                $password = $previous['password'];
            }
            if ($password === '' || strlen($password) > 1024 || !preg_match('/\A[^\x00-\x1f\x7f]+\z/u', $password)) {
                throw new InvalidArgumentException(t('mail_setup.invalid_config'));
            }
            $config = array_replace($config, compact('host', 'port', 'username', 'password'));
        }

        $cipher = crypto_encrypt('mail_setup', json_encode($config, JSON_THROW_ON_ERROR));
        $pdo->prepare('UPDATE market_mail_setup SET revision=?,payload_ciphertext=? WHERE id=1')->execute([$config['revision'], $cipher]);
        audit_log($pdo, $actor, 'mail.setup.updated', null, ['mode' => $mode]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function mail_setup_dns(PDO $pdo): array
{
    $config = mail_setup_view($pdo);
    return mail_dns_status($config['from'], $config['selector']);
}

function mail_setup_test_prefix(int $actor, array $transport): string
{
    return 'mail-setup:' . $actor . ':' . $transport['revision'] . ':';
}

function mail_setup_test(PDO $pdo, int $actor): void
{
    if ($pdo->inTransaction()) throw new LogicException('Mail test requires its own transaction.');
    $pdo->beginTransaction();
    try {
        $recipient = $pdo->prepare('SELECT email FROM users WHERE id=? AND is_active=1 FOR UPDATE');
        $recipient->execute([$actor]);
        $email = $recipient->fetchColumn();
        governance_assert_actor($pdo, $actor, 'site.manage');
        $transport = mail_runtime_config($pdo, true);
        if (!is_string($email) || !mail_address_valid($email) || mail_transport_reason($pdo, $transport) !== null) {
            throw new DomainException(t('mail_setup.not_ready'));
        }

        $recent = $pdo->prepare("SELECT COUNT(*) FROM market_mail_queue WHERE user_id=? AND dedupe_key LIKE 'mail-setup:%' AND created_at>UTC_TIMESTAMP()-INTERVAL 1 HOUR");
        $recent->execute([$actor]);
        if ((int)$recent->fetchColumn() >= 3) throw new DomainException(t('account.rate_limit'));

        $key = mail_setup_test_prefix($actor, $transport) . bin2hex(random_bytes(16));
        mail_enqueue($pdo, $actor, $email, t('mail_setup.test_subject'), t('mail_setup.test_body'), $key);
        audit_log($pdo, $actor, 'mail.setup.test_queued', null, ['mode' => $transport['mode']]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function mail_setup_test_status(PDO $pdo, int $actor): ?array
{
    governance_assert_actor($pdo, $actor, 'site.manage');
    $transport = mail_runtime_config($pdo);
    $query = $pdo->prepare('SELECT status,created_at,attempts,last_error_code FROM market_mail_queue WHERE user_id=? AND dedupe_key LIKE ? ORDER BY id DESC LIMIT 1');
    $query->execute([$actor, mail_setup_test_prefix($actor, $transport) . '%']);
    $row = $query->fetch();
    if (!$row) return null;
    return [
        'status' => $row['status'], 'created_at' => $row['created_at'], 'attempts' => (int)$row['attempts'],
        'synthetic' => $transport['mode'] === 'test',
        'retrying' => $row['status'] === 'pending' && $row['last_error_code'] === 'delivery_failed',
    ];
}
