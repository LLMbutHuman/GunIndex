<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
if (in_array('--help', $argv, true)) {
    fwrite(STDOUT, "Usage: php ops/create-admin.php\nCreates the first administrator only, with confirmed TOTP. Requires DB_* and base64 APP_KEY.\nRun interactively on a trusted terminal; no credentials in command arguments.\n"); exit(0);
}
require_once __DIR__ . '/../config/bootstrap.php';
function admin_prompt(string $label, bool $hidden = false): string
{
    if (!function_exists('stream_isatty') || !stream_isatty(STDIN)) { throw new RuntimeException('A trusted interactive terminal is required.'); }
    fwrite(STDOUT, $label); $terminal = null;
    if ($hidden) {
        if (!function_exists('shell_exec')) { throw new RuntimeException('Terminal hiding is unavailable.'); }
        $terminal = shell_exec('stty -g');
        if (!is_string($terminal) || trim($terminal) === '') { throw new RuntimeException('Cannot read terminal settings.'); }
        shell_exec('stty -echo');
    }
    try {
        $value = fgets(STDIN, 2048);
        if ($value === false) { throw new RuntimeException('Input aborted.'); }
        return rtrim($value, "\r\n");
    } finally { if ($hidden && $terminal !== null) { shell_exec('stty ' . escapeshellarg(trim($terminal))); fwrite(STDOUT, "\n"); } }
}
try {
    auth_encryption_key();
    if ((int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_admin=1')->fetchColumn() !== 0) { throw new RuntimeException('An administrator already exists.'); }
    $username = trim(admin_prompt('Benutzername: ')); $email = trim(admin_prompt('E-Mail: '));
    $password = admin_prompt('Passwort (mindestens zwölf Zeichen): ', true); $confirmation = admin_prompt('Passwort wiederholen: ', true);
    if (!preg_match('/\A[A-Za-z0-9_\-]{3,50}\z/', $username) || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !hash_equals($password, $confirmation)) { throw new RuntimeException('Invalid account details or password confirmation.'); }
    $hash = auth_hash($password); unset($password, $confirmation); $secret = random_bytes(20);
    fwrite(STDOUT, "\nNur lokal in deine Authenticator-App übernehmen. Nicht protokollieren oder teilen.\n");
    fwrite(STDOUT, 'TOTP-Schlüssel (SHA-1, 6 Stellen, 30 Sekunden): ' . auth_base32($secret) . "\n");
    $counter = auth_totp_counter($secret, trim(admin_prompt('Aktueller Code aus der App: ')));
    if ($counter === null) { throw new RuntimeException('TOTP enrollment was not confirmed.'); }
    $encrypted = auth_secret_encrypt($secret); sodium_memzero($secret);
    if (!$pdo->query("SELECT GET_LOCK('gunindex.initial-admin',10)")->fetchColumn()) { throw new RuntimeException('Administrator initialization is busy.'); }
    try {
        $pdo->beginTransaction();
        if ((int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_admin=1')->fetchColumn() !== 0) { throw new RuntimeException('An administrator was created concurrently.'); }
        $statement = $pdo->prepare('INSERT INTO users (username,email,password_hash,is_admin,mfa_secret,mfa_last_counter) VALUES (?,?,?,1,?,?)');
        $statement->execute([$username, $email, $hash, $encrypted, $counter]);
        if (function_exists('audit_log')) { audit_log($pdo, (int)$pdo->lastInsertId(), 'account.initial_admin_created'); }
        $pdo->commit();
    } finally { if ($pdo->inTransaction()) { $pdo->rollBack(); } $pdo->query("SELECT RELEASE_LOCK('gunindex.initial-admin')"); }
    fwrite(STDOUT, "Administrator angelegt. Für die Anmeldung den nächsten neuen TOTP-Code verwenden.\n");
} catch (Throwable $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, "Administrator konnte nicht angelegt werden. Konfiguration, Eingaben und lokalen Datenbankzustand prüfen.\n"); exit(1);
}
