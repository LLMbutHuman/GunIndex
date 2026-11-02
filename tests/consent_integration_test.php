<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('DB_DATABASE')!=='gunindex_rebuild_test'){if(PHP_SAPI!=='cli')http_response_code(404);exit(2);}
require_once __DIR__.'/../config/bootstrap.php';
if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='gunindex_rebuild_test')exit(2);
$passed=0;
function contract_check(bool $ok):void{if(!$ok)throw new RuntimeException('Contract integration assertion failed.');}
function contract_test(string $name,callable $run):void{global $passed;$run();$passed++;echo 'PASS '.$name."\n";}
function contract_reject(callable $run):void{try{$run();}catch(InvalidArgumentException|DomainException){return;}throw new RuntimeException('Contract guard bypassed.');}
function contract_setting(PDO $pdo,string $key,string $value):void{$pdo->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,$value]);site_settings($pdo,true);}
function contract_request(CurlHandle $browser,string $path,?array $fields=null):array{
    contract_check(str_starts_with($path,'/')&&!str_starts_with($path,'//'));
    curl_setopt($browser,CURLOPT_URL,'http://127.0.0.1:8081'.$path);
    if($fields===null)curl_setopt($browser,CURLOPT_HTTPGET,true);else{curl_setopt($browser,CURLOPT_POST,true);curl_setopt($browser,CURLOPT_POSTFIELDS,http_build_query($fields));}
    $body=curl_exec($browser);contract_check(is_string($body));return ['status'=>(int)curl_getinfo($browser,CURLINFO_RESPONSE_CODE),'body'=>$body];
}
function contract_csrf(array $response):string{contract_check(preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/',$response['body'],$m)===1);return $m[1];}
function contract_input(array $documents):array{$out=['legal_confirm'=>'1','legal_language'=>'de','legal_documents'=>[]];foreach($documents as $doc)$out['legal_documents'][$doc['id']]=$doc['content_hash'];return $out;}
$settings=['legal_publication_enabled'=>'1','legal_registration_documents'=>'','legal_checkout_documents'=>'','registration_enabled'=>'1','manual_paypal_enabled'=>'1','manual_paypal_approved'=>'1','manual_tax_reviewed'=>'1','manual_tax_bps'=>'1900','manual_paypal_recipient'=>'synthetic-merchant@example.invalid','finance_enabled'=>'0'];
$before=[];$q=$pdo->prepare('SELECT setting_value,updated_at FROM system_settings WHERE setting_key=?');foreach($settings as $key=>$_){$q->execute([$key]);$before[$key]=$q->fetch()?:null;}
$browser=curl_init();curl_setopt_array($browser,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false]);
$error=null;
try{
    foreach($settings as $key=>$value)contract_setting($pdo,$key,$value);
    $suffix=bin2hex(random_bytes(8));$code='contract-'.$suffix;
    $pdo->prepare('INSERT INTO users(username,email,password_hash,is_admin) VALUES (?,?,?,1)')->execute(['contract_admin_'.$suffix,'contract_admin_'.$suffix.'@example.invalid','disabled-synthetic-password']);$admin=(int)$pdo->lastInsertId();
    $body1=str_repeat('Synthetic version one: no actual legal or provider approval. ',8);
    $body2=str_repeat('Synthetic version two: no actual legal or provider approval. ',8);
    $first=legal_create($pdo,$admin,$code,'1.0','de','Synthetic contract fixture',$body1);
    legal_approve($pdo,$admin,$first,'Synthetic fixture reviewer; no legal approval',gmdate('Y-m-d'),array_fill(0,5,'1'));
    foreach(['legal_registration_documents','legal_checkout_documents'] as $key)contract_setting($pdo,$key,$code);
    $name='contract_user_'.$suffix;$password='Synthetic contract '.bin2hex(random_bytes(16));
    $registration=['username'=>$name,'email'=>$name.'@example.invalid','password'=>$password,'password_confirm'=>$password,'zip'=>'10115','city'=>'Synthetic city','country_code'=>'DE','preferred_locale'=>'de'];
    $form=contract_request($browser,'/auth/register.php');contract_check($form['status']===200&&str_contains($form['body'],'version_id='.$first));$registration['csrf_token']=contract_csrf($form);
    $input=contract_input(consent_documents($pdo,'registration','de'));
    $absent=static function()use($pdo,$name):void{$q=$pdo->prepare('SELECT COUNT(*) FROM users WHERE username=?');$q->execute([$name]);contract_check((int)$q->fetchColumn()===0);};
    contract_test('registration_missing_consent_rolls_back_account',static function()use($browser,$registration,$absent):void{contract_check(contract_request($browser,'/auth/register.php',$registration)['status']===422);$absent();});
    contract_test('registration_unchecked_version_rolls_back_account',static function()use($browser,$registration,$input,$absent):void{$input['legal_confirm']='0';contract_check(contract_request($browser,'/auth/register.php',$registration+$input)['status']===422);$absent();});
    $second=legal_create($pdo,$admin,$code,'2.0','de','Synthetic contract fixture two',$body2);
    legal_approve($pdo,$admin,$second,'Synthetic fixture reviewer; no legal approval',gmdate('Y-m-d'),array_fill(0,5,'1'));
    contract_test('registration_stale_version_is_not_silently_replaced',static function()use($browser,$registration,$input,$absent):void{contract_check(contract_request($browser,'/auth/register.php',$registration+$input)['status']===422);$absent();});
    contract_test('exact_old_version_remains_downloadable',static function()use($browser,$code,$first,$body1,$second,$body2):void{$old=contract_request($browser,'/marketplace/legal.php?document='.$code.'&language=de&version_id='.$first.'&download=1');contract_check($old['status']===200&&$old['body']===$body1);$latest=contract_request($browser,'/marketplace/legal.php?document='.$code.'&language=de&download=1');contract_check($latest['status']===200&&$latest['body']===$body2);$wrong=contract_request($browser,'/marketplace/legal.php?document=wrong-code&language=de&version_id='.$second);contract_check($wrong['status']===404);});
    $input=contract_input(consent_documents($pdo,'registration','de'));
    contract_test('tampering_blocks_form_acceptance_and_download',static function()use($pdo,$second,$body2,$browser,$registration,$input,$code,$absent):void{
        $pdo->prepare('UPDATE market_legal_documents SET body_text=? WHERE id=?')->execute([$body2.'tampered',$second]);
        try{contract_check(contract_request($browser,'/auth/register.php')['status']===503);contract_check(contract_request($browser,'/auth/register.php',$registration+$input)['status']===503);$absent();contract_check(contract_request($browser,'/marketplace/legal.php?document='.$code.'&language=de&version_id='.$second.'&download=1')['status']===503);}
        finally{$pdo->prepare('UPDATE market_legal_documents SET body_text=? WHERE id=?')->execute([$body2,$second]);}
    });
    $userId=0;
    contract_test('registration_and_exact_receipt_commit_together',static function()use($pdo,$browser,$registration,$input,$name,$second,$body2,&$userId):void{
        contract_check(contract_request($browser,'/auth/register.php',$registration+$input)['status']===303);
        $q=$pdo->prepare('SELECT id FROM users WHERE username=?');$q->execute([$name]);$userId=(int)$q->fetchColumn();contract_check($userId>0);
        $q=$pdo->prepare("SELECT document_id,content_hash FROM market_contract_receipts WHERE user_id=? AND scope='registration' AND context_key='account' AND invoice_id IS NULL");$q->execute([$userId]);$rows=$q->fetchAll();contract_check(count($rows)===1&&(int)$rows[0]['document_id']===$second&&$rows[0]['content_hash']===hash('sha256',$body2));
    });
    contract_test('missing_language_and_disabled_publication_fail_closed',static function()use($pdo):void{contract_reject(fn()=>consent_documents($pdo,'checkout','en'));contract_setting($pdo,'legal_publication_enabled','0');try{contract_reject(fn()=>consent_documents($pdo,'checkout','de'));}finally{contract_setting($pdo,'legal_publication_enabled','1');}});
    $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')->execute(['Synthetic contract category',$code]);$category=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code) VALUES (?,?,'Synthetic contract item','Synthetic ordinary item for isolated contract checks.','fixed',1200,'active','10115','Synthetic city','DE')")->execute([$userId,$category]);$listing=(int)$pdo->lastInsertId();
    contract_test('checkout_missing_consent_creates_no_order',static function()use($pdo,$userId,$listing):void{contract_reject(fn()=>manual_order($pdo,$userId,$listing,'bump_single',bin2hex(random_bytes(32))));$q=$pdo->prepare('SELECT COUNT(*) FROM market_invoices WHERE user_id=?');$q->execute([$userId]);contract_check((int)$q->fetchColumn()===0);});
    $order=0;$key=bin2hex(random_bytes(32));
    contract_test('each_order_has_its_own_exact_document_receipt',static function()use($pdo,$userId,$listing,$input,$second,$key,&$order):void{$order=manual_order($pdo,$userId,$listing,'bump_single',$key,null,$input);$other=manual_order($pdo,$userId,$listing,'bump_single',bin2hex(random_bytes(32)),null,$input);contract_check($order!==$other);$q=$pdo->prepare("SELECT invoice_id,document_id,content_hash FROM market_contract_receipts WHERE user_id=? AND scope='checkout' ORDER BY invoice_id");$q->execute([$userId]);$receipts=$q->fetchAll();contract_check(count($receipts)===2);foreach($receipts as $receipt)contract_check(in_array((int)$receipt['invoice_id'],[$order,$other],true)&&(int)$receipt['document_id']===$second&&$receipt['content_hash']===$input['legal_documents'][$second]);});
    contract_test('idempotent_order_retry_preserves_original_receipts',static function()use($pdo,$userId,$listing,$key,$order):void{contract_check(manual_order($pdo,$userId,$listing,'bump_single',$key)===$order);$q=$pdo->prepare('SELECT COUNT(*) FROM market_contract_receipts WHERE invoice_id=?');$q->execute([$order]);contract_check((int)$q->fetchColumn()===1);});
    contract_test('unseen_current_document_cannot_be_accepted_by_old_form',static function()use($pdo,$browser,$code,$first,$body1,$userId):void{$form=contract_request($browser,'/marketplace/legal.php?document='.$code.'&language=de');$response=contract_request($browser,'/marketplace/legal.php?document='.$code.'&language=de',['csrf_token'=>contract_csrf($form),'accept_document'=>'1','document_id'=>(string)$first,'document_hash'=>hash('sha256',$body1)]);contract_check($response['status']===422);$q=$pdo->prepare("SELECT COUNT(*) FROM market_legal_acceptances WHERE user_id=? AND acceptance_type='updated_terms'");$q->execute([$userId]);contract_check((int)$q->fetchColumn()===0);});
    echo 'Contract integration checks passed: '.$passed."\n";
}catch(Throwable $e){$error=$e;}
finally{
    if($pdo->inTransaction())$pdo->rollBack();
    foreach($before as $key=>$row){if($row===null)$pdo->prepare('DELETE FROM system_settings WHERE setting_key=?')->execute([$key]);else $pdo->prepare('UPDATE system_settings SET setting_value=?,updated_at=? WHERE setting_key=?')->execute([$row['setting_value'],$row['updated_at'],$key]);}
    site_settings($pdo,true);curl_close($browser);
}
if($error){fwrite(STDERR,'FAIL contract integration '.get_class($error).' at '.basename($error->getFile()).':'.$error->getLine()."\n");exit(1);}
