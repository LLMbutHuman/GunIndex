<?php
declare(strict_types=1);

/**
 * Real MySQL/HTTP regressions for synthetic auction receivables only.
 * Run sequentially with the isolated test server on 127.0.0.1:8081.
 * No provider requests are made; manual receipt/refund references are fixtures.
 */
if (PHP_SAPI !== 'cli' || getenv('DB_DATABASE') !== 'gunindex_rebuild_test') {
    if (PHP_SAPI !== 'cli') http_response_code(404);
    exit(2);
}
require_once __DIR__.'/../config/bootstrap.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'gunindex_rebuild_test') exit(2);

function abt_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function abt_reject(callable $operation, string|array $classes): void
{
    try { $operation(); } catch (Throwable $error) {
        foreach ((array)$classes as $class) if ($error instanceof $class) return;
        throw $error;
    }
    throw new RuntimeException('Expected auction billing rejection did not occur.');
}
function abt_scalar(PDO $pdo, string $sql, array $parameters=[]): mixed
{
    $query=$pdo->prepare($sql); $query->execute($parameters); return $query->fetchColumn();
}
function abt_row(PDO $pdo, string $sql, array $parameters=[]): array
{
    $query=$pdo->prepare($sql); $query->execute($parameters); $row=$query->fetch();
    abt_check(is_array($row),'Expected synthetic fixture row.'); return $row;
}
function abt_setting(PDO $pdo, string $key, string $value): void
{
    $pdo->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,$value]);
    site_settings($pdo,true);
}
function abt_profile(PDO $pdo, int $user, string $name='Synthetic auction invoice recipient'): void
{
    finance_save_profile($pdo,$user,['legal_name'=>$name,'address_line1'=>'Synthetic Street 1',
        'address_line2'=>'Isolated fixture only','postal_code'=>'10115','city'=>'Synthetic town',
        'country_code'=>'DE','vat_id'=>'']);
}
function abt_user(PDO $pdo, bool $admin=false, bool $profile=true): array
{
    $name='auction_fee_'.bin2hex(random_bytes(8)); $password='Synthetic auction '.bin2hex(random_bytes(16));
    $secret=$admin?random_bytes(20):null;
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,is_admin,mfa_secret,country_code,preferred_locale) VALUES (?,?,?,'Synthetic auction billing fixture','10115','Synthetic town',?,?,'DE','de')")
        ->execute([$name,$name.'@example.invalid',auth_hash($password),(int)$admin,$secret===null?null:auth_secret_encrypt($secret)]);
    $id=(int)$pdo->lastInsertId(); if ($profile) abt_profile($pdo,$id);
    return ['id'=>$id,'username'=>$name,'email'=>$name.'@example.invalid','password'=>$password];
}
function abt_input(int $category, array $extra=[]): array
{
    return array_replace(['title'=>'Synthetic folding table '.bin2hex(random_bytes(5)),
        'description'=>'Ordinary synthetic folding table for isolated auction billing regression tests.',
        'category_id'=>(string)$category,'type'=>'auction','price'=>'10.00','buyout_price'=>'100.00',
        'status'=>'active','zip'=>'10115','city'=>'Synthetic town','country_code'=>'DE',
        'condition_label'=>'used','pickup'=>'1','shipping'=>'1','duration_days'=>'7','accept_rules'=>'1'],$extra);
}
function abt_accept(array $quote): array
{
    $input=['auction_fee_quote'=>$quote['hash'],'auction_fee_action'=>'publish','accept_auction_fees'=>'1',
        'legal_confirm'=>'1','legal_language'=>$quote['payload']['locale'],'legal_documents'=>[]];
    foreach ($quote['documents'] as $document) $input['legal_documents'][(int)$document['id']]=$document['content_hash'];
    return $input;
}
function abt_publish(PDO $pdo, int $seller, int $category, array $extra=[]): array
{
    $quote=auction_billing_quote($pdo,$seller); abt_check(is_array($quote),'Expected enabled auction quote.');
    $id=market_save_listing($pdo,$seller,array_replace(abt_input($category),abt_accept($quote),$extra));
    $charge=abt_row($pdo,"SELECT * FROM market_auction_fee_charges WHERE listing_id=? AND kind='listing'",[$id]);
    return ['id'=>$id,'invoice'=>$charge['invoice_id']===null?null:(int)$charge['invoice_id'],'quote'=>$quote];
}
function abt_status(PDO $pdo, int $invoice): string
{
    return (string)abt_scalar($pdo,'SELECT status FROM market_invoices WHERE id=?',[$invoice]);
}
function abt_no_publication(PDO $pdo, int $seller): void
{
    foreach (['market_listings'=>'seller_id','market_auction_fee_contracts'=>'seller_id','market_invoices'=>'user_id',
        'market_manual_orders'=>'user_id','market_invoice_snapshots'=>'user_id','market_contract_receipts'=>'user_id',
        'market_legal_acceptances'=>'user_id'] as $table=>$column) {
        abt_check((int)abt_scalar($pdo,'SELECT COUNT(*) FROM '.$table.' WHERE '.$column.'=?',[$seller])===0,'Rejected publication left a row in '.$table.'.');
    }
    abt_check(!$pdo->inTransaction(),'Rejected publication left an open transaction.');
}
function abt_charge_count(PDO $pdo, int $listing, int $expected): void
{
    abt_check((int)abt_scalar($pdo,'SELECT COUNT(*) FROM market_auction_fee_charges WHERE listing_id=?',[$listing])===$expected,'Unexpected number of assessed fees.');
}
function abt_retry_charge(PDO $pdo, int $listing, string $kind, ?int $sale): ?int
{
    $pdo->beginTransaction();
    try {
        market_lock_listing($pdo,$listing);
        $invoice=auction_billing_charge($pdo,$listing,$kind,$sale); $pdo->commit(); return $invoice;
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}
/** Only this fixture is allowed to be processed by the global close worker. */
function abt_close(PDO $pdo, int $listing): void
{
    $pdo->prepare("UPDATE market_listings SET ends_at='1000-01-01 00:00:00' WHERE id=?")->execute([$listing]);
    $first=(int)abt_scalar($pdo,"SELECT id FROM market_listings WHERE type='auction' AND status='active' AND ends_at<=UTC_TIMESTAMP() ORDER BY ends_at,id LIMIT 1");
    abt_check($first===$listing,'Refusing to close an unrelated auction fixture.');
    abt_check(market_close_due($pdo,1)===1,'Synthetic auction did not close.');
}
function abt_pay(PDO $pdo, int $admin, int $invoice, ?string $transaction=null): string
{
    $order=abt_row($pdo,'SELECT i.gross_cents,o.reference FROM market_invoices i JOIN market_manual_orders o ON o.invoice_id=i.id WHERE i.id=?',[$invoice]);
    $transaction??='ABPAY'.strtoupper(bin2hex(random_bytes(12)));
    manual_review($pdo,$admin,$invoice,$transaction,$order['reference'],(int)$order['gross_cents'],'approve',
        'Synthetic verified receipt fixture; no actual provider payment.');
    return $transaction;
}
function abt_document(PDO $pdo, int $actor, string $code, string $version='1.0'): int
{
    $id=legal_create($pdo,$actor,$code,$version,'de','Synthetic auction terms '.$version,
        str_repeat('Synthetic auction fee terms for isolated tests. No actual legal or provider approval. ',6));
    legal_approve($pdo,$actor,$id,'Synthetic reviewer only; no actual legal approval',gmdate('Y-m-d'),array_fill(0,5,'1'));
    return $id;
}
function abt_browser(): CurlHandle
{
    $browser=curl_init();
    curl_setopt_array($browser,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>5]);
    return $browser;
}
function abt_request(CurlHandle $browser, string $path, ?array $fields=null): array
{
    abt_check(str_starts_with($path,'/')&&!str_starts_with($path,'//'),'Only local HTTP test paths are allowed.');
    curl_setopt($browser,CURLOPT_URL,'http://127.0.0.1:8081'.$path);
    if ($fields===null) curl_setopt($browser,CURLOPT_HTTPGET,true);
    else { curl_setopt($browser,CURLOPT_POST,true); curl_setopt($browser,CURLOPT_POSTFIELDS,http_build_query($fields)); }
    $body=curl_exec($browser); abt_check(is_string($body),'Local test server request failed.');
    return [(int)curl_getinfo($browser,CURLINFO_RESPONSE_CODE),$body,(string)curl_getinfo($browser,CURLINFO_CONTENT_TYPE)];
}
function abt_csrf(string $body): string
{
    abt_check(preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/',$body,$match)===1,'CSRF control missing.');
    return $match[1];
}
function abt_form_accept(string $body): array
{
    abt_check(preg_match('/name="auction_fee_quote"\s+value="([a-f0-9]{64})"/',$body,$match)===1,'Auction quote missing from form.');
    $input=['auction_fee_quote'=>$match[1],'auction_fee_action'=>'publish','accept_auction_fees'=>'1','legal_confirm'=>'1','legal_documents'=>[]];
    abt_check(preg_match('/<button\b[^>]*name="auction_fee_action"[^>]*value="publish"[^>]*>/',$body)===1,'Explicit paid-publication submit button is missing.');
    abt_check(preg_match('/name="legal_language"\s+value="([a-z]{2})"/',$body,$language)===1,'Legal language missing.');
    $input['legal_language']=$language[1];
    preg_match_all('/name="legal_documents\[([0-9]+)\]"\s+value="([a-f0-9]{64})"/',$body,$documents,PREG_SET_ORDER);
    foreach ($documents as $document) $input['legal_documents'][(int)$document[1]]=$document[2];
    abt_check(count($input['legal_documents'])>0,'No concrete legal version in form.');
    abt_check(preg_match('/<input\b[^>]*name="accept_auction_fees"[^>]*>/',$body,$checkbox)===1
        && !preg_match('/\bchecked\b/i',$checkbox[0]),'Fee consent must not be preselected.');
    return $input;
}
function abt_login(CurlHandle $browser, array $user): void
{
    [$status,$body]=abt_request($browser,'/auth/login.php'); abt_check($status===200,'Login form unavailable.');
    [$status]=abt_request($browser,'/auth/login.php',['csrf_token'=>abt_csrf($body),'login'=>$user['email'],'password'=>$user['password']]);
    abt_check($status===303,'Synthetic login failed.');
}
function abt_mfa(PDO $pdo, CurlHandle $browser, int $user): void
{
    [$status,$body]=abt_request($browser,'/auth/mfa.php'); abt_check($status===200,'MFA challenge unavailable.');
    $secret=auth_secret_decrypt((string)abt_scalar($pdo,'SELECT mfa_secret FROM users WHERE id=?',[$user]));
    [$status]=abt_request($browser,'/auth/mfa.php',['csrf_token'=>abt_csrf($body),'code'=>auth_totp($secret,intdiv(time(),30))]);
    abt_check($status===303,'Synthetic MFA login failed.');
}

// Child mode inherits only the guarded synthetic DB. It never changes settings.
if (($argv[1]??'')==='--buyout-race') {
    try {
        $request=json_decode(stream_get_contents(STDIN,4097),true,8,JSON_THROW_ON_ERROR);
        abt_check(is_array($request)&&is_int($request['listing']??null)&&is_int($request['buyer']??null)
            &&is_int($request['start']??null)&&$request['listing']>0&&$request['buyer']>0,'Invalid concurrent fixture request.');
        $synthetic=(int)abt_scalar($pdo,"SELECT COUNT(*) FROM market_listings l JOIN users s ON s.id=l.seller_id JOIN market_auction_fee_contracts c ON c.listing_id=l.id WHERE l.id=? AND s.full_name='Synthetic auction billing fixture' AND s.email LIKE '%@example.invalid'",[$request['listing']]);
        abt_check($synthetic===1,'Concurrent operation requires an owned synthetic auction contract.');
        abt_check((int)abt_scalar($pdo,"SELECT COUNT(*) FROM users WHERE id=? AND full_name='Synthetic auction billing fixture' AND email LIKE '%@example.invalid'",[$request['buyer']])===1,'Concurrent operation requires a synthetic buyer.');
        $delay=$request['start']-(int)(microtime(true)*1000);
        abt_check($delay>=-5000&&$delay<=10000,'Concurrent test start is out of range.');
        if ($delay>0) usleep($delay*1000);
        try { market_buy_now($pdo,$request['listing'],$request['buyer']); $result='sold'; }
        catch (MarketConflictException) { $result='conflict'; }
        fwrite(STDOUT,json_encode(['status'=>$result,'buyer'=>$request['buyer']],JSON_THROW_ON_ERROR));
        exit(0);
    } catch (Throwable $error) {
        fwrite(STDERR,'Concurrent synthetic buyout failed: '.get_class($error).' at '.basename($error->getFile()).':'.$error->getLine().PHP_EOL);
        exit(1);
    }
}

$configured=[
    'auction_fees_enabled'=>'1','auction_fees_approved'=>'1','fee_auction_listing_cents'=>'50',
    'manual_paypal_enabled'=>'1','manual_paypal_approved'=>'1','manual_paypal_recipient'=>'auction-merchant@example.invalid',
    'manual_tax_reviewed'=>'1','manual_tax_bps'=>'1900','finance_enabled'=>'1',
    'operator_legal_name'=>'Synthetic auction fixture operator','operator_address_line1'=>'Synthetic Street 9',
    'operator_address_line2'=>'No actual operator identity','operator_postcode'=>'10115','operator_city'=>'Synthetic town',
    'operator_country'=>'DE','operator_email'=>'auction-operator@example.invalid','operator_vat_id'=>'SYNTHETIC-NOT-VALID',
    'operator_tax_number'=>'','operator_registration'=>'Synthetic fixture only','invoice_prefix'=>'ABTEST',
    'legal_publication_enabled'=>'1','legal_checkout_documents'=>'','maintenance_enabled'=>'0',
];
$saved=[];
foreach ($configured as $key=>$_) {
    $query=$pdo->prepare('SELECT setting_value,updated_at FROM system_settings WHERE setting_key=?');
    $query->execute([$key]); $saved[$key]=$query->fetch()?:null;
}
$taxQuery=$pdo->query("SELECT * FROM market_tax_rules WHERE country_code='DE'"); $savedTax=$taxQuery->fetch()?:null;
$savedCountry=abt_row($pdo,"SELECT is_active,updated_at FROM market_countries WHERE code='DE'");
$savedLocale=$_SESSION['locale']??null; $hadLocale=array_key_exists('locale',$_SESSION??[]);
$passed=0; $failure=null;
try {
    $_SESSION['locale']='de';
    foreach ($configured as $key=>$value) abt_setting($pdo,$key,$value);
    $pdo->exec("UPDATE market_countries SET is_active=1 WHERE code='DE'");
    $admin=abt_user($pdo,true);
    finance_save_tax($pdo,$admin['id'],'DE',1900,'Synthetic approved tax fixture only; no actual tax advice.');
    $code='auction-contract-'.bin2hex(random_bytes(8));
    $document=abt_document($pdo,$admin['id'],$code);
    abt_setting($pdo,'legal_checkout_documents',$code);
    $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')->execute(['Synthetic folding tables','auction-fees-'.bin2hex(random_bytes(8))]);
    $category=(int)$pdo->lastInsertId();

    $tests=[
        'default_off_and_legacy_auctions_never_acquire_retroactive_fees'=>static function() use($pdo,$category): void {
            $seller=abt_user($pdo); $buyer=abt_user($pdo);
            $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,buyout_cents,status,zip,city,country_code,ends_at) VALUES (?,?,'Synthetic legacy table','Ordinary synthetic folding table fixture.','auction',1000,10000,'active','10115','Synthetic town','DE',UTC_TIMESTAMP()+INTERVAL 7 DAY)")->execute([$seller['id'],$category]);
            $legacy=(int)$pdo->lastInsertId();
            abt_check((int)abt_scalar($pdo,'SELECT auction_fee_eligible FROM market_listings WHERE id=?',[$legacy])===0,'Migration/default must exclude historical rows.');
            $pdo->exec("DELETE FROM system_settings WHERE setting_key='auction_fees_enabled'"); site_settings($pdo,true);
            try {
                abt_check(!auction_billing_enabled($pdo)&&auction_billing_quote($pdo,$seller['id'])===null,'Missing enable flag must default off.');
                $free=market_save_listing($pdo,$seller['id'],abt_input($category));
                abt_check((int)abt_scalar($pdo,'SELECT auction_fee_eligible FROM market_listings WHERE id=?',[$free])===0,'Free publication must be permanently marked.');
            } finally { abt_setting($pdo,'auction_fees_enabled','1'); }
            foreach ([$legacy,$free] as $id) {
                market_save_listing($pdo,$seller['id'],abt_input($category),[],$id);
                market_buy_now($pdo,$id,$buyer['id']);
                abt_check(auction_billing_contract($pdo,$id)===null,'Historical auction unexpectedly gained a contract.');
                abt_charge_count($pdo,$id,0);
            }
            abt_check((int)abt_scalar($pdo,'SELECT COUNT(*) FROM market_invoices WHERE user_id=?',[$seller['id']])===0,'Historical auction was charged.');
        },
        'publication_requires_actual_configuration_and_billing_profile'=>static function() use($pdo,$category,$configured): void {
            $seller=abt_user($pdo,false,false);
            abt_reject(fn()=>auction_billing_quote($pdo,$seller['id']),PaymentUnavailable::class);
            abt_profile($pdo,$seller['id']);
            foreach (['auction_fees_approved','finance_enabled','manual_paypal_enabled','manual_paypal_approved','manual_tax_reviewed','legal_publication_enabled'] as $key) {
                abt_setting($pdo,$key,'0');
                try { abt_reject(fn()=>market_save_listing($pdo,$seller['id'],abt_input($category)),[PaymentUnavailable::class,DomainException::class]); }
                finally { abt_setting($pdo,$key,$configured[$key]); }
                abt_no_publication($pdo,$seller['id']);
            }
        },
        'publication_commits_exact_quote_consent_receivable_and_no_payment'=>static function() use($pdo,$category,$document): void {
            $seller=abt_user($pdo); $fixture=abt_publish($pdo,$seller['id'],$category); $id=$fixture['id']; $invoice=$fixture['invoice'];
            abt_check(is_int($invoice)&&$invoice>0,'Positive fee needs one invoice order.');
            abt_check(auction_billing_contract($pdo,$id)===$fixture['quote']['payload'],'Accepted contract differs from displayed quote.');
            $charge=abt_row($pdo,"SELECT * FROM market_auction_fee_charges WHERE listing_id=? AND kind='listing'",[$id]);
            abt_check((int)$charge['gross_cents']===50&&$charge['sale_cents']===null,'Listing fee or basis is wrong.');
            $order=abt_row($pdo,'SELECT * FROM market_manual_orders WHERE invoice_id=?',[$invoice]);
            $snapshot=finance_snapshot($pdo,$invoice); $i=payments_invoice($pdo,$invoice,$seller['id']);
            finance_assert_invoice_snapshot($i,$snapshot);
            abt_check($i['status']==='issued'&&(int)$i['gross_cents']===50&&$i['service_code']==='auction_listing','Fee was not recorded as unpaid listing service.');
            abt_check($order['reference']===$i['invoice_number']&&$snapshot['order_reference']===$order['reference'],'Payment reference mismatch.');
            abt_check((int)$order['net_cents']===42&&(int)$order['tax_cents']===8&&(int)$order['tax_bps']===1900,'Cent-accurate fee tax split failed.');
            abt_check($order['recipient']==='auction-merchant@example.invalid'&&$snapshot['auction_listing_id']===$id,'Frozen recipient or listing binding is missing.');
            $acceptance=abt_row($pdo,'SELECT document_id,content_hash FROM market_auction_fee_acceptances WHERE listing_id=?',[$id]);
            $receipt=abt_row($pdo,'SELECT document_id,content_hash,context_key FROM market_contract_receipts WHERE invoice_id=?',[$invoice]);
            abt_check((int)$acceptance['document_id']===$document&&(int)$receipt['document_id']===$document
                &&$receipt['content_hash']===$acceptance['content_hash']&&$receipt['context_key']==='invoice:'.$invoice,'Exact consent version was not bound to the charge.');
            abt_check((int)abt_scalar($pdo,"SELECT COUNT(*) FROM market_finance_events WHERE invoice_id=? AND kind='fee_due' AND amount_cents=50",[$invoice])===1,'Receivable event missing.');
            foreach (['market_invoice_documents','market_payment_attempts','market_bump_credits','market_premium_bookings'] as $table)
                abt_check((int)abt_scalar($pdo,'SELECT COUNT(*) FROM '.$table.' WHERE invoice_id=?',[$invoice])===0,'Publication incorrectly created '.$table.'.');
            abt_check((int)abt_scalar($pdo,"SELECT COUNT(*) FROM market_finance_events WHERE invoice_id=? AND kind='cash_in'",[$invoice])===0,'Publication invented a payment.');
        },
        'missing_tampered_or_unaccepted_quote_rolls_back_every_business_row'=>static function() use($pdo,$category): void {
            $seller=abt_user($pdo); $quote=auction_billing_quote($pdo,$seller['id']); $valid=abt_accept($quote);
            $variants=[];
            foreach (['auction_fee_quote','auction_fee_action','accept_auction_fees','legal_documents','legal_confirm','legal_language'] as $key) { $bad=$valid; unset($bad[$key]); $variants[]=$bad; }
            $variants[]=array_replace($valid,['auction_fee_action'=>'save']);
            $variants[]=array_replace($valid,['auction_fee_quote'=>str_repeat('0',64)]);
            $variants[]=array_replace($valid,['auction_fee_quote'=>['not-a-scalar']]);
            $variants[]=array_replace($valid,['accept_auction_fees'=>'0']);
            $variants[]=array_replace($valid,['legal_language'=>'en']);
            $bad=$valid; $bad['legal_documents'][array_key_first($bad['legal_documents'])]=str_repeat('0',64); $variants[]=$bad;
            foreach ($variants as $input) {
                abt_reject(fn()=>market_save_listing($pdo,$seller['id'],array_replace(abt_input($category),$input)),InvalidArgumentException::class);
                abt_no_publication($pdo,$seller['id']);
            }
        },
        'stale_price_recipient_profile_or_legal_version_cannot_be_silently_accepted'=>static function() use($pdo,$category,$admin,$code): void {
            $seller=abt_user($pdo); $quote=auction_billing_quote($pdo,$seller['id']); $input=array_replace(abt_input($category),abt_accept($quote));
            foreach (['fee_auction_listing_cents'=>['51','50'],'manual_paypal_recipient'=>['changed@example.invalid','auction-merchant@example.invalid']] as $key=>[$changed,$original]) {
                abt_setting($pdo,$key,$changed);
                try { abt_reject(fn()=>market_save_listing($pdo,$seller['id'],$input),InvalidArgumentException::class); }
                finally { abt_setting($pdo,$key,$original); }
                abt_no_publication($pdo,$seller['id']);
            }
            abt_profile($pdo,$seller['id'],'Changed synthetic invoice recipient');
            abt_reject(fn()=>market_save_listing($pdo,$seller['id'],$input),InvalidArgumentException::class); abt_no_publication($pdo,$seller['id']);
            $quote=auction_billing_quote($pdo,$seller['id']); $input=array_replace(abt_input($category),abt_accept($quote));
            $otherCode='auction-stale-'.bin2hex(random_bytes(8)); abt_document($pdo,$admin['id'],$otherCode);
            abt_setting($pdo,'legal_checkout_documents',$otherCode);
            try { abt_reject(fn()=>market_save_listing($pdo,$seller['id'],$input),InvalidArgumentException::class); abt_no_publication($pdo,$seller['id']); }
            finally { abt_setting($pdo,'legal_checkout_documents',$code); }
        },
        'draft_is_free_until_first_explicitly_accepted_publication'=>static function() use($pdo,$category): void {
            $seller=abt_user($pdo); $draft=market_save_listing($pdo,$seller['id'],abt_input($category,['status'=>'draft']));
            abt_check(auction_billing_contract($pdo,$draft)===null,'Draft acquired a contract.'); abt_charge_count($pdo,$draft,0);
            abt_check((int)abt_scalar($pdo,'SELECT auction_fee_eligible FROM market_listings WHERE id=?',[$draft])===1,'New draft lost fee eligibility.');
            abt_reject(fn()=>market_save_listing($pdo,$seller['id'],abt_input($category),[],$draft),InvalidArgumentException::class);
            $row=abt_row($pdo,'SELECT status,ends_at FROM market_listings WHERE id=?',[$draft]);
            abt_check($row['status']==='draft'&&$row['ends_at']===null,'Rejected first publication changed the draft.');
            abt_check((int)abt_scalar($pdo,'SELECT COUNT(*) FROM market_invoices WHERE user_id=?',[$seller['id']])===0,'Rejected draft publication charged a fee.');
            $quote=auction_billing_quote($pdo,$seller['id']); market_save_listing($pdo,$seller['id'],array_replace(abt_input($category),abt_accept($quote)),[],$draft);
            abt_charge_count($pdo,$draft,1);
        },
        'editing_pausing_and_resuming_preserve_one_fee_contract_and_original_deadline'=>static function() use($pdo,$category): void {
            $seller=abt_user($pdo); $fixture=abt_publish($pdo,$seller['id'],$category); $id=$fixture['id'];
            $ends=(string)abt_scalar($pdo,'SELECT ends_at FROM market_listings WHERE id=?',[$id]);
            $hash=(string)abt_scalar($pdo,'SELECT payload_hash FROM market_auction_fee_contracts WHERE listing_id=?',[$id]);
            market_save_listing($pdo,$seller['id'],abt_input($category,['duration_days'=>'14']),[],$id);
            market_transition($pdo,$id,$seller['id'],'pause');
            abt_check(abt_scalar($pdo,'SELECT status FROM market_listings WHERE id=?',[$id])==='paused','Pause failed.');
            market_save_listing($pdo,$seller['id'],abt_input($category,['duration_days'=>'1']),[],$id);
            abt_check(abt_scalar($pdo,'SELECT ends_at FROM market_listings WHERE id=?',[$id])===$ends,'An edit/resume reset the auction clock.');
            abt_check(abt_scalar($pdo,'SELECT payload_hash FROM market_auction_fee_contracts WHERE listing_id=?',[$id])===$hash,'An edit changed the contract.');
            foreach (['type'=>'fixed','status'=>'draft'] as $key=>$value)
                abt_reject(fn()=>market_save_listing($pdo,$seller['id'],abt_input($category,[$key=>$value]),[],$id),MarketConflictException::class);
            abt_check(abt_retry_charge($pdo,$id,'listing',null)===$fixture['invoice'],'Repeated assessment returned another fee order.');
            abt_charge_count($pdo,$id,1);
            abt_check((int)abt_scalar($pdo,'SELECT COUNT(*) FROM market_invoices WHERE user_id=?',[$seller['id']])===1,'Editing charged twice.');
        },
        'ended_auction_fee_uses_visible_sale_price_and_retries_once'=>static function() use($pdo,$category): void {
            $seller=abt_user($pdo); $winner=abt_user($pdo); $other=abt_user($pdo);
            $fixture=abt_publish($pdo,$seller['id'],$category,['buyout_price'=>'']); $id=$fixture['id'];
            market_bid($pdo,$id,$winner['id'],5000); market_bid($pdo,$id,$other['id'],2000); abt_close($pdo,$id);
            $listing=abt_row($pdo,'SELECT status,highest_bidder_id,current_bid_cents FROM market_listings WHERE id=?',[$id]);
            abt_check($listing['status']==='ended'&&(int)$listing['highest_bidder_id']===$winner['id']&&(int)$listing['current_bid_cents']===2100,'Unexpected auction winner or visible final price.');
            $charge=abt_row($pdo,"SELECT * FROM market_auction_fee_charges WHERE listing_id=? AND kind='success'",[$id]);
            abt_check((int)$charge['sale_cents']===2100&&(int)$charge['gross_cents']===63,'Success fee used a proxy maximum instead of final price.');
            abt_check(abt_retry_charge($pdo,$id,'success',2100)===(int)$charge['invoice_id'],'Success retry returned another invoice.');
            abt_reject(fn()=>abt_retry_charge($pdo,$id,'success',5000),RuntimeException::class);
            abt_charge_count($pdo,$id,2);
            abt_check((int)abt_scalar($pdo,'SELECT COUNT(*) FROM market_manual_orders WHERE user_id=?',[$seller['id']])===2,'Repeated close created an extra payment reference.');
        },
        'unsold_close_never_creates_success_commission'=>static function() use($pdo,$category): void {
            $seller=abt_user($pdo); $fixture=abt_publish($pdo,$seller['id'],$category); abt_close($pdo,$fixture['id']);
            $listing=abt_row($pdo,'SELECT status,highest_bidder_id,current_bid_cents FROM market_listings WHERE id=?',[$fixture['id']]);
            abt_check($listing['status']==='ended'&&$listing['highest_bidder_id']===null&&$listing['current_bid_cents']===null,'Unsold close invented a buyer or sale.');
            abt_charge_count($pdo,$fixture['id'],1);
            abt_reject(fn()=>abt_retry_charge($pdo,$fixture['id'],'success',1000),RuntimeException::class);
            abt_charge_count($pdo,$fixture['id'],1);
        },
        'buyout_creates_one_commission_and_repeated_purchase_is_rejected'=>static function() use($pdo,$category): void {
            $seller=abt_user($pdo); $buyer=abt_user($pdo); $other=abt_user($pdo); $fixture=abt_publish($pdo,$seller['id'],$category);
            $result=market_buy_now($pdo,$fixture['id'],$buyer['id']);
            abt_check($result['status']==='sold'&&$result['price_cents']===10000,'Unexpected buyout result.');
            $charge=abt_row($pdo,"SELECT * FROM market_auction_fee_charges WHERE listing_id=? AND kind='success'",[$fixture['id']]);
            abt_check((int)$charge['gross_cents']===300&&(int)$charge['sale_cents']===10000,'Buyout commission incorrect.');
            abt_reject(fn()=>market_buy_now($pdo,$fixture['id'],$other['id']),MarketConflictException::class);
            abt_check(abt_retry_charge($pdo,$fixture['id'],'success',10000)===(int)$charge['invoice_id'],'Buyout assessment is not idempotent.');
            abt_charge_count($pdo,$fixture['id'],2);
            abt_check((int)abt_scalar($pdo,"SELECT COUNT(*) FROM market_bids WHERE listing_id=? AND event_type='buyout'",[$fixture['id']])===1,'Duplicate buyout event.');
        },
        'two_parallel_buyouts_create_one_sale_one_success_fee_and_one_reference'=>static function() use($pdo,$category): void {
            $seller=abt_user($pdo); $buyers=[abt_user($pdo),abt_user($pdo)]; $fixture=abt_publish($pdo,$seller['id'],$category);
            $start=(int)(microtime(true)*1000)+1500; $children=[]; $results=[];
            try {
                foreach ($buyers as $buyer) {
                    $pipes=[];
                    $process=proc_open([PHP_BINARY,__FILE__,'--buyout-race'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
                    abt_check(is_resource($process),'Cannot start real concurrent PHP worker.');
                    $children[]=[$process,$pipes];
                    fwrite($pipes[0],json_encode(['listing'=>$fixture['id'],'buyer'=>$buyer['id'],'start'=>$start],JSON_THROW_ON_ERROR));
                    fclose($pipes[0]);
                }
                foreach ($children as $index=>[$process,$pipes]) {
                    $body=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]);
                    fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($process); unset($children[$index]);
                    abt_check($exit===0&&$errors==='','Concurrent synthetic worker failed: '.substr($errors,0,500));
                    $results[]=json_decode($body,true,8,JSON_THROW_ON_ERROR);
                }
            } finally {
                foreach ($children as [$process,$pipes]) {
                    foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
                }
            }
            $states=array_column($results,'status'); sort($states);
            abt_check($states===['conflict','sold'],'Competing buyers did not serialize to one sale.');
            $winner=array_values(array_filter($results,static fn(array $result):bool=>$result['status']==='sold'))[0]['buyer'];
            abt_check((int)abt_scalar($pdo,'SELECT highest_bidder_id FROM market_listings WHERE id=?',[$fixture['id']])===$winner,'Recorded winner differs from successful worker.');
            abt_charge_count($pdo,$fixture['id'],2);
            $charge=abt_row($pdo,"SELECT * FROM market_auction_fee_charges WHERE listing_id=? AND kind='success'",[$fixture['id']]);
            abt_check((int)$charge['gross_cents']===300&&(int)$charge['sale_cents']===10000,'Concurrent success commission is wrong.');
            abt_check((int)abt_scalar($pdo,"SELECT COUNT(*) FROM market_invoices WHERE user_id=? AND service_code='auction_success'",[$seller['id']])===1,'Concurrent buyouts duplicated a receivable.');
            abt_check((int)abt_scalar($pdo,'SELECT COUNT(*) FROM market_manual_orders WHERE invoice_id=?',[$charge['invoice_id']])===1,'Concurrent buyouts duplicated payment references.');
            abt_check((int)abt_scalar($pdo,"SELECT COUNT(*) FROM market_finance_events WHERE invoice_id=? AND kind='fee_due'",[$charge['invoice_id']])===1,'Concurrent buyouts duplicated fee bookkeeping.');
            abt_check((int)abt_scalar($pdo,"SELECT COUNT(*) FROM market_bids WHERE listing_id=? AND event_type='buyout'",[$fixture['id']])===1,'Concurrent buyouts duplicated sale events.');
        },
        'accepted_contract_survives_settings_profile_tax_and_legal_changes'=>static function() use($pdo,$category,$admin,$configured,$code,$document): void {
            $seller=abt_user($pdo); $buyer=abt_user($pdo); $fixture=abt_publish($pdo,$seller['id'],$category); $contract=$fixture['quote']['payload'];
            $changed=['fee_auction_listing_cents'=>'999','manual_paypal_recipient'=>'later-merchant@example.invalid',
                'operator_legal_name'=>'Later synthetic operator','auction_fees_enabled'=>'0'];
            $otherCode='auction-frozen-'.bin2hex(random_bytes(8)); abt_document($pdo,$admin['id'],$otherCode);
            try {
                foreach ($changed as $key=>$value) abt_setting($pdo,$key,$value);
                abt_setting($pdo,'legal_checkout_documents',$otherCode);
                abt_profile($pdo,$seller['id'],'Later synthetic billing identity');
                finance_save_tax($pdo,$admin['id'],'DE',700,'Different synthetic tax fixture for future contracts only.');
                market_buy_now($pdo,$fixture['id'],$buyer['id']);
                $charge=abt_row($pdo,"SELECT * FROM market_auction_fee_charges WHERE listing_id=? AND kind='success'",[$fixture['id']]);
                $invoice=(int)$charge['invoice_id']; $snapshot=finance_snapshot($pdo,$invoice);
                abt_check((int)$charge['gross_cents']===300&&$snapshot['net_cents']===252&&$snapshot['tax_cents']===48,'Accepted tariff/tax was recalculated.');
                abt_check($snapshot['buyer']===$contract['buyer']&&$snapshot['operator']===$contract['operator']
                    &&$snapshot['fee_version']===$contract['fee_version']&&$snapshot['tax_bps']===1900,'Accepted invoice identity or tariff changed.');
                abt_check(abt_scalar($pdo,'SELECT recipient FROM market_manual_orders WHERE invoice_id=?',[$invoice])===$contract['recipient'],'Accepted recipient changed.');
                abt_check((int)abt_scalar($pdo,'SELECT document_id FROM market_contract_receipts WHERE invoice_id=?',[$invoice])===$document,'Success fee accepted a later legal document.');
                abt_check(auction_billing_contract($pdo,$fixture['id'])===$contract,'Original contract was overwritten.');
            } finally {
                foreach ($changed as $key=>$_) abt_setting($pdo,$key,$configured[$key]);
                abt_setting($pdo,'legal_checkout_documents',$code);
                finance_save_tax($pdo,$admin['id'],'DE',1900,'Synthetic approved tax fixture only; no actual tax advice.');
            }
        },
        'zero_fees_have_events_but_no_zero_value_invoice_and_cap_is_applied'=>static function() use($pdo,$category): void {
            $seller=abt_user($pdo); $buyer=abt_user($pdo); abt_setting($pdo,'fee_auction_listing_cents','0');
            try {
                $tiny=abt_publish($pdo,$seller['id'],$category,['price'=>'0.01','buyout_price'=>'0.01']);
                abt_check($tiny['invoice']===null,'Zero listing fee created an invoice.');
                market_buy_now($pdo,$tiny['id'],$buyer['id']);
                $charge=abt_row($pdo,"SELECT gross_cents,invoice_id FROM market_auction_fee_charges WHERE listing_id=? AND kind='success'",[$tiny['id']]);
                abt_check((int)$charge['gross_cents']===0&&$charge['invoice_id']===null,'Rounded zero success fee created an invoice.');
                abt_charge_count($pdo,$tiny['id'],2);
                abt_check((int)abt_scalar($pdo,'SELECT COUNT(*) FROM market_invoices WHERE user_id=?',[$seller['id']])===0,'Zero fees created a payment order.');
                $large=abt_publish($pdo,$seller['id'],$category,['buyout_price'=>'100000.00']); market_buy_now($pdo,$large['id'],$buyer['id']);
                abt_check((int)abt_scalar($pdo,"SELECT gross_cents FROM market_auction_fee_charges WHERE listing_id=? AND kind='success'",[$large['id']])===2990,'Success fee cap was exceeded.');
            } finally { abt_setting($pdo,'fee_auction_listing_cents','50'); }
        },
        'database_success_fee_constraint_rejects_null_sale_basis'=>static function() use($pdo,$category): void {
            $seller=abt_user($pdo); abt_setting($pdo,'fee_auction_listing_cents','0');
            try { $fixture=abt_publish($pdo,$seller['id'],$category); }
            finally { abt_setting($pdo,'fee_auction_listing_cents','50'); }
            $pdo->beginTransaction();
            try {
                $rejected=false;
                try {
                    // Every other constraint is satisfied: existing contract, unused kind,
                    // zero gross and NULL invoice. Only the missing sale basis is invalid.
                    $pdo->prepare("INSERT INTO market_auction_fee_charges(listing_id,kind,invoice_id,gross_cents,sale_cents) VALUES (?,'success',NULL,0,NULL)")->execute([$fixture['id']]);
                } catch (PDOException $error) {
                    if (!str_contains($error->getMessage(),'auction_fee_basis')) throw $error;
                    $rejected=true;
                }
                abt_check($rejected,'SQL CHECK accepted UNKNOWN for a NULL success sale basis.');
            } finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
            abt_charge_count($pdo,$fixture['id'],1);
        },
        'expired_or_bid_locked_auction_cannot_be_edited_or_restarted'=>static function() use($pdo,$category): void {
            $seller=abt_user($pdo); $buyer=abt_user($pdo); $fixture=abt_publish($pdo,$seller['id'],$category);
            market_bid($pdo,$fixture['id'],$buyer['id'],2000);
            abt_reject(fn()=>market_save_listing($pdo,$seller['id'],abt_input($category),[],$fixture['id']),MarketConflictException::class);
            $paused=abt_publish($pdo,$seller['id'],$category); market_transition($pdo,$paused['id'],$seller['id'],'pause');
            $pdo->prepare('UPDATE market_listings SET ends_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id=?')->execute([$paused['id']]);
            abt_reject(fn()=>market_save_listing($pdo,$seller['id'],abt_input($category),[],$paused['id']),MarketConflictException::class);
            abt_check(abt_scalar($pdo,'SELECT status FROM market_listings WHERE id=?',[$paused['id']])==='paused','Expired pause resumed without authorization.');
            abt_charge_count($pdo,$paused['id'],1); abt_charge_count($pdo,$fixture['id'],1);
        },
        'seller_and_outsider_cannot_cancel_an_earned_unpaid_fee'=>static function() use($pdo,$category): void {
            $seller=abt_user($pdo); $other=abt_user($pdo); $fixture=abt_publish($pdo,$seller['id'],$category); $invoice=$fixture['invoice'];
            foreach (['issued','pending','manual_rejected'] as $status) {
                $pdo->prepare('UPDATE market_invoices SET status=? WHERE id=?')->execute([$status,$invoice]);
                foreach ([$seller['id'],$other['id']] as $actor) abt_reject(fn()=>finance_cancel($pdo,$invoice,$actor),PaymentConflict::class);
                abt_check(abt_status($pdo,$invoice)===$status,'Self/foreign cancellation changed the receivable.');
            }
            abt_check((int)abt_scalar($pdo,'SELECT COUNT(*) FROM market_invoice_documents WHERE invoice_id=?',[$invoice])===0,'Rejected cancellation produced a document.');
        },
        'verified_manual_fee_payment_has_no_rewards_and_one_invoice_and_cash_entry'=>static function() use($pdo,$category,$admin): void {
            $seller=abt_user($pdo); $fixture=abt_publish($pdo,$seller['id'],$category); $invoice=$fixture['invoice'];
            $transaction=abt_pay($pdo,$admin['id'],$invoice); abt_pay($pdo,$admin['id'],$invoice,$transaction);
            abt_check(abt_status($pdo,$invoice)==='paid','Verified fee payment was not recorded.');
            foreach (['market_bump_credits','market_premium_bookings'] as $table)
                abt_check((int)abt_scalar($pdo,'SELECT COUNT(*) FROM '.$table.' WHERE invoice_id=?',[$invoice])===0,'Fee payment granted an unrelated reward.');
            abt_check((int)abt_scalar($pdo,'SELECT COUNT(*) FROM market_payment_attempts WHERE invoice_id=?',[$invoice])===1,'Payment replay created a second attempt.');
            abt_check((int)abt_scalar($pdo,'SELECT COUNT(*) FROM market_invoice_documents WHERE origin_invoice_id=?',[$invoice])===1,'Payment replay created multiple invoices.');
            $cash=abt_row($pdo,"SELECT COUNT(*) AS n,SUM(amount_cents) AS amount FROM market_finance_events WHERE invoice_id=? AND kind='cash_in'",[$invoice]);
            abt_check((int)$cash['n']===1&&(int)$cash['amount']===50,'Payment replay duplicated cash accounting.');
            $document=(int)abt_scalar($pdo,'SELECT id FROM market_invoice_documents WHERE origin_invoice_id=?',[$invoice]);
            abt_check(finance_issue_invoice($pdo,$invoice,$admin['id'])===$document,'Invoice retry changed document identity.');
            $other=abt_publish($pdo,$seller['id'],$category);
            abt_reject(fn()=>abt_pay($pdo,$admin['id'],$other['invoice'],$transaction),PaymentConflict::class);
            abt_check(abt_status($pdo,$other['invoice'])==='issued','Duplicate capture paid a different receivable.');
            abt_reject(fn()=>auction_billing_waive($pdo,$admin['id'],$invoice,'Synthetic paid fee must use refund instead.'),PaymentConflict::class);
        },
        'snapshot_mismatch_rolls_back_manual_attempt_payment_and_document'=>static function() use($pdo,$category,$admin): void {
            $seller=abt_user($pdo); $fixture=abt_publish($pdo,$seller['id'],$category); $invoice=$fixture['invoice'];
            $original=(string)abt_scalar($pdo,'SELECT payload_hash FROM market_invoice_snapshots WHERE invoice_id=?',[$invoice]);
            $pdo->prepare('UPDATE market_invoice_snapshots SET payload_hash=? WHERE invoice_id=?')->execute([str_repeat('0',64),$invoice]);
            try { abt_reject(fn()=>abt_pay($pdo,$admin['id'],$invoice),RuntimeException::class); }
            finally { $pdo->prepare('UPDATE market_invoice_snapshots SET payload_hash=? WHERE invoice_id=?')->execute([$original,$invoice]); }
            abt_check(abt_status($pdo,$invoice)==='issued','Snapshot mismatch committed payment status.');
            foreach (['market_payment_attempts','market_invoice_documents'] as $table)
                abt_check((int)abt_scalar($pdo,'SELECT COUNT(*) FROM '.$table.' WHERE invoice_id=?',[$invoice])===0,'Rejected payment left '.$table.'.');
            abt_check((int)abt_scalar($pdo,"SELECT COUNT(*) FROM market_finance_events WHERE invoice_id=? AND kind='cash_in'",[$invoice])===0,'Rejected payment created cash.');
        },
        'waiver_requires_finance_permission_reviewed_claim_and_is_idempotent'=>static function() use($pdo,$category,$admin): void {
            $seller=abt_user($pdo); $fixture=abt_publish($pdo,$seller['id'],$category); $invoice=$fixture['invoice'];
            abt_reject(fn()=>auction_billing_waive($pdo,$seller['id'],$invoice,'Synthetic unauthorized waiver request.'),DomainException::class);
            abt_reject(fn()=>auction_billing_waive($pdo,$admin['id'],$invoice,'short'),InvalidArgumentException::class);
            $pdo->prepare('UPDATE market_manual_orders SET customer_claimed_at=UTC_TIMESTAMP() WHERE invoice_id=?')->execute([$invoice]);
            $pdo->prepare("UPDATE market_invoices SET status='pending' WHERE id=?")->execute([$invoice]);
            abt_reject(fn()=>auction_billing_waive($pdo,$admin['id'],$invoice,'Synthetic claim must first be reconciled.'),PaymentConflict::class);
            abt_check(abt_status($pdo,$invoice)==='pending','Unreviewed claim was waived.');
            manual_review($pdo,$admin['id'],$invoice,'','',0,'reject','Synthetic claim reconciled: no actual incoming payment.');
            $reason='Synthetic reviewed receivable waived for fixture correction.';
            auction_billing_waive($pdo,$admin['id'],$invoice,$reason);
            $charge=abt_row($pdo,'SELECT * FROM market_auction_fee_charges WHERE invoice_id=?',[$invoice]);
            auction_billing_waive($pdo,$admin['id'],$invoice,'A repeated request must preserve the first explanation.');
            abt_check(abt_status($pdo,$invoice)==='void'&&(int)$charge['waived_by']===$admin['id']&&$charge['waiver_reason']===$reason&&$charge['waived_at']!==null,'Waiver lacks accountable evidence.');
            abt_check(abt_row($pdo,'SELECT * FROM market_auction_fee_charges WHERE invoice_id=?',[$invoice])===$charge,'Waiver retry overwrote evidence.');
            $event=abt_row($pdo,"SELECT COUNT(*) AS n,SUM(amount_cents) AS amount FROM market_finance_events WHERE invoice_id=? AND kind='fee_waived'",[$invoice]);
            abt_check((int)$event['n']===1&&(int)$event['amount']===-50,'Waiver was booked more than once.');
            abt_reject(fn()=>abt_pay($pdo,$admin['id'],$invoice),PaymentConflict::class);
            abt_check(abt_retry_charge($pdo,$fixture['id'],'listing',null)===$invoice,'Assessment retry resurrected a waived fee.');
            abt_charge_count($pdo,$fixture['id'],1);
        },
        'waiver_rejects_any_payment_attempt_even_without_a_customer_claim'=>static function() use($pdo,$category,$admin): void {
            $seller=abt_user($pdo); $fixture=abt_publish($pdo,$seller['id'],$category); $invoice=$fixture['invoice'];
            $pdo->prepare("INSERT INTO market_payment_attempts(invoice_id,provider,status,idempotency_key,mode) VALUES (?,'paypal','pending',?,'sandbox')")->execute([$invoice,bin2hex(random_bytes(16))]);
            abt_reject(fn()=>auction_billing_waive($pdo,$admin['id'],$invoice,'Synthetic pending attempt requires reconciliation.'),PaymentConflict::class);
            abt_check(abt_status($pdo,$invoice)==='issued','Invoice with an attempt was waived.');
            abt_check(abt_scalar($pdo,'SELECT waived_at FROM market_auction_fee_charges WHERE invoice_id=?',[$invoice])===null,'Attempt guard changed waiver evidence.');
        },
        'paid_fees_support_existing_partial_full_refunds_without_duplicate_credit'=>static function() use($pdo,$category,$admin): void {
            $seller=abt_user($pdo); $fixture=abt_publish($pdo,$seller['id'],$category); $invoice=$fixture['invoice']; abt_pay($pdo,$admin['id'],$invoice);
            $request=finance_request_refund($pdo,$seller['id'],$invoice,20,'Synthetic partial fee correction requested.');
            $first='ABREF'.strtoupper(bin2hex(random_bytes(12)));
            $refund=finance_record_refund($pdo,$admin['id'],$invoice,20,$first,'Synthetic externally verified refund fixture; no actual funds.',$request);
            abt_check(abt_status($pdo,$invoice)==='partially_refunded','Partial refund state missing.');
            abt_check(finance_record_refund($pdo,$admin['id'],$invoice,20,$first,'Synthetic retry of verified refund.',$request)===$refund,'Refund retry is not idempotent.');
            abt_reject(fn()=>finance_record_refund($pdo,$admin['id'],$invoice,31,'ABREF'.strtoupper(bin2hex(random_bytes(12))),'Synthetic over-refund must be rejected.'),PaymentConflict::class);
            finance_record_refund($pdo,$admin['id'],$invoice,30,'ABREF'.strtoupper(bin2hex(random_bytes(12))),'Synthetic remaining refund fixture; no actual funds.');
            abt_check(abt_status($pdo,$invoice)==='refunded','Full refund state missing.');
            abt_check((int)abt_scalar($pdo,"SELECT COUNT(*) FROM market_invoice_documents WHERE invoice_id=? AND document_type='credit_note'",[$invoice])===2,'Refund generated duplicate credit documents.');
            abt_check((int)abt_scalar($pdo,"SELECT SUM(amount_cents) FROM market_finance_events WHERE invoice_id=? AND kind IN ('cash_in','cash_refund')",[$invoice])===0,'Refund cash balance is not zero.');
            abt_check((int)abt_scalar($pdo,'SELECT gross_cents FROM market_auction_fee_charges WHERE invoice_id=?',[$invoice])===50,'Refund rewrote original assessment.');
        },
        'http_publication_uses_actual_form_quote_and_csrf_then_preserves_ownership'=>static function() use($pdo,$category): void {
            $seller=abt_user($pdo); $other=abt_user($pdo); $browser=abt_browser(); $foreign=abt_browser();
            try {
                [$status]=abt_request($browser,'/user/create-listing.php'); abt_check($status===303,'Anonymous publication was not protected.');
                abt_login($browser,$seller); [$status,$body]=abt_request($browser,'/user/create-listing.php');
                abt_check($status===200,'Publication form unavailable.'); $accepted=abt_form_accept($body); $csrf=abt_csrf($body);
                $input=abt_input($category);
                [$status]=abt_request($browser,'/user/create-listing.php',array_replace($input,$accepted));
                abt_check($status===403,'Publication without CSRF was accepted.'); abt_no_publication($pdo,$seller['id']);
                [$status]=abt_request($browser,'/user/create-listing.php',$input+['csrf_token'=>$csrf]);
                abt_check($status===422,'Publication without quote consent was accepted.'); abt_no_publication($pdo,$seller['id']);
                [$status]=abt_request($browser,'/user/create-listing.php',array_replace($input,$accepted,['csrf_token'=>$csrf]));
                abt_check($status===303,'Accepted real form did not publish.');
                $id=(int)abt_scalar($pdo,'SELECT id FROM market_listings WHERE seller_id=?',[$seller['id']]); abt_charge_count($pdo,$id,1);
                abt_login($foreign,$other); [$status]=abt_request($foreign,'/user/edit-listing.php?id='.$id);
                abt_check($status===404,'Foreign user can inspect the edit form.');
                [$status,$foreignBody]=abt_request($foreign,'/user/create-listing.php'); abt_check($status===200,'Foreign own form unavailable.');
                [$status]=abt_request($foreign,'/user/edit-listing.php?id='.$id,array_replace(abt_input($category),abt_form_accept($foreignBody),['csrf_token'=>abt_csrf($foreignBody)]));
                abt_check($status===404,'Foreign user changed the seller contract.');
                abt_check((int)abt_scalar($pdo,'SELECT seller_id FROM market_auction_fee_contracts WHERE listing_id=?',[$id])===$seller['id'],'Contract owner changed.');
                abt_charge_count($pdo,$id,1);
            } finally { curl_close($browser); curl_close($foreign); }
        },
        'http_payment_claim_never_pays_and_fee_cancellation_is_server_side_blocked'=>static function() use($pdo,$category): void {
            $seller=abt_user($pdo); $other=abt_user($pdo); $fixture=abt_publish($pdo,$seller['id'],$category); $invoice=$fixture['invoice'];
            $browser=abt_browser(); $foreign=abt_browser();
            try {
                abt_login($browser,$seller); [$status,$body]=abt_request($browser,'/user/billing.php?order='.$invoice);
                abt_check($status===200,'Owner cannot view fee payment order.');
                $reference=(string)abt_scalar($pdo,'SELECT invoice_number FROM market_invoices WHERE id=?',[$invoice]);
                abt_check(str_contains($body,$reference)&&str_contains($body,'auction-merchant@example.invalid'),'Payment order hides reference or recipient.');
                [$status]=abt_request($browser,'/user/billing.php?order='.$invoice,['action'=>'claim','invoice_id'=>(string)$invoice]);
                abt_check($status===403&&abt_status($pdo,$invoice)==='issued','Claim without CSRF mutated order.');
                [$status]=abt_request($browser,'/user/billing.php?order='.$invoice,['csrf_token'=>abt_csrf($body),'action'=>'claim','invoice_id'=>(string)$invoice]);
                abt_check($status===200&&abt_status($pdo,$invoice)==='pending','Claim did not remain pending manual verification.');
                abt_check((int)abt_scalar($pdo,'SELECT COUNT(*) FROM market_payment_attempts WHERE invoice_id=?',[$invoice])===0,'Claim invented a payment attempt.');
                [$status,$body]=abt_request($browser,'/user/invoices.php'); abt_check($status===200,'Invoice account unavailable.');
                [$status]=abt_request($browser,'/user/invoices.php',['csrf_token'=>abt_csrf($body),'action'=>'cancel','invoice_id'=>(string)$invoice]);
                abt_check($status===422&&abt_status($pdo,$invoice)==='pending','HTTP self-cancellation erased receivable.');
                abt_login($foreign,$other); [$status]=abt_request($foreign,'/user/billing.php?order='.$invoice);
                abt_check($status===404,'Foreign user can read fee payment order.');
                [$status]=abt_request($foreign,'/admin/finance.php'); abt_check($status===403,'Customer gained financial administration.');
                abt_charge_count($pdo,$fixture['id'],1);
            } finally { curl_close($browser); curl_close($foreign); }
        },
        'http_waiver_requires_admin_mfa_csrf_and_records_one_audited_reversal'=>static function() use($pdo,$category,$admin): void {
            $seller=abt_user($pdo); $fixture=abt_publish($pdo,$seller['id'],$category); $invoice=$fixture['invoice']; $browser=abt_browser();
            try {
                abt_login($browser,$admin); [$status]=abt_request($browser,'/admin/finance.php');
                abt_check(in_array($status,[303,403],true),'Admin bypassed MFA challenge.');
                abt_mfa($pdo,$browser,$admin['id']); [$status,$body]=abt_request($browser,'/admin/finance.php');
                abt_check($status===200,'MFA financial administration unavailable.');
                $fields=['action'=>'auction_waive','invoice_id'=>(string)$invoice,'reason'=>'Synthetic reviewed HTTP fee waiver fixture.'];
                [$status]=abt_request($browser,'/admin/finance.php',$fields);
                abt_check($status===403&&abt_status($pdo,$invoice)==='issued','Waiver without CSRF mutated invoice.');
                [$status]=abt_request($browser,'/admin/finance.php',$fields+['csrf_token'=>abt_csrf($body)]);
                abt_check($status===200&&abt_status($pdo,$invoice)==='void','MFA-authorized waiver failed.');
                abt_check((int)abt_scalar($pdo,"SELECT COUNT(*) FROM market_finance_events WHERE invoice_id=? AND actor_id=? AND kind='fee_waived'",[$invoice,$admin['id']])===1,'HTTP waiver lacks unique actor-bound reversal.');
            } finally { curl_close($browser); }
        },
    ];
    foreach ($tests as $name=>$run) { $run(); ++$passed; fwrite(STDOUT,'PASS '.$name.PHP_EOL); }
    fwrite(STDOUT,'Auction billing integration checks passed: '.$passed.PHP_EOL);
} catch (Throwable $error) { $failure=$error; }
finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // Restore original setting values AND timestamps, including originally absent keys.
    foreach ($saved as $key=>$row) {
        if ($row===null) $pdo->prepare('DELETE FROM system_settings WHERE setting_key=?')->execute([$key]);
        else $pdo->prepare('INSERT INTO system_settings(setting_key,setting_value,updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=VALUES(updated_at)')->execute([$key,$row['setting_value'],$row['updated_at']]);
    }
    site_settings($pdo,true);
    if ($savedTax===null) $pdo->exec("DELETE FROM market_tax_rules WHERE country_code='DE'");
    else $pdo->prepare('INSERT INTO market_tax_rules(country_code,basis_points,explanation,reviewed_by,reviewed_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE basis_points=VALUES(basis_points),explanation=VALUES(explanation),reviewed_by=VALUES(reviewed_by),reviewed_at=VALUES(reviewed_at)')
        ->execute([$savedTax['country_code'],$savedTax['basis_points'],$savedTax['explanation'],$savedTax['reviewed_by'],$savedTax['reviewed_at']]);
    $pdo->prepare("UPDATE market_countries SET is_active=?,updated_at=? WHERE code='DE'")->execute([$savedCountry['is_active'],$savedCountry['updated_at']]);
    if ($hadLocale) $_SESSION['locale']=$savedLocale; else unset($_SESSION['locale']);
}
if ($failure!==null) {
    fwrite(STDERR,'FAIL '.($name??'initialization').' ('.get_class($failure).' at '.basename($failure->getFile()).':'.$failure->getLine().'): '.$failure->getMessage().PHP_EOL);
    exit(1);
}
