<?php
declare(strict_types=1);
require_once __DIR__.'/i18n.php';

function auth_input(array $source, string $key, bool $trim = true): string
{
    $value = $source[$key] ?? '';
    if (!is_string($value)) { throw new InvalidArgumentException(t('account.scalar_input')); }
    return $trim ? trim($value) : $value;
}

function auth_password_check(string $password): void
{
    $max = defined('PASSWORD_ARGON2ID') ? 1024 : 72;
    if (strlen($password) > $max || str_contains($password, "\0") || preg_match_all('/./us', $password) < 12) {
        throw new InvalidArgumentException(t('account.password_policy'));
    }
}

function auth_hash(string $password): string
{
    auth_password_check($password);
    return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
}

/** Shared locked files prevent a rate-limit bypass by opening another session. */
function auth_rate_limit(string $kind, string $subject, int $limit, int $seconds): void
{
    $directory = getenv('AUTH_RATE_LIMIT_DIR') ?: dirname(__DIR__) . '/var/auth-limits';
    if (is_link($directory) || (!is_dir($directory) && !@mkdir($directory, 0700, true))
        || !is_dir($directory) || !@chmod($directory, 0700)) {
        abort_request(503, t('account.unavailable'));
    }
    $path = $directory . '/' . hash('sha256', $kind . "\0" . $subject) . '.json';
    if (is_link($path)) { abort_request(503, t('account.unavailable')); }
    $file = @fopen($path, 'c+b');
    if ($file === false || !@chmod($path, 0600) || !flock($file, LOCK_EX)) {
        if (is_resource($file)) { fclose($file); }
        abort_request(503, t('account.unavailable'));
    }
    try {
        $raw = stream_get_contents($file, 4097);
        if ($raw === false || strlen($raw) > 4096) { throw new RuntimeException('Rate storage unavailable'); }
        $now = time();
        $state = $raw === '' ? ['start' => $now, 'count' => 0] : json_decode($raw, true, 4, JSON_THROW_ON_ERROR);
        if (!is_array($state) || !is_int($state['start'] ?? null) || !is_int($state['count'] ?? null)
            || $state['start'] > $now + 5 || $state['count'] < 0) { throw new RuntimeException('Rate storage invalid'); }
        if ($state['start'] + $seconds <= $now) { $state = ['start' => $now, 'count' => 0]; }
        if ($state['count'] >= $limit) {
            header('Retry-After: ' . max(1, $state['start'] + $seconds - $now));
            abort_request(429, t('account.rate_limit'));
        }
        ++$state['count']; $next = json_encode($state, JSON_THROW_ON_ERROR); rewind($file);
        if (!ftruncate($file, 0) || fwrite($file, $next) !== strlen($next) || !fflush($file)) {
            throw new RuntimeException('Rate storage write failed');
        }
    } catch (Throwable $error) { abort_request(503, t('account.unavailable')); }
    finally { flock($file, LOCK_UN); fclose($file); }
}

function auth_ip(): string { return (string)($_SERVER['REMOTE_ADDR'] ?? 'local-cli'); }

function auth_clear_session(): void
{
    $preferences = [];
    foreach (['locale','market_country'] as $key) if (is_string($_SESSION[$key] ?? null)) $preferences[$key] = $_SESSION[$key];
    $_SESSION = $preferences;
    if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
}

function current_user(PDO $pdo): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) || $id < 1) { return null; }
    $statement = $pdo->prepare('SELECT * FROM users WHERE id = ?'); $statement->execute([$id]); $user = $statement->fetch();
    $now = time();
    if (!$user || (int)$user['is_active'] !== 1 || (int)$user['auth_version'] !== ($_SESSION['auth_version'] ?? null)
        || $now - (int)($_SESSION['auth_started_at'] ?? 0) > 43200
        || $now - (int)($_SESSION['auth_last_seen'] ?? 0) > 1800) {
        auth_clear_session(); return null;
    }
    $_SESSION['auth_last_seen'] = $now;
    return $user;
}

function require_login(PDO $pdo): array
{
    $user = current_user($pdo); if ($user === null) { redirect('/auth/login.php'); } return $user;
}

function auth_requires_mfa(PDO $pdo, array $user): bool
{
    return (int)$user['is_admin'] === 1 || (function_exists('rbac_requires_mfa') && rbac_requires_mfa($pdo,(int)$user['id']));
}

function require_admin(PDO $pdo): array
{
    $user = require_login($pdo);
    if ((int)$user['is_admin'] !== 1) { abort_request(403, t('account.admin_required')); }
    if (empty($user['mfa_secret']) || ($_SESSION['admin_mfa_user_id'] ?? null) !== (int)$user['id']) {
        abort_request(403, t('account.mfa_required'));
    }
    return $user;
}

function auth_issue_session(array $user, bool $mfa = false): void
{
    auth_clear_session();
    $_SESSION['user_id'] = (int)$user['id']; $_SESSION['username'] = (string)$user['username'];
    $_SESSION['auth_version'] = (int)$user['auth_version'];
    $_SESSION['auth_started_at'] = $_SESSION['auth_last_seen'] = time();
    if ($mfa) { $_SESSION['admin_mfa_user_id'] = (int)$user['id']; }
    $_SESSION['locale'] ??= (string)($user['preferred_locale'] ?? 'de');
    $_SESSION['market_country'] ??= (string)($user['country_code'] ?? 'DE');
}

function auth_authenticate(PDO $pdo, string $login, string $password): ?array
{
    auth_rate_limit('login-ip', auth_ip(), 120, 900);
    auth_rate_limit('login-pair', auth_ip() . "\0" . strtolower(substr($login, 0, 190)), 10, 900);
    if (strlen($login) > 190 || strlen($password) > 1024 || $login === '' || $password === '' || str_contains($password, "\0")) { return null; }
    $query = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1'); $query->execute([$login, $login]); $user = $query->fetch();
    $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    // Same-cost decoy work occurs on known and unknown login names.
    $decoy = password_hash(bin2hex(random_bytes(24)), $algorithm);
    $valid = password_verify($password, $user ? (string)$user['password_hash'] : $decoy);
    if (!$user || !$valid || (int)$user['is_active'] !== 1) { return null; }
    if (password_needs_rehash((string)$user['password_hash'], $algorithm)) {
        $hash = password_hash($password, $algorithm);
        $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND password_hash = ?');
        $update->execute([$hash, $user['id'], $user['password_hash']]); $user['password_hash'] = $hash;
    }
    return $user;
}

function auth_encryption_key(): string
{
    $encoded = getenv('APP_KEY'); $key = is_string($encoded) ? base64_decode($encoded, true) : false;
    if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt') || $key === false || strlen($key) !== 32) {
        throw new RuntimeException('Administrator MFA requires a configured application key.');
    }
    return $key;
}

function auth_secret_encrypt(string $secret): string
{
    $key = auth_encryption_key(); $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
    return 'v1:' . base64_encode($nonce . sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($secret, 'GunIndex.admin-totp.v1', $nonce, $key));
}

function auth_secret_decrypt(string $encrypted): string
{
    $keys = [auth_encryption_key()];
    $previous = getenv('APP_KEY_PREVIOUS');
    if (is_string($previous) && $previous !== '') {
        $decoded = base64_decode($previous,true);
        if ($decoded !== false && strlen($decoded) === 32 && !hash_equals($keys[0],$decoded)) $keys[] = $decoded;
    }
    if (!str_starts_with($encrypted, 'v1:')) throw new RuntimeException('Invalid MFA data.');
    $data = base64_decode(substr($encrypted,3),true); $nonceSize=SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
    if ($data===false || strlen($data)<=$nonceSize+SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) throw new RuntimeException('Invalid MFA data.');
    foreach ($keys as $key) {
        $plain=sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($data,$nonceSize),'GunIndex.admin-totp.v1',substr($data,0,$nonceSize),$key);
        if ($plain!==false && strlen($plain)===20) return $plain;
    }
    throw new RuntimeException('Invalid MFA data.');
}

/** RFC 4648 encoding for authenticator enrollment, not encryption. */
function auth_base32(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $buffer = 0; $bits = 0; $output = '';
    foreach (unpack('C*', $bytes) as $byte) {
        $buffer = ($buffer << 8) | $byte; $bits += 8;
        while ($bits >= 5) { $bits -= 5; $output .= $alphabet[($buffer >> $bits) & 31]; }
        $buffer &= (1 << $bits) - 1;
    }
    if ($bits > 0) { $output .= $alphabet[($buffer << (5 - $bits)) & 31]; } return $output;
}

/** RFC 6238 SHA-1, six digits, 30-second step. */
function auth_totp(string $secret, int $counter): string
{
    if (strlen($secret) !== 20 || $counter < 0) { throw new InvalidArgumentException('Invalid TOTP parameters.'); }
    $mac = hash_hmac('sha1', pack('N2', intdiv($counter, 4294967296), $counter & 0xffffffff), $secret, true);
    $offset = ord($mac[19]) & 15; $number = unpack('N', substr($mac, $offset, 4))[1] & 0x7fffffff;
    return str_pad((string)($number % 1000000), 6, '0', STR_PAD_LEFT);
}

function auth_totp_counter(string $secret, string $code, int $after = -1, ?int $timestamp = null): ?int
{
    if (!preg_match('/\A[0-9]{6}\z/', $code)) { return null; }
    $counter = intdiv($timestamp ?? time(), 30);
    for ($candidate = max(0, $counter - 1); $candidate <= $counter + 1; ++$candidate) {
        if ($candidate > $after && hash_equals(auth_totp($secret, $candidate), $code)) { return $candidate; }
    }
    return null;
}

function auth_verify_pending_mfa(PDO $pdo, string $code): ?array
{
    auth_rate_limit('mfa-ip', auth_ip(), 40, 300); $id = $_SESSION['pending_admin_id'] ?? null;
    if (!is_int($id) || time() - (int)($_SESSION['pending_admin_started'] ?? 0) > 600) { auth_clear_session(); return null; }
    auth_rate_limit('mfa-user', (string)$id, 8, 300); $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE'); $query->execute([$id]); $user = $query->fetch();
        if (!$user || !auth_requires_mfa($pdo,$user) || (int)$user['is_active'] !== 1 || empty($user['mfa_secret'])
            || (int)$user['auth_version'] !== ($_SESSION['pending_admin_version'] ?? null)) {
            $pdo->rollBack(); auth_clear_session(); return null;
        }
        $secret = auth_secret_decrypt((string)$user['mfa_secret']);
        $counter = auth_totp_counter($secret, $code,
            $user['mfa_last_counter'] === null ? -1 : (int)$user['mfa_last_counter']);
        if ($counter === null) { $pdo->rollBack(); return null; }
        $pdo->prepare('UPDATE users SET mfa_last_counter = ?,mfa_secret=? WHERE id = ?')->execute([$counter,auth_secret_encrypt($secret),$id]);
        $pdo->commit(); auth_issue_session($user, true); return $user;
    } catch (Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
}
