<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/fees.php';
require_once __DIR__ . '/../config/payments.php';
$user = require_login($pdo);
$pageTitle = 'Zahlungen und Gebühren';
$selected = null;
if (isset($_GET['invoice_id'])) {
    $id = is_string($_GET['invoice_id']) ? filter_var($_GET['invoice_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
    if (!$id) abort_request(404, 'Rechnung nicht gefunden.');
    try { $selected = payments_invoice($pdo, (int)$id, (int)$user['id']); }
    catch (PaymentConflict) { abort_request(404, 'Rechnung nicht gefunden.'); }
}
$query = $pdo->prepare('SELECT id,invoice_number,service_code,fee_version,gross_cents,currency,status,created_at  ,(SELECT mode FROM market_payment_attempts a WHERE a.invoice_id=market_invoices.id ORDER BY a.id DESC LIMIT 1) AS payment_mode FROM market_invoices WHERE user_id=? ORDER BY id DESC LIMIT 100');
$query->execute([$user['id']]); $invoices = $query->fetchAll();
$methods = payments_methods();
require __DIR__ . '/../includes/header.php';
?>
<main class="page-shell">
  <div class="page-heading"><p class="eyebrow">Dein Konto</p><h1>Zahlungen &amp; Gebühren</h1><a class="button" href="/user/billing.php">Manuelle Zahlungsaufträge &amp; Bump-Guthaben</a><a class="button secondary" href="/user/invoices.php">Rechnungs-PDFs, Stornos &amp; Erstattungen</a><p>Klare Beträge. Nachvollziehbare Zustände.</p></div>
  <section class="panel">
    <h2>Aktueller Zahlungsstand</h2>
    <p><?=h(t('auction_billing.fixed_notice'))?></p>
    <p>Mit einem privaten PayPal-Konto kannst du bei einem freigegebenen Händler bezahlen. Für den automatischen Empfang über PayPal Checkout wird hier ein geeignetes Händlerkonto benötigt. Ein Privatkonto wird nicht durch einen technischen Umweg freigeschaltet.</p>
    <div class="form-grid">
    <?php foreach ($methods as $key => $method): ?>
      <div class="panel" <?= !$method['available'] ? 'aria-disabled="true"' : '' ?>>
        <strong><?= h($method['label']) ?></strong><p><?= h($method['reason']) ?></p>
        <span class="badge"><?= $method['available'] ? 'Konfiguriert' : 'Aktuell nicht verfügbar' ?></span>
      </div>
    <?php endforeach; ?>
    </div>
  </section>
  <?php if ($selected): ?>
  <section class="panel"><h2><?= h($selected['invoice_number'] ?: 'Rechnungsentwurf') ?></h2>
    <p><?= h($selected['service_code']) ?> · <?= h(money_eur((int)$selected['gross_cents'])) ?> · <?= h(payments_status_label($selected['status'],$selected['payment_mode']??null)) ?></p>
    <?php if (!empty($selected['is_manual'])):?><p><a class="button" href="/user/billing.php?order=<?=(int)$selected['id']?>"><?=h(t('auction_billing.open'))?></a></p>
    <?php elseif (in_array($selected['status'], ['issued', 'pending'], true) && $methods['paypal']['available']): ?>
    <form action="/actions/payment-checkout.php" method="post"><?= csrf_field() ?><input type="hidden" name="invoice_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="provider" value="paypal"><p>Du wirst zur sicheren Bestätigung an PayPal weitergeleitet.</p><button class="button" type="submit">Zahlungspflichtig mit PayPal fortfahren</button></form>
    <?php else: ?><p>Für diesen Datensatz steht aktuell kein Checkout zur Verfügung.</p><?php endif; ?>
  </section>
  <?php endif; ?>
  <section class="panel"><h2>Meine Rechnungsdatensätze</h2>
  <?php if (!$invoices): ?><p>Noch keine Rechnungen vorhanden.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Referenz</th><th>Leistung</th><th>Betrag</th><th>Status</th><th>Datum</th></tr></thead><tbody>
    <?php foreach ($invoices as $invoice): ?>
      <tr><td><a href="/user/payments.php?invoice_id=<?= (int)$invoice['id'] ?>"><?= h($invoice['invoice_number'] ?: 'Entwurf #' . $invoice['id']) ?></a></td><td><?= h($invoice['service_code']) ?></td><td><?= h(money_eur((int)$invoice['gross_cents'])) ?></td><td><?= h(payments_status_label($invoice['status'],$invoice['payment_mode']??null)) ?></td><td><?= h($invoice['created_at']) ?> UTC</td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?></section>
  <section class="panel"><h2>Vorgesehenes Preismodell</h2><p>Diese Übersicht ist eine Preisvorschau. Eine kostenpflichtige Funktion wird erst nach vollständiger technischer und geschäftlicher Freigabe angeboten.</p>
    <div class="table-wrap"><table><thead><tr><th>Leistung</th><th>Vorgesehener Endpreis</th></tr></thead><tbody>
    <?php foreach (['private_fixed','wanted','search_alert','auction_listing','bump_single','bump_pack','category_top','home_top','banner_monthly'] as $code): $quote = fees_quote($code, null, $pdo); ?>
      <tr><td><?= h($quote['label']) ?></td><td><?= h(money_eur($quote['gross_cents'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div><p>Auktionsprovision: progressiv 3 % / 2 % / 1 % / 0,5 %, höchstens 29,90 €, zuzüglich Einstellgebühr. Alte Rechnungsbeträge werden durch eine Preisänderung nicht verändert.</p>
  </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
