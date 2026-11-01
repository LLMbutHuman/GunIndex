<?php
declare(strict_types=1);

function mail_address_valid(string $address): bool
{
    return strlen($address) <= 190 && $address !== '' && $address[0] !== '-'
        && !preg_match('/[\x00-\x20\x7f]/', $address) && filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
}

function mail_origin(): string
{
    $origin = rtrim((string)(getenv('APP_URL') ?: ''), '/');
    $url = parse_url($origin);
    if (!is_array($url) || !isset($url['scheme'], $url['host']) || isset($url['user']) || isset($url['pass'])
        || isset($url['query']) || isset($url['fragment']) || !empty($url['path'])
        || preg_match('/[\x00-\x20\\\\]/', $origin) || filter_var($origin, FILTER_VALIDATE_URL) === false) {
        throw new RuntimeException('Account links require a configured application origin.');
    }
    $test = str_ends_with((string)getenv('DB_DATABASE'), '_test') && getenv('APP_ENV') === 'development';
    $local = $test && in_array($url['host'], ['localhost', '127.0.0.1', '[::1]'], true);
    if ($url['scheme'] !== 'https' && !($local && $url['scheme'] === 'http')) {
        throw new RuntimeException('Account links require HTTPS.');
    }
    return $origin;
}

/** Values are encrypted together: no address, reset token or message body in logs. */
function mail_enqueue(PDO $pdo, ?int $userId, string $to, string $subject, string $body, string $dedupe): void
{
    if (!mail_address_valid($to) || strlen($subject) > 200 || preg_match('/[\r\n\x00]/', $subject)
        || strlen($body) > 50000 || !preg_match('//u', $subject . $body)
        || !preg_match('/\A[a-zA-Z0-9:._-]{1,190}\z/', $dedupe)) {
        throw new InvalidArgumentException('Invalid mail request.');
    }
    $payload = crypto_encrypt('mail', json_encode(['to'=>$to,'subject'=>$subject,'body'=>$body], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    $query = $pdo->prepare('INSERT INTO market_mail_queue(user_id,dedupe_key,payload_ciphertext) VALUES (?,?,?) ON DUPLICATE KEY UPDATE dedupe_key=VALUES(dedupe_key)');
    $query->execute([$userId, $dedupe, $payload]);
}

function notification_preferences(PDO $pdo, int $userId): array
{
    $query = $pdo->prepare('SELECT inapp_enabled,mail_enabled FROM market_notification_preferences WHERE user_id=?');
    $query->execute([$userId]);
    return $query->fetch() ?: ['inapp_enabled'=>1,'mail_enabled'=>0];
}

function notify_user(PDO $pdo, int $userId, string $key, string $title, string $body, ?string $url = null, bool $mail = false): void
{
    if ($userId < 1 || !preg_match('/\A[a-zA-Z0-9:._-]{1,128}\z/', $key) || strlen($title) > 200
        || strlen($body) > 5000 || !preg_match('//u', $title.$body)
        || ($url !== null && (strlen($url) > 500 || !str_starts_with($url, '/') || str_starts_with($url, '//') || preg_match('/[\r\n\\\\]/', $url)))) {
        throw new InvalidArgumentException('Invalid notification.');
    }
    $query = $pdo->prepare('SELECT email,email_verified_at FROM users WHERE id=? AND is_active=1'); $query->execute([$userId]); $recipient = $query->fetch();
    if (!$recipient) return;
    $email = $recipient['email'];
    $preferences = notification_preferences($pdo, $userId);
    $visible = (int)(bool)$preferences['inapp_enabled'];
    $emailWanted = $mail && (bool)$preferences['mail_enabled'] && $recipient['email_verified_at'] !== null;
    if (!$visible && !$emailWanted) return;
    $query = $pdo->prepare('INSERT INTO market_notifications(user_id,event_key,title,body,target_url,is_visible) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE event_key=VALUES(event_key)');
    $query->execute([$userId,$key,$title,$body,$url,$visible]);
    if ($query->rowCount() !== 1 || !$emailWanted) return;
    $link = '';
    try { if ($url !== null) $link = "\n\n" . mail_origin() . $url; } catch (RuntimeException) { /* Keep the local notification without inventing an origin. */ }
    mail_enqueue($pdo, $userId, $email, $title, $body . $link, 'notification:'.$userId.':'.$key);
}

function notification_unread_count(PDO $pdo, int $userId): int
{
    $query = $pdo->prepare('SELECT COUNT(*) FROM market_notifications WHERE user_id=? AND is_visible=1 AND read_at IS NULL');
    $query->execute([$userId]); return (int)$query->fetchColumn();
}

function mail_transport_reason(): ?string
{
    $transport = getenv('MAIL_TRANSPORT') ?: 'none';
    if ($transport === 'test') {
        return str_ends_with((string)getenv('DB_DATABASE'), '_test') && getenv('APP_ENV') === 'development'
            ? null : 'Test transport is restricted to the isolated development database.';
    }
    if ($transport !== 'sendmail') return 'Mail delivery is not configured.';
    $path = getenv('MAIL_SENDMAIL_PATH') ?: '/usr/sbin/sendmail';
    if (!preg_match('~\A/[a-zA-Z0-9_./-]+\z~', $path) || !is_file($path) || !is_executable($path)) return 'Local sendmail transport is unavailable.';
    if (!mail_address_valid((string)getenv('MAIL_FROM'))) return 'A valid sender address is required.';
    return null;
}

/** A local MTA handles SMTP/TLS; argv prevents shell or option injection. */
function mail_send_local(array $message, string $dedupe): void
{
    if (getenv('MAIL_TRANSPORT') !== 'sendmail' || mail_transport_reason() !== null) throw new RuntimeException('Local mail transport is not configured.');
    if (!mail_address_valid($message['to'] ?? '') || !is_string($message['subject'] ?? null) || !is_string($message['body'] ?? null)
        || preg_match('/[\r\n\x00]/', $message['subject'])) throw new RuntimeException('Invalid queued mail.');
    $from = (string)getenv('MAIL_FROM'); $host = substr($from, strpos($from, '@') + 1);
    $subject = mb_encode_mimeheader($message['subject'], 'UTF-8', 'B', "\r\n", strlen('Subject: '));
    $headers = 'From: ' . $from . "\r\nTo: " . $message['to'] . "\r\nSubject: " . $subject . "\r\n"
        . 'Message-ID: <' . hash('sha256', $dedupe) . '@' . $host . ">\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $bytes = $headers . chunk_split(base64_encode($message['body']), 76, "\r\n");
    $process = proc_open([getenv('MAIL_SENDMAIL_PATH') ?: '/usr/sbin/sendmail','-t','-i','-f',$from],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('MTA unavailable.');
    foreach ($pipes as $pipe) stream_set_blocking($pipe, false);
    $position = 0; $end = microtime(true) + 20; $exit = -1;
    try {
        do {
            $status = proc_get_status($process);
            foreach ([1,2] as $number) if (isset($pipes[$number]) && is_resource($pipes[$number])) stream_get_contents($pipes[$number]);
            if (isset($pipes[0]) && is_resource($pipes[0])) {
                if ($position < strlen($bytes)) {
                    $written = fwrite($pipes[0], substr($bytes, $position, 8192));
                    if ($written === false) throw new RuntimeException('MTA input failed.');
                    $position += $written;
                }
                if ($position === strlen($bytes)) { fclose($pipes[0]); unset($pipes[0]); }
            }
            if (!$status['running']) { $exit = (int)$status['exitcode']; break; }
            if (microtime(true) > $end) { proc_terminate($process); throw new RuntimeException('MTA timeout.'); }
            usleep(10000);
        } while (true);
    } finally {
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        $status = proc_get_status($process);
        if ($status['running']) { proc_terminate($process); usleep(100000); if (proc_get_status($process)['running']) proc_terminate($process,9); }
        $closed = proc_close($process); if ($exit < 0) $exit = $closed;
    }
    if ($exit !== 0 || $position !== strlen($bytes)) throw new RuntimeException('MTA did not accept the mail.');
}

function mail_worker_once(PDO $pdo, int $limit = 25): array
{
    $counts = ['delivered'=>0,'failed'=>0,'deferred'=>0];
    if ($pdo->inTransaction()) throw new LogicException('Mail worker requires its own transaction.');
    // Successful payloads are erased immediately; abandoned sensitive payloads
    // and expired capability hashes have a short configurable technical lifetime.
    $retentionRaw = getenv('MAIL_QUEUE_RETENTION_DAYS');
    $retention = $retentionRaw === false || $retentionRaw === '' ? 7 : filter_var($retentionRaw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>30]]);
    if ($retention === false) throw new RuntimeException('Mail retention configuration is invalid.');
    $pdo->exec("UPDATE market_mail_queue SET status='failed',processing_token=NULL,locked_until=NULL,last_error_code='lease_expired' WHERE status='processing' AND attempts>=8 AND locked_until<UTC_TIMESTAMP()");
    $pdo->exec("UPDATE market_mail_queue SET payload_ciphertext='',status='expired',processing_token=NULL,locked_until=NULL WHERE status IN ('pending','failed','processing') AND (locked_until IS NULL OR locked_until<UTC_TIMESTAMP()) AND ((dedupe_key LIKE 'account:reset:%' AND created_at<=UTC_TIMESTAMP()-INTERVAL 30 MINUTE) OR (dedupe_key LIKE 'account:verify:%' AND created_at<=UTC_TIMESTAMP()-INTERVAL 1 DAY))");
    $pdo->exec("UPDATE market_mail_queue SET payload_ciphertext='',status='expired',processing_token=NULL,locked_until=NULL WHERE status IN ('pending','failed','processing') AND created_at<UTC_TIMESTAMP()-INTERVAL " . $retention . " DAY AND (locked_until IS NULL OR locked_until<UTC_TIMESTAMP())");
    $pdo->exec('DELETE FROM market_auth_tokens WHERE expires_at<UTC_TIMESTAMP()-INTERVAL ' . $retention . ' DAY');
    if (mail_transport_reason() !== null) {
        $counts['deferred'] = (int)$pdo->query("SELECT COUNT(*) FROM market_mail_queue WHERE status IN ('pending','processing')")->fetchColumn();
        return $counts;
    }
    for ($i=0; $i<max(1,min(100,$limit)); ++$i) {
        $pdo->beginTransaction();
        try {
            $query = $pdo->query("SELECT * FROM market_mail_queue WHERE attempts<8 AND ((status='pending' AND next_attempt_at<=UTC_TIMESTAMP()) OR (status='processing' AND locked_until<UTC_TIMESTAMP())) ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED");
            $item = $query->fetch();
            if (!$item) { $pdo->commit(); break; }
            $lease = bin2hex(random_bytes(16));
            $pdo->prepare("UPDATE market_mail_queue SET status='processing',attempts=attempts+1,processing_token=?,locked_until=UTC_TIMESTAMP()+INTERVAL 2 MINUTE WHERE id=?")
                ->execute([$lease,$item['id']]);
            $pdo->commit();
        } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
        try {
            // Keep the account shared-lock through local handoff. A deletion or
            // suspension cannot commit between this check and MTA acceptance.
            $pdo->beginTransaction();
            $active = true;
            if ($item['user_id'] !== null) {
                $query=$pdo->prepare('SELECT is_active FROM users WHERE id=? FOR SHARE');$query->execute([$item['user_id']]);
                $active=(int)$query->fetchColumn()===1;
            }
            $query=$pdo->prepare('SELECT status,processing_token FROM market_mail_queue WHERE id=? FOR UPDATE');$query->execute([$item['id']]);$current=$query->fetch();
            if (!$current || $current['status']!=='processing' || !hash_equals((string)$current['processing_token'],$lease)) {$pdo->commit();++$counts['deferred'];continue;}
            if (!$active) {
                $pdo->prepare("UPDATE market_mail_queue SET status='cancelled',payload_ciphertext='',processing_token=NULL,locked_until=NULL WHERE id=?")->execute([$item['id']]);
                $pdo->commit();++$counts['deferred'];continue;
            }
            $payload = json_decode(crypto_decrypt('mail', $item['payload_ciphertext']), true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($payload) || !mail_address_valid($payload['to'] ?? '') || !is_string($payload['subject'] ?? null) || !is_string($payload['body'] ?? null)) throw new RuntimeException('Invalid queue payload.');
            if (getenv('MAIL_TRANSPORT') !== 'test') mail_send_local($payload, $item['dedupe_key']);
            $query = $pdo->prepare("UPDATE market_mail_queue SET status='delivered',payload_ciphertext='',delivered_at=UTC_TIMESTAMP(),processing_token=NULL,locked_until=NULL,last_error_code=NULL WHERE id=? AND status='processing' AND processing_token=?");
            $query->execute([$item['id'],$lease]); $counts['delivered'] += $query->rowCount();$pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $attempts = (int)$item['attempts'] + 1; $delay = min(86400, 60 * (2 ** min(10, $attempts-1)));
            $query = $pdo->prepare("UPDATE market_mail_queue SET status=?,next_attempt_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND),last_error_code='delivery_failed',processing_token=NULL,locked_until=NULL WHERE id=? AND processing_token=?");
            $query->execute([$attempts >= 8 ? 'failed' : 'pending',$delay,$item['id'],$lease]); ++$counts['failed'];
        }
    }
    return $counts;
}
