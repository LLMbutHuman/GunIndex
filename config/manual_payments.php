<?php
declare(strict_types=1);
function manual_payment_reason(PDO $pdo):?string {
 if(site_setting($pdo,'manual_paypal_enabled','0')!=='1')return t('billing_customer.manual_off');
 if(site_setting($pdo,'manual_paypal_approved','0')!=='1')return t('billing_customer.manual_recipient_approval');
 if(site_setting($pdo,'manual_tax_reviewed','0')!=='1')return t('billing_customer.manual_tax_review');
 if(!filter_var(site_setting($pdo,'manual_paypal_recipient',''),FILTER_VALIDATE_EMAIL))return t('billing_customer.manual_recipient_missing');
 $bps=(int)site_setting($pdo,'manual_tax_bps','0');if($bps<0||$bps>10000)return t('billing_customer.manual_tax_invalid');
 return null;
}
function direct_payment_reason(PDO $pdo):?string {
 $paypal=manual_payment_reason($pdo);
 return $paypal!==null&&bank_gate($pdo)!==null&&bitcoin_gate($pdo,null,true)!==null?$paypal:null;
}
function manual_terms(int $quantity,bool $additionalMethod=false):string {
 $key=$quantity===1?'billing_customer.bump_terms_single':'billing_customer.bump_terms_pack';
 return t($key).' '.t('billing_customer.bump_terms').' '.t($additionalMethod?'billing_customer.verify_direct':'billing_customer.verify_paypal')
  .' '.t('billing_customer.bump_activation').' '.t('billing_customer.terms_version',['version'=>$additionalMethod?'manual-bump-1.3':'manual-bump-1.2']);
}

function manual_checkout_quote(PDO $pdo,string $service):array {
 $quote=fees_quote($service,null,$pdo);
 $terms=match($service){
  'bump_single','bump_pack'=>manual_terms($service==='bump_pack'?3:1,bank_gate($pdo)===null||bitcoin_gate($pdo,null,true)===null),
  'category_top','home_top','banner_monthly'=>business_terms($pdo,$service),
  default=>throw new InvalidArgumentException(t('billing_customer.invalid_order')),
 };
 $quote['terms']=$terms;
 $quote['language']=i18n_locale();
 $quote['confirmation']=t('billing_customer.confirm');
 $quote['receipt']=$terms."\n\n".$quote['confirmation'];
 $quote['approval_hash']=hash('sha256',json_encode([
  $service,$quote['currency'],$quote['gross_cents'],$quote['version'],$quote['language'],$quote['receipt'],
 ],JSON_THROW_ON_ERROR));
 return $quote;
}

function manual_checkout_fields(PDO $pdo,array $services):void {
 echo '<fieldset class="span-2"><legend>'.h(t('payment_customer.service')).'</legend>';
 foreach($services as $index=>$service){
  $quote=manual_checkout_quote($pdo,$service);
  echo '<label><input type="radio" name="service" value="'.h($service).'" required'.($index===0?' checked':'').'> '
   .h(payments_service_label($service).' · '.money_eur($quote['gross_cents'],$quote['language'])).'</label>'
   .'<input type="hidden" name="service_terms['.h($service).']" value="'.h($quote['approval_hash']).'">'
   .'<details><summary>'.h(t('billing_customer.service_terms',['service'=>payments_service_label($service)])).'</summary>'
   .'<p>'.h($quote['terms']).'</p></details>';
 }
 echo '</fieldset><label><input type="checkbox" name="accept_terms" value="1" required> '.h(t('billing_customer.confirm')).'</label>';
}
function manual_order(PDO $pdo,int $userId,int $listingId,string $service,string $requestKey,?int $campaignId=null,array $consent=[]):int {
 if(($reason=direct_payment_reason($pdo))!==null)throw new PaymentUnavailable($reason);
 if(!in_array($service,['bump_single','bump_pack','category_top','home_top','banner_monthly'],true)||!preg_match('/\A[a-f0-9]{64}\z/',$requestKey))throw new InvalidArgumentException(t('billing_customer.invalid_order'));
 $listing=market_find($pdo,$listingId,false);if(!$listing||(int)$listing['seller_id']!==$userId||($listing['type']==='auction'&&market_expired($listing)))throw new PaymentConflict(t('billing_customer.own_active_listing'));
 $promotion=in_array($service,['category_top','home_top','banner_monthly'],true);
 if($promotion&&(!function_exists('business_reserve_order')||($businessReason=business_reason($pdo))!==null))throw new PaymentUnavailable($businessReason??t('billing_customer.placement_unavailable'));
 $pdo->beginTransaction();
 try{
  maintenance_assert_market_available($pdo);
  $q=$pdo->prepare('SELECT id FROM users WHERE id=? AND is_active=1 FOR UPDATE');$q->execute([$userId]);if(!$q->fetchColumn())throw new PaymentConflict(t('billing_customer.account_missing'));
  $q=$pdo->prepare('SELECT invoice_id FROM market_manual_orders WHERE request_key=? AND user_id=?');$q->execute([$requestKey,$userId]);$previous=$q->fetchColumn();if($previous){$pdo->commit();return(int)$previous;}
  $contractDocuments=consent_check($pdo,'checkout',$consent,i18n_locale());
  if(($consent['accept_terms']??null)!=='1')throw new InvalidArgumentException(t('billing_customer.terms_required'));
  $quote=manual_checkout_quote($pdo,$service);
  $hashes=$consent['service_terms']??null;
  $hash=is_array($hashes)?($hashes[$service]??null):null;
  if(!is_string($hash)||!hash_equals($quote['approval_hash'],$hash))throw new InvalidArgumentException(t('consent.changed'));
  if($quote['gross_cents']<1)throw new PaymentConflict(t('billing_customer.free_credits'));
  $quantity=$service==='bump_pack'?3:1;
  $taxBps=(int)site_setting($pdo,'manual_tax_bps','0');$tax=fees_tax_split($quote['gross_cents'],$taxBps,true);
  $termsHash=hash('sha256',$quote['receipt']);
  $q=$pdo->prepare('SELECT COUNT(*) FROM market_manual_orders WHERE user_id=? AND created_at>UTC_TIMESTAMP()-INTERVAL 1 HOUR');$q->execute([$userId]);if((int)$q->fetchColumn()>=10)throw new PaymentConflict(t('billing_customer.hourly_limit'));
  $reference='GI-'.strtoupper(bin2hex(random_bytes(10)));
  $q=$pdo->prepare("INSERT INTO market_invoices(user_id,invoice_number,service_code,fee_version,gross_cents,status) VALUES (?,?,?,?,?,'issued')");$q->execute([$userId,$reference,$service,$quote['version'],$quote['gross_cents']]);$id=(int)$pdo->lastInsertId();
  $q=$pdo->prepare('INSERT INTO market_manual_orders(invoice_id,user_id,listing_id,reference,recipient,quantity,net_cents,tax_cents,tax_bps,terms_hash,terms_text,terms_language,request_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
  $q->execute([$id,$userId,$listingId,$reference,manual_payment_reason($pdo)===null?site_setting($pdo,'manual_paypal_recipient'):'',$quantity,$tax['net_cents'],$tax['tax_cents'],$taxBps,$termsHash,$quote['receipt'],$quote['language'],$requestKey]);
  if($promotion)business_reserve_order($pdo,$id,$userId,$listing,$service,$campaignId);
  if(function_exists('finance_prepare_order'))finance_prepare_order($pdo,$id,$userId);
  consent_record($pdo,$userId,'checkout',$id,$contractDocuments);
  audit_log($pdo,$userId,'payment.manual_ordered',$id,['fee_version'=>$quote['version'],'terms_hash'=>$termsHash,'terms_language'=>$quote['language'],'approval_hash'=>$quote['approval_hash']]);
  $pdo->commit();return$id;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
function manual_claim(PDO $pdo,int $userId,int $invoiceId): void {
 $pdo->beginTransaction();
 try{
  $q=$pdo->prepare('SELECT i.status,i.service_code,o.customer_claimed_at FROM market_invoices i JOIN market_manual_orders o ON o.invoice_id=i.id AND o.user_id=i.user_id WHERE i.id=? AND i.user_id=? FOR UPDATE');
  $q->execute([$invoiceId,$userId]);$order=$q->fetch();
  if(!$order||!in_array($order['status'],['issued','pending','manual_rejected'],true))
   throw new PaymentConflict(t('auction_correction.claim_unavailable'));
  if(bank_order($pdo,$invoiceId))throw new PaymentConflict(t('bank.conflict'));
  if(bitcoin_order($pdo,$invoiceId))throw new PaymentConflict(t('bitcoin.conflict'));
  if(auction_billing_service($order['service_code'])){
   $charge=auction_fee_lock_charge($pdo,$invoiceId);
   if(!$charge||$charge['waived_at']!==null||auction_fee_replacement($pdo,$invoiceId,true))
    throw new PaymentConflict(t('auction_correction.claim_unavailable'));
   auction_fee_assert_late_receipts_resolved($pdo,(int)$charge['id']);
  }
  if($order['status']!=='pending'||$order['customer_claimed_at']===null){
   $pdo->prepare('UPDATE market_manual_orders SET customer_claimed_at=UTC_TIMESTAMP() WHERE invoice_id=?')->execute([$invoiceId]);
   $pdo->prepare("UPDATE market_invoices SET status='pending' WHERE id=?")->execute([$invoiceId]);
  }
  $pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
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
  if(bank_order($pdo,$invoiceId))throw new PaymentConflict(t('bank.conflict'));
  if(bitcoin_order($pdo,$invoiceId))throw new PaymentConflict(t('bitcoin.conflict'));
  if($invoice['status']==='paid'){
   $q=$pdo->prepare("SELECT capture_id FROM market_payment_attempts WHERE invoice_id=? AND provider='paypal_manual' AND status='manual_verified'");$q->execute([$invoiceId]);
   if($decision==='approve'&&$transaction===$q->fetchColumn()){$pdo->commit();return;}
   throw new PaymentConflict('Dieser Auftrag wurde bereits abgeschlossen.');
  }
  if(!in_array($invoice['status'],['issued','pending','manual_rejected'],true))throw new PaymentConflict('Dieser Auftrag kann nicht freigegeben werden.');
  if($decision==='approve'){
   if(!hash_equals($invoice['reference'],$reference)||$received!==(int)$invoice['gross_cents']||$invoice['currency']!=='EUR')throw new PaymentConflict('Betrag, Währung oder Zahlungsreferenz stimmen nicht überein.');
   $auctionFee=auction_billing_service($invoice['service_code']);
   if($auctionFee)auction_billing_verify_payment($pdo,$invoice);
   $q=$pdo->prepare("INSERT INTO market_payment_attempts(invoice_id,provider,status,capture_id,idempotency_key,mode) VALUES (?,'paypal_manual','manual_verified',?,?,'live')");$q->execute([$invoiceId,$transaction,bin2hex(random_bytes(16))]);$attempt=(int)$pdo->lastInsertId();
   $pdo->prepare("INSERT INTO market_payment_events(attempt_id,provider_event_id,kind,payload_hash) VALUES (?,?,'manual_verified',?)")->execute([$attempt,'manual:'.$transaction,hash('sha256',$reference.':'.$received.':'.$invoice['recipient'])]);
   $pdo->prepare("UPDATE market_invoices SET status='paid' WHERE id=?")->execute([$invoiceId]);
   manual_fulfill($pdo,$invoice);
   if(function_exists('finance_issue_invoice'))finance_issue_invoice($pdo,$invoiceId,$actorId);
  }else{$pdo->prepare("UPDATE market_invoices SET status='manual_rejected' WHERE id=?")->execute([$invoiceId]);}
  if($decision==='approve'&&function_exists('notify_user'))notify_user($pdo,(int)$invoice['user_id'],'payment:'.$invoiceId,i18n_user_text($pdo,(int)$invoice['user_id'],'notification.payment_confirmed.title'),i18n_user_text($pdo,(int)$invoice['user_id'],'notification.payment_confirmed.body',['reference'=>$invoice['invoice_number']]),'/user/billing.php?order='.$invoiceId,true);
  audit_log($pdo,$actorId,'payment.manual_'.$decision,$invoiceId,['reason'=>$reason,'provider'=>'paypal_manual','verified_manually'=>true]);$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof PDOException&&$e->getCode()==='23000')throw new PaymentConflict('Die Transaktionskennung wurde bereits zugeordnet.');throw$e;}
}
/** The payment and fulfilment use the same invoice transaction. */
function manual_fulfill(PDO $pdo,array $invoice): void {
 if(auction_billing_service($invoice['service_code']))return;
 if(in_array($invoice['service_code'],['bump_single','bump_pack'],true)){
  $pdo->prepare('INSERT INTO market_bump_credits(invoice_id,user_id,quantity,remaining) VALUES (?,?,?,?)')->execute([$invoice['id'],$invoice['user_id'],$invoice['quantity'],$invoice['quantity']]);
  return;
 }
 if(!function_exists('business_fulfill_manual'))throw new PaymentConflict('Die gebuchte Leistung ist nicht zur Freigabe verfügbar.');
 business_fulfill_manual($pdo,(int)$invoice['id']);
}
function manual_bump(PDO $pdo,int $userId,int $listingId,bool $immediateStart=false,array $consent=[]):void {
 if(!$immediateStart)throw new PaymentConflict(t('billing_customer.start_required'));
 $pdo->beginTransaction();
 try{
  maintenance_assert_market_available($pdo);
  $listing=market_lock_listing($pdo,$listingId);
  if((int)$listing['seller_id']!==$userId||$listing['status']!=='active'||!catalog_category_allowed($pdo,(int)$listing['category_id'])||($listing['type']==='auction'&&market_expired($listing)))throw new PaymentConflict(t('billing_customer.boost_unavailable'));
  $q=$pdo->prepare('SELECT is_active FROM users WHERE id=? FOR SHARE');$q->execute([$userId]);if((int)$q->fetchColumn()!==1)throw new PaymentConflict(t('billing_customer.account_inactive'));
  if($listing['bumped_at']!==null&&strtotime($listing['bumped_at'].' UTC')>time()-259200)throw new PaymentConflict(t('billing_customer.boost_cooldown'));
  $q=$pdo->prepare('SELECT c.id FROM market_bump_credits c JOIN market_invoices i ON i.id=c.invoice_id WHERE c.user_id=? AND c.remaining>0 AND i.status=\'paid\' ORDER BY c.id LIMIT 1 FOR UPDATE');$q->execute([$userId]);$credit=$q->fetchColumn();if(!$credit)throw new PaymentConflict(t('billing_customer.credits_missing'));
  $declaration=consent_start_check('bump',$consent);
  $pdo->prepare('UPDATE market_bump_credits SET remaining=remaining-1 WHERE id=? AND remaining>0')->execute([$credit]);
  $pdo->prepare('UPDATE market_listings SET bumped_at=UTC_TIMESTAMP() WHERE id=?')->execute([$listingId]);
  $pdo->prepare('INSERT INTO market_bump_events(credit_id,listing_id,user_id) VALUES (?,?,?)')->execute([$credit,$listingId,$userId]);
  audit_log($pdo,$userId,'listing.bumped',$listingId,['credit_id'=>(int)$credit,'immediate_start_requested'=>true]+$declaration);$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
