<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__ . '/../config/mail_smtp.php';

function smtp_test_require(bool $condition, string $message = 'Unexpected SMTP test result.'): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function smtp_test_rejected(callable $operation, string $class, ?string $message = null): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        smtp_test_require($error instanceof $class && ($message === null || $error->getMessage() === $message));
        smtp_test_require($error->getPrevious() === null);
        return;
    }
    throw new RuntimeException('Unsafe SMTP input was accepted.');
}

function smtp_test_fixture(string $mode, string $fixture): void
{
    $server = stream_socket_server('tcp://127.0.0.1:0', context: stream_context_create(['ssl' => [
        'local_cert' => $fixture . '/server.pem', 'verify_peer' => false,
    ]]));
    smtp_test_require(is_resource($server));
    echo stream_socket_get_name($server, false) . "\n";
    fflush(STDOUT);
    $socket = stream_socket_accept($server, 7);
    smtp_test_require(is_resource($socket));
    stream_set_timeout($socket, 5);
    $write = static function (string $text) use ($socket): void { fwrite($socket, $text . "\r\n"); };
    $read = static function () use ($socket): string { return rtrim(fgets($socket, 4096) ?: '', "\r\n"); };
    $tls = static fn(): bool => @stream_socket_enable_crypto(
        $socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER
    ) === true;

    if ($mode === 'wrong-name') {
        smtp_test_require(!$tls() || $read() === '', 'Certificate mismatch allowed SMTP commands.');
        return;
    }
    if ($mode === 'smtps') {
        smtp_test_require($tls(), 'Implicit TLS handshake failed.');
    }
    $write('220 smtp.example.net ESMTP');
    smtp_test_require(str_starts_with($read(), 'EHLO '));
    if ($mode === 'plain-relay') {
        $write("250-smtp.example.net\r\n250 AUTH PLAIN");
        smtp_test_require($read() === 'STARTTLS', 'SMTP client did not require STARTTLS.');
        $write('454 TLS unavailable');
        $next = $read();
        smtp_test_require($next === '' || $next === 'QUIT', 'SMTP commands sent without TLS.');
        $write('221 Bye');
        return;
    }
    if ($mode === 'starttls') {
        $write("250-smtp.example.net\r\n250 STARTTLS");
        smtp_test_require($read() === 'STARTTLS');
        $write('220 Continue');
        smtp_test_require($tls(), 'STARTTLS handshake failed.');
        smtp_test_require(str_starts_with($read(), 'EHLO '));
    }
    $write("250-smtp.example.net\r\n250 AUTH PLAIN");
    smtp_test_require($read() === 'AUTH PLAIN');
    $write('334 ');
    smtp_test_require(base64_decode($read(), true) === "\0mail@example.net\0synthetic-credential");
    $write('235 Authenticated');
    smtp_test_require($read() === 'MAIL FROM:<mail@Example.NET>');
    $write('250 Sender accepted');
    smtp_test_require($read() === 'RCPT TO:<recipient@example.org>');
    $write('250 Recipient accepted');
    smtp_test_require($read() === 'DATA');
    $write('354 End data with a dot');
    $data = '';
    while (($line = fgets($socket, 4096)) !== false && rtrim($line, "\r\n") !== '.') {
        $data .= $line;
        smtp_test_require(strlen($data) < 5000);
    }
    smtp_test_require($line !== false);
    [$headers, $body] = explode("\r\n\r\n", $data, 2);
    smtp_test_require(str_contains($headers, 'Message-ID: <' . hash('sha256', 'notification:12:mail.fixture') . '@Example.NET>'));
    smtp_test_require(str_contains(strtolower($headers), 'content-type: text/plain; charset=utf-8'));
    smtp_test_require(str_contains($headers, 'Content-Transfer-Encoding: base64'));
    smtp_test_require(str_replace("\r\n", "\n", base64_decode($body, true)) === "Grüße\nIhre E-Mail");
    $write('250 Message accepted');
    smtp_test_require($read() === 'QUIT');
    $write('221 Bye');
}

function smtp_test_wire(array $plan): void
{
    smtp_test_require(extension_loaded('openssl') && class_exists(PHPMailer\PHPMailer\PHPMailer::class),
        'The wire test requires OpenSSL and installed Composer dependencies.');
    $fixture = sys_get_temp_dir() . '/gunindex-smtp-' . bin2hex(random_bytes(12));
    smtp_test_require(mkdir($fixture, 0700));
    $config = $fixture . '/openssl.cnf';
    $caFile = $fixture . '/ca.pem';
    $serverPem = $fixture . '/server.pem';
    try {
        file_put_contents($config, "[req]\ndistinguished_name = req_dn\n[req_dn]\n[ca_ext]\nbasicConstraints = critical,CA:TRUE\n"
            . "keyUsage = critical,keyCertSign,cRLSign\n[server_ext]\nbasicConstraints = critical,CA:FALSE\n"
            . "keyUsage = critical,digitalSignature,keyEncipherment\nextendedKeyUsage = serverAuth\nsubjectAltName = DNS:smtp.example.net\n");
        $options = ['config' => $config, 'private_key_bits' => 2048, 'digest_alg' => 'sha256'];
        $caKey = openssl_pkey_new($options);
        $serverKey = openssl_pkey_new($options);
        $caRequest = openssl_csr_new(['commonName' => 'GunIndex tests'], $caKey, $options);
        $serverRequest = openssl_csr_new(['commonName' => 'smtp.example.net'], $serverKey, $options);
        $ca = openssl_csr_sign($caRequest, null, $caKey, 1, $options + ['x509_extensions' => 'ca_ext'], 1);
        $certificate = openssl_csr_sign($serverRequest, $ca, $caKey, 1, $options + ['x509_extensions' => 'server_ext'], 2);
        smtp_test_require(openssl_x509_export_to_file($ca, $caFile));
        smtp_test_require(openssl_x509_export($certificate, $serverCertificate));
        smtp_test_require(openssl_pkey_export($serverKey, $serverPrivateKey, options: $options));
        smtp_test_require(file_put_contents($serverPem, $serverCertificate . $serverPrivateKey) !== false);

        foreach (['starttls', 'smtps', 'wrong-name', 'plain-relay'] as $mode) {
            $php = [PHP_BINARY];
            if (PHP_OS_FAMILY === 'Windows' && php_ini_loaded_file() === false) {
                array_push($php, '-d', 'extension_dir=' . ini_get('extension_dir'), '-d', 'extension=openssl');
            }
            $command = array_merge($php, [__FILE__, '--fixture', $mode, $fixture]);
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            smtp_test_require(is_resource($process));
            try {
                $listen = trim(fgets($pipes[1]) ?: '');
                smtp_test_require(preg_match('/\A127\.0\.0\.1:([0-9]{1,5})\z/', $listen, $matches) === 1);
                $connection = array_replace($plan['connection'], ['host' => '127.0.0.1', 'port' => (int)$matches[1],
                    'security' => in_array($mode, ['smtps', 'wrong-name'], true) ? 'ssl' : 'tls']);
                $connection['ssl']['cafile'] = $caFile;
                if ($mode === 'wrong-name') {
                    $connection['ssl']['peer_name'] = 'other.example.net';
                }
                $failure = null;
                try {
                    // The lower-level transport lets an isolated fixture use loopback. Public routing is checked above.
                    mail_smtp_deliver($connection, $plan['message'], hrtime(true) / 1e9 + 15);
                } catch (PHPMailer\PHPMailer\Exception $error) {
                    $failure = $error->getMessage();
                }
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                foreach ($pipes as $pipe) { fclose($pipe); }
                $exit = proc_close($process);
                smtp_test_require($exit === 0, 'SMTP fixture ' . $mode . ' failed. ' . $stdout . $stderr);
                smtp_test_require(($failure === null) === in_array($mode, ['starttls', 'smtps'], true),
                    'Unexpected TLS result for ' . $mode . ': ' . $failure);
                echo 'PASS authentic wire SMTP fixture ' . $mode . "\n";
            } finally {
                if (is_resource($process)) {
                    foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
                    if (proc_get_status($process)['running']) { proc_terminate($process); }
                    proc_close($process);
                }
            }
        }
    } finally {
        foreach ([$config, $caFile, $serverPem] as $path) { if (is_file($path)) { unlink($path); } }
        rmdir($fixture);
    }
}

if (($argv[1] ?? '') === '--fixture') {
    smtp_test_fixture($argv[2], $argv[3]);
    exit;
}

$config = ['host' => ' SMTP.Example.net. ', 'port' => 587, 'from' => 'mail@Example.NET',
    'username' => 'mail@example.net', 'password' => 'synthetic-credential'];
$message = ['to' => 'recipient@example.org', 'subject' => 'Bestätigung', 'body' => "Grüße\nIhre E-Mail"];
$dedupe = 'notification:12:mail.fixture';
$addresses = static fn(string $host): array => ['8.8.8.8'];
$never = static function (): void { throw new LogicException('Delivery callback unexpectedly invoked.'); };

foreach (['mx.example.net' => 'mx.example.net', 'MX.XN--BCHER-KVA.DE.' => 'mx.xn--bcher-kva.de',
    "  relay.example.xn--p1ai\t" => 'relay.example.xn--p1ai'] as $raw => $canonical) {
    smtp_test_require(mail_smtp_domain($raw) === $canonical);
}
foreach (['localhost', 'relay.local', 'a.internal', 's.home.arpa', 'https://relay.example.net',
    'relay.example.net:587', 'relay.example.net;localhost', 'relay.example.net/other', 'relay@example.net',
    '127.0.0.1', '2130706433', '0x7f.0.0.1', '[::1]', 'localhost..com', 'bad-.example.net',
    'bad_name.example.net', 'host.example.net..', 'smtp.bücher.de', "valid.example.net\r\n", "valid.example.net\0",
    str_repeat('x', 64) . '.example.net'] as $host) {
    smtp_test_require(mail_smtp_domain($host) === null, 'Invalid SMTP hostname accepted.');
}
echo "PASS SMTP relay hostname normalization\n";

$plans = [];
$sender = static function (array $plan) use (&$plans): void { $plans[] = $plan; };
mail_smtp_send($config, $message, $dedupe, static function (string $host): array {
    smtp_test_require($host === 'smtp.example.net');
    return ['2606:4700:4700::1111', '8.8.8.8', '1.1.1.1'];
}, $sender);
$connection = $plans[0]['connection'];
smtp_test_require($connection === [
    'host' => '8.8.8.8', 'port' => 587, 'security' => 'tls',
    'username' => $config['username'], 'password' => $config['password'],
    'ssl' => ['peer_name' => 'smtp.example.net', 'verify_peer' => true, 'verify_peer_name' => true,
        'allow_self_signed' => false, 'SNI_enabled' => true, 'disable_compression' => true],
]);
smtp_test_require($plans[0]['message'] === ['from' => $config['from']] + $message + [
    'message_id' => '<' . hash('sha256', $dedupe) . '@Example.NET>',
]);
echo "PASS pinned STARTTLS relay and stable UTF-8 message\n";

$smtps = array_replace($config, ['port' => 465]);
mail_smtp_send($smtps, $message, $dedupe, static fn(string $host): array => ['2606:4700:4700:0:0:0:0:1111'], $sender);
smtp_test_require($plans[1]['connection']['host'] === '[2606:4700:4700::1111]');
smtp_test_require($plans[1]['connection']['port'] === 465 && $plans[1]['connection']['security'] === 'ssl');
smtp_test_require($plans[1]['connection']['ssl'] === $connection['ssl']);
smtp_test_require($plans[1]['message']['message_id'] === $plans[0]['message']['message_id']);
echo "PASS pinned IPv6 SMTPS preserves the original TLS identity\n";

foreach (['10.20.30.40', '172.31.0.1', '192.168.0.2', '127.0.0.1', '0.0.0.0', '169.254.169.254',
    '100.64.1.1', '198.18.0.1', '192.0.2.1', '224.0.0.1', '239.255.255.250', '240.0.0.4',
    '::1', '::ffff:8.8.8.8', '::ffff:127.0.0.1', 'fc00::1', 'fd00::1', 'fe80::1', 'ff02::1',
    '2001:db8::1', '2002:7f00:1::1', '3fff::1', '64:ff9b::7f00:1', '250.256.1.1', '8.8.8.8 ',
    'bad.example.net', '1.1.1.1:465', '[2606:4700:4700::1111]'] as $unsafe) {
    smtp_test_rejected(static fn() => mail_smtp_send(
        $config, $message, $dedupe, static fn(string $host): array => ['8.8.8.8', $unsafe], $never
    ), RuntimeException::class);
}
foreach ([[], ['b' => '8.8.8.8'], [null], array_fill(0, 65, '8.8.8.8')] as $invalidAddresses) {
    smtp_test_rejected(static fn() => mail_smtp_send(
        $config, $message, $dedupe, static fn(string $host): array => $invalidAddresses, $never
    ), RuntimeException::class);
}
echo "PASS unsafe or mixed DNS candidates block delivery\n";

foreach ([['host' => '127.0.0.1'], ['port' => 25], ['port' => '587'], ['username' => ''], ['username' => "me\0"],
    ['password' => null], ['password' => "key\r\nsecret"], ['from' => 'me@[127.0.0.1]']] as $invalidConfig) {
    smtp_test_rejected(static fn() => mail_smtp_send(
        array_replace($config, $invalidConfig), $message, $dedupe, $never, $never
    ), InvalidArgumentException::class);
}
foreach ([['to' => 'x@example.org,b@example.org'], ['to' => 'Name <x@example.org>'], ['to' => "me@example.org\r\nBcc:x@example.org"],
    ['subject' => "line\nBcc: x@example.org"], ['subject' => "line\rBcc: x@example.org"],
    ['subject' => "bad\x80"], ['subject' => str_repeat('a', 201)], ['body' => "bad\x80"],
    ['body' => str_repeat('a', 50001)], ['body' => null]] as $invalidMessage) {
    smtp_test_rejected(static fn() => mail_smtp_send(
        $config, array_replace($message, $invalidMessage), $dedupe, $never, $never
    ), InvalidArgumentException::class);
}
foreach (["bad\nkey", '', str_repeat('a', 191)] as $invalidKey) {
    smtp_test_rejected(static fn() => mail_smtp_send($config, $message, $invalidKey, $never, $never), InvalidArgumentException::class);
}
mail_smtp_send($config, array_replace($message, ['body' => '']), $dedupe, $addresses, $sender);
smtp_test_require($plans[2]['message']['body'] === '');
echo "PASS relay, envelope, message and header boundary validation\n";

$failure = static function (): void {
    throw new RuntimeException('SMTP credentials, recipient, or message may be present here.');
};
smtp_test_rejected(static fn() => mail_smtp_send($config, $message, $dedupe, $failure, $never),
    RuntimeException::class, 'SMTP relay DNS lookup failed.');
smtp_test_rejected(static fn() => mail_smtp_send($config, $message, $dedupe, $addresses, $failure),
    RuntimeException::class, 'SMTP relay did not accept the message.');
echo "PASS relay failures discard original exception details\n";

if (in_array('--wire', $argv, true)) {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($autoload)) { require_once $autoload; }
    smtp_test_wire($plans[0]);
}
