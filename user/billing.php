<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);$pageTitle=t('billing_customer.title');$error='';$success='';
if(!isset($_SESSION['billing_order_key']))$_SESSION['billing_order_key']=bin2hex(random_bytes(32));
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 require_csrf();
 try{
  $action=post_string('action');
  if($action==='order'){
   if(post_string('accept_terms')!=='1')throw new InvalidArgumentException(t('billing_customer.terms_required'));
   $key=post_string('order_key',64);if(!hash_equals($_SESSION['billing_order_key'],$key))throw new PaymentConflict(t('billing_customer.reload'));
   $id=manual_order($pdo,(int)$user['id'],(int)post_string('listing_id'),post_string('service'),$key,null,$_POST);
   $_SESSION['billing_order_key']=bin2hex(random_bytes(32));redirect('/user/billing.php?order='.$id);
  }elseif($action==='claim'){
   $id=filter_var(post_string('invoice_id',20),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
   if($id===false)throw new PaymentConflict(t('auction_correction.claim_unavailable'));
   manual_claim($pdo,(int)$user['id'],$id);
   $success=t('billing_customer.claim_saved');
  }elseif($action==='bump'){manual_bump($pdo,(int)$user['id'],(int)post_string('listing_id'),post_string('immediate_start')==='1',$_POST);$success=t('billing_customer.bumped');}
  else throw new InvalidArgumentException(t('finance_customer.invalid_action'));
 }catch(InvalidArgumentException|DomainException|PaymentConflict|PaymentUnavailable|MarketConflictException|MarketNotFoundException $e){http_response_code(422);$error=$e->getMessage();}
}
$q=$pdo->prepare("SELECT l.* FROM market_listings l WHERE seller_id=? AND status='active' ORDER BY id DESC LIMIT 100");$q->execute([$user['id']]);$listings=array_values(array_filter($q->fetchAll(),fn($l)=>catalog_category_allowed($pdo,(int)$l['category_id'])&&($l['type']!=='auction'||!market_expired($l))));
$q=$pdo->prepare('SELECT COALESCE(SUM(c.remaining),0) FROM market_bump_credits c JOIN market_invoices i ON i.id=c.invoice_id WHERE c.user_id=? AND i.status=\'paid\'');$q->execute([$user['id']]);$credits=(int)$q->fetchColumn();
$q=$pdo->prepare('SELECT i.*,o.reference,o.recipient,o.quantity,o.terms_text,o.net_cents,o.tax_cents,o.customer_claimed_at FROM market_invoices i JOIN market_manual_orders o ON o.invoice_id=i.id WHERE i.user_id=? ORDER BY i.id DESC LIMIT 100');$q->execute([$user['id']]);$orders=$q->fetchAll();
$selected=null;if(isset($_GET['order'])){$raw=$_GET['order'];$id=is_string($raw)?filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;if(!$id)abort_request(404,t('billing_customer.not_found'));$q=$pdo->prepare('SELECT i.*,o.listing_id,o.reference,o.recipient,o.quantity,o.terms_text,o.terms_language,o.net_cents,o.tax_cents,o.customer_claimed_at FROM market_invoices i JOIN market_manual_orders o ON o.invoice_id=i.id WHERE i.id=? AND i.user_id=?');$q->execute([$id,$user['id']]);$selected=$q->fetch();if(!$selected)abort_request(404,t('billing_customer.not_found'));}
$selectedPromotion=null;if($selected&&in_array($selected['service_code'],['category_top','home_top','banner_monthly'],true)){$q=$pdo->prepare('SELECT * FROM market_premium_bookings WHERE invoice_id=? AND user_id=?');$q->execute([$selected['id'],$user['id']]);$selectedPromotion=$q->fetch()?:null;}
$bankOrder=$selected?bank_order($pdo,(int)$selected['id']):null;
$bitcoinOrder=$selected?bitcoin_order($pdo,(int)$selected['id']):null;
$bankAvailable=bank_gate($pdo)===null;
$bitcoinAvailable=bitcoin_gate($pdo)===null;
$bitcoinCanStart=$bitcoinAvailable&&bitcoin_gate($pdo,null,true)===null;
$selectedPayable=$selected&&!$bankOrder&&!$bitcoinOrder&&filter_var($selected['recipient'],FILTER_VALIDATE_EMAIL)&&in_array($selected['status'],['issued','pending','manual_rejected'],true)&&manual_payment_reason($pdo)===null;
$selectedAuction=$selected&&auction_billing_service($selected['service_code']);
if($selectedAuction&&!finance_enabled($pdo))$selectedPayable=false;
$feeReplacement=null;$feePredecessor=null;$feeReview=false;
if($selectedAuction){
 $feeReplacement=auction_fee_replacement($pdo,(int)$selected['id']);
 $q=$pdo->prepare('SELECT previous_invoice_id,charge_id FROM market_auction_fee_corrections WHERE replacement_invoice_id=?');
 $q->execute([$selected['id']]);$feePredecessor=$q->fetch()?:null;
 $related=$feePredecessor??$feeReplacement;
 if($related&&auction_fee_has_open_late_receipts($pdo,(int)$related['charge_id'])){
  $feeReview=true;
  $selectedPayable=false;
 }
}
if($selectedPromotion&&($selectedPromotion['status']!=='reserved'||strtotime($selectedPromotion['reserved_until'].' UTC')<=time()||business_reason($pdo)!==null)){
 $selectedPayable=false;
 $bankAvailable=false;
 $bitcoinAvailable=$bitcoinCanStart=false;
}
if($feeReview)$bankAvailable=$bitcoinAvailable=$bitcoinCanStart=false;
$canStartDirect=$selected&&($bankAvailable||$bitcoinCanStart)&&direct_checkout_can_start($pdo,$selected);

$reason=direct_payment_reason($pdo);
try{$contractDocuments=consent_documents($pdo,'checkout',i18n_locale());}catch(InvalidArgumentException|DomainException $e){$reason=$e->getMessage();}
include __DIR__.'/../includes/header.php';
?>
<main class="container"><div class="page-heading"><?php if($selectedAuction):?><h1><?=h(t('auction_billing.heading'))?></h1><a href="/user/invoices.php?auction=<?=(int)$selected['listing_id']?>"><?=h(t('auction_billing.open'))?></a><?php else:?><p class="eyebrow"><?=h(t('billing_customer.eyebrow'))?></p><h1><?=h(t('billing_customer.heading'))?></h1><a class="button secondary" href="/user/promotions.php"><?=h(t('billing_customer.promotions'))?></a><p><?=h(t('billing_customer.credits',['count'=>$credits]))?></p><?php endif;?></div>
<?php if($error):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?><?php if($success):?><p class="notice success" role="status"><?=h($success)?></p><?php endif;?>
<?php if(!$selectedAuction):?><section class="panel"><h2><?=h(t('billing_customer.order'))?></h2>
<?php if($reason):?><p class="notice"><?=h($reason)?> <?=h(t('billing_customer.blocked'))?></p><?php elseif(!$listings):?><p><?=h(t('billing_customer.create_listing'))?></p><?php else:?>
<form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="order"><input type="hidden" name="order_key" value="<?=h($_SESSION['billing_order_key'])?>">
<?php consent_form($contractDocuments,i18n_locale());?>
<label class="field"><?=h(t('billing_customer.listing'))?><select name="listing_id"><?php foreach($listings as $l):?><option value="<?=(int)$l['id']?>"><?=h($l['title'])?></option><?php endforeach;?></select></label>
<p><?=h(t('billing_customer.price_help'))?> <a href="/marketplace/policy.php"><?=h(t('billing_customer.read_terms'))?></a>.</p>
<?php manual_checkout_fields($pdo,['bump_single','bump_pack']);?>
<button class="button" type="submit"><?=h(t('billing_customer.order_now'))?></button></form>
<?php endif;?></section><?php endif;?>
<?php if($selected)consent_order_links($pdo,(int)$user['id'],(int)$selected['id']);?>
<?php if($selected):?><section class="panel"><h2><?=h(t('billing_customer.order_title',['reference'=>$selected['reference']]))?></h2><p><?=h(t('billing_customer.total'))?> <strong><?=h(money_eur((int)$selected['gross_cents'],i18n_locale()))?></strong> · <?=h(t('billing_customer.net'))?> <?=h(money_eur((int)$selected['net_cents'],i18n_locale()))?> · <?=h(t('billing_customer.vat'))?> <?=h(money_eur((int)$selected['tax_cents'],i18n_locale()))?></p>
<?php if(!$bankOrder&&!$bitcoinOrder&&filter_var($selected['recipient'],FILTER_VALIDATE_EMAIL)):?><p><?=h(t('payment_customer.reference'))?> <strong><?=h($selected['reference'])?></strong><br><?=h(t('billing_customer.recipient'))?> <?=h($selected['recipient'])?></p><?php endif;?>
<p><?=h(t('payment_customer.status'))?> <?=h(payments_status_label($selected['status']))?></p>
<?php if($feeReplacement):?>
<p class="notice"><?=h(t('auction_correction.obsolete'))?>
<a href="?order=<?=(int)$feeReplacement['replacement_invoice_id']?>"><?=h(t('auction_correction.replacement'))?></a></p>
<?php endif;?>
<?php if($feePredecessor):?><p><a href="?order=<?=(int)$feePredecessor['previous_invoice_id']?>"><?=h(t('auction_correction.previous'))?></a></p><?php endif;?>
<?php if($feeReview):?><p class="notice"><?=h(t('auction_correction.unresolved'))?></p><?php endif;?>
<?php include __DIR__.'/../includes/bank-payment.php';?>
<?php include __DIR__.'/../includes/bitcoin-payment.php';?>
<?php if($selectedPayable):?>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank" rel="noopener noreferrer"><input type="hidden" name="cmd" value="_xclick"><input type="hidden" name="business" value="<?=h($selected['recipient'])?>"><input type="hidden" name="amount" value="<?=h(payments_decimal((int)$selected['gross_cents']))?>"><input type="hidden" name="currency_code" value="EUR"><input type="hidden" name="item_name" value="<?=h(site_setting($pdo,'brand_name').' · '.$selected['service_code'].' · '.$selected['reference'])?>"><input type="hidden" name="invoice" value="<?=h($selected['reference'])?>"><input type="hidden" name="custom" value="<?=h($selected['reference'])?>"><input type="hidden" name="no_shipping" value="1"><input type="hidden" name="charset" value="utf-8"><button class="button" type="submit"><?=h(t('billing_customer.open_paypal',['amount'=>money_eur((int)$selected['gross_cents'],i18n_locale())]))?></button></form>
<p><?=h(t('billing_customer.paypal_help'))?></p>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="claim"><input type="hidden" name="invoice_id" value="<?=(int)$selected['id']?>"><button class="button secondary" type="submit"><?=h(t('billing_customer.claim'))?></button></form>
<?php endif;?><?php if($selectedPromotion):?><p><?=h(t('billing_customer.placement'))?> <?=h(business_state_label($selectedPromotion['status']))?> · <?=h(t('billing_customer.reserved_until'))?> <?=h($selectedPromotion['reserved_until'])?> UTC. <a href="/user/promotions.php"><?=h(t('billing_customer.manage'))?></a></p><?php endif;?><details><summary><?=h(t('billing_customer.saved_terms'))?></summary><p id="saved-order-terms" lang="<?=h($selected['terms_language']??'')?>"><?=nl2br(h($selected['terms_text']))?></p></details></section><?php endif;?>
<?php if(!$selectedAuction):?><section class="panel"><h2><?=h(t('billing_customer.activate'))?></h2><?php if($credits>0&&$listings):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="bump"><label class="field"><?=h(t('billing_customer.listing'))?><select name="listing_id"><?php foreach($listings as $l):?><option value="<?=(int)$l['id']?>"><?=h($l['title'])?><?= $l['bumped_at']?' · '.h(t('billing_customer.last_bump',['time'=>$l['bumped_at']])):''?></option><?php endforeach;?></select></label><p><?=h(t('billing_customer.activation_help'))?></p><?php consent_start_fields('bump');?><button class="button" type="submit"><?=h(t('billing_customer.activate_now'))?></button></form><?php else:?><p><?=h(t('billing_customer.credit_help'))?></p><?php endif;?></section>
<?php endif;?><section class="panel"><h2><?=h(t('billing_customer.orders'))?></h2><?php if(!$orders):?><p><?=h(t('finance_customer.no_orders'))?></p><?php else:?><div class="table-wrap"><table><thead><tr><th><?=h(t('payment_customer.reference'))?></th><th><?=h(t('Betrag'))?></th><th><?=h(t('payment_customer.status'))?></th></tr></thead><tbody><?php foreach($orders as $o):?><tr><td><a href="?order=<?=(int)$o['id']?>"><?=h($o['reference'])?></a></td><td><?=h(money_eur((int)$o['gross_cents'],i18n_locale()))?></td><td><?=h(payments_status_label($o['status']))?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section></main>
<?php include __DIR__.'/../includes/footer.php';?>
