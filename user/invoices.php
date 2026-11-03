<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';$user=require_login($pdo);$pageTitle='Rechnungen und Erstattungen';$error='';$success='';
if(isset($_GET['download'])){
 $id=is_string($_GET['download'])?filter_var($_GET['download'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;
 if(!$id)abort_request(404,'Dokument nicht gefunden.');$staff=false;if(($_GET['staff']??'')==='1'){$user=require_permission($pdo,'finance.manage');$staff=true;}
 try{$doc=finance_document($pdo,(int)$id,(int)$user['id'],$staff);$pdf=finance_pdf($doc);}catch(PaymentConflict){abort_request(404,'Dokument nicht gefunden.');}catch(PaymentUnavailable $e){abort_request(503,$e->getMessage());}
 audit_log($pdo,(int)$user['id'],'finance.document_read',(int)$doc['invoice_id'],['document_id'=>(int)$id,'staff'=>$staff]);
 header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.$doc['document_number'].'.pdf"');header('Cache-Control: no-store, private');header('Content-Length: '.strlen($pdf));echo$pdf;exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'){require_csrf();try{
 $action=post_string('action');$id=(int)post_string('invoice_id');
 if($action==='refund'){finance_request_refund($pdo,(int)$user['id'],$id,money_parse(post_string('amount')),post_string('reason',2000));$success='Deine Erstattungsanfrage wurde zur Prüfung gespeichert. Es wurde noch kein Geld zurückgezahlt.';}
 elseif($action==='cancel'){finance_cancel($pdo,$id,(int)$user['id']);$success='Der unbezahlte Auftrag wurde storniert.';}
 else throw new InvalidArgumentException('Ungültige Aktion.');
}catch(InvalidArgumentException|PaymentConflict $e){http_response_code(422);$error=$e->getMessage();}}
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]])?:1;
$q=$pdo->prepare('SELECT COUNT(*) FROM market_invoices WHERE user_id=?');$q->execute([$user['id']]);$total=(int)$q->fetchColumn();
$q=$pdo->prepare('SELECT * FROM market_invoices WHERE user_id=? ORDER BY id DESC LIMIT 25 OFFSET '.(($page-1)*25));$q->execute([$user['id']]);$orders=$q->fetchAll();
$q=$pdo->prepare('SELECT d.* FROM market_invoice_documents d JOIN market_invoices i ON i.id=d.invoice_id WHERE i.user_id=? ORDER BY d.id DESC LIMIT 100');$q->execute([$user['id']]);$documents=$q->fetchAll();
$q=$pdo->prepare('SELECT * FROM market_refund_requests WHERE user_id=? ORDER BY id DESC LIMIT 100');$q->execute([$user['id']]);$requests=$q->fetchAll();
include __DIR__.'/../includes/header.php';?>
<main class="container"><div class="page-heading"><p class="eyebrow">Dein Konto</p><h1>Rechnungen. Klar dokumentiert.</h1><a class="button secondary" href="/user/billing-details.php">Rechnungsanschrift bearbeiten</a></div>
<?php if($error):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?><?php if($success):?><p class="notice success" role="status"><?=h($success)?></p><?php endif;?>
<?php if(!finance_enabled($pdo)):?><p class="notice">Die vollständige Rechnungsausstellung ist noch nicht aktiviert. Zahlungsaufträge sind keine bereits ausgestellten steuerlichen Rechnungs-PDFs.</p><?php endif;?>
<section class="panel"><h2>Unveränderliche Dokumente</h2><?php if(!$documents):?><p>Noch keine Rechnungsdokumente vorhanden.</p><?php else:?><div class="table-wrap"><table><thead><tr><th>Dokument</th><th>Art</th><th>Betrag</th><th>Datum</th></tr></thead><tbody><?php foreach($documents as $doc):?><tr><td><a href="?download=<?=(int)$doc['id']?>"><?=h($doc['document_number'])?> · PDF</a></td><td><?=h(['invoice'=>'Rechnung','credit_note'=>'Gutschrift','cancellation'=>'Storno'][$doc['document_type']]??$doc['document_type'])?></td><td><?=h(money_eur((int)$doc['amount_cents']))?></td><td><?=h($doc['issued_at'])?> UTC</td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
<section class="panel"><h2>Aufträge und Entscheidungen</h2><?php foreach($orders as $order):?><article class="panel"><h3><?=h($order['invoice_number']??('#'.$order['id']))?></h3><p><?=h($order['service_code'])?> · <?=h(money_eur((int)$order['gross_cents']))?> · <?=h(payments_status_label($order['status']))?></p>
<?php if(auction_billing_service($order['service_code'])):?><p><?php if(in_array($order['status'],['issued','pending','manual_rejected'],true))echo h(t('auction_billing.due')).' ';?><a href="/user/billing.php?order=<?=(int)$order['id']?>"><?=h(t('auction_billing.open'))?></a></p>
<?php elseif(in_array($order['status'],['issued','pending','manual_rejected'],true)):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="cancel"><input type="hidden" name="invoice_id" value="<?=(int)$order['id']?>"><button class="button secondary" type="submit">Unbezahlten Auftrag stornieren</button></form><?php endif;?>
<?php if(in_array($order['status'],['paid','partially_refunded'],true)):?><details><summary>Erstattung anfragen</summary><p>Eine Anfrage bestätigt noch keine Rückzahlung. Gesetzliche Ansprüche werden im Einzelfall geprüft.</p><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="refund"><input type="hidden" name="invoice_id" value="<?=(int)$order['id']?>"><label class="field">Gewünschter EUR-Betrag<input name="amount" inputmode="decimal" required></label><label class="field">Grund<textarea name="reason" minlength="10" maxlength="2000" required></textarea></label><button class="button" type="submit">Erstattungsanfrage senden</button></form></details><?php endif;?></article><?php endforeach;?><?php if(!$orders):?><p>Noch keine Aufträge.</p><?php endif;?>
<nav class="pagination"><?php if($page>1):?><a href="?page=<?=$page-1?>">Zurück</a><?php endif;?><span>Seite <?=$page?></span><?php if($page*25<$total):?><a href="?page=<?=$page+1?>">Weiter</a><?php endif;?></nav></section>
<section class="panel"><h2>Deine Erstattungsanfragen</h2><?php foreach($requests as $request):?><p>#<?=(int)$request['id']?> · <?=h(money_eur((int)$request['requested_cents']))?> · <?=h($request['status'])?><br><?=h($request['decision_reason']??'Die Entscheidung steht noch aus.')?></p><?php endforeach;?><?php if(!$requests):?><p>Keine offenen Anfragen.</p><?php endif;?></section></main><?php include __DIR__.'/../includes/footer.php';?>
