<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';$user=require_login($pdo);$pageTitle=t('finance_customer.title');$error='';$success='';
if(isset($_GET['download'])){
 $id=is_string($_GET['download'])?filter_var($_GET['download'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;
 if(!$id)abort_request(404,t('finance_customer.doc_not_found'));$staff=false;if(($_GET['staff']??'')==='1'){$user=require_permission($pdo,'finance.manage');$staff=true;}
 try{$doc=finance_document($pdo,(int)$id,(int)$user['id'],$staff);$pdf=finance_pdf($doc);}catch(PaymentConflict){abort_request(404,t('finance_customer.doc_not_found'));}catch(PaymentUnavailable $e){abort_request(503,$e->getMessage());}
 audit_log($pdo,(int)$user['id'],'finance.document_read',(int)$doc['invoice_id'],['document_id'=>(int)$id,'staff'=>$staff]);
 header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.$doc['document_number'].'.pdf"');header('Cache-Control: no-store, private');header('Content-Length: '.strlen($pdf));echo$pdf;exit;
}
$auction=null;
$invoiceFilter='i.user_id=?';$invoiceParams=[(int)$user['id']];
if(isset($_GET['auction'])){
 $auctionId=is_string($_GET['auction'])?filter_var($_GET['auction'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;
 if(!$auctionId)abort_request(404,t('finance_customer.doc_not_found'));
 $q=$pdo->prepare("SELECT id,title FROM market_listings WHERE id=? AND seller_id=? AND type='auction'");$q->execute([$auctionId,$user['id']]);$auction=$q->fetch();
 if(!$auction)abort_request(404,t('finance_customer.doc_not_found'));
 $invoiceFilter.=" AND i.service_code IN ('auction_listing','auction_success') AND EXISTS(SELECT 1 FROM market_manual_orders m WHERE m.invoice_id=i.id AND m.listing_id=?)";
 $invoiceParams[]=$auctionId;
 $pageTitle=t('auction_billing.heading');
}
if($_SERVER['REQUEST_METHOD']==='POST'){require_csrf();try{
 $action=post_string('action');$id=(int)post_string('invoice_id');
 if($auction){
  $q=$pdo->prepare('SELECT 1 FROM market_invoices i WHERE '.$invoiceFilter.' AND i.id=?');$q->execute([...$invoiceParams,$id]);
  if(!$q->fetchColumn())throw new PaymentConflict(t('finance_customer.doc_not_found'));
 }
 if($action==='refund'){
  try{$amount=money_parse(post_string('amount'));}catch(InvalidArgumentException){throw new InvalidArgumentException(t('finance_customer.amount_invalid'));}
  finance_request_refund($pdo,(int)$user['id'],$id,$amount,post_string('reason',2000));$success=t('finance_customer.refund_saved');
 }
 elseif($action==='cancel'){finance_cancel($pdo,$id,(int)$user['id']);$success=t('finance_customer.cancelled');}
 else throw new InvalidArgumentException(t('finance_customer.invalid_action'));
}catch(InvalidArgumentException|PaymentConflict $e){http_response_code(422);$error=$e->getMessage();}}
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]])?:1;
$q=$pdo->prepare('SELECT COUNT(*) FROM market_invoices i WHERE '.$invoiceFilter);$q->execute($invoiceParams);$total=(int)$q->fetchColumn();
$q=$pdo->prepare("SELECT i.*,EXISTS(SELECT 1 FROM market_payment_attempts a WHERE a.invoice_id=i.id AND a.provider='paypal' AND a.mode='sandbox' AND a.status='paid') AS sandbox,(SELECT k.chain FROM market_bitcoin_orders k WHERE k.invoice_id=i.id) AS bitcoin_chain FROM market_invoices i WHERE ".$invoiceFilter.' ORDER BY i.id DESC LIMIT 25 OFFSET '.(($page-1)*25));$q->execute($invoiceParams);$orders=$q->fetchAll();
$q=$pdo->prepare('SELECT d.* FROM market_invoice_documents d JOIN market_invoices i ON i.id=d.invoice_id WHERE '.$invoiceFilter.' ORDER BY d.id DESC LIMIT 100');$q->execute($invoiceParams);$documents=$q->fetchAll();
$q=$pdo->prepare('SELECT r.* FROM market_refund_requests r JOIN market_invoices i ON i.id=r.invoice_id AND i.user_id=r.user_id WHERE '.$invoiceFilter.' ORDER BY r.id DESC LIMIT 100');$q->execute($invoiceParams);$requests=$q->fetchAll();
$pageLink=static fn(int $number):string=>'?'.http_build_query($auction?['auction'=>(int)$auction['id'],'page'=>$number]:['page'=>$number]);
include __DIR__.'/../includes/header.php';?>
<main class="container"><div class="page-heading"><p class="eyebrow"><?=h(t('payment_customer.account'))?></p><h1><?=h(t($auction?'auction_billing.heading':'finance_customer.heading'))?></h1><?php if($auction):?><p><?=h($auction['title'])?></p><a class="button secondary" href="/user/invoices.php"><?=h(t('finance_customer.heading'))?></a><?php else:?><a class="button secondary" href="/user/billing-details.php"><?=h(t('finance_customer.edit'))?></a><?php endif;?></div>
<?php if($error):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?><?php if($success):?><p class="notice success" role="status"><?=h($success)?></p><?php endif;?>
<?php if(!finance_enabled($pdo)):?><p class="notice"><?=h(t('finance_customer.docs_off'))?></p><?php endif;?>
<section class="panel"><h2><?=h(t('finance_customer.documents'))?></h2><?php if(!$documents):?><p><?=h(t('finance_customer.empty_documents'))?></p><?php else:?><div class="table-wrap"><table><thead><tr><th><?=h(t('finance_customer.document'))?></th><th><?=h(t('finance_customer.kind'))?></th><th><?=h(t('Betrag'))?></th><th><?=h(t('payment_customer.date'))?></th></tr></thead><tbody><?php foreach($documents as $doc):?><tr><td><a href="?download=<?=(int)$doc['id']?>"><?=h($doc['document_number'])?> · PDF</a></td><td><?=h(in_array($doc['document_type'],['invoice','credit_note','cancellation'],true)?t('finance_customer.'.$doc['document_type']):$doc['document_type'])?></td><td><?=h(money_eur((int)$doc['amount_cents']))?></td><td><?=h($doc['issued_at'])?> UTC</td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
<section class="panel"><h2><?=h(t('finance_customer.orders'))?></h2><?php foreach($orders as $order):?><article class="panel"><h3><?=h($order['invoice_number']??('#'.$order['id']))?></h3><p><?=h(payments_service_label($order['service_code']))?> · <?=h(money_eur((int)$order['gross_cents']))?> · <?=h(payments_status_label($order['status']))?></p>
<?php if((int)$order['sandbox']===1):?><p><?=h(t('paypal_refunds.sandbox'))?></p><?php endif;?>
<?php if($order['bitcoin_chain']==='regtest'):?><p><?=h(t('bitcoin.regtest'))?></p><?php endif;?>
<?php if(auction_billing_service($order['service_code'])):?><p><?php if(in_array($order['status'],['issued','pending','manual_rejected'],true))echo h(t('auction_billing.due')).' ';?><a href="/user/billing.php?order=<?=(int)$order['id']?>"><?=h(t('auction_billing.open'))?></a></p>
<?php elseif(in_array($order['status'],['issued','pending','manual_rejected'],true)):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="cancel"><input type="hidden" name="invoice_id" value="<?=(int)$order['id']?>"><button class="button secondary" type="submit"><?=h(t('finance_customer.cancel_order'))?></button></form><?php endif;?>
<?php if(in_array($order['status'],['paid','partially_refunded'],true)):?><details><summary><?=h(t('finance_customer.refund'))?></summary><p><?=h(t('finance_customer.refund_help'))?></p><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="refund"><input type="hidden" name="invoice_id" value="<?=(int)$order['id']?>"><label class="field"><?=h(t('finance_customer.refund_amount'))?><input name="amount" inputmode="decimal" required></label><label class="field"><?=h(t('finance_customer.reason'))?><textarea name="reason" minlength="10" maxlength="2000" required></textarea></label><button class="button" type="submit"><?=h(t('finance_customer.send_refund'))?></button></form></details><?php endif;?></article><?php endforeach;?><?php if(!$orders):?><p><?=h(t('finance_customer.no_orders'))?></p><?php endif;?>
<nav class="pagination"><?php if($page>1):?><a href="<?=h($pageLink($page-1))?>"><?=h(t('Zurück'))?></a><?php endif;?><span><?=h(t('finance_customer.page',['page'=>$page]))?></span><?php if($page*25<$total):?><a href="<?=h($pageLink($page+1))?>"><?=h(t('Weiter'))?></a><?php endif;?></nav></section>
<section class="panel"><h2><?=h(t('finance_customer.requests'))?></h2><?php foreach($requests as $request):?><p>#<?=(int)$request['id']?> · <?=h(money_eur((int)$request['requested_cents']))?> · <?=h(t('paypal_refunds.request_'.$request['status']))?><br><?=h($request['decision_reason']??t('paypal_refunds.awaiting'))?></p><?php endforeach;?><?php if(!$requests):?><p><?=h(t('finance_customer.no_requests'))?></p><?php endif;?></section></main><?php include __DIR__.'/../includes/footer.php';?>
