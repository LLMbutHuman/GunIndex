<?php
declare(strict_types=1);

function finance_enabled(PDO $pdo): bool { return site_setting($pdo,'finance_enabled','0')==='1'; }
function finance_text(array $values,string $key,int $max,bool $required=true): string {
 $value=$values[$key]??'';if(!is_string($value)||!preg_match('//u',$value)||preg_match('/[\x00-\x1f\x7f]/u',$value)||mb_strlen(trim($value))>$max||($required&&trim($value)===''))throw new InvalidArgumentException('Bitte vollständige, gültige Rechnungsangaben eingeben.');
 return trim($value);
}
function finance_billing_profile(PDO $pdo,int $userId):?array {
 $q=$pdo->prepare('SELECT * FROM market_billing_profiles WHERE user_id=?');$q->execute([$userId]);return $q->fetch()?:null;
}
function finance_save_profile(PDO $pdo,int $userId,array $values):void {
 $country=geo_country(finance_text($values,'country_code',2));$postal=finance_text($values,'postal_code',16);
 if(!geo_validate_postcode($country,$postal))throw new InvalidArgumentException('Die Postleitzahl passt nicht zum Rechnungsland.');
 $data=[$userId,finance_text($values,'legal_name',150),finance_text($values,'address_line1',150),finance_text($values,'address_line2',150,false),geo_normalize_postcode($country,$postal),finance_text($values,'city',100),$country,finance_text($values,'vat_id',40,false)];
 $q=$pdo->prepare('INSERT INTO market_billing_profiles(user_id,legal_name,address_line1,address_line2,postal_code,city,country_code,vat_id) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE legal_name=VALUES(legal_name),address_line1=VALUES(address_line1),address_line2=VALUES(address_line2),postal_code=VALUES(postal_code),city=VALUES(city),country_code=VALUES(country_code),vat_id=VALUES(vat_id)');
 $q->execute($data);audit_log($pdo,$userId,'finance.billing_updated',$userId,[]);
}
function finance_operator(PDO $pdo):array {
 $values=site_settings($pdo);$out=[];
 foreach(['legal_name'=>150,'address_line1'=>150,'address_line2'=>150,'postcode'=>16,'city'=>100,'country'=>2,'email'=>190,'vat_id'=>40,'tax_number'=>50,'registration'=>150] as $key=>$max){
  $out[$key]=finance_text($values,'operator_'.$key,$max,!in_array($key,['address_line2','vat_id','tax_number','registration'],true));
 }
 if($out['vat_id']===''&&$out['tax_number']==='')throw new PaymentUnavailable('Für die Rechnungsausstellung fehlt die geprüfte Steuer- oder Umsatzsteuerkennung.');
 if(!filter_var($out['email'],FILTER_VALIDATE_EMAIL)||!geo_validate_postcode($out['country'],$out['postcode']))throw new PaymentUnavailable('Betreiberanschrift oder Kontakt sind unvollständig.');
 return$out;
}
function finance_save_tax(PDO $pdo,int $actor,string $country,int $bps,string $explanation):void {
 governance_assert_actor($pdo,$actor,'finance.manage');$country=geo_country($country);
 if($bps<0||$bps>10000||mb_strlen(trim($explanation))<10||mb_strlen($explanation)>300)throw new InvalidArgumentException('Geprüften Steuersatz und nachvollziehbare Erläuterung angeben.');
 $pdo->prepare('INSERT INTO market_tax_rules(country_code,basis_points,explanation,reviewed_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE basis_points=VALUES(basis_points),explanation=VALUES(explanation),reviewed_by=VALUES(reviewed_by),reviewed_at=UTC_TIMESTAMP()')->execute([$country,$bps,trim($explanation),$actor]);
 audit_log($pdo,$actor,'finance.tax_reviewed',null,['country'=>$country,'basis_points'=>$bps]);
}
function finance_prepare_order(PDO $pdo,int $invoiceId,int $userId):void {
 if(!finance_enabled($pdo))return;
 if(!$pdo->inTransaction())throw new LogicException('Order snapshots require a transaction.');
 $q=$pdo->prepare('SELECT * FROM market_invoice_snapshots WHERE invoice_id=?');$q->execute([$invoiceId]);if($q->fetch())return;
 $buyer=finance_billing_profile($pdo,$userId);if(!$buyer)throw new PaymentUnavailable('Bitte zuerst deine vollständige Rechnungsanschrift speichern.');
 $operator=finance_operator($pdo);
 $q=$pdo->prepare('SELECT * FROM market_tax_rules WHERE country_code=?');$q->execute([$buyer['country_code']]);$rule=$q->fetch();if(!$rule)throw new PaymentUnavailable('Die Steuerbehandlung für dieses Rechnungsland ist noch nicht freigegeben.');
 // A buyer-supplied VAT ID never automatically grants a tax exemption.
 $q=$pdo->prepare('SELECT * FROM market_invoices WHERE id=? AND user_id=?');$q->execute([$invoiceId,$userId]);$invoice=$q->fetch();if(!$invoice)throw new PaymentConflict('Auftrag nicht gefunden.');
 $tax=fees_tax_split((int)$invoice['gross_cents'],(int)$rule['basis_points'],true);
 $payload=['version'=>'finance-1.0','operator'=>$operator,'buyer'=>$buyer,'service_code'=>$invoice['service_code'],'label'=>fees_quote($invoice['service_code'],null,$pdo)['label'],'fee_version'=>$invoice['fee_version'],'currency'=>'EUR','gross_cents'=>(int)$invoice['gross_cents'],'net_cents'=>$tax['net_cents'],'tax_cents'=>$tax['tax_cents'],'tax_bps'=>(int)$rule['basis_points'],'tax_explanation'=>$rule['explanation'],'order_reference'=>$invoice['invoice_number'],'ordered_at'=>$invoice['created_at'],'locale'=>i18n_locale(),'test_mode'=>str_ends_with((string)getenv('DB_DATABASE'),'_test')];
 $json=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
 $pdo->prepare('INSERT INTO market_invoice_snapshots(invoice_id,user_id,payload_ciphertext,payload_hash) VALUES (?,?,?,?)')->execute([$invoiceId,$userId,crypto_encrypt('finance',$json),hash('sha256',$json)]);
 $pdo->prepare('UPDATE market_manual_orders SET net_cents=?,tax_cents=?,tax_bps=? WHERE invoice_id=?')->execute([$tax['net_cents'],$tax['tax_cents'],$rule['basis_points'],$invoiceId]);
}
function finance_snapshot(PDO $pdo,int $invoiceId):array {
 $q=$pdo->prepare('SELECT * FROM market_invoice_snapshots WHERE invoice_id=?');$q->execute([$invoiceId]);$row=$q->fetch();if(!$row)throw new PaymentConflict('Für diesen Auftrag wurde noch kein verbindlicher Rechnungssnapshot erstellt.');
 $json=crypto_decrypt('finance',$row['payload_ciphertext']);if(!hash_equals($row['payload_hash'],hash('sha256',$json)))throw new RuntimeException('Invoice snapshot integrity failed.');
 return json_decode($json,true,32,JSON_THROW_ON_ERROR);
}
function finance_write_document(PDO $pdo,array $invoice,array $payload,string $type,int $amount,?int $actor,?int $related=null):int {
 if(!$pdo->inTransaction())throw new LogicException('Document issuance requires a transaction.');
 if(!in_array($type,['invoice','credit_note','cancellation'],true)||$amount<1||$amount>9999999999)throw new InvalidArgumentException('Ungültiges Rechnungsdokument.');
 $year=(int)gmdate('Y');$pdo->prepare('INSERT IGNORE INTO market_invoice_sequences(calendar_year,next_number) VALUES (?,1)')->execute([$year]);
 $q=$pdo->prepare('SELECT next_number FROM market_invoice_sequences WHERE calendar_year=? FOR UPDATE');$q->execute([$year]);$number=(int)$q->fetchColumn();
 $prefix=site_setting($pdo,'invoice_prefix','GI');if(!preg_match('/\A[A-Z0-9][A-Z0-9-]{0,9}\z/',$prefix))throw new RuntimeException('Invoice prefix configuration invalid.');
 $reference=$prefix.'-'.$year.'-'.str_pad((string)$number,6,'0',STR_PAD_LEFT);
 $pdo->prepare('UPDATE market_invoice_sequences SET next_number=next_number+1 WHERE calendar_year=?')->execute([$year]);
 $payload['document_number']=$reference;$payload['document_type']=$type;$payload['issued_at']=gmdate('Y-m-d H:i:s');$payload['related_document_id']=$related;
 $json=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
 $q=$pdo->prepare('INSERT INTO market_invoice_documents(invoice_id,origin_invoice_id,document_number,document_type,related_document_id,amount_cents,payload_ciphertext,content_hash,created_by) VALUES (?,?,?,?,?,?,?,?,?)');
 $q->execute([$invoice['id'],$type==='invoice'?$invoice['id']:null,$reference,$type,$related,$amount,crypto_encrypt('finance',$json),hash('sha256',$json),$actor]);return(int)$pdo->lastInsertId();
}
function finance_issue_invoice(PDO $pdo,int $invoiceId,?int $actor=null):int {
 if(!finance_enabled($pdo))return 0;$own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
 try{
  $q=$pdo->prepare('SELECT * FROM market_invoices WHERE id=? FOR UPDATE');$q->execute([$invoiceId]);$invoice=$q->fetch();if(!$invoice||$invoice['status']!=='paid')throw new PaymentConflict('Nur bestätigte Zahlungen können hier abgerechnet werden.');
  $q=$pdo->prepare('SELECT id FROM market_invoice_documents WHERE origin_invoice_id=?');$q->execute([$invoiceId]);if($id=$q->fetchColumn()){if($own)$pdo->commit();return(int)$id;}
  $q=$pdo->prepare('SELECT mode FROM market_payment_attempts WHERE invoice_id=? ORDER BY id DESC LIMIT 1');$q->execute([$invoiceId]);if($q->fetchColumn()==='sandbox'){if($own)$pdo->commit();return 0;}
  $payload=finance_snapshot($pdo,$invoiceId);$id=finance_write_document($pdo,$invoice,$payload,'invoice',(int)$invoice['gross_cents'],$actor);
  $pdo->prepare("INSERT INTO market_finance_events(invoice_id,actor_id,kind,amount_cents,reference) VALUES (?,?,'cash_in',?,?)")->execute([$invoiceId,$actor,$invoice['gross_cents'],'paid:'.$invoiceId]);
  if($actor)audit_log($pdo,$actor,'finance.invoice_issued',$invoiceId,['document_id'=>$id]);
  if(function_exists('notify_user'))notify_user($pdo,(int)$invoice['user_id'],'invoice:'.$id,i18n_user_text($pdo,(int)$invoice['user_id'],'notification.invoice_issued.title'),i18n_user_text($pdo,(int)$invoice['user_id'],'notification.invoice_issued.body',['reference'=>$invoice['invoice_number']]),'/user/invoices.php',true);
  if($own)$pdo->commit();return$id;
 }catch(Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw$e;}
}
function finance_request_refund(PDO $pdo,int $userId,int $invoiceId,int $amount,string $reason):int {
 if($amount<1||mb_strlen(trim($reason))<10||mb_strlen($reason)>2000)throw new InvalidArgumentException('Betrag und nachvollziehbaren Grund angeben.');
 $pdo->beginTransaction();try{
  $q=$pdo->prepare("SELECT * FROM market_invoices WHERE id=? AND user_id=? AND status IN ('paid','partially_refunded') FOR UPDATE");$q->execute([$invoiceId,$userId]);$invoice=$q->fetch();if(!$invoice)throw new PaymentConflict('Keine erstattungsfähige Zahlung gefunden.');
  $q=$pdo->prepare('SELECT COALESCE(SUM(amount_cents),0) FROM market_refunds WHERE invoice_id=?');$q->execute([$invoiceId]);if($amount>(int)$invoice['gross_cents']-(int)$q->fetchColumn())throw new PaymentConflict('Der angefragte Betrag übersteigt die noch nicht erstattete Zahlung.');
  $q=$pdo->prepare("SELECT COUNT(*) FROM market_refund_requests WHERE invoice_id=? AND status='requested'");$q->execute([$invoiceId]);if((int)$q->fetchColumn()>0)throw new PaymentConflict('Eine offene Erstattungsanfrage besteht bereits.');
  $pdo->prepare('INSERT INTO market_refund_requests(invoice_id,user_id,requested_cents,reason) VALUES (?,?,?,?)')->execute([$invoiceId,$userId,$amount,trim($reason)]);$id=(int)$pdo->lastInsertId();audit_log($pdo,$userId,'finance.refund_requested',$invoiceId,['request_id'=>$id]);$pdo->commit();return$id;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
function finance_record_refund(PDO $pdo,int $actor,int $invoiceId,int $amount,string $transaction,string $reason,?int $requestId=null):int {
 governance_assert_actor($pdo,$actor,'finance.manage');$transaction=strtoupper(trim($transaction));
 if($amount<1||!preg_match('/\A[A-Z0-9]{10,64}\z/',$transaction)||mb_strlen(trim($reason))<10||mb_strlen($reason)>2000)throw new InvalidArgumentException('Tatsächlichen Erstattungsbetrag, Transaktionskennung und Begründung angeben.');
 $pdo->beginTransaction();try{
  $q=$pdo->prepare('SELECT * FROM market_invoices WHERE id=? FOR UPDATE');$q->execute([$invoiceId]);$invoice=$q->fetch();if(!$invoice)throw new PaymentConflict('Auftrag nicht gefunden.');
  $q=$pdo->prepare('SELECT * FROM market_refunds WHERE transaction_id=?');$q->execute([$transaction]);if($prior=$q->fetch()){if((int)$prior['invoice_id']===$invoiceId&&(int)$prior['amount_cents']===$amount){$pdo->commit();return(int)$prior['id'];}throw new PaymentConflict('Diese Erstattung wurde bereits anders zugeordnet.');}
  if(!in_array($invoice['status'],['paid','partially_refunded'],true))throw new PaymentConflict('Diese Zahlung ist nicht erstattungsfähig.');
  $q=$pdo->prepare('SELECT 1 FROM market_payment_attempts WHERE capture_id=?');$q->execute([$transaction]);if($q->fetchColumn())throw new PaymentConflict('Die ursprüngliche Zahlung ist kein Erstattungsnachweis.');
  $q=$pdo->prepare('SELECT COALESCE(SUM(amount_cents),0) FROM market_refunds WHERE invoice_id=?');$q->execute([$invoiceId]);$before=(int)$q->fetchColumn();$total=$before+$amount;if($total>(int)$invoice['gross_cents'])throw new PaymentConflict('Übererstattung wird verweigert.');
  if($requestId!==null){$q=$pdo->prepare("SELECT * FROM market_refund_requests WHERE id=? AND invoice_id=? AND status='requested' FOR UPDATE");$q->execute([$requestId,$invoiceId]);if(!$q->fetch())throw new PaymentConflict('Erstattungsanfrage nicht offen.');}
  $pdo->prepare('INSERT INTO market_refunds(invoice_id,request_id,transaction_id,amount_cents,verified_by,reason) VALUES (?,?,?,?,?,?)')->execute([$invoiceId,$requestId,$transaction,$amount,$actor,trim($reason)]);$id=(int)$pdo->lastInsertId();$document=null;
  $q=$pdo->prepare("SELECT id FROM market_invoice_documents WHERE origin_invoice_id=?");$q->execute([$invoiceId]);$original=$q->fetchColumn();
  if($original){$payload=finance_snapshot($pdo,$invoiceId);$afterTax=fees_tax_split($total,(int)$payload['tax_bps'],true);$beforeTax=fees_tax_split($before,(int)$payload['tax_bps'],true);$payload['gross_cents']=$amount;$payload['net_cents']=$afterTax['net_cents']-$beforeTax['net_cents'];$payload['tax_cents']=$amount-$payload['net_cents'];$payload['correction_reason']=trim($reason);$document=finance_write_document($pdo,$invoice,$payload,'credit_note',$amount,$actor,(int)$original);$pdo->prepare('UPDATE market_refunds SET credit_document_id=? WHERE id=?')->execute([$document,$id]);}
  $pdo->prepare("UPDATE market_invoices SET status=? WHERE id=?")->execute([$total===(int)$invoice['gross_cents']?'refunded':'partially_refunded',$invoiceId]);
  $pdo->prepare('UPDATE market_bump_credits SET remaining=0 WHERE invoice_id=?')->execute([$invoiceId]);if(function_exists('business_refund_invoice'))business_refund_invoice($pdo,$invoiceId);
  $pdo->prepare("INSERT INTO market_finance_events(invoice_id,actor_id,kind,amount_cents,reference) VALUES (?,?,'cash_refund',?,?)")->execute([$invoiceId,$actor,-$amount,'refund:'.$transaction]);
  if($requestId!==null)$pdo->prepare("UPDATE market_refund_requests SET status='recorded',decision_reason=? WHERE id=?")->execute([trim($reason),$requestId]);
  audit_log($pdo,$actor,'finance.refund_recorded',$invoiceId,['refund_id'=>$id,'document_id'=>$document,'verified_manually'=>true,'remaining_entitlements_suspended'=>true]);
  if(function_exists('notify_user'))notify_user($pdo,(int)$invoice['user_id'],'refund:'.$id,i18n_user_text($pdo,(int)$invoice['user_id'],'notification.refund_recorded.title'),i18n_user_text($pdo,(int)$invoice['user_id'],'notification.refund_recorded.body',['reference'=>$invoice['invoice_number']]),'/user/invoices.php',true);
  $pdo->commit();return$id;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof PDOException&&$e->getCode()==='23000')throw new PaymentConflict('Diese Erstattungskennung wurde bereits zugeordnet.');throw$e;}
}
function finance_cancel(PDO $pdo,int $invoiceId,int $userId):void {
 $pdo->beginTransaction();try{
  $q=$pdo->prepare("SELECT * FROM market_invoices WHERE id=? AND user_id=? FOR UPDATE");$q->execute([$invoiceId,$userId]);$invoice=$q->fetch();if(!$invoice||!in_array($invoice['status'],['issued','pending','manual_rejected'],true))throw new PaymentConflict('Nur unbezahlte Aufträge können hier storniert werden.');
  if(function_exists('business_cancel_invoice'))business_cancel_invoice($pdo,$invoiceId);
  $q=$pdo->prepare('SELECT id FROM market_invoice_documents WHERE origin_invoice_id=?');$q->execute([$invoiceId]);$original=$q->fetchColumn();
  if($original)finance_write_document($pdo,$invoice,finance_snapshot($pdo,$invoiceId),'cancellation',(int)$invoice['gross_cents'],$userId,(int)$original);
  $pdo->prepare("UPDATE market_invoices SET status='void' WHERE id=?")->execute([$invoiceId]);audit_log($pdo,$userId,'finance.order_cancelled',$invoiceId,[]);$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
function finance_document(PDO $pdo,int $documentId,int $userId,bool $staff=false):array {
 $q=$pdo->prepare('SELECT d.*,i.user_id FROM market_invoice_documents d JOIN market_invoices i ON i.id=d.invoice_id WHERE d.id=?');$q->execute([$documentId]);$row=$q->fetch();
 if(!$row||((int)$row['user_id']!==$userId&&!($staff&&rbac_has_permission($pdo,$userId,'finance.manage'))))throw new PaymentConflict('Rechnungsdokument nicht gefunden.');
 $json=crypto_decrypt('finance',$row['payload_ciphertext']);if(!hash_equals($row['content_hash'],hash('sha256',$json)))throw new RuntimeException('Invoice integrity failed.');$row['payload']=json_decode($json,true,32,JSON_THROW_ON_ERROR);return$row;
}
function finance_pdf(array $document):string {
 $autoload=dirname(__DIR__).'/vendor/autoload.php';if(!is_file($autoload))throw new PaymentUnavailable('Die lokale PDF-Bibliothek ist noch nicht installiert.');require_once$autoload;
 $p=$document['payload'];$type=$document['document_type'];$lang=$p['locale']??'de';
 $titles=['de'=>['invoice'=>'Rechnung','credit_note'=>'Gutschrift','cancellation'=>'Stornorechnung'],'en'=>['invoice'=>'Invoice','credit_note'=>'Credit note','cancellation'=>'Cancellation invoice'],'fr'=>['invoice'=>'Facture','credit_note'=>'Avoir','cancellation'=>'Facture annulée'],'nl'=>['invoice'=>'Factuur','credit_note'=>'Creditnota','cancellation'=>'Annulering'],'pl'=>['invoice'=>'Faktura','credit_note'=>'Nota kredytowa','cancellation'=>'Anulowanie'],'cs'=>['invoice'=>'Faktura','credit_note'=>'Dobropis','cancellation'=>'Storno'],'da'=>['invoice'=>'Faktura','credit_note'=>'Kreditnota','cancellation'=>'Annullering'],'it'=>['invoice'=>'Fattura','credit_note'=>'Nota di credito','cancellation'=>'Storno']];
 $title=$titles[$lang][$type]??$titles['de'][$type];$operator=$p['operator'];$buyer=$p['buyer'];$sign=$type==='invoice'?'':'− ';
 $html='<!doctype html><html><meta charset="utf-8"><style>body{font:11px DejaVu Sans,sans-serif;color:#13282a;margin:28px}h1{font-size:26px}table{border-collapse:collapse;width:100%;margin:30px 0}td,th{border-bottom:1px solid #ccd7d5;padding:12px;text-align:left}.test{color:#a00}.small{font-size:9px}</style><body>';
 if($p['test_mode']??false)$html.='<p class="test">TESTDOKUMENT · DEVELOPMENT</p>';
 $html.='<h1>'.h($title).' '.h($document['document_number']).'</h1><p>'.h($p['issued_at']).' UTC · EUR</p><p><strong>'.h($operator['legal_name']).'</strong><br>'.h($operator['address_line1']).'<br>'.h($operator['address_line2']).'<br>'.h($operator['postcode'].' '.$operator['city'].' · '.$operator['country']).'<br>'.h($operator['email']).'</p><p><strong>'.h($buyer['legal_name']).'</strong><br>'.h($buyer['address_line1']).'<br>'.h($buyer['address_line2']).'<br>'.h($buyer['postal_code'].' '.$buyer['city'].' · '.$buyer['country_code']).'</p>';
 $html.='<table><thead><tr><th>Leistung / Service</th><th>Anzahl</th><th>EUR</th></tr></thead><tbody><tr><td>'.h($p['label']).'<br><span class="small">'.h($p['fee_version']).'</span></td><td>1</td><td>'.h($sign.money_eur((int)$p['gross_cents'])).'</td></tr></tbody></table><p>Netto: '.h($sign.money_eur((int)$p['net_cents'])).'<br>Umsatzsteuer / VAT ('.h(intdiv((int)$p['tax_bps'],100).','.str_pad((string)((int)$p['tax_bps']%100),2,'0',STR_PAD_LEFT)).' %): '.h($sign.money_eur((int)$p['tax_cents'])).'<br><strong>Gesamt / Total: '.h($sign.money_eur((int)$p['gross_cents'])).'</strong></p>';
 $html.='<p>'.h($p['tax_explanation']).'</p><p class="small">Auftrag / Reference: '.h($p['order_reference']).'<br>Bestelldatum: '.h(substr($p['ordered_at'],0,10)).'<br>'.h($operator['vat_id']!==''?'USt-ID: '.$operator['vat_id']:'Steuernummer: '.$operator['tax_number']).'<br>'.h($operator['registration']).'</p>';
 if(isset($p['related_document_id']))$html.='<p>Bezug zum ursprünglichen Dokument / Related document: #'.(int)$p['related_document_id'].'</p>';
 if(isset($p['correction_reason']))$html.='<p>'.h($p['correction_reason']).'</p>';
 $html.='<p class="small">Vorauszahlung für Guthaben oder reservierte Plattformleistung. Der tatsächliche Leistungsbeginn wird gesondert in der Aktivierungshistorie dokumentiert.</p></body></html>';
 $private=rtrim(getenv('GENERAL_PRIVATE_DIR')?:'/var/lib/gunindex/private','/').'/pdf-fonts';if(!is_dir($private)&&!mkdir($private,0700,true))throw new RuntimeException('Private font cache unavailable.');
 $pdf=new \Dompdf\Dompdf(['isRemoteEnabled'=>false,'isPhpEnabled'=>false,'isJavascriptEnabled'=>false,'defaultFont'=>'DejaVu Sans','fontCache'=>$private,'tempDir'=>$private,'chroot'=>dirname(__DIR__).'/vendor/dompdf']);$pdf->loadHtml($html,'UTF-8');$pdf->setPaper('A4');$pdf->render();return$pdf->output();
}
