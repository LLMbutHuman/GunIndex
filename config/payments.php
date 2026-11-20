<?php
declare(strict_types=1);
require_once __DIR__.'/i18n.php';

final class PaymentUnavailable extends RuntimeException {}
final class PaymentConflict extends RuntimeException {}

function payments_config(): array
{
    $read = static fn(string $key, string $fallback = ''): string => (($value = getenv($key)) !== false) ? $value : $fallback;
    return [
        'enabled' => $read('PAYPAL_ENABLED', '0') === '1',
        'mode' => $read('PAYPAL_MODE', 'sandbox'),
        'scope' => $read('PAYPAL_BUSINESS_SCOPE', 'general_only'),
        'account_type' => $read('PAYPAL_ACCOUNT_TYPE', 'personal'),
        'billing_approved' => $read('PLATFORM_BILLING_APPROVED', '0') === '1',
        'live_approved' => $read('PAYPAL_LIVE_APPROVED', '0') === '1',
        'refunds_enabled' => $read('PAYPAL_REFUNDS_ENABLED', '0') === '1',
        'refunds_live_approved' => $read('PAYPAL_REFUNDS_LIVE_APPROVED', '0') === '1',
        'client_id' => $read('PAYPAL_CLIENT_ID'), 'client_secret' => $read('PAYPAL_CLIENT_SECRET'),
        'merchant_id' => $read('PAYPAL_MERCHANT_ID'), 'webhook_id' => $read('PAYPAL_WEBHOOK_ID'),
        'app_url' => $read('APP_URL'),
    ];
}

function payments_origin(array $config): string
{
    $url = rtrim((string)($config['app_url'] ?? ''), '/');
    $parts = parse_url($url);
    if ($parts === false || filter_var($url, FILTER_VALIDATE_URL) === false || preg_match('/[\x00-\x20\\\\]/', $url) || !isset($parts['host'], $parts['scheme']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !empty($parts['path'])) {
        throw new PaymentUnavailable(t('payment_customer.origin_invalid'));
    }
    $local = ($config['mode'] ?? '') === 'sandbox' && in_array(strtolower($parts['host']), ['localhost', '127.0.0.1', '[::1]'], true);
    if ($parts['scheme'] !== 'https' && !($local && $parts['scheme'] === 'http')) {
        throw new PaymentUnavailable(t('payment_customer.origin_https'));
    }
    return $url;
}

function payments_paypal_reason(?array $config = null): ?string
{
    $config ??= payments_config();
    if (!$config['enabled']) return t('payment_customer.paypal_off');
    if ($config['account_type'] !== 'business') return t('payment_customer.paypal_account');
    if ($config['scope'] !== 'general_only' || !$config['billing_approved']) return t('payment_customer.paypal_scope');
    if (!in_array($config['mode'], ['sandbox', 'live'], true)) return t('payment_customer.paypal_mode');
    if ($config['mode'] === 'live' && !$config['live_approved']) return t('payment_customer.paypal_approval');
    foreach (['client_id', 'client_secret', 'merchant_id', 'webhook_id', 'app_url'] as $key) {
        if (!is_string($config[$key] ?? null) || $config[$key] === '') return t('payment_customer.paypal_config');
    }
    try { payments_origin($config); } catch (PaymentUnavailable $error) { return $error->getMessage(); }
    return null;
}

function payments_methods(?array $config = null,?PDO $pdo=null): array
{
    $reason = payments_paypal_reason($config);
    $bankReason=$pdo===null?'Banküberweisung ist zurzeit nicht verfügbar.':bank_gate($pdo);
    $bitcoinReason=$pdo===null?'Bitcoin ist zurzeit nicht verfügbar.':bitcoin_gate($pdo,null,true);
    return [
        'paypal' => ['label' => 'PayPal', 'available' => $reason === null, 'reason' => $reason ?? t('payment_customer.paypal_official')],
        'bitcoin' => ['label' => 'Bitcoin', 'available' => $bitcoinReason===null, 'reason' => $bitcoinReason??t('bitcoin.start_help')],
        'bank' => ['label' => $pdo===null?'Banküberweisung':t('bank.bank_transfer'), 'available' => $bankReason===null, 'reason' => $bankReason??t('bank.start_help')],
    ];
}

function payments_assert_provider(string $provider, ?array $config = null): void
{
    $methods = payments_methods($config);
    if (!isset($methods[$provider]) || !$methods[$provider]['available']) {
        throw new PaymentUnavailable($methods[$provider]['reason'] ?? 'Diese Zahlungsart ist nicht verfügbar.');
    }
}

function payments_api_cents(mixed $amount): int
{
    if (!is_array($amount) || ($amount['currency_code'] ?? null) !== 'EUR' || !is_string($amount['value'] ?? null) || !preg_match('/\A([0-9]{1,8})\.([0-9]{2})\z/', $amount['value'], $match)) {
        throw new PaymentConflict('Währung oder Zahlungsbetrag stimmt nicht überein.');
    }
    return (int)$match[1] * 100 + (int)$match[2];
}

function payments_decimal(int $cents): string
{
    if ($cents < 1 || $cents > 9999999999) throw new InvalidArgumentException('Ungültiger Rechnungsbetrag.');
    return intdiv($cents, 100) . '.' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
}

/** Official fixed-origin REST transport; injection is PHP/CLI-only, never a browser option. */
final class MarketPayPal
{
    private ?Closure $transport;
    private ?string $token = null;
    public function __construct(private array $config, ?callable $transport = null)
    {
        $this->transport = $transport === null ? null : Closure::fromCallable($transport);
        if (!in_array($config['mode'] ?? '', ['sandbox', 'live'], true)) throw new PaymentUnavailable('Ungültige Zahlungsumgebung.');
    }
    private function request(string $method, string $path, array $headers, string $body = ''): array
    {
        if (!preg_match('~\A/(?:v1/oauth2/token|v1/notifications/verify-webhook-signature|v2/checkout/orders(?:/[A-Z0-9]{6,64}(?:/capture)?)?|v2/payments/(?:captures/[A-Z0-9]{6,64}/refund|refunds/[A-Z0-9]{6,64}))\z~', $path)) {
            throw new PaymentConflict('Ungültiger Providerpfad.');
        }
        $origin = $this->config['mode'] === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        if ($this->transport !== null) {
            $response = ($this->transport)($method, $origin . $path, $headers, $body);
        } else {
            if (!function_exists('curl_init')) throw new PaymentUnavailable('Die sichere HTTPS-Anbindung ist nicht verfügbar.');
            $handle = curl_init($origin . $path);
            curl_setopt_array($handle, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
            if ($method !== 'GET') curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
            $bundle = getenv('CURL_CA_BUNDLE') ?: getenv('SSL_CERT_FILE');
            if (is_string($bundle) && $bundle !== '') curl_setopt($handle, CURLOPT_CAINFO, $bundle);
            $raw = curl_exec($handle);
            $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);
            if (!is_string($raw)) throw new PaymentUnavailable(t('payment_customer.provider_timeout'));
            $response = ['status' => $status, 'body' => $raw];
        }
        if (!is_array($response) || !is_int($response['status'] ?? null) || $response['status'] < 200 || $response['status'] >= 300) throw new PaymentUnavailable(t('payment_customer.provider_rejected'));
        try { $decoded = is_array($response['body'] ?? null) ? $response['body'] : json_decode((string)($response['body'] ?? ''), true, 64, JSON_THROW_ON_ERROR); }
        catch (JsonException) { throw new PaymentUnavailable(t('payment_customer.provider_response')); }
        if (!is_array($decoded)) throw new PaymentUnavailable(t('payment_customer.provider_response'));
        return $decoded;
    }
    private function api(string $method, string $path, ?array $body = null, ?string $requestId = null, bool $representation = false): array
    {
        if ($this->token === null) {
            $auth = $this->request('POST', '/v1/oauth2/token', ['Authorization: Basic ' . base64_encode($this->config['client_id'] . ':' . $this->config['client_secret']), 'Content-Type: application/x-www-form-urlencoded'], 'grant_type=client_credentials');
            if (!is_string($auth['access_token'] ?? null) || $auth['access_token'] === '' || strlen($auth['access_token']) > 8192 || preg_match('/[\r\n]/', $auth['access_token'])) throw new PaymentUnavailable(t('payment_customer.provider_auth'));
            $this->token = $auth['access_token'];
        }
        $headers = ['Authorization: Bearer ' . $this->token, 'Content-Type: application/json', 'Accept: application/json'];
        if ($representation) $headers[] = 'Prefer: return=representation';
        if ($requestId !== null) {
            if (!preg_match('/\A[a-zA-Z0-9-]{16,64}\z/', $requestId)) throw new PaymentConflict('Ungültige Zahlungsreferenz.');
            $headers[] = 'PayPal-Request-Id: ' . $requestId;
        }
        return $this->request($method, $path, $headers, $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR));
    }
    public function create(array $invoice, string $requestId): array
    {
        $origin = payments_origin($this->config);
        return $this->api('POST', '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'invoice:' . $invoice['id'], 'custom_id' => (string)$invoice['id'], 'invoice_id' => $invoice['invoice_number'],
                'payee' => ['merchant_id' => $this->config['merchant_id']],
                'amount' => ['currency_code' => 'EUR', 'value' => payments_decimal((int)$invoice['gross_cents'])],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => ['user_action' => 'PAY_NOW', 'return_url' => $origin . '/actions/payment-return.php?invoice_id=' . $invoice['id'], 'cancel_url' => $origin . '/actions/payment-return.php?invoice_id=' . $invoice['id'] . '&cancel=1']]],
        ], $requestId);
    }
    private function orderId(string $orderId): string
    {
        if (!preg_match('/\A[A-Z0-9]{6,64}\z/', $orderId)) throw new PaymentConflict('Ungültige Providerreferenz.');
        return $orderId;
    }
    public function order(string $orderId): array { return $this->api('GET', '/v2/checkout/orders/' . $this->orderId($orderId)); }
    public function capture(string $orderId, string $requestId): array { return $this->api('POST', '/v2/checkout/orders/' . $this->orderId($orderId) . '/capture', null, $requestId); }
    public function approval(array $order): string
    {
        $id = $this->orderId((string)($order['id'] ?? ''));
        $expected = $this->config['mode'] === 'sandbox' ? 'www.sandbox.paypal.com' : 'www.paypal.com';
        foreach ($order['links'] ?? [] as $link) {
            if (!is_array($link) || !in_array($link['rel'] ?? null, ['approve', 'payer-action'], true) || !is_string($link['href'] ?? null)) continue;
            $url = parse_url($link['href']); $query = []; parse_str($url['query'] ?? '', $query);
            if (($url['scheme'] ?? '') === 'https' && ($url['host'] ?? '') === $expected && ($url['path'] ?? '') === '/checkoutnow' && !isset($url['user']) && !isset($url['pass']) && !isset($url['fragment']) && (!isset($url['port']) || $url['port'] === 443) && ($query['token'] ?? null) === $id) return $link['href'];
        }
        throw new PaymentConflict('Die Checkoutadresse konnte nicht bestätigt werden.');
    }
    public function validate(array $order, array $invoice, string $orderId, bool $completed, bool $forRefund = false): ?string
    {
        if (($order['id'] ?? null) !== $orderId || ($order['intent'] ?? null) !== 'CAPTURE' || ($order['status'] ?? null) !== ($completed ? 'COMPLETED' : 'APPROVED') || count($order['purchase_units'] ?? []) !== 1) throw new PaymentConflict('Der Providerauftrag passt nicht zur Rechnung.');
        $unit = $order['purchase_units'][0];
        if (($unit['reference_id'] ?? null) !== 'invoice:' . $invoice['id'] || ($unit['custom_id'] ?? null) !== (string)$invoice['id'] || ($unit['invoice_id'] ?? null) !== $invoice['invoice_number'] || ($unit['payee']['merchant_id'] ?? null) !== $this->config['merchant_id'] || payments_api_cents($unit['amount'] ?? null) !== (int)$invoice['gross_cents']) throw new PaymentConflict(t('payment_customer.mismatch'));
        if (!$completed) return null;
        $captures = $unit['payments']['captures'] ?? [];
        if (count($captures) !== 1) throw new PaymentConflict(t('payment_customer.incomplete'));
        $capture = $captures[0];
        $states = $forRefund ? ['COMPLETED','PARTIALLY_REFUNDED','REFUNDED'] : ['COMPLETED'];
        if (!in_array($capture['status'] ?? null,$states,true) || payments_api_cents($capture['amount'] ?? null) !== (int)$invoice['gross_cents'] || ($capture['final_capture'] ?? true) !== true) throw new PaymentConflict(t('payment_customer.confirm'));
        return $this->orderId((string)($capture['id'] ?? ''));
    }
    public function verifyWebhook(array $headers, array $event): bool
    {
        $headers = array_change_key_case($headers, CASE_LOWER);
        foreach (['paypal-auth-algo', 'paypal-cert-url', 'paypal-transmission-id', 'paypal-transmission-sig', 'paypal-transmission-time'] as $name) {
            if (!is_string($headers[$name] ?? null) || $headers[$name] === '' || strlen($headers[$name]) > 8192 || preg_match('/[\r\n]/', $headers[$name])) return false;
        }
        $cert = parse_url($headers['paypal-cert-url']);
        if (($cert['scheme'] ?? '') !== 'https' || !in_array($cert['host'] ?? '', ['api.paypal.com', 'api.sandbox.paypal.com', 'api-m.paypal.com', 'api-m.sandbox.paypal.com'], true) || isset($cert['user']) || isset($cert['pass']) || isset($cert['port']) || isset($cert['query']) || isset($cert['fragment']) || !preg_match('~\A/v1/notifications/certs/[a-zA-Z0-9-]+\z~', $cert['path'] ?? '')) return false;
        $answer = $this->api('POST', '/v1/notifications/verify-webhook-signature', ['auth_algo' => $headers['paypal-auth-algo'], 'cert_url' => $headers['paypal-cert-url'], 'transmission_id' => $headers['paypal-transmission-id'], 'transmission_sig' => $headers['paypal-transmission-sig'], 'transmission_time' => $headers['paypal-transmission-time'], 'webhook_id' => $this->config['webhook_id'], 'webhook_event' => $event]);
        return ($answer['verification_status'] ?? null) === 'SUCCESS';
    }
    public function refund(string $captureId, int $cents, string $requestId, ?string $reference=null): array
    {
        $payload=['amount'=>['currency_code'=>'EUR','value'=>payments_decimal($cents)]];
        if ($reference!==null) {
            if (!preg_match('/\AGI-R-[a-f0-9]{32}\z/',$reference)) throw new PaymentConflict('Ungültige Erstattungsreferenz.');
            $payload['custom_id']=$reference;
        }
        return $this->api('POST','/v2/payments/captures/'.$this->orderId($captureId).'/refund',$payload,$requestId,true);
    }
    public function refundDetails(string $id): array { return $this->api('GET','/v2/payments/refunds/'.$this->orderId($id)); }
}

function payments_invoice(PDO $pdo, int $invoiceId, int $userId, bool $lock = false): array
{
    $query = $pdo->prepare('SELECT i.*,(SELECT COUNT(*) FROM market_manual_orders o WHERE o.invoice_id=i.id) AS is_manual,(SELECT a.mode FROM market_payment_attempts a WHERE a.invoice_id=i.id ORDER BY a.id DESC LIMIT 1) AS payment_mode FROM market_invoices i WHERE i.id=? AND i.user_id=?' . ($lock ? ' FOR UPDATE' : ''));
    $query->execute([$invoiceId, $userId]);
    $invoice = $query->fetch();
    if (!$invoice) throw new PaymentConflict('Rechnung nicht gefunden.');
    return $invoice;
}

function payments_assert_invoice(array $invoice): void
{
    if (!in_array($invoice['status'] ?? '', ['issued', 'pending'], true) || !is_string($invoice['invoice_number'] ?? null) || !preg_match('/\A[A-Za-z0-9-]{1,64}\z/', $invoice['invoice_number']) || ($invoice['currency'] ?? '') !== 'EUR') throw new PaymentConflict('Diese Rechnung ist nicht zahlbar.');
    payments_decimal((int)$invoice['gross_cents']);
}

function direct_checkout_can_start(PDO $pdo,array $invoice): bool
{
    if (!in_array($invoice['status'],['issued','pending'],true)||$invoice['currency']!=='EUR'||(int)$invoice['gross_cents']<1) return false;
    $q=$pdo->prepare('SELECT EXISTS(SELECT 1 FROM market_invoice_snapshots WHERE invoice_id=?) AND NOT EXISTS(SELECT 1 FROM market_payment_attempts WHERE invoice_id=?) AND NOT EXISTS(SELECT 1 FROM market_manual_orders WHERE invoice_id=? AND customer_claimed_at IS NOT NULL)');
    $q->execute([$invoice['id'],$invoice['id'],$invoice['id']]);
    return (bool)$q->fetchColumn();
}

function payments_begin(PDO $pdo, int $invoiceId, int $userId, string $provider): string
{
    $config = payments_config(); payments_assert_provider($provider, $config);
    $client = new MarketPayPal($config);
    // Persist the request identity before any remote side effect. A lost HTTP
    // response or later transaction failure must retry the SAME provider request.
    $pdo->beginTransaction();
    try {
        maintenance_assert_market_available($pdo);
        $invoice = payments_invoice($pdo, $invoiceId, $userId, true); payments_assert_invoice($invoice); payments_assert_automatic($pdo, (int)$invoice['id']);
        if($invoice['service_code']==='dealer_subscription'&&$config['mode']!=='live')throw new PaymentUnavailable(t('dealer_subscription.unavailable'));
        if(function_exists('finance_assert_payable'))finance_assert_payable($pdo,$invoice,$config['mode']);
        $query = $pdo->prepare('SELECT * FROM market_payment_attempts WHERE invoice_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE'); $query->execute([$invoiceId]); $attempt = $query->fetch();
        if ($attempt && ($attempt['provider'] !== 'paypal' || !in_array($attempt['status'], ['created', 'pending'], true))) throw new PaymentConflict('Der letzte Zahlungsversuch muss zuerst geprüft werden.');
        if (!$attempt) {
            $idempotency = bin2hex(random_bytes(16));
            $query = $pdo->prepare("INSERT INTO market_payment_attempts(invoice_id,provider,status,idempotency_key,mode,created_at,updated_at) VALUES (?,'paypal','created',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())"); $query->execute([$invoiceId, $idempotency, $config['mode']]);
            $attempt = ['id' => (int)$pdo->lastInsertId(), 'idempotency_key' => $idempotency, 'provider_order_id' => null, 'mode' => $config['mode']];
        }
        $pdo->commit();
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
    $attemptId = (int)$attempt['id'];
    $pdo->beginTransaction();
    try {
        maintenance_assert_market_available($pdo);
        $invoice = payments_invoice($pdo, $invoiceId, $userId, true); payments_assert_invoice($invoice); payments_assert_automatic($pdo, (int)$invoice['id']);
        $query = $pdo->prepare('SELECT * FROM market_payment_attempts WHERE id=? AND invoice_id=? FOR UPDATE'); $query->execute([$attemptId, $invoiceId]); $attempt = $query->fetch();
        if (!$attempt || !in_array($attempt['status'], ['created', 'pending'], true)) throw new PaymentConflict('Dieser Zahlungsversuch ist nicht mehr offen.');
        if ($attempt['mode'] !== $config['mode']) throw new PaymentConflict('Die Zahlungsumgebung wurde geändert.');
        $order = $attempt['provider_order_id'] ? $client->order($attempt['provider_order_id']) : $client->create($invoice, $attempt['idempotency_key']);
        $approval = $client->approval($order);
        $query = $pdo->prepare("UPDATE market_payment_attempts SET provider_order_id=?,status='pending',updated_at=UTC_TIMESTAMP() WHERE id=?"); $query->execute([$order['id'], $attempt['id']]);
        $pdo->commit(); return $approval;
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}

/** Must be called under invoice and attempt row locks. */
function payments_record_paid(PDO $pdo, array $invoice, array $attempt, string $capture, string $eventId, string $payloadHash): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Payment booking requires transaction.');
    if (in_array($invoice['status'],['paid','partially_refunded','refunded'],true)) {
        if ($attempt['status'] !== 'paid' || $attempt['capture_id'] !== $capture) throw new PaymentConflict('Eine andere Zahlung ist bereits zugeordnet.');
    } else {
        payments_assert_invoice($invoice); payments_assert_automatic($pdo, (int)$invoice['id']);
        if (!in_array($attempt['status'], ['created', 'pending'], true)) throw new PaymentConflict('Der Zahlungsversuch ist abgeschlossen oder gesperrt.');
        $query = $pdo->prepare("UPDATE market_payment_attempts SET status='paid',capture_id=?,updated_at=UTC_TIMESTAMP() WHERE id=?"); $query->execute([$capture, $attempt['id']]);
        $query = $pdo->prepare("UPDATE market_invoices SET status='paid',updated_at=UTC_TIMESTAMP() WHERE id=?"); $query->execute([$invoice['id']]);
    }
    $query = $pdo->prepare('SELECT attempt_id FROM market_payment_events WHERE provider_event_id=?'); $query->execute([$eventId]); $existing = $query->fetchColumn();
    if ($existing !== false && (int)$existing !== (int)$attempt['id']) throw new PaymentConflict('Zahlungsereignis ist bereits anderweitig zugeordnet.');
    if ($existing === false) { $query = $pdo->prepare("INSERT INTO market_payment_events(attempt_id,provider_event_id,kind,payload_hash,created_at) VALUES (?,?,'capture.completed',?,UTC_TIMESTAMP())"); $query->execute([$attempt['id'], $eventId, $payloadHash]); }
    // Both capture and verified webhook use this same transaction and idempotent issuer.
    if(function_exists('finance_issue_invoice')&&!in_array($invoice['status'],['partially_refunded','refunded'],true))finance_issue_invoice($pdo,(int)$invoice['id']);
}

function payments_capture(PDO $pdo, int $invoiceId, int $userId, ?MarketPayPal $client=null): void
{
    $config = payments_config(); payments_assert_provider('paypal', $config); $client ??= new MarketPayPal($config);
    $pdo->beginTransaction();
    try {
        $invoice = payments_invoice($pdo, $invoiceId, $userId, true);
        $settled=in_array($invoice['status'],['paid','partially_refunded','refunded'],true);
        if (!$settled) payments_assert_invoice($invoice); payments_assert_automatic($pdo, (int)$invoice['id']);
        $query = $pdo->prepare("SELECT * FROM market_payment_attempts WHERE invoice_id=? AND provider='paypal' ORDER BY id DESC LIMIT 1 FOR UPDATE"); $query->execute([$invoiceId]); $attempt = $query->fetch();
        if (!$attempt || !$attempt['provider_order_id'] || $attempt['mode'] !== $config['mode'] || !in_array($attempt['status'], ['created', 'pending', 'paid'], true)) throw new PaymentConflict('Kein bestätigbarer Zahlungsversuch.');
        if(function_exists('finance_assert_payable'))finance_assert_payable($pdo,$invoice,$attempt['mode']);
        if ($settled && $attempt['status'] !== 'paid') throw new PaymentConflict('Eine andere Zahlung ist bereits zugeordnet.');
        $order = $client->order($attempt['provider_order_id']);
        if (($order['status'] ?? '') !== 'COMPLETED') {
            if ($settled) throw new PaymentConflict('Der Providerstand muss geprüft werden.');
            $client->validate($order, $invoice, $attempt['provider_order_id'], false);
            $client->capture($attempt['provider_order_id'], $attempt['idempotency_key'] . '-capture');
            $order = $client->order($attempt['provider_order_id']);
        }
        $capture = $client->validate($order, $invoice, $attempt['provider_order_id'], true,$settled);
        payments_record_paid($pdo, $invoice, $attempt, $capture, 'capture:' . $capture, hash('sha256', json_encode($order, JSON_THROW_ON_ERROR)));
        $pdo->commit();
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}

function payments_webhook(PDO $pdo, array $headers, array $event, ?MarketPayPal $client=null): bool
{
    $config = payments_config(); payments_assert_provider('paypal', $config); $client ??= new MarketPayPal($config);
    if (!$client->verifyWebhook($headers, $event)) return false;
    if (in_array($event['event_type']??null,['PAYMENT.CAPTURE.REFUNDED','PAYMENT.REFUND.PENDING','PAYMENT.REFUND.FAILED'],true)) {
        if (function_exists('paypal_refund_webhook')) paypal_refund_webhook($pdo,$config,$client,$event);
        return true;
    }
    if (($event['event_type'] ?? '') !== 'PAYMENT.CAPTURE.COMPLETED') return true;
    $eventId = $event['id'] ?? null; $orderId = $event['resource']['supplementary_data']['related_ids']['order_id'] ?? null;
    if (!is_string($eventId) || !preg_match('/\A[A-Z0-9-]{6,100}\z/', $eventId) || !is_string($orderId)) throw new PaymentConflict('Ungültiges Zahlungsereignis.');
    $query = $pdo->prepare('SELECT a.id,a.invoice_id,i.user_id FROM market_payment_attempts a JOIN market_invoices i ON i.id=a.invoice_id WHERE a.provider_order_id=? AND a.provider=?'); $query->execute([$orderId, 'paypal']); $reference = $query->fetch();
    if (!$reference) throw new PaymentConflict('Zahlung nicht zugeordnet.');
    $pdo->beginTransaction();
    try {
        $invoice = payments_invoice($pdo, (int)$reference['invoice_id'], (int)$reference['user_id'], true);
        $settled=in_array($invoice['status'],['paid','partially_refunded','refunded'],true);
        if (!$settled) payments_assert_invoice($invoice); payments_assert_automatic($pdo, (int)$invoice['id']);
        $query = $pdo->prepare('SELECT * FROM market_payment_attempts WHERE id=? FOR UPDATE'); $query->execute([$reference['id']]); $attempt = $query->fetch();
        if (!$attempt || $attempt['mode'] !== $config['mode'] || !in_array($attempt['status'], ['pending', 'paid'], true)) throw new PaymentConflict('Zahlungsversuch muss geprüft werden.');
        $order = $client->order($orderId); $capture = $client->validate($order, $invoice, $orderId, true,$settled);
        if (($event['resource']['id'] ?? '') !== $capture || payments_api_cents($event['resource']['amount'] ?? null) !== (int)$invoice['gross_cents']) throw new PaymentConflict('Webhook und Providerauftrag stimmen nicht überein.');
        payments_record_paid($pdo, $invoice, $attempt, $capture, $eventId, hash('sha256', json_encode($event, JSON_THROW_ON_ERROR)));
        $pdo->commit(); return true;
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}

function payments_assert_automatic(PDO $pdo,int $invoiceId):void {
 $q=$pdo->prepare('SELECT 1 FROM market_manual_orders WHERE invoice_id=?');$q->execute([$invoiceId]);if($q->fetchColumn())throw new PaymentConflict('Dieser Zahlungsauftrag benötigt die manuelle Prüfung.');
}
function payments_status_label(string $status,?string $mode=null):string {
 if($status==='paid'&&$mode==='sandbox')return t('payment_customer.state_sandbox');
 if(!in_array($status,['draft','issued','pending','paid','manual_rejected','partially_refunded','refunded','void'],true))$status='processing';
 return t('payment_customer.state_'.$status);
}

function payments_service_label(string $service): string
{
    if ($service === 'dealer_subscription') return t('dealer_subscription.title');
    $key = 'payment_customer.service_' . $service;
    $title = t($key);
    return $title === $key ? $service : $title;
}
