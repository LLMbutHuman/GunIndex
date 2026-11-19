<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';$admin=function_exists('require_permission')?require_permission($pdo,'finance.manage'):require_admin($pdo);$pageTitle='Manuelle Zahlungsprüfung';$error='';$success='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 require_csrf();
 try{
  if(post_string('review_confirmed')!=='1')throw new InvalidArgumentException('Bitte bestätige die Prüfung im tatsächlichen PayPal-Konto.');
  $decision=post_string('decision',24);
  $amount=money_parse(post_string('received_amount',16));
  $transaction=post_string('transaction_id',64);
  $reason=post_string('reason',1000);
  $invoiceId=filter_var(post_string('invoice_id',20),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if($decision!=='late_refund'&&$invoiceId===false)throw new InvalidArgumentException(t('auction_correction.invalid_receipt'));
  if($decision==='late_receipt'){
   auction_fee_record_late_receipt($pdo,(int)$admin['id'],$invoiceId,post_string('reference',64),$transaction,$amount,$reason);
   redirect('/admin/payments.php?invoice='.$invoiceId.'#late-receipts');
  }elseif($decision==='late_refund'){
   $attempt=filter_var(post_string('attempt_id',20),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
   if($attempt===false)throw new InvalidArgumentException(t('auction_correction.invalid_refund'));
   auction_fee_record_late_refund($pdo,(int)$admin['id'],$attempt,$amount,$transaction,$reason);
   $success=t('auction_correction.return_recorded');
  }else{
   manual_review($pdo,(int)$admin['id'],$invoiceId,$transaction,post_string('reference',64),$amount,$decision,trim($reason));
   $success='Die Entscheidung wurde protokolliert. Die gebuchte Leistung wird pro bestätigtem Auftrag höchstens einmal freigegeben. Abgelaufene knappe Reservierungen bleiben zur Klärung gesperrt.';
  }
 }catch(InvalidArgumentException|PaymentConflict|DomainException $e){http_response_code(422);$error=$e->getMessage();}
}
$filter=is_string($_GET['status']??null)?$_GET['status']:'pending';
$where=$filter==='all'?'1=1':"i.status IN ('issued','pending','manual_rejected')";
$params=[];
$reference=$_GET['reference']??'';
$selectedInvoice=$_GET['invoice']??null;
if(!is_string($reference)||strlen($reference)>64)abort_request(422,t('auction_correction.invalid_receipt'));
$reference=trim($reference);
if($selectedInvoice!==null){
 $id=is_string($selectedInvoice)?filter_var($selectedInvoice,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;
 if($id===false)abort_request(404,t('auction_correction.ineligible'));
 $where='i.id=?';$params=[$id];
}elseif($reference!==''){
 $where='o.reference=?';$params=[$reference];
}
$q=$pdo->prepare("SELECT i.*,o.reference,o.recipient,o.quantity,o.customer_claimed_at,u.username,c.replacement_invoice_id,p.previous_invoice_id,b.reference AS bank_reference FROM market_invoices i JOIN market_manual_orders o ON o.invoice_id=i.id JOIN users u ON u.id=i.user_id LEFT JOIN market_auction_fee_corrections c ON c.previous_invoice_id=i.id LEFT JOIN market_auction_fee_corrections p ON p.replacement_invoice_id=i.id LEFT JOIN market_bank_orders b ON b.invoice_id=i.id WHERE $where ORDER BY i.id DESC LIMIT 100");
$q->execute($params);$orders=$q->fetchAll();
$lateReceipts=$pdo->query("SELECT l.attempt_id,l.previous_invoice_id,l.amount_cents,l.created_at,a.capture_id,o.reference,o.recipient FROM market_auction_late_receipts l JOIN market_payment_attempts a ON a.id=l.attempt_id JOIN market_manual_orders o ON o.invoice_id=l.previous_invoice_id WHERE l.refund_id IS NULL ORDER BY l.attempt_id LIMIT 100")->fetchAll();
$bookingStates=[];$q=$pdo->query('SELECT invoice_id,status FROM market_premium_bookings ORDER BY id DESC LIMIT 1000');foreach($q->fetchAll() as $b)$bookingStates[(int)$b['invoice_id']]=$b['status'];
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page"><div class="page-heading"><p class="eyebrow">Control room</p><h1>Zahlungseingänge prüfen.</h1><p>Entscheidend ist der tatsächliche PayPal-Eingang. Screenshots, Nutzerhinweise und Browser-Rückleitungen sind keine Zahlungsbestätigung.</p></div><div class="admin-layout"><?php include __DIR__.'/../includes/admin-nav.php';?><div class="admin-content">
<?php if($error):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?><?php if($success):?><p class="notice success"><?=h($success)?></p><?php endif;?>
<section class="panel"><h2>Konfiguration</h2><p><?=h(manual_payment_reason($pdo)??'Manuelle Zahlungsaufträge sind konfiguriert.')?></p><a class="button secondary" href="/admin/settings.php">Empfängerkonto, Preise und Freigaben ändern</a><p>Ein Privatkonto ist nur verwendbar, wenn PayPal diese geschäftliche Nutzung und den Zahlungsbutton für das Konto erlaubt. Manuelle Prüfung ersetzt keine Anbieteranforderung. Automatische REST-Zahlungen bleiben separat gesperrt.</p><a href="?status=pending">Offene Aufträge</a> · <a href="?status=all">Alle Aufträge</a></section>
<section class="panel">
<form method="get" class="form-grid">
<label class="field"><?=h(t('auction_correction.find_reference'))?><input name="reference" maxlength="64" value="<?=h($reference)?>" required></label>
<button class="button secondary" type="submit"><?=h(t('auction_correction.find'))?></button>
</form></section>
<section class="panel" id="late-receipts">
<h2><?=h(t('auction_correction.review_title'))?></h2>
<?php if(!$lateReceipts):?><p><?=h(t('auction_correction.empty'))?></p><?php endif;?>
<?php foreach($lateReceipts as $late):?>
<article><h3><a href="?invoice=<?=(int)$late['previous_invoice_id']?>"><?=h($late['reference'])?></a> · <?=h(money_eur((int)$late['amount_cents']))?></h3>
<p><?=h($late['capture_id'])?> · <?=h($late['recipient'])?> · <?=h($late['created_at'])?> UTC</p>
<form method="post" class="form-grid"><?=csrf_field()?>
<input type="hidden" name="attempt_id" value="<?=(int)$late['attempt_id']?>">
<label class="field"><?=h(t('auction_correction.amount_returned'))?><input name="received_amount" inputmode="decimal" required></label>
<label class="field"><?=h(t('auction_correction.return_id'))?><input name="transaction_id" maxlength="64" autocomplete="off" required></label>
<label class="field"><?=h(t('auction_billing.reason'))?><textarea name="reason" minlength="10" maxlength="1000" required></textarea></label>
<label><input type="checkbox" name="review_confirmed" value="1" required> <?=h(t('auction_correction.return_confirm'))?></label>
<button class="button secondary" type="submit" name="decision" value="late_refund"><?=h(t('auction_correction.return_submit'))?></button>
</form></article>
<?php endforeach;?>
</section>
<?php foreach($orders as $o):?><section class="panel"><h2><?=h($o['reference'])?> · <?=h(money_eur((int)$o['gross_cents']))?></h2><p><?=h($o['username'])?> · <?=h($o['service_code'])?> · <?=h(payments_status_label($o['status']))?><br>Empfängerkonto <?=h($o['recipient'])?> · Hinweis des Nutzers: <?=h($o['customer_claimed_at']??'Keiner')?></p>
<?php if($o['previous_invoice_id']!==null):?><p><a href="?invoice=<?=(int)$o['previous_invoice_id']?>"><?=h(t('auction_correction.previous'))?></a></p><?php endif;?>
<?php if($o['replacement_invoice_id']!==null):?>
<p><a href="?invoice=<?=(int)$o['replacement_invoice_id']?>"><?=h(t('auction_correction.replacement'))?></a></p>
<details><summary><?=h(t('auction_correction.record_late'))?></summary>
<form method="post" class="form-grid"><?=csrf_field()?>
<input type="hidden" name="invoice_id" value="<?=(int)$o['id']?>">
<label class="field"><?=h(t('auction_correction.capture_id'))?><input name="transaction_id" maxlength="64" autocomplete="off" required></label>
<label class="field"><?=h(t('auction_correction.amount_received'))?><input name="received_amount" inputmode="decimal" required></label>
<label class="field"><?=h(t('auction_correction.find_reference'))?><input name="reference" maxlength="64" required></label>
<label class="field"><?=h(t('auction_billing.reason'))?><textarea name="reason" minlength="10" maxlength="1000" required></textarea></label>
<label><input type="checkbox" name="review_confirmed" value="1" required> <?=h(t('auction_correction.receipt_confirm'))?></label>
<button class="button secondary" type="submit" name="decision" value="late_receipt"><?=h(t('auction_correction.record_late'))?></button>
</form></details><?php endif;?>
<?php if(isset($bookingStates[(int)$o['id']])):?><p>Platzierungszustand: <?=h(business_state_label($bookingStates[(int)$o['id']]))?></p><?php endif;?>
<?php if($o['bank_reference']!==null):?><p><a href="/admin/bank.php"><?=h(t('bank.admin_title'))?></a> · <code><?=h($o['bank_reference'])?></code></p>
<?php elseif(filter_var($o['recipient'],FILTER_VALIDATE_EMAIL)&&in_array($o['status'],['issued','pending','manual_rejected'],true)):?><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="invoice_id" value="<?=(int)$o['id']?>">
<label class="field">Tatsächliche PayPal-Transaktionskennung<input name="transaction_id" maxlength="64" autocomplete="off"></label><label class="field">Tatsächlicher EUR-Eingang<input name="received_amount" inputmode="decimal" required></label><label class="field">Geprüfter Verwendungszweck<input name="reference" maxlength="64" required></label><label class="field">Begründung<textarea name="reason" minlength="10" maxlength="1000" required></textarea></label><label><input type="checkbox" name="review_confirmed" value="1" required> Ich habe den abgeschlossenen Eingang, EUR-Betrag, Empfänger und diese Referenz direkt in PayPal geprüft. Keine vertraulichen Kontodaten in die Begründung schreiben.</label><label class="field">Entscheidung<select name="decision"><option value="approve">Eingang bestätigen und gebuchte Leistung freigeben</option><option value="reject">Nicht bestätigen</option></select></label><button class="button" type="submit">Prüfentscheidung speichern</button></form><?php endif;?></section><?php endforeach;?>
<?php if(!$orders):?><section class="panel"><p>Keine passenden Zahlungsaufträge.</p></section><?php endif;?></div></div></main>
<?php include __DIR__.'/../includes/footer.php';?>
