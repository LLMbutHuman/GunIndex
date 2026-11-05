<?php
declare(strict_types=1);

/** One listing is one auction run. Relisting must create a new listing, never reuse bids. */
function auction_billing_service(string $service): bool {
    return in_array($service,['auction_listing','auction_success'],true);
}
function auction_billing_enabled(PDO $pdo): bool {
    $q=$pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='auction_fees_enabled'".($pdo->inTransaction()?' FOR SHARE':''));
    $value=$q->fetchColumn();
    if($value!==false&&!in_array($value,['0','1'],true))throw new PaymentUnavailable(t('auction_billing.unavailable'));
    return $value==='1';
}
function auction_billing_json(array $payload): string {
    return json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
function auction_billing_contract(PDO $pdo,int $listingId,bool $current=false): ?array {
    if($current&&!$pdo->inTransaction())throw new LogicException('Current contract read requires a transaction.');
    $q=$pdo->prepare('SELECT * FROM market_auction_fee_contracts WHERE listing_id=?'.($current?' FOR SHARE':''));$q->execute([$listingId]);$row=$q->fetch();if(!$row)return null;
    $json=crypto_decrypt('finance',$row['payload_ciphertext']);
    if(!hash_equals($row['payload_hash'],hash('sha256',$json)))throw new RuntimeException('Auction contract integrity failed.');
    $payload=json_decode($json,true,32,JSON_THROW_ON_ERROR);
    if(($payload['buyer']['user_id']??null)!==(int)$row['seller_id'])throw new RuntimeException('Auction contract owner mismatch.');
    return $payload;
}
/** Quote hash binds prices, recipient, invoice data, language and exact legal versions. */
function auction_billing_quote(PDO $pdo,int $sellerId,bool $lock=false): ?array {
    if($lock&&!$pdo->inTransaction())throw new LogicException('Auction quote locking requires a transaction.');
    if(!auction_billing_enabled($pdo))return null;
    // Hold all settings stable while the caller commits publication and contract.
    if($lock)$pdo->query('SELECT setting_key FROM system_settings ORDER BY setting_key FOR SHARE')->fetchAll();
    site_settings($pdo,true);
    if(site_setting($pdo,'auction_fees_approved','0')!=='1'||!finance_enabled($pdo)||manual_payment_reason($pdo)!==null)
        throw new PaymentUnavailable(t('auction_billing.unavailable'));
    $q=$pdo->prepare('SELECT * FROM market_billing_profiles WHERE user_id=?'.($lock?' FOR SHARE':''));$q->execute([$sellerId]);$buyer=$q->fetch();
    if(!$buyer)throw new PaymentUnavailable(t('auction_billing.billing_required'));
    $buyer['user_id']=(int)$buyer['user_id'];
    $operator=finance_operator($pdo);
    $q=$pdo->prepare('SELECT * FROM market_tax_rules WHERE country_code=?'.($lock?' FOR SHARE':''));$q->execute([$buyer['country_code']]);$rule=$q->fetch();
    if(!$rule)throw new PaymentUnavailable(t('auction_billing.unavailable'));
    $documents=consent_documents($pdo,'checkout',i18n_locale(),$lock);
    if(!$documents)throw new PaymentUnavailable(t('auction_billing.legal_required'));
    $q=$pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='fee_auction_listing_cents'");$amount=$q->fetchColumn();
    if($amount===false)$amount='50';
    if(!preg_match('/\A[0-9]{1,7}\z/',$amount))throw new PaymentUnavailable(t('auction_billing.unavailable'));
    $tariff=['listing_cents'=>(int)$amount,'rates'=>[300,200,100,50],'cap_cents'=>2990];
    fees_tax_split($tariff['listing_cents'],(int)$rule['basis_points'],true);
    $payload=['version'=>'auction-billing-1','fee_version'=>'ab1-'.substr(hash('sha256',auction_billing_json($tariff)),0,20),
        'tariff'=>$tariff,'buyer'=>$buyer,'operator'=>$operator,'tax_bps'=>(int)$rule['basis_points'],'tax_explanation'=>$rule['explanation'],
        'recipient'=>site_setting($pdo,'manual_paypal_recipient'),'currency'=>'EUR','locale'=>i18n_locale(),
        'labels'=>['listing'=>t('auction_billing.listing_label'),'success'=>t('auction_billing.success_label')],
        'terms'=>t('auction_billing.tariff',['listing'=>money_eur($tariff['listing_cents'])]).' '.t('auction_billing.accept'),
        'documents'=>array_map(static fn(array $d):array=>['id'=>(int)$d['id'],'hash'=>$d['content_hash'],'language'=>$d['language']],$documents)];
    return ['payload'=>$payload,'documents'=>$documents,'hash'=>hash('sha256',auction_billing_json($payload))];
}
function auction_billing_validate_quote(array $quote,array $input): void {
    $hash=$input['auction_fee_quote']??null;
    if(($input['auction_fee_action']??null)!=='publish'||($input['accept_auction_fees']??null)!=='1'||!is_string($hash)||!hash_equals($quote['hash'],$hash))
        throw new InvalidArgumentException(t('auction_billing.changed'));
    consent_validate_submission($quote['documents'],$input,$quote['payload']['locale']);
}
/** Called inside market_save_listing after ownership/row locking, before mutation. */
function auction_billing_prepare(PDO $pdo,int $seller,array $data,array $input,?array $existing): ?array {
    if(!$pdo->inTransaction())throw new LogicException('Auction billing requires publication transaction.');
    // A concurrent publication may have committed after our initial lineage snapshot.
    // Drafts have no contract yet: do not gap-lock all missing contract keys.
    $currentContract=$existing&&$existing['type']==='auction'&&in_array($existing['status'],['active','paused'],true)&&(int)$existing['auction_fee_eligible']===1;
    $contract=$existing?auction_billing_contract($pdo,(int)$existing['id'],$currentContract):null;
    if($contract!==null){
        if($data['type']!=='auction'||$data['status']!=='active')throw new MarketConflictException(t('auction_billing.locked'));
        if(market_expired($existing))throw new MarketConflictException(t('auction_billing.expired'));
        return ['existing'=>true,'payload'=>$contract];
    }
    if($data['type']!=='auction'||$data['status']!=='active')return null;
    // Migration defaults and already public free auctions never acquire a retroactive tariff.
    if($existing&&((int)$existing['auction_fee_eligible']!==1||($existing['type']==='auction'&&in_array($existing['status'],['active','paused'],true))))return null;
    $quote=auction_billing_quote($pdo,$seller,true);
    if($existing)$quote=relisting_discount_quote($pdo,$seller,(int)$existing['id'],$quote,true);
    if($quote===null)return null;
    auction_billing_validate_quote($quote,$input);return $quote+['existing'=>false];
}
function auction_billing_publish(PDO $pdo,int $listingId,int $seller,?array $quote): void {
    if(!$pdo->inTransaction())throw new LogicException('Auction publication requires transaction.');
    if($quote===null){$pdo->prepare('UPDATE market_listings SET auction_fee_eligible=0 WHERE id=?')->execute([$listingId]);return;}
    if($quote['existing'])return;
    $json=auction_billing_json($quote['payload']);
    $pdo->prepare('INSERT INTO market_auction_fee_contracts(listing_id,seller_id,payload_ciphertext,payload_hash) VALUES (?,?,?,?)')->execute([$listingId,$seller,crypto_encrypt('finance',$json),hash('sha256',$json)]);
    foreach($quote['documents'] as $document){
        legal_accept($pdo,$seller,(int)$document['id'],'checkout');
        $pdo->prepare('INSERT INTO market_auction_fee_acceptances(listing_id,document_id,content_hash) VALUES (?,?,?)')->execute([$listingId,$document['id'],$document['content_hash']]);
    }
    audit_log($pdo,$seller,'auction.fee_contract_accepted',$listingId,['fee_version'=>$quote['payload']['fee_version'],'contract_hash'=>hash('sha256',$json)]);
    auction_billing_charge($pdo,$listingId,'listing',null);
}
/** Pure calculation against the accepted tariff, never current admin settings. */
function auction_billing_amount(array $contract,string $kind,?int $sale): int {
    $tariff=$contract['tariff']??[];
    if(($contract['version']??'')!=='auction-billing-1'||($tariff['rates']??null)!==[300,200,100,50]||($tariff['cap_cents']??null)!==2990
        ||!is_int($tariff['listing_cents']??null)||$tariff['listing_cents']<0||$tariff['listing_cents']>9999999)throw new RuntimeException('Unsupported auction tariff.');
    if($kind==='listing'&&$sale===null)return $tariff['listing_cents'];
    if($kind!=='success'||$sale===null||$sale<1)throw new InvalidArgumentException('Invalid auction charge basis.');
    return fees_progressive($sale,$tariff['rates'],$tariff['cap_cents']);
}
/** Local atomic receivable only: no provider request and no invented money receipt. */
function auction_billing_charge(PDO $pdo,int $listingId,string $kind,?int $sale): ?int {
    if(!$pdo->inTransaction())throw new LogicException('Auction fees require caller transaction.');
    $contract=auction_billing_contract($pdo,$listingId);if($contract===null)return null;
    $gross=auction_billing_amount($contract,$kind,$sale);
    $q=$pdo->prepare('SELECT * FROM market_auction_fee_charges WHERE listing_id=? AND kind=? FOR UPDATE');$q->execute([$listingId,$kind]);$previous=$q->fetch();
    if($previous){
        if((int)$previous['gross_cents']!==$gross||($previous['sale_cents']===null?null:(int)$previous['sale_cents'])!==$sale)throw new RuntimeException('Auction fee event mismatch.');
        return $previous['invoice_id']===null?null:(int)$previous['invoice_id'];
    }
    $invoiceId=null;$seller=(int)$contract['buyer']['user_id'];
    $listing=market_lock_listing($pdo,$listingId);
    if((int)$listing['seller_id']!==$seller||$listing['type']!=='auction'
        ||($kind==='listing'&&$listing['status']!=='active')
        ||($kind==='success'&&(!in_array($listing['status'],['ended','sold'],true)||(int)$listing['highest_bidder_id']<1||(int)$listing['current_bid_cents']!==$sale)))
        throw new RuntimeException('Auction charge is not bound to an actual event.');
    if($gross>0){
        $reference='GI-'.strtoupper(bin2hex(random_bytes(10)));$service=$kind==='listing'?'auction_listing':'auction_success';
        $pdo->prepare("INSERT INTO market_invoices(user_id,invoice_number,service_code,fee_version,gross_cents,status) VALUES (?,?,?,?,?,'issued')")->execute([$seller,$reference,$service,$contract['fee_version'],$gross]);$invoiceId=(int)$pdo->lastInsertId();
        $q=$pdo->prepare('SELECT * FROM market_invoices WHERE id=?');$q->execute([$invoiceId]);$invoice=$q->fetch();
        $tax=fees_tax_split($gross,(int)$contract['tax_bps'],true);
        $snapshot=['version'=>'finance-1.0','operator'=>$contract['operator'],'buyer'=>$contract['buyer'],'service_code'=>$service,'label'=>$contract['labels'][$kind],
            'fee_version'=>$contract['fee_version'],'currency'=>'EUR','gross_cents'=>$gross,'net_cents'=>$tax['net_cents'],'tax_cents'=>$tax['tax_cents'],
            'tax_bps'=>$contract['tax_bps'],'tax_explanation'=>$contract['tax_explanation'],'order_reference'=>$reference,'ordered_at'=>$invoice['created_at'],
            'locale'=>$contract['locale'],'test_mode'=>str_ends_with((string)getenv('DB_DATABASE'),'_test'),'auction_listing_id'=>$listingId,'sale_cents'=>$sale,'service_description'=>$contract['terms']];
        $json=auction_billing_json($snapshot);
        $pdo->prepare('INSERT INTO market_invoice_snapshots(invoice_id,user_id,payload_ciphertext,payload_hash) VALUES (?,?,?,?)')->execute([$invoiceId,$seller,crypto_encrypt('finance',$json),hash('sha256',$json)]);
        $pdo->prepare('INSERT INTO market_manual_orders(invoice_id,user_id,listing_id,reference,recipient,quantity,net_cents,tax_cents,tax_bps,terms_hash,terms_text,request_key) VALUES (?,?,?,?,?,1,?,?,?,?,?,?)')
            ->execute([$invoiceId,$seller,$listingId,$reference,$contract['recipient'],$tax['net_cents'],$tax['tax_cents'],$contract['tax_bps'],hash('sha256',$contract['terms']),$contract['terms'],hash('sha256','auction-fee:'.$listingId.':'.$kind)]);
        // Copy the historical acceptance, never require consent to a later document version.
        $pdo->prepare("INSERT INTO market_contract_receipts(user_id,scope,context_key,invoice_id,document_id,content_hash) SELECT ?,'checkout',?,?,document_id,content_hash FROM market_auction_fee_acceptances WHERE listing_id=?")
            ->execute([$seller,'invoice:'.$invoiceId,$invoiceId,$listingId]);
        $pdo->prepare("INSERT INTO market_finance_events(invoice_id,kind,amount_cents,reference) VALUES (?,'fee_due',?,?)")->execute([$invoiceId,$gross,'auction-fee:'.$listingId.':'.$kind]);
    }
    $pdo->prepare('INSERT INTO market_auction_fee_charges(listing_id,kind,invoice_id,gross_cents,sale_cents) VALUES (?,?,?,?,?)')->execute([$listingId,$kind,$invoiceId,$gross,$sale]);
    audit_log($pdo,$seller,'auction.fee_assessed',$listingId,['initiator'=>'system','kind'=>$kind,'invoice_id'=>$invoiceId,'gross_cents'=>$gross,'sale_cents'=>$sale]);
    if($invoiceId!==null)notify_user($pdo,$seller,'auction-fee-due:'.$listingId.':'.$kind,
        i18n_user_text($pdo,$seller,'auction_billing.assessed_title'),
        i18n_user_text($pdo,$seller,'auction_billing.assessed_body',['amount'=>money_eur($gross),'listing'=>(string)$listingId,'reference'=>$reference]),
        '/user/billing.php?order='.$invoiceId,true);
    return $invoiceId;
}
function auction_billing_verify_payment(PDO $pdo,array $invoice): void {
    if(!finance_enabled($pdo))throw new PaymentUnavailable(t('auction_billing.unavailable'));
    $q=$pdo->prepare('SELECT gross_cents,waived_at FROM market_auction_fee_charges WHERE invoice_id=?');$q->execute([$invoice['id']]);$charge=$q->fetch();
    if(!$charge||$charge['waived_at']!==null||(int)$charge['gross_cents']!==(int)$invoice['gross_cents'])throw new PaymentConflict(t('auction_billing.changed'));
    finance_assert_invoice_snapshot($invoice,finance_snapshot($pdo,(int)$invoice['id']));
}
/** Waiver of a reviewed unpaid receivable. Paid amounts use verified refund records. */
function auction_billing_waive(PDO $pdo,int $actor,int $invoiceId,string $reason): void {
    governance_assert_actor($pdo,$actor,'finance.manage');$reason=trim($reason);
    if(mb_strlen($reason)<10||mb_strlen($reason)>2000)throw new InvalidArgumentException(t('auction_billing.invalid_waiver'));
    $pdo->beginTransaction();try{
        governance_assert_actor($pdo,$actor,'finance.manage');
        $q=$pdo->prepare('SELECT i.*,o.customer_claimed_at FROM market_invoices i JOIN market_manual_orders o ON o.invoice_id=i.id WHERE i.id=? FOR UPDATE');$q->execute([$invoiceId]);$invoice=$q->fetch();
        if(!$invoice||!auction_billing_service($invoice['service_code'])||!in_array($invoice['status'],['issued','pending','manual_rejected','void'],true))throw new PaymentConflict(t('auction_billing.invalid_waiver'));
        $q=$pdo->prepare('SELECT * FROM market_auction_fee_charges WHERE invoice_id=? FOR UPDATE');$q->execute([$invoiceId]);$charge=$q->fetch();
        if(!$charge)throw new PaymentConflict(t('auction_billing.invalid_waiver'));
        if($charge['waived_at']!==null){$pdo->commit();return;}
        $q=$pdo->prepare('SELECT COUNT(*) FROM market_payment_attempts WHERE invoice_id=?');$q->execute([$invoiceId]);
        if((int)$q->fetchColumn()>0||$invoice['status']==='void'||($invoice['customer_claimed_at']!==null&&$invoice['status']!=='manual_rejected'))throw new PaymentConflict(t('auction_billing.invalid_waiver'));
        $pdo->prepare("UPDATE market_invoices SET status='void' WHERE id=?")->execute([$invoiceId]);
        $pdo->prepare('UPDATE market_auction_fee_charges SET waived_by=?,waiver_reason=?,waived_at=UTC_TIMESTAMP() WHERE id=?')->execute([$actor,$reason,$charge['id']]);
        $pdo->prepare("INSERT INTO market_finance_events(invoice_id,actor_id,kind,amount_cents,reference) VALUES (?,?,'fee_waived',?,?)")->execute([$invoiceId,$actor,-(int)$charge['gross_cents'],'auction-waiver:'.$charge['id']]);
        audit_log($pdo,$actor,'auction.fee_waived',(int)$charge['listing_id'],['invoice_id'=>$invoiceId,'reason'=>$reason]);
        $seller=(int)$invoice['user_id'];
        notify_user($pdo,$seller,'auction-fee-waived:'.$charge['id'],i18n_user_text($pdo,$seller,'auction_billing.waived_title'),
            i18n_user_text($pdo,$seller,'auction_billing.waived_body',['amount'=>money_eur((int)$charge['gross_cents']),'reference'=>$invoice['invoice_number']]),
            '/user/billing.php?order='.$invoiceId,true);
        $pdo->commit();
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}

/** Optional controls in the shared fixed/draft/auction form; mandatory server-side only for paid publication. */
function auction_billing_form(PDO $pdo,int $seller,?array $listing=null): bool {
    $contract=$listing?auction_billing_contract($pdo,(int)$listing['id']):null;
    if($contract!==null){
        echo '<section class="panel"><h2>'.h(t('auction_billing.heading')).'</h2><p>'.h(t('auction_billing.preserved')).'</p><p>'.h($contract['terms']).'</p></section>';return false;
    }
    if($listing&&((int)$listing['auction_fee_eligible']!==1||($listing['type']==='auction'&&in_array($listing['status'],['active','paused'],true))))return false;
    try{
        $quote=auction_billing_quote($pdo,$seller);
        if($listing)$quote=relisting_discount_quote($pdo,$seller,(int)$listing['id'],$quote);
    }catch(InvalidArgumentException|DomainException|PaymentUnavailable $error){
        echo '<p class="notice">'.h($error->getMessage()).' <a href="/user/billing-details.php">'.h(t('auction_billing.billing_required')).'</a></p>';return false;
    }
    if($quote===null)return false;
    echo '<fieldset class="panel auction-fee-contract"><legend>'.h(t('auction_billing.heading')).'</legend><p>'.h($quote['payload']['terms']).'</p>';
    echo '<input type="hidden" name="auction_fee_quote" value="'.h($quote['hash']).'"><label class="check"><input type="checkbox" name="accept_auction_fees" value="1"> '.h(t('auction_billing.accept')).'</label></fieldset>';
    consent_form($quote['documents'],$quote['payload']['locale'],false);
    return true;
}
