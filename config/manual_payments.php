<?php
declare(strict_types=1);
function manual_payment_reason(PDO $pdo):?string {
 if(site_setting($pdo,'manual_paypal_enabled','0')!=='1')return 'Manuelle PayPal-Zahlungen sind noch nicht aktiviert.';
 if(site_setting($pdo,'manual_paypal_approved','0')!=='1')return 'Die Eignung des Empfängerkontos für diese geschäftlichen Zahlungen muss geprüft werden.';
 if(site_setting($pdo,'manual_tax_reviewed','0')!=='1')return 'Steuerbehandlung und Bestellbedingungen sind noch nicht freigegeben.';
 if(!filter_var(site_setting($pdo,'manual_paypal_recipient',''),FILTER_VALIDATE_EMAIL))return 'Ein geprüftes Empfängerkonto fehlt.';
 $bps=(int)site_setting($pdo,'manual_tax_bps','0');if($bps<0||$bps>10000)return 'Die Steuerkonfiguration ist ungültig.';
 return null;
}
function manual_terms(int $quantity):string {
 return 'Bump-Guthaben: '.$quantity.' Aktivierung(en). Ein Bump pro Inserat alle 72 Stunden; verändert nur die Sortieraktualität. Keine Reichweiten- oder Verkaufsgarantie. Zahlung wird vom Betreiber anhand des tatsächlichen PayPal-Eingangs geprüft. Guthaben entsteht erst nach Prüfung und wird vom Nutzer gesondert aktiviert. Gesetzliche Verbraucherrechte bleiben bestehen. Kein vorzeitiger Leistungsbeginn wird mit der Bestellung erklärt. Version manual-bump-1.0.';
}
function manual_order(PDO $pdo,int $userId,int $listingId,string $service,string $requestKey,?int $campaignId=null,array $consent=[]):int {
 if(manual_payment_reason($pdo)!==null)throw new PaymentUnavailable(manual_payment_reason($pdo));
 if(!in_array($service,['bump_single','bump_pack','category_top','home_top','banner_monthly'],true)||!preg_match('/\A[a-f0-9]{64}\z/',$requestKey))throw new InvalidArgumentException('Ungültige Bestellung.');
 $listing=market_find($pdo,$listingId,false);if(!$listing||(int)$listing['seller_id']!==$userId||($listing['type']==='auction'&&market_expired($listing)))throw new PaymentConflict('Nur eigene aktive Angebote können beworben werden.');
 $promotion=in_array($service,['category_top','home_top','banner_monthly'],true);
 if($promotion&&(!function_exists('business_reserve_order')||($businessReason=business_reason($pdo))!==null))throw new PaymentUnavailable($businessReason??'Die Platzierungsverwaltung ist noch nicht verfügbar.');
 $quote=fees_quote($service,null,$pdo);if($quote['gross_cents']<1)throw new PaymentConflict('Für kostenlose Guthaben ist dieser Zahlungsweg nicht vorgesehen.');
 $quantity=$service==='bump_pack'?3:1;$terms=$promotion?business_terms($pdo,$service,$listing):manual_terms($quantity);
 $tax=fees_tax_split($quote['gross_cents'],(int)site_setting($pdo,'manual_tax_bps','0'),true);
 $pdo->beginTransaction();
 try{
  maintenance_assert_market_available($pdo);
  $q=$pdo->prepare('SELECT id FROM users WHERE id=? AND is_active=1 FOR UPDATE');$q->execute([$userId]);if(!$q->fetchColumn())throw new PaymentConflict('Konto nicht verfügbar.');
  $q=$pdo->prepare('SELECT invoice_id FROM market_manual_orders WHERE request_key=? AND user_id=?');$q->execute([$requestKey,$userId]);$previous=$q->fetchColumn();if($previous){$pdo->commit();return(int)$previous;}
  $contractDocuments=consent_check($pdo,'checkout',$consent,i18n_locale());
  $q=$pdo->prepare('SELECT COUNT(*) FROM market_manual_orders WHERE user_id=? AND created_at>UTC_TIMESTAMP()-INTERVAL 1 HOUR');$q->execute([$userId]);if((int)$q->fetchColumn()>=10)throw new PaymentConflict('Bitte später erneut bestellen. Das Stundenlimit ist erreicht.');
  $reference='GI-'.strtoupper(bin2hex(random_bytes(10)));
  $q=$pdo->prepare("INSERT INTO market_invoices(user_id,invoice_number,service_code,fee_version,gross_cents,status) VALUES (?,?,?,?,?,'issued')");$q->execute([$userId,$reference,$service,$quote['version'],$quote['gross_cents']]);$id=(int)$pdo->lastInsertId();
  $q=$pdo->prepare('INSERT INTO market_manual_orders(invoice_id,user_id,listing_id,reference,recipient,quantity,net_cents,tax_cents,tax_bps,terms_hash,terms_text,request_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
  $q->execute([$id,$userId,$listingId,$reference,site_setting($pdo,'manual_paypal_recipient'),$quantity,$tax['net_cents'],$tax['tax_cents'],(int)site_setting($pdo,'manual_tax_bps','0'),hash('sha256',$terms),$terms,$requestKey]);
  if($promotion)business_reserve_order($pdo,$id,$userId,$listing,$service,$campaignId);
  if(function_exists('finance_prepare_order'))finance_prepare_order($pdo,$id,$userId);
  consent_record($pdo,$userId,'checkout',$id,$contractDocuments);
  audit_log($pdo,$userId,'payment.manual_ordered',$id,['fee_version'=>$quote['version'],'terms_hash'=>hash('sha256',$terms)]);
  $pdo->commit();return$id;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
function manual_review(PDO $pdo,int $actorId,int $invoiceId,string $transaction,string $reference,int $received,string $decision,string $reason):void {
 if(function_exists('governance_assert_actor'))governance_assert_actor($pdo,$actorId,'finance.manage');else settings_assert_admin($pdo,$actorId);
 if(!in_array($decision,['approve','reject'],true)||strlen($reason)<10||strlen($reason)>1000)throw new InvalidArgumentException('Entscheidung und nachvollziehbare Begründung fehlen.');
 $transaction=strtoupper(trim($transaction));
 if($decision==='approve'&&!preg_match('/\A[A-Z0-9]{10,64}\z/',$transaction))throw new InvalidArgumentException('Eine tatsächliche PayPal-Transaktionskennung ist erforderlich.');
 $pdo->beginTransaction();
 try{
  if(function_exists('governance_assert_actor'))governance_assert_actor($pdo,$actorId,'finance.manage');else settings_assert_admin($pdo,$actorId);
  $q=$pdo->prepare('SELECT i.*,o.reference,o.quantity,o.recipient FROM market_invoices i JOIN market_manual_orders o ON o.invoice_id=i.id WHERE i.id=? FOR UPDATE');$q->execute([$invoiceId]);$invoice=$q->fetch();if(!$invoice)throw new PaymentConflict('Zahlungsauftrag nicht gefunden.');
  if($invoice['status']==='paid'){
   $q=$pdo->prepare("SELECT capture_id FROM market_payment_attempts WHERE invoice_id=? AND provider='paypal_manual' AND status='manual_verified'");$q->execute([$invoiceId]);
   if($decision==='approve'&&$transaction===$q->fetchColumn()){$pdo->commit();return;}
   throw new PaymentConflict('Dieser Auftrag wurde bereits abgeschlossen.');
  }
  if(!in_array($invoice['status'],['issued','pending','manual_rejected'],true))throw new PaymentConflict('Dieser Auftrag kann nicht freigegeben werden.');
  if($decision==='approve'){
   if(!hash_equals($invoice['reference'],$reference)||$received!==(int)$invoice['gross_cents']||$invoice['currency']!=='EUR')throw new PaymentConflict('Betrag, Währung oder Zahlungsreferenz stimmen nicht überein.');
   $q=$pdo->prepare("INSERT INTO market_payment_attempts(invoice_id,provider,status,capture_id,idempotency_key,mode) VALUES (?,'paypal_manual','manual_verified',?,?,'live')");$q->execute([$invoiceId,$transaction,bin2hex(random_bytes(16))]);$attempt=(int)$pdo->lastInsertId();
   $pdo->prepare("INSERT INTO market_payment_events(attempt_id,provider_event_id,kind,payload_hash) VALUES (?,?,'manual_verified',?)")->execute([$attempt,'manual:'.$transaction,hash('sha256',$reference.':'.$received.':'.$invoice['recipient'])]);
   $pdo->prepare("UPDATE market_invoices SET status='paid' WHERE id=?")->execute([$invoiceId]);
   if(in_array($invoice['service_code'],['bump_single','bump_pack'],true))$pdo->prepare('INSERT INTO market_bump_credits(invoice_id,user_id,quantity,remaining) VALUES (?,?,?,?)')->execute([$invoiceId,$invoice['user_id'],$invoice['quantity'],$invoice['quantity']]);
   elseif(auction_billing_service($invoice['service_code']))auction_billing_verify_payment($pdo,$invoice);
   elseif(function_exists('business_fulfill_manual'))business_fulfill_manual($pdo,$invoiceId);
   else throw new PaymentConflict('Die gebuchte Leistung ist nicht zur Freigabe verfügbar.');
   if(function_exists('finance_issue_invoice'))finance_issue_invoice($pdo,$invoiceId,$actorId);
  }else{$pdo->prepare("UPDATE market_invoices SET status='manual_rejected' WHERE id=?")->execute([$invoiceId]);}
  if($decision==='approve'&&function_exists('notify_user'))notify_user($pdo,(int)$invoice['user_id'],'payment:'.$invoiceId,i18n_user_text($pdo,(int)$invoice['user_id'],'notification.payment_confirmed.title'),i18n_user_text($pdo,(int)$invoice['user_id'],'notification.payment_confirmed.body',['reference'=>$invoice['invoice_number']]),'/user/billing.php?order='.$invoiceId,true);
  audit_log($pdo,$actorId,'payment.manual_'.$decision,$invoiceId,['reason'=>$reason,'provider'=>'paypal_manual','verified_manually'=>true]);$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof PDOException&&$e->getCode()==='23000')throw new PaymentConflict('Die Transaktionskennung wurde bereits zugeordnet.');throw$e;}
}
function manual_bump(PDO $pdo,int $userId,int $listingId,bool $immediateStart=false):void {
 if(!$immediateStart)throw new PaymentConflict('Bitte den sofortigen Beginn dieses einzelnen Bumps ausdrücklich verlangen.');
 $pdo->beginTransaction();
 try{
  maintenance_assert_market_available($pdo);
  $listing=market_lock_listing($pdo,$listingId);
  if((int)$listing['seller_id']!==$userId||$listing['status']!=='active'||!catalog_category_allowed($pdo,(int)$listing['category_id'])||($listing['type']==='auction'&&market_expired($listing)))throw new PaymentConflict('Dieses Angebot kann nicht hervorgehoben werden.');
  $q=$pdo->prepare('SELECT is_active FROM users WHERE id=? FOR SHARE');$q->execute([$userId]);if((int)$q->fetchColumn()!==1)throw new PaymentConflict('Konto nicht aktiv.');
  if($listing['bumped_at']!==null&&strtotime($listing['bumped_at'].' UTC')>time()-259200)throw new PaymentConflict('Der nächste Bump ist erst nach 72 Stunden möglich.');
  $q=$pdo->prepare('SELECT c.id FROM market_bump_credits c JOIN market_invoices i ON i.id=c.invoice_id WHERE c.user_id=? AND c.remaining>0 AND i.status=\'paid\' ORDER BY c.id LIMIT 1 FOR UPDATE');$q->execute([$userId]);$credit=$q->fetchColumn();if(!$credit)throw new PaymentConflict('Kein bezahltes Bump-Guthaben vorhanden.');
  $pdo->prepare('UPDATE market_bump_credits SET remaining=remaining-1 WHERE id=? AND remaining>0')->execute([$credit]);
  $pdo->prepare('UPDATE market_listings SET bumped_at=UTC_TIMESTAMP() WHERE id=?')->execute([$listingId]);
  $pdo->prepare('INSERT INTO market_bump_events(credit_id,listing_id,user_id) VALUES (?,?,?)')->execute([$credit,$listingId,$userId]);
  audit_log($pdo,$userId,'listing.bumped',$listingId,['credit_id'=>(int)$credit,'immediate_start_requested'=>true,'consent_version'=>'bump-start-1.0']);$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
