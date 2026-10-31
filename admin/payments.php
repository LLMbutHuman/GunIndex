<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';$admin=require_admin($pdo);$pageTitle='Manuelle Zahlungsprüfung';$error='';$success='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 require_csrf();
 try{
  if(post_string('review_confirmed')!=='1')throw new InvalidArgumentException('Bitte bestätige die Prüfung im tatsächlichen PayPal-Konto.');
  manual_review($pdo,(int)$admin['id'],(int)post_string('invoice_id'),post_string('transaction_id',64),post_string('reference',64),money_parse(post_string('received_amount')),post_string('decision'),trim(post_string('reason',1000)));
  $success='Die Entscheidung wurde protokolliert. Guthaben wird pro bestätigtem Auftrag höchstens einmal erzeugt.';
 }catch(InvalidArgumentException|PaymentConflict|DomainException $e){http_response_code(422);$error=$e->getMessage();}
}
$filter=is_string($_GET['status']??null)?$_GET['status']:'pending';
$where=$filter==='all'?'1=1':"i.status IN ('issued','pending','manual_rejected')";
$q=$pdo->query("SELECT i.*,o.reference,o.recipient,o.quantity,o.customer_claimed_at,u.username FROM market_invoices i JOIN market_manual_orders o ON o.invoice_id=i.id JOIN users u ON u.id=i.user_id WHERE $where ORDER BY i.id DESC LIMIT 100");$orders=$q->fetchAll();
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page"><div class="page-heading"><p class="eyebrow">Control room</p><h1>Zahlungseingänge prüfen.</h1><p>Entscheidend ist der tatsächliche PayPal-Eingang. Screenshots, Nutzerhinweise und Browser-Rückleitungen sind keine Zahlungsbestätigung.</p></div><div class="admin-layout"><?php include __DIR__.'/../includes/admin-nav.php';?><div class="admin-content">
<?php if($error):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?><?php if($success):?><p class="notice success"><?=h($success)?></p><?php endif;?>
<section class="panel"><h2>Konfiguration</h2><p><?=h(manual_payment_reason($pdo)??'Manuelle Zahlungsaufträge sind konfiguriert.')?></p><a class="button secondary" href="/admin/settings.php">Empfängerkonto, Preise und Freigaben ändern</a><p>Ein Privatkonto ist nur verwendbar, wenn PayPal diese geschäftliche Nutzung und den Zahlungsbutton für das Konto erlaubt. Manuelle Prüfung ersetzt keine Anbieteranforderung. Automatische REST-Zahlungen bleiben separat gesperrt.</p><a href="?status=pending">Offene Aufträge</a> · <a href="?status=all">Alle Aufträge</a></section>
<?php foreach($orders as $o):?><section class="panel"><h2><?=h($o['reference'])?> · <?=h(money_eur((int)$o['gross_cents']))?></h2><p><?=h($o['username'])?> · <?=h($o['service_code'])?> · <?=h(payments_status_label($o['status']))?><br>Empfängerkonto <?=h($o['recipient'])?> · Hinweis des Nutzers: <?=h($o['customer_claimed_at']??'Keiner')?></p>
<?php if($o['status']!=='paid'):?><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="invoice_id" value="<?=(int)$o['id']?>">
<label class="field">Tatsächliche PayPal-Transaktionskennung<input name="transaction_id" maxlength="64" autocomplete="off"></label><label class="field">Tatsächlicher EUR-Eingang<input name="received_amount" inputmode="decimal" required></label><label class="field">Geprüfter Verwendungszweck<input name="reference" maxlength="64" required></label><label class="field">Begründung<textarea name="reason" minlength="10" maxlength="1000" required></textarea></label><label><input type="checkbox" name="review_confirmed" value="1" required> Ich habe den abgeschlossenen Eingang, EUR-Betrag, Empfänger und diese Referenz direkt in PayPal geprüft. Keine vertraulichen Kontodaten in die Begründung schreiben.</label><label class="field">Entscheidung<select name="decision"><option value="approve">Eingang bestätigen und Guthaben freigeben</option><option value="reject">Nicht bestätigen</option></select></label><button class="button" type="submit">Prüfentscheidung speichern</button></form><?php endif;?></section><?php endforeach;?>
<?php if(!$orders):?><section class="panel"><p>Keine passenden Zahlungsaufträge.</p></section><?php endif;?></div></div></main>
<?php include __DIR__.'/../includes/footer.php';?>
