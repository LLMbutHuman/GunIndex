<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';$admin=require_permission($pdo,'finance.manage');$pageTitle=t('finance_admin.title');$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){require_csrf();try{
 $action=post_string('action');
 if($action==='tax'){
  if(post_string('review_confirmed')!=='1')throw new InvalidArgumentException(t('finance_admin.tax_confirm_required'));
  $basisPoints=filter_var(post_string('basis_points',5),FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>10000]]);
  $country=post_string('country_code',2);
  if($basisPoints===false||!isset(geo_countries()[$country]))throw new InvalidArgumentException(t('finance_admin.tax_invalid'));
  finance_save_tax($pdo,(int)$admin['id'],$country,$basisPoints,post_string('explanation',300));$success=t('finance_admin.tax_saved');
 }
 elseif($action==='paypal_refund'){
  $requestId=filter_var(post_string('request_id',20),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if(!$requestId||post_string('review_confirmed')!=='1')throw new InvalidArgumentException(t('paypal_refunds.ineligible'));
  paypal_refund_queue($pdo,(int)$admin['id'],$requestId,money_parse(post_string('amount',11)),post_string('reason',2000));
  redirect('/admin/finance.php#refund-'.$requestId);
 }elseif($action==='paypal_reconcile'){
  $jobId=filter_var(post_string('job_id',20),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if(!$jobId)throw new InvalidArgumentException(t('paypal_refunds.ineligible'));
  paypal_refund_reconcile($pdo,(int)$admin['id'],$jobId,post_string('provider_id',64));
  $success=t('paypal_refunds.reconciled');
 }
 elseif(in_array($action,['refund','bank_refund'],true)){
  if(post_string('review_confirmed')!=='1')throw new InvalidArgumentException(t('finance_admin.refund_confirm_required'));
  $bank=$action==='bank_refund';
  finance_record_refund($pdo,(int)$admin['id'],(int)post_string('invoice_id'),money_parse(post_string('amount')),post_string('transaction_id',$bank?128:64),post_string('reason',2000),post_string('request_id')!==''?(int)post_string('request_id'):null,$bank?'bank':'paypal');
  $success=t('finance_admin.refund_saved');
 }elseif($action==='auction_waive'){
  auction_billing_waive($pdo,(int)$admin['id'],(int)post_string('invoice_id'),post_string('reason',2000));$success=t('auction_billing.waived');
 }elseif($action==='auction_correct'){
  $invoiceId=filter_var(post_string('invoice_id',20),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if($invoiceId===false)throw new InvalidArgumentException(t('auction_correction.ineligible'));
  $replacement=auction_fee_correct($pdo,(int)$admin['id'],$invoiceId,money_parse(post_string('reduction',11)),post_string('reason',2000));
  redirect('/admin/payments.php?invoice='.$replacement);
 }elseif($action==='reject'){
  $requestId=filter_var(post_string('request_id',20),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if($requestId===false)throw new InvalidArgumentException(t('paypal_refunds.ineligible'));
  finance_reject_refund_request($pdo,(int)$admin['id'],$requestId,post_string('reason',2000));$success=t('finance_admin.decision_saved');
 }else throw new InvalidArgumentException(t('finance_customer.invalid_action'));
}catch(InvalidArgumentException|PaymentConflict|PaymentUnavailable|DomainException $e){http_response_code(422);$error=$e->getMessage();}}
$taxes=$pdo->query('SELECT * FROM market_tax_rules ORDER BY country_code')->fetchAll();
$paypalConfig=payments_config();$paypalRefundReason=paypal_refund_gate($pdo,$paypalConfig);
$q=$pdo->prepare("SELECT r.*,i.invoice_number,i.gross_cents,u.username,j.id AS job_id,j.status AS job_status,j.reference,j.provider_refund_id,j.first_dispatch_at,j.context_hash,(SELECT a.mode FROM market_payment_attempts a WHERE a.invoice_id=i.id AND a.provider='paypal' AND a.status='paid' AND a.capture_id IS NOT NULL ORDER BY a.id DESC LIMIT 1) AS api_mode,EXISTS(SELECT 1 FROM market_bank_orders b WHERE b.invoice_id=i.id) AS bank_payment,(SELECT k.chain FROM market_bitcoin_orders k WHERE k.invoice_id=i.id) AS bitcoin_chain FROM market_refund_requests r JOIN market_invoices i ON i.id=r.invoice_id JOIN users u ON u.id=r.user_id LEFT JOIN market_paypal_refund_jobs j ON j.request_id=r.id ORDER BY (r.status='processing') DESC,r.id DESC LIMIT 100");
$q->execute();$requests=$q->fetchAll();
$documents=$pdo->query('SELECT * FROM market_invoice_documents ORDER BY id DESC LIMIT 100')->fetchAll();
$auctionCharges=$pdo->query("SELECT i.id AS invoice_id,i.invoice_number,i.status,i.gross_cents,o.listing_id FROM market_invoices i JOIN market_manual_orders o ON o.invoice_id=i.id WHERE i.service_code IN ('auction_listing','auction_success') AND i.status IN ('issued','pending','manual_rejected') ORDER BY i.id DESC LIMIT 100")->fetchAll();
include __DIR__.'/../includes/header.php';?>
<main class="container admin-page"><div class="page-heading"><p class="eyebrow"><?=h(t('finance_admin.eyebrow'))?></p><h1><?=h(t('finance_admin.heading'))?></h1><p><?=h(t('finance_admin.document_integrity'))?></p></div><div class="admin-layout"><?php include __DIR__.'/../includes/admin-nav.php';?><div class="admin-content">
<?php if($error):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?><?php if($success):?><p class="notice success"><?=h($success)?></p><?php endif;?>
<section class="panel"><h2><?=h(t('auction_billing.heading'))?></h2><p><?=h(t('auction_billing.waive_help'))?></p>
<?php foreach($auctionCharges as $charge):?>
<article><h3><?=h($charge['invoice_number'])?> · <?=h(money_eur((int)$charge['gross_cents']))?></h3>
<p>#<?=(int)$charge['listing_id']?> · <?=h(payments_status_label($charge['status']))?></p>
<form method="post" class="form-grid"><?=csrf_field()?>
<input type="hidden" name="invoice_id" value="<?=(int)$charge['invoice_id']?>">
<label class="field"><?=h(t('auction_billing.reason'))?><textarea name="reason" minlength="10" maxlength="2000" required></textarea></label>
<?php if((int)$charge['gross_cents']>1):?>
<label class="field"><?=h(t('auction_correction.reduction'))?><input name="reduction" inputmode="decimal" maxlength="11"></label>
<button class="button" type="submit" name="action" value="auction_correct"><?=h(t('auction_correction.submit'))?></button>
<?php endif;?>
<button class="button secondary" type="submit" name="action" value="auction_waive"><?=h(t('auction_billing.waive'))?></button>
</form></article>
<?php endforeach;?></section>
<section class="panel"><h2><?=h(t('finance_admin.issue_setup'))?></h2><p><?=h(t('finance_admin.issue_help'))?></p><a class="button secondary" href="/admin/settings.php"><?=h(t('finance_admin.operator_settings'))?></a><a class="button secondary" href="/admin/payments.php"><?=h(t('finance_admin.payments'))?></a></section>
<section class="panel"><h2><?=h(t('finance_admin.taxes'))?></h2><?php foreach($taxes as $tax):?><p><?=h($tax['country_code'])?> · <?=(int)$tax['basis_points']?> <?=h(t('finance_admin.basis_points_unit'))?> · <?=h($tax['explanation'])?></p><?php endforeach;?>
<form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="tax"><label class="field"><?=h(t('finance_admin.country'))?><select name="country_code"><?php foreach(geo_countries() as $code=>$name):?><option value="<?=h($code)?>"><?=h($name)?></option><?php endforeach;?></select></label><label class="field"><?=h(t('finance_admin.basis_points'))?><input type="number" name="basis_points" min="0" max="10000" required><small><?=h(t('finance_admin.basis_points_example'))?></small></label><label class="field"><?=h(t('finance_admin.tax_basis'))?><textarea name="explanation" minlength="10" maxlength="300" required></textarea></label><label><input type="checkbox" name="review_confirmed" value="1" required> <?=h(t('finance_admin.tax_confirm'))?></label><button class="button"><?=h(t('finance_admin.tax_save'))?></button></form></section>
<section class="panel"><h2><?=h(t('finance_admin.refunds'))?></h2>
<?php if($paypalRefundReason!==null):?><p><?=h($paypalRefundReason)?></p><?php endif;?>
<?php if(!$requests):?><p><?=h(t('finance_admin.refunds_empty'))?></p><?php endif;?>
<?php foreach($requests as $r):?>
<article class="panel" id="refund-<?=(int)$r['id']?>">
<h3>#<?=(int)$r['id']?> · <?=h($r['invoice_number'])?></h3>
<p><?=h($r['username'])?> · <?=h(money_eur((int)$r['requested_cents']))?> · <?=h(t('paypal_refunds.request_'.$r['status']))?><br><?=h($r['reason'])?></p>
<?php if($r['api_mode']==='sandbox'):?><p><?=h(t('paypal_refunds.sandbox'))?></p><?php endif;?>
<?php if($r['bitcoin_chain']!==null):?><p><a href="/admin/bitcoin.php"><?=h(t('bitcoin.admin_title'))?></a> · <?=h(t('bitcoin.review_required'))?></p><?php endif;?>
<?php if($r['job_id']!==null):?>
<p><?=h(t('paypal_refunds.status_'.$r['job_status']))?> · <code><?=h($r['reference'])?></code></p>
<?php if($paypalRefundReason===null&&$r['context_hash']===paypal_refund_context($paypalConfig)&&$r['first_dispatch_at']!==null&&$r['status']==='processing'):?>
<form method="post" class="form-grid"><?=csrf_field()?>
<input type="hidden" name="action" value="paypal_reconcile">
<input type="hidden" name="job_id" value="<?=(int)$r['job_id']?>">
<label class="field"><?=h(t('paypal_refunds.provider_id'))?><input name="provider_id" maxlength="64" value="<?=h($r['provider_refund_id']??'')?>" required></label>
<button class="button secondary" type="submit"><?=h(t('paypal_refunds.reconcile'))?></button>
</form>
<?php endif;?>
<?php elseif($r['status']==='requested'&&$paypalRefundReason===null&&$r['api_mode']===$paypalConfig['mode']):?>
<form method="post" class="form-grid"><?=csrf_field()?>
<input type="hidden" name="action" value="paypal_refund">
<input type="hidden" name="request_id" value="<?=(int)$r['id']?>">
<label class="field"><?=h(t('paypal_refunds.amount'))?><input name="amount" inputmode="decimal" maxlength="11" value="<?=h(payments_decimal((int)$r['requested_cents']))?>" required></label>
<label class="field"><?=h(t('auction_billing.reason'))?><textarea name="reason" minlength="10" maxlength="2000" required></textarea></label>
<label><input type="checkbox" name="review_confirmed" value="1" required> <?=h(t('paypal_refunds.authorize_confirm'))?></label>
<button class="button" type="submit"><?=h(t('paypal_refunds.authorize'))?></button>
</form>
<?php endif;?>
<?php if($r['status']==='requested'):?>
<form method="post" class="form-grid"><?=csrf_field()?>
<input type="hidden" name="request_id" value="<?=(int)$r['id']?>">
<input type="hidden" name="invoice_id" value="<?=(int)$r['invoice_id']?>">
<label class="field"><?=h(t('finance_admin.decision'))?><select name="action"><?php if((int)$r['bank_payment']===1):?><option value="bank_refund"><?=h(t('bank.refund_record'))?></option><?php elseif($r['api_mode']!=='sandbox'&&$r['bitcoin_chain']===null):?><option value="refund"><?=h(t('finance_admin.record_refund'))?></option><?php endif;?><option value="reject"><?=h(t('finance_admin.reject_request'))?></option></select></label>
<?php if($r['api_mode']!=='sandbox'&&$r['bitcoin_chain']===null):?>
<label class="field"><?=h(t('finance_admin.refund_eur'))?><input name="amount" inputmode="decimal" value="<?=h(payments_decimal((int)$r['requested_cents']))?>"></label>
<?php if((int)$r['bank_payment']===1):?>
<label class="field"><?=h(t('bank.refund_id'))?><input name="transaction_id" maxlength="128" autocomplete="off"></label>
<label><input type="checkbox" name="review_confirmed" value="1"> <?=h(t('bank.refund_confirm'))?></label>
<?php else:?>
<label class="field"><?=h(t('finance_admin.refund_paypal_id'))?><input name="transaction_id" maxlength="64" autocomplete="off"></label>
<label><input type="checkbox" name="review_confirmed" value="1"> <?=h(t('finance_admin.refund_verify'))?></label>
<?php endif;?>
<?php endif;?>
<label class="field"><?=h(t('finance_admin.reason'))?><textarea name="reason" minlength="10" maxlength="2000" required></textarea></label>
<button class="button secondary" type="submit"><?=h(t('finance_admin.decision'))?> protokollieren</button>
</form>
<?php endif;?>
</article>
<?php endforeach;?>
</section>
<section class="panel"><h2><?=h(t('finance_admin.documents'))?></h2><div class="table-wrap"><table><thead><tr><th><?=h(t('finance_customer.document'))?></th><th><?=h(t('finance_customer.kind'))?></th><th><?=h(t('Betrag'))?></th><th><?=h(t('payment_customer.date'))?></th></tr></thead><tbody><?php foreach($documents as $d):?><tr><td><a href="/user/invoices.php?download=<?=(int)$d['id']?>&amp;staff=1"><?=h($d['document_number'])?> · PDF</a></td><td><?=h(in_array($d['document_type'],['invoice','credit_note','cancellation'],true)?t('finance_customer.'.$d['document_type']):$d['document_type'])?></td><td><?=h(money_eur((int)$d['amount_cents']))?></td><td><?=h($d['issued_at'])?> UTC</td></tr><?php endforeach;?></tbody></table></div></section>
</div></div></main><?php include __DIR__.'/../includes/footer.php';?>
