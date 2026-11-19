<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/mail.php';

function mail_smtp_domain(string $host): ?string
{
    $host = strtolower(trim($host, " \t"));
    if (str_ends_with($host, '.')) {
        $host = substr($host, 0, -1);
    }
    if (strlen($host) > 253 || !preg_match(
        '/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:[a-z]{2,63}|xn--[a-z0-9-]{1,58}[a-z0-9])\z/',
        $host
    )) {
        return null;
    }
    $suffix = substr($host, strrpos($host, '.') + 1);
    return in_array($suffix, ['arpa', 'example', 'internal', 'invalid', 'local', 'localhost', 'onion', 'test'], true)
        ? null : $host;
}

/**
 * Deliver one queued plaintext message through an authenticated TLS relay.
 * The CLI test resolver receives the normalized host and returns a list of IP literals.
 * The CLI test sender receives one plan with 'connection' and 'message' arrays.
 */
function mail_smtp_send(
    #[SensitiveParameter] array $config,
    #[SensitiveParameter] array $message,
    #[SensitiveParameter] string $dedupe,
    ?callable $resolver = null,
    ?callable $sender = null
): void {
    if (PHP_SAPI !== 'cli' && ($resolver !== null || $sender !== null)) {
        throw new LogicException('SMTP test adapters require CLI.');
    }

    $hostname = is_string($config['host'] ?? null) ? mail_smtp_domain($config['host']) : null;
    $port = $config['port'] ?? null;
    $from = $config['from'] ?? null;
    $username = $config['username'] ?? null;
    $password = $config['password'] ?? null;
    if ($hostname === null || !in_array($port, [465, 587], true) || !is_string($from) || !mail_address_valid($from)
        || !is_string($username) || $username === '' || strlen($username) > 320 || preg_match('/[\x00-\x1f\x7f]/', $username)
        || !is_string($password) || $password === '' || strlen($password) > 1024 || preg_match('/[\x00-\x1f\x7f]/', $password)) {
        throw new InvalidArgumentException('Invalid SMTP relay configuration.');
    }
    $senderDomain = substr($from, strrpos($from, '@') + 1);
    if (mail_smtp_domain($senderDomain) === null) {
        throw new InvalidArgumentException('SMTP sender requires a public mail domain.');
    }

    $to = $message['to'] ?? null;
    $subject = $message['subject'] ?? null;
    $body = $message['body'] ?? null;
    if (!is_string($to) || !mail_address_valid($to) || !is_string($subject) || strlen($subject) > 200
        || preg_match('/[\x00-\x08\x0a-\x1f\x7f]/', $subject) || !preg_match('//u', $subject)
        || !is_string($body) || strlen($body) > 50000 || !preg_match('//u', $body)
        || !preg_match('/\A[a-zA-Z0-9:._-]{1,190}\z/', $dedupe)) {
        throw new InvalidArgumentException('Invalid queued SMTP message.');
    }

    $deadline = hrtime(true) / 1e9 + 20;
    try {
        $addresses = ($resolver ?? 'mail_smtp_resolve')($hostname);
    } catch (Throwable) {
        throw new RuntimeException('SMTP relay DNS lookup failed.');
    }
    if (!is_array($addresses) || !array_is_list($addresses) || count($addresses) > 64 || $addresses === []) {
        throw new RuntimeException('SMTP relay DNS lookup failed.');
    }
    $ipv4 = null;
    $ipv6 = null;
    foreach ($addresses as $address) {
        if (!is_string($address) || filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            throw new RuntimeException('SMTP relay DNS must resolve exclusively to public addresses.');
        }
        $packed = inet_pton($address);
        if (strlen($packed) === 4 && ord($packed[0]) < 224) {
            $ipv4 ??= inet_ntop($packed);
        } elseif (strlen($packed) === 16 && (ord($packed[0]) & 0xe0) === 0x20
            && !str_starts_with($packed, "\x20\x02")
            && !(str_starts_with($packed, "\x3f\xff") && (ord($packed[2]) & 0xf0) === 0)) {
            $ipv6 ??= '[' . inet_ntop($packed) . ']';
        } else {
            throw new RuntimeException('SMTP relay DNS must resolve exclusively to public unicast addresses.');
        }
    }

    // Use a single literal so there are no additional lookups or fallback attempts after submission.
    $connection = [
        'host' => $ipv4 ?? $ipv6,
        'port' => $port,
        'security' => $port === 465 ? 'ssl' : 'tls',
        'username' => $username,
        'password' => $password,
        'ssl' => [
            'peer_name' => $hostname,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'SNI_enabled' => true,
            'disable_compression' => true,
        ],
    ];
    $envelope = [
        'from' => $from,
        'to' => $to,
        'subject' => $subject,
        'body' => $body,
        'message_id' => '<' . hash('sha256', $dedupe) . '@' . $senderDomain . '>',
    ];
    try {
        if ($sender !== null) {
            $sender(['connection' => $connection, 'message' => $envelope]);
        } else {
            mail_smtp_deliver($connection, $envelope, $deadline);
        }
    } catch (Throwable) {
        // SMTP exceptions can contain sensitive addresses, server replies, or authentication diagnostics.
        throw new RuntimeException('SMTP relay did not accept the message.');
    }
}

/** @return list<string> */
function mail_smtp_resolve(string $hostname): array
{
    $records = @dns_get_record($hostname . '.', DNS_A | DNS_AAAA);
    if ($records === false) {
        return [];
    }
    $addresses = [];
    foreach ($records as $record) {
        if (($record['type'] ?? null) === 'A') {
            $addresses[] = $record['ip'] ?? '';
        } elseif (($record['type'] ?? null) === 'AAAA') {
            $addresses[] = $record['ipv6'] ?? '';
        }
    }
    return $addresses;
}

function mail_smtp_deliver(
    #[SensitiveParameter] array $connection,
    #[SensitiveParameter] array $message,
    float $deadline
): void {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!class_exists(PHPMailer::class) && is_file($autoload)) {
        require_once $autoload;
    }
    if (!class_exists(PHPMailer::class) || !function_exists('stream_socket_client') || !extension_loaded('openssl')) {
        throw new RuntimeException('SMTP TLS transport unavailable.');
    }
    $smtp = new class($deadline) extends SMTP {
        public function __construct(private readonly float $deadline)
        {
        }

        public function connect($host, $port = null, $timeout = 30, $options = [])
        {
            $timeout = $this->operationTimeout();
            return $timeout > 0 && parent::connect($host, $port, $timeout, $options);
        }

        public function startTLS()
        {
            if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220) || !$this->setSocketTimeout()) {
                return false;
            }
            return true === @stream_socket_enable_crypto(
                $this->smtp_conn,
                true,
                STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
            );
        }

        public function authenticate(#[SensitiveParameter] $username, #[SensitiveParameter] $password, $authtype = null, $OAuth = null)
        {
            $crypto = is_resource($this->smtp_conn) ? stream_get_meta_data($this->smtp_conn)['crypto'] ?? [] : [];
            return in_array($crypto['protocol'] ?? null, ['TLSv1.2', 'TLSv1.3'], true)
                && parent::authenticate($username, $password, $authtype, $OAuth);
        }

        public function client_send(#[SensitiveParameter] $data, $command = '')
        {
            if (!$this->setSocketTimeout()) {
                $this->close();
                return false;
            }
            $written = parent::client_send($data, $command);
            if ($written !== strlen($data)) {
                $this->close();
                return false;
            }
            return $written;
        }

        protected function get_lines()
        {
            $seconds = $this->operationTimeout();
            if ($seconds === 0) {
                $this->close();
                return '';
            }
            $this->Timeout = $seconds;
            $this->Timelimit = $seconds;
            return parent::get_lines();
        }

        private function setSocketTimeout(): bool
        {
            $seconds = $this->operationTimeout();
            return $seconds > 0 && is_resource($this->smtp_conn) && stream_set_timeout($this->smtp_conn, $seconds);
        }

        private function operationTimeout(): int
        {
            return max(0, min(5, (int)ceil($this->deadline - hrtime(true) / 1e9)));
        }
    };

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->setSMTPInstance($smtp);
    $mail->Host = $connection['host'];
    $mail->Port = $connection['port'];
    $mail->SMTPSecure = $connection['security'];
    $mail->SMTPAutoTLS = false;
    $mail->SMTPAuth = true;
    $mail->SMTPDebug = SMTP::DEBUG_OFF;
    $mail->Username = $connection['username'];
    $mail->Password = $connection['password'];
    $mail->Timeout = 5;
    $mail->SMTPOptions = ['ssl' => $connection['ssl'] + [
        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
    ]];
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->Encoding = PHPMailer::ENCODING_BASE64;
    $mail->isHTML(false);
    $mail->AllowEmpty = true;
    $mail->setFrom($message['from']);
    $mail->addAddress($message['to']);
    $mail->Subject = $message['subject'];
    $mail->Body = $message['body'];
    $mail->MessageID = $message['message_id'];
    if (!$mail->send()) {
        throw new RuntimeException('SMTP message was rejected.');
    }
}
