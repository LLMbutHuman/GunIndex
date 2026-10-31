<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/payments.php';
require_post();
if (payments_paypal_reason() !== null) abort_request(503, 'Zahlungswebhooks sind nicht aktiviert.');
$size = $_SERVER['CONTENT_LENGTH'] ?? null;
if ($size !== null && (!ctype_digit((string)$size) || (int)$size > 262144)) abort_request(413, 'Nachricht zu groß.');
$raw = file_get_contents('php://input', false, null, 0, 262145);
if (!is_string($raw) || strlen($raw) > 262144) abort_request(413, 'Nachricht zu groß.');
try { $event = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); }
catch (JsonException) { abort_request(400, 'Ungültiges Ereignis.'); }
if (!is_array($event)) abort_request(400, 'Ungültiges Ereignis.');
$headers = [];
foreach (['AUTH_ALGO','CERT_URL','TRANSMISSION_ID','TRANSMISSION_SIG','TRANSMISSION_TIME'] as $key) {
    $value = $_SERVER['HTTP_PAYPAL_' . $key] ?? null;
    if (is_string($value)) $headers['paypal-' . strtolower(str_replace('_','-',$key))] = $value;
}
try {
    if (!payments_webhook($pdo, $headers, $event)) abort_request(400, 'Ereignis nicht bestätigt.');
    http_response_code(204);
} catch (PaymentConflict) { abort_request(409, 'Das Zahlungsereignis muss abgeglichen werden.'); }
catch (Throwable) { abort_request(503, 'Der Zahlungsabgleich ist vorübergehend nicht verfügbar.'); }
