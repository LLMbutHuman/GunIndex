<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/payments.php';
$user = require_login($pdo);
$rawId = $_POST['invoice_id'] ?? $_GET['invoice_id'] ?? null;
$id = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
if (!$id) abort_request(404, t('payment_customer.not_found'));
try { $invoice = payments_invoice($pdo, (int)$id, (int)$user['id']); }
catch (PaymentConflict) { abort_request(404, t('payment_customer.not_found')); }
$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    try { payments_capture($pdo, (int)$id, (int)$user['id']); redirect('/user/payments.php?invoice_id=' . (int)$id); }
    catch (PaymentConflict | PaymentUnavailable $error) { http_response_code(409); $message = $error->getMessage(); }
    catch (Throwable) { http_response_code(503); $message = t('payment_customer.return_error'); }
}
$pageTitle = t('payment_customer.return_title'); require __DIR__ . '/../includes/header.php';
?>
<main class="page-shell"><section class="panel"><p class="eyebrow"><?=h(t('payment_customer.return_kicker'))?></p><h1><?=h(t('payment_customer.return_heading'))?></h1>
<p><?=h(t('payment_customer.return_explainer'))?></p>
<?php if ($message): ?><p class="notice" role="alert"><?= h($message) ?></p><?php endif; ?>
<?php if (isset($_GET['cancel'])): ?><p><?=h(t('payment_customer.return_cancel'))?></p><?php endif; ?>
<p><?= h($invoice['invoice_number'] ?: t('payment_customer.state_draft')) ?> · <?= h(money_eur((int)$invoice['gross_cents'])) ?></p>
<?php if (payments_paypal_reason() === null && in_array($invoice['status'], ['issued','pending','paid'], true)): ?>
<form method="post" action="/actions/payment-return.php"><?= csrf_field() ?><input type="hidden" name="invoice_id" value="<?= (int)$id ?>"><button class="button" type="submit"><?=h(t('payment_customer.return_check'))?></button></form>
<?php else: ?><p><?=h(t('payment_customer.return_unavailable'))?></p><?php endif; ?>
<a class="button secondary" href="/user/payments.php?invoice_id=<?= (int)$id ?>"><?=h(t('payment_customer.return_link'))?></a></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
