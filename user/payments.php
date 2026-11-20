<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/fees.php';
require_once __DIR__ . '/../config/payments.php';
$user = require_login($pdo);
$pageTitle = t('payment_customer.title');
$selected = null;
if (isset($_GET['invoice_id'])) {
    $id = is_string($_GET['invoice_id']) ? filter_var($_GET['invoice_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
    if (!$id) abort_request(404, t('payment_customer.not_found'));
    try { $selected = payments_invoice($pdo, (int)$id, (int)$user['id']); }
    catch (PaymentConflict) { abort_request(404, t('payment_customer.not_found')); }
}
$query = $pdo->prepare('SELECT id,invoice_number,service_code,fee_version,gross_cents,currency,status,created_at  ,(SELECT mode FROM market_payment_attempts a WHERE a.invoice_id=market_invoices.id ORDER BY a.id DESC LIMIT 1) AS payment_mode FROM market_invoices WHERE user_id=? ORDER BY id DESC LIMIT 100');
$query->execute([$user['id']]); $invoices = $query->fetchAll();
$methods = payments_methods(null,$pdo);
$bankAvailable=$methods['bank']['available'];
$bankOrder=$selected?bank_order($pdo,(int)$selected['id']):null;
$bitcoinCanStart=$methods['bitcoin']['available'];
$paypalCanStart=$methods['paypal']['available'];
if (($selected['service_code'] ?? null) === 'dealer_subscription') {
    $bitcoinCanStart = $bitcoinCanStart && bitcoin_config()['chain'] === 'main';
    $paypalCanStart = $paypalCanStart && payments_config()['mode'] === 'live';
}
$bitcoinAvailable=bitcoin_gate($pdo)===null;
$bitcoinOrder=$selected?bitcoin_order($pdo,(int)$selected['id']):null;
$canStartDirect=$selected&&($bankAvailable||$bitcoinCanStart)&&direct_checkout_can_start($pdo,$selected);
require __DIR__ . '/../includes/header.php';
?>
<main class="page-shell">
  <div class="page-heading"><p class="eyebrow"><?=h(t('payment_customer.account'))?></p><h1><?=h($pageTitle)?></h1><a class="button" href="/user/billing.php"><?=h(t('payment_customer.manual_link'))?></a><a class="button secondary" href="/user/invoices.php"><?=h(t('payment_customer.invoices_link'))?></a></div>
  <section class="panel">
    <h2><?=h(t('payment_customer.overview'))?></h2>
    <p><?=h(t('auction_billing.fixed_notice'))?></p>
    <p><?=h(t('payment_customer.personal_paypal'))?></p>
    <div class="form-grid">
    <?php foreach ($methods as $key => $method): ?>
      <div class="panel" <?= !$method['available'] ? 'aria-disabled="true"' : '' ?>>
        <strong><?= h($method['label']) ?></strong><p><?= h($method['reason']) ?></p>
      <span class="badge"><?=h(t($method['available'] ? 'payment_customer.configured' : 'payment_customer.unavailable'))?></span>
      </div>
    <?php endforeach; ?>
    </div>
  </section>
  <?php if ($selected): ?>
  <section class="panel"><h2><?= h($selected['invoice_number'] ?: t('payment_customer.invoice_draft')) ?></h2>
    <p><?= h(payments_service_label($selected['service_code'])) ?> · <?= h(money_eur((int)$selected['gross_cents'])) ?> · <?= h(payments_status_label($selected['status'],$selected['payment_mode']??null)) ?></p>
    <?php if (!empty($selected['is_manual'])):?><p><a class="button" href="/user/billing.php?order=<?=(int)$selected['id']?>"><?=h(t('auction_billing.open'))?></a></p>
    <?php elseif (!$bankOrder && !$bitcoinOrder && in_array($selected['status'], ['issued', 'pending'], true) && $paypalCanStart): ?>
    <form action="/actions/payment-checkout.php" method="post"><?= csrf_field() ?><input type="hidden" name="invoice_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="provider" value="paypal"><p><?=h(t('payment_customer.paypal_redirect'))?></p><button class="button" type="submit"><?=h(t('payment_customer.paypal_submit'))?></button></form>
    <?php elseif (!$bankOrder && !$bitcoinOrder && !$canStartDirect): ?><p><?=h(t('payment_customer.checkout_unavailable'))?></p><?php endif; ?>
    <?php if(empty($selected['is_manual'])){include __DIR__.'/../includes/bank-payment.php';include __DIR__.'/../includes/bitcoin-payment.php';}?>
  </section>
  <?php endif; ?>
  <section class="panel"><h2><?=h(t('payment_customer.records'))?></h2>
  <?php if (!$invoices): ?><p><?=h(t('payment_customer.empty'))?></p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th><?=h(t('payment_customer.reference'))?></th><th><?=h(t('payment_customer.service'))?></th><th><?=h(t('Betrag'))?></th><th><?=h(t('payment_customer.status'))?></th><th><?=h(t('payment_customer.date'))?></th></tr></thead><tbody>
    <?php foreach ($invoices as $invoice): ?>
      <tr><td><a href="/user/payments.php?invoice_id=<?= (int)$invoice['id'] ?>"><?= h($invoice['invoice_number'] ?: t('payment_customer.draft',['id'=>$invoice['id']])) ?></a></td><td><?= h(payments_service_label($invoice['service_code'])) ?></td><td><?= h(money_eur((int)$invoice['gross_cents'])) ?></td><td><?= h(payments_status_label($invoice['status'],$invoice['payment_mode']??null)) ?></td><td><?= h($invoice['created_at']) ?> UTC</td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?></section>
  <section class="panel"><h2><?=h(t('payment_customer.pricing'))?></h2><p><?=h(t('payment_customer.price_intro'))?></p>
    <div class="table-wrap"><table><thead><tr><th><?=h(t('payment_customer.service'))?></th><th><?=h(t('payment_customer.end_price'))?></th></tr></thead><tbody>
    <?php foreach (['private_fixed','wanted','search_alert','auction_listing','bump_single','bump_pack','category_top','home_top','banner_monthly'] as $code): $quote = fees_quote($code, null, $pdo); ?>
      <tr><td><?= h(payments_service_label($code)) ?></td><td><?= h(money_eur($quote['gross_cents'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div><p><?=h(t('payment_customer.commission'))?></p>
  </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
