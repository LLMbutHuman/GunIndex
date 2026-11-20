<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';

header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$subscriptionOwner = (int)require_login($pdo)['id'];
$subscriptionMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($subscriptionMethod, ['GET','HEAD','POST'], true)) {
    header('Allow: GET, HEAD, POST');
    abort_request(405, t('account.method'));
}
if ($subscriptionMethod === 'POST') require_csrf();

try {
    $subscriptionPublished = business_dealer_profile($pdo, $subscriptionOwner) !== null;
} catch (Throwable $failure) {
    error_log('Dealer subscription profile check failed ('.get_class($failure).')');
    abort_request(503, t('dealer_subscription.unavailable'));
}

$subscriptionError = '';
if ($subscriptionMethod === 'POST') {
    if (!$subscriptionPublished) abort_request(403, t('dealer_subscription.owner_required'));
    try {
        $invoiceId = dealer_subscription_order($pdo, $subscriptionOwner, post_string('request_key', 32), post_string('version', 80), $_POST);
        redirect('/user/payments.php?invoice_id='.$invoiceId);
    } catch (PaymentConflict $failure) {
        http_response_code(409);
        $subscriptionError = $failure->getMessage() === t('dealer_subscription.pending')
            ? t('dealer_subscription.pending') : ($failure->getMessage() === t('account.rate_limit') ? t('account.rate_limit') : t('dealer_subscription.changed'));
    } catch (InvalidArgumentException $failure) {
        http_response_code(422);
        $subscriptionError = match ($failure->getMessage()) {
            t('consent.confirm') => t('consent.confirm'),
            t('consent.changed') => t('consent.changed'),
            default => t('dealer_subscription.invalid'),
        };
    } catch (MarketAccessException) {
        http_response_code(403);
        $subscriptionError = t('dealer_subscription.owner_required');
    } catch (PaymentUnavailable|DomainException) {
        http_response_code(503);
        $subscriptionError = t('dealer_subscription.unavailable');
    } catch (Throwable $failure) {
        http_response_code(503);
        error_log('Dealer subscription order failed ('.get_class($failure).')');
        $subscriptionError = t('dealer_subscription.unavailable');
    }
}

try {
    $subscriptionHistory = dealer_subscription_history($pdo, $subscriptionOwner);
    $subscriptionActive = $subscriptionPublished && dealer_subscription_active($pdo, $subscriptionOwner);
} catch (Throwable $failure) {
    error_log('Dealer subscription history failed ('.get_class($failure).')');
    abort_request(503, t('dealer_subscription.unavailable'));
}

$subscriptionContract = null;
if (isset($_GET['contract'])) {
    try {
        $subscriptionContract = trade_integer($_GET['contract']);
    } catch (InvalidArgumentException) {
        abort_request(404, t('dealer_subscription.unavailable'));
    }
    if (!in_array($subscriptionContract, array_map('intval', array_column($subscriptionHistory, 'invoice_id')), true)) {
        abort_request(404, t('dealer_subscription.unavailable'));
    }
}

$subscriptionTerms = dealer_subscription_terms();
$subscriptionLocale = i18n_locale();
$subscriptionPending = null;
foreach ($subscriptionHistory as $subscriptionOrder) {
    if (in_array($subscriptionOrder['status'], ['draft','issued','pending'], true)) {
        $subscriptionPending = (int)$subscriptionOrder['invoice_id'];
        break;
    }
}
$subscriptionDocuments = [];
if ($subscriptionPublished && $subscriptionPending === null) {
    try {
        if (dealer_subscription_available($pdo)) $subscriptionDocuments = consent_documents($pdo, 'checkout', $subscriptionLocale);
    } catch (InvalidArgumentException|DomainException) {
        $subscriptionDocuments = [];
    } catch (Throwable $failure) {
        error_log('Dealer subscription availability failed ('.get_class($failure).')');
    }
}
$subscriptionStates = [
    'draft'=>'dealer_subscription.status_draft', 'issued'=>'dealer_subscription.status_issued',
    'pending'=>'dealer_subscription.status_pending', 'paid'=>'dealer_subscription.status_paid',
    'manual_rejected'=>'dealer_subscription.status_manual_rejected', 'partially_refunded'=>'dealer_subscription.status_partially_refunded',
    'refunded'=>'dealer_subscription.status_refunded', 'void'=>'dealer_subscription.status_void',
];
$pageTitle = t('dealer_subscription.title');
include __DIR__.'/../includes/header.php';
?>
<main class="page" id="main-content">
  <div class="page-heading">
    <h1><?=h($pageTitle)?></h1>
    <div class="button-row">
      <a href="/user/dealer.php"><?=h(t('dashboard.dealer'))?></a>
      <a href="/user/dealer-stock.php"><?=h(t('dealer_stock.title'))?></a>
      <a href="/user/invoices.php"><?=h(t('dealer_subscription.invoices'))?></a>
    </div>
  </div>

  <?php if ($subscriptionError !== ''): ?><p class="notice error" role="alert"><?=h($subscriptionError)?></p><?php endif;?>
  <?php if ($subscriptionActive): ?>
    <p class="notice success" role="status"><?=h(t('dealer_subscription.active'))?> <a href="/user/dealer-stock-export.php?owner_id=<?=$subscriptionOwner?>"><?=h(t('dealer_subscription.export_csv'))?></a></p>
  <?php endif;?>

  <section class="panel" aria-labelledby="dealer-subscription-plan">
    <h2 id="dealer-subscription-plan"><?=h(t('dealer_subscription.plan'))?></h2>
    <p><?=h(t('dealer_subscription.access',['days'=>$subscriptionTerms['term_days']]))?></p>
    <?php if ($subscriptionTerms['gross_cents'] > 0): ?>
      <p><strong><?=h(t('dealer_subscription.price',['amount'=>money_eur($subscriptionTerms['gross_cents'])]))?></strong></p>
    <?php endif;?>
    <p><?=h(t('dealer_subscription.renewal'))?></p>
    <?php if (!$subscriptionPublished): ?>
      <p class="notice"><?=h(t('dealer_subscription.owner_required'))?></p>
    <?php elseif ($subscriptionPending !== null): ?>
      <p class="notice"><?=h(t('dealer_subscription.pending'))?> <a href="/user/payments.php?invoice_id=<?=$subscriptionPending?>"><?=h(t('dealer_subscription.payments'))?></a></p>
    <?php elseif ($subscriptionDocuments): ?>
      <p><?=h(t('dealer_subscription.payment_methods'))?></p>
      <form method="post" action="/user/dealer-subscription.php" class="form-grid">
        <?=csrf_field()?>
        <input type="hidden" name="request_key" value="<?=h(bin2hex(random_bytes(16)))?>">
        <input type="hidden" name="version" value="<?=h($subscriptionTerms['version'])?>">
        <?php consent_form($subscriptionDocuments, $subscriptionLocale); ?>
        <label class="check"><input type="checkbox" name="confirm_subscription" value="1" required> <?=h(t('dealer_subscription.confirm',['days'=>$subscriptionTerms['term_days'],'amount'=>money_eur($subscriptionTerms['gross_cents'])]))?></label>
        <button class="button" type="submit"><?=h(t('dealer_subscription.order'))?></button>
      </form>
    <?php else: ?>
      <p class="notice"><?=h(t('dealer_subscription.unavailable'))?></p>
    <?php endif;?>
  </section>

  <section class="panel" aria-labelledby="dealer-subscription-history">
    <h2 id="dealer-subscription-history"><?=h(t('dealer_subscription.history'))?></h2>
    <?php if (!$subscriptionHistory): ?><p><?=h(t('dealer_subscription.empty'))?></p><?php endif;?>
    <?php foreach ($subscriptionHistory as $subscriptionOrder): ?>
      <?php $subscriptionInvoiceId = (int)$subscriptionOrder['invoice_id']; ?>
      <article class="panel" data-dealer-subscription-order="<?=$subscriptionInvoiceId?>">
        <h3><?=h(t('dealer_subscription.order_reference',['number'=>$subscriptionOrder['invoice_number'] ?? $subscriptionInvoiceId]))?></h3>
        <p><?=h(t($subscriptionStates[$subscriptionOrder['status']] ?? 'dealer_subscription.status_processing'))?> · <?=h(money_eur((int)$subscriptionOrder['gross_cents']))?> · <?=h(t('dealer_subscription.days',['days'=>$subscriptionOrder['term_days']]))?></p>
        <?php if ($subscriptionOrder['starts_at'] !== null || $subscriptionOrder['ends_at'] !== null || $subscriptionOrder['revoked_at'] !== null): ?>
          <dl class="detail-facts">
            <?php foreach (['starts_at','ends_at','revoked_at'] as $subscriptionDateKey): ?>
              <?php if ($subscriptionOrder[$subscriptionDateKey] !== null): ?>
                <div><dt><?=h(t('dealer_subscription.'.$subscriptionDateKey))?></dt><dd><time datetime="<?=h(str_replace(' ','T',$subscriptionOrder[$subscriptionDateKey]).'Z')?>"><?=h($subscriptionOrder[$subscriptionDateKey])?> UTC</time></dd></div>
              <?php endif;?>
            <?php endforeach;?>
          </dl>
        <?php endif;?>
        <div class="button-row">
          <a href="/user/payments.php?invoice_id=<?=$subscriptionInvoiceId?>"><?=h(t('dealer_subscription.payments'))?></a>
          <a href="?contract=<?=$subscriptionInvoiceId?>#dealer-subscription-contract"><?=h(t('dealer_subscription.contract'))?></a>
        </div>
      </article>
    <?php endforeach;?>
  </section>

  <?php if ($subscriptionContract !== null): ?>
    <div id="dealer-subscription-contract">
      <?php try { consent_order_links($pdo, $subscriptionOwner, $subscriptionContract); }
      catch (Throwable $failure) {
          error_log('Dealer subscription contract view failed ('.get_class($failure).')');
          echo '<p class="notice error" role="alert">'.h(t('consent.unavailable')).'</p>';
      } ?>
    </div>
  <?php endif;?>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
