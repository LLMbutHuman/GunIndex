<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__ . '/../config/auth.php';
$checks = 0;
function verify_auth(bool $condition, string $name): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException('FAIL ' . $name); }
    ++$checks; fwrite(STDOUT, 'PASS ' . $name . "\n");
}
try {
    // Published SHA-1 test vectors from RFC 6238, reduced to the common six digits.
    $secret = '12345678901234567890';
    verify_auth(auth_base32($secret) === 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'rfc4648_base32');
    foreach ([59 => '287082', 1111111109 => '081804', 1111111111 => '050471',
        1234567890 => '005924', 2000000000 => '279037', 20000000000 => '353130'] as $time => $expected) {
        verify_auth(auth_totp($secret, intdiv($time, 30)) === $expected, 'rfc6238_sha1_' . $time);
    }
    verify_auth(auth_totp_counter($secret, '287082', -1, 59) === 1, 'totp_current_counter');
    verify_auth(auth_totp_counter($secret, '287082', 1, 59) === null, 'totp_replay_rejected');
    verify_auth(auth_totp_counter($secret, '12x456', -1, 59) === null, 'totp_bad_format_rejected');
    putenv('APP_KEY=' . base64_encode(random_bytes(32)));
    $cipher = auth_secret_encrypt($secret);
    verify_auth(auth_secret_decrypt($cipher) === $secret, 'aead_roundtrip');
    verify_auth(auth_secret_encrypt($secret) !== $cipher, 'aead_random_nonce');
    $data = base64_decode(substr($cipher, 3), true);
    $data[strlen($data) - 1] = chr(ord($data[strlen($data) - 1]) ^ 1);
    try {
        auth_secret_decrypt('v1:' . base64_encode($data));
        throw new LogicException('Tampered ciphertext was accepted.');
    } catch (RuntimeException $error) { verify_auth(true, 'aead_tamper_rejected'); }
    putenv('APP_KEY=' . base64_encode(random_bytes(32)));
    try {
        auth_secret_decrypt($cipher);
        throw new LogicException('Wrong key was accepted.');
    } catch (RuntimeException $error) { verify_auth(true, 'aead_wrong_key_rejected'); }
    $password = '  Long <em>password</em> & space  ';
    $hash = auth_hash($password);
    verify_auth(password_verify($password, $hash), 'password_exact_characters');
    verify_auth(!password_verify(trim($password), $hash), 'password_spaces_preserved');
    try { auth_hash('short'); throw new LogicException('Short password was accepted.'); }
    catch (InvalidArgumentException $error) { verify_auth(true, 'short_password_rejected'); }
    fwrite(STDOUT, 'Auth standalone checks passed: ' . $checks . "\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Auth standalone check failed. No key, password or ciphertext was logged.\n");
    exit(1);
}
