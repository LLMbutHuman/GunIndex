<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/payments.php';
$user = require_login($pdo);
$rawId = $_POST['invoice_id'] ?? $_GET['invoice_id'] ?? null;
$id = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
if (!$id) abort_request(404, 'Rechnung nicht gefunden.');
try { $invoice = payments_invoice($pdo, (int)$id, (int)$user['id']); }
catch (PaymentConflict) { abort_request(404, 'Rechnung nicht gefunden.'); }
$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    try { payments_capture($pdo, (int)$id, (int)$user['id']); redirect('/user/payments.php?invoice_id=' . (int)$id); }
    catch (PaymentConflict | PaymentUnavailable $error) { http_response_code(409); $message = $error->getMessage(); }
    catch (Throwable) { http_response_code(503); $message = 'Die Bestätigung ist gerade nicht möglich. Der Zahlungsstand muss serverseitig abgeglichen werden.'; }
}
$pageTitle = 'Zahlung prüfen'; require __DIR__ . '/../includes/header.php';
?>
<main class="page-shell"><section class="panel"><p class="eyebrow">Sicherer Zahlungsabgleich</p><h1>Zahlungsstatus prüfen</h1>
<p>Die Rückkehr von PayPal bestätigt noch keinen Zahlungseingang. Der Betrag und der aktuelle Providerauftrag werden ausschließlich serverseitig geprüft.</p>
<?php if ($message): ?><p class="notice" role="alert"><?= h($message) ?></p><?php endif; ?>
<?php if (isset($_GET['cancel'])): ?><p>Du hast den Checkout verlassen. Ein Browserparameter ändert den gespeicherten Zahlungsstatus nicht.</p><?php endif; ?>
<p><?= h($invoice['invoice_number'] ?: 'Entwurf') ?> · <?= h(money_eur((int)$invoice['gross_cents'])) ?></p>
<?php if (payments_paypal_reason() === null && in_array($invoice['status'], ['issued','pending','paid'], true)): ?>
<form method="post" action="/actions/payment-return.php"><?= csrf_field() ?><input type="hidden" name="invoice_id" value="<?= (int)$id ?>"><button class="button" type="submit">Zahlungsstand verbindlich abgleichen</button></form>
<?php else: ?><p>Ein automatischer Zahlungsabgleich ist aktuell nicht verfügbar.</p><?php endif; ?>
<a class="button secondary" href="/user/payments.php?invoice_id=<?= (int)$id ?>">Zur Rechnungsübersicht</a></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
