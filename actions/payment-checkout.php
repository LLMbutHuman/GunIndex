<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/payments.php';
$user = require_login($pdo); require_post(); require_csrf();
$invoice = is_string($_POST['invoice_id'] ?? null) ? filter_var($_POST['invoice_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
$provider = $_POST['provider'] ?? null;
if (!$invoice || !is_string($provider)) abort_request(422, 'Ungültige Zahlungsanforderung.');
try { $approval = payments_begin($pdo, (int)$invoice, (int)$user['id'], $provider); header('Location: ' . $approval, true, 303); exit; }
catch (PaymentUnavailable $error) { abort_request(503, $error->getMessage()); }
catch (PaymentConflict $error) { abort_request(409, $error->getMessage()); }
catch (Throwable) { abort_request(503, 'Die Zahlung konnte nicht gestartet werden. Es wurde keine lokale Zahlungsbestätigung erzeugt.'); }
