<?php
declare(strict_types=1);
/** Synthetic integration/PDF/HTTP regression; never runs against a production DB. */
if(PHP_SAPI!=='cli'||getenv('DB_DATABASE')!=='gunindex_rebuild_test'){if(PHP_SAPI!=='cli')http_response_code(404);exit(2);}
require_once __DIR__.'/../config/bootstrap.php';
if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='gunindex_rebuild_test')exit(2);

function fin_assert(bool $condition):void{if(!$condition)throw new RuntimeException('Finance assertion failed.');}
function fin_reject(callable $operation,string $class=Throwable::class):void{try{$operation();}catch(Throwable $e){if($e instanceof $class)return;throw$e;}throw new RuntimeException('Expected financial rejection.');}
function fin_setting(PDO $pdo,string $key,string $value):void{$pdo->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,$value]);site_settings($pdo,true);}
function fin_user(PDO $pdo,bool $admin=false):array{
 $name='finance_test_'.bin2hex(random_bytes(8));$password='Synthetic finance '.bin2hex(random_bytes(16));
 $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,is_admin) VALUES (?,?,?,'Synthetic finance fixture','10115','Testort',?)")->execute([$name,$name.'@example.invalid',auth_hash($password),(int)$admin]);
 return ['id'=>(int)$pdo->lastInsertId(),'email'=>$name.'@example.invalid','password'=>$password];
}
function fin_profile(PDO $pdo,int $user,string $country='DE',string $name='Synthetic invoice recipient'):void{
 finance_save_profile($pdo,$user,['legal_name'=>$name,'address_line1'=>'Teststraße 1','address_line2'=>'Only synthetic fixtures','postal_code'=>$country==='AT'?'1010':'10115','city'=>'Testort','country_code'=>$country,'vat_id'=>'SYNTHETIC-NOT-A-VALID-VAT-ID']);
}
function fin_order(PDO $pdo,array $user,int $category,bool $profile=true):int{
 if($profile)fin_profile($pdo,(int)$user['id']);
 $listing=market_save_listing($pdo,(int)$user['id'],['title'=>'Finance fixture '.bin2hex(random_bytes(8)),'description'=>'Ordinary synthetic product for isolated invoice regression tests.','category_id'=>(string)$category,'type'=>'fixed','price'=>'45.00','status'=>'active','zip'=>'10115','city'=>'Testort','country_code'=>'DE','condition_label'=>'used','pickup'=>'1','shipping'=>'1','duration_days'=>'7','accept_rules'=>'1']);
 return manual_order($pdo,(int)$user['id'],$listing,'bump_single',bin2hex(random_bytes(32)));
}
function fin_pay(PDO $pdo,int $admin,int $invoice,?string $transaction=null):string{
 $q=$pdo->prepare('SELECT o.reference,i.gross_cents FROM market_manual_orders o JOIN market_invoices i ON i.id=o.invoice_id WHERE o.invoice_id=?');$q->execute([$invoice]);$order=$q->fetch();fin_assert((bool)$order);
 $transaction??='FINPAY'.strtoupper(bin2hex(random_bytes(12)));manual_review($pdo,$admin,$invoice,$transaction,$order['reference'],(int)$order['gross_cents'],'approve','Synthetic direct account verification, not an actual payment.');return$transaction;
}
/** Only synthetic adapters below; no network transport or provider credentials are used. */
function fin_provider_mode(string $mode,callable $run):void{
 $values=['PAYPAL_ENABLED'=>'1','PAYPAL_MODE'=>$mode,'PAYPAL_ACCOUNT_TYPE'=>'business','PAYPAL_BUSINESS_SCOPE'=>'general_only','PLATFORM_BILLING_APPROVED'=>'1','PAYPAL_LIVE_APPROVED'=>'1','PAYPAL_CLIENT_ID'=>'synthetic-client','PAYPAL_CLIENT_SECRET'=>'synthetic-secret','PAYPAL_MERCHANT_ID'=>'SYNTHETICMERCH','PAYPAL_WEBHOOK_ID'=>'SYNTHETICHOOK','APP_URL'=>'https://synthetic.example.invalid'];$before=[];
 foreach($values as $key=>$value){$before[$key]=getenv($key);putenv($key.'='.$value);}try{$run();}finally{foreach($before as $key=>$value)putenv($value===false?$key:$key.'='.$value);}
}
function fin_auto_order(PDO $pdo,array $user,string $mode):array{
 fin_profile($pdo,$user['id']);$pdo->beginTransaction();
 try{$quote=fees_quote('bump_single',null,$pdo);$ref='AUTO-'.strtoupper(bin2hex(random_bytes(10)));
  $pdo->prepare("INSERT INTO market_invoices(user_id,invoice_number,service_code,fee_version,gross_cents,status) VALUES (?,?,'bump_single',?,?,'issued')")->execute([$user['id'],$ref,$quote['version'],$quote['gross_cents']]);$id=(int)$pdo->lastInsertId();
  finance_prepare_order($pdo,$id,$user['id']);$order='ORDER'.strtoupper(bin2hex(random_bytes(10)));$capture='CAPTURE'.strtoupper(bin2hex(random_bytes(10)));
  $pdo->prepare("INSERT INTO market_payment_attempts(invoice_id,provider,status,provider_order_id,idempotency_key,mode) VALUES (?,'paypal','pending',?,?,?)")->execute([$id,$order,bin2hex(random_bytes(16)),$mode]);
  $pdo->commit();return ['invoice'=>payments_invoice($pdo,$id,$user['id']),'order'=>$order,'capture'=>$capture];
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
function fin_auto_client(array $fixture,array &$calls,bool $signature=true,bool $initiallyCompleted=true):MarketPayPal{
 $completed=$initiallyCompleted;
 return new MarketPayPal(payments_config(),static function($method,$url,$headers,$body)use($fixture,&$calls,$signature,&$completed):array{
  $path=parse_url($url,PHP_URL_PATH);$calls[]=$path;
  if($path==='/v1/oauth2/token')return ['status'=>200,'body'=>['access_token'=>'synthetic-token']];
  if($path==='/v1/notifications/verify-webhook-signature')return ['status'=>200,'body'=>['verification_status'=>$signature?'SUCCESS':'FAILURE']];
  if($path==='/v2/checkout/orders/'.$fixture['order'].'/capture'){$completed=true;return ['status'=>201,'body'=>['status'=>'COMPLETED']];}
  if($path!=='/v2/checkout/orders/'.$fixture['order']||$method!=='GET')throw new RuntimeException('Unexpected synthetic provider operation.');
  $i=$fixture['invoice'];$amount=['currency_code'=>'EUR','value'=>payments_decimal((int)$i['gross_cents'])];
  $unit=['reference_id'=>'invoice:'.$i['id'],'custom_id'=>(string)$i['id'],'invoice_id'=>$i['invoice_number'],'payee'=>['merchant_id'=>'SYNTHETICMERCH'],'amount'=>$amount];
  if($completed)$unit['payments']=['captures'=>[['id'=>$fixture['capture'],'status'=>'COMPLETED','final_capture'=>true,'amount'=>$amount]]];
  return ['status'=>200,'body'=>['id'=>$fixture['order'],'intent'=>'CAPTURE','status'=>$completed?'COMPLETED':'APPROVED','purchase_units'=>[$unit]]];
 });
}
function fin_auto_event(array $fixture):array{return ['id'=>'EVENT-'.strtoupper(bin2hex(random_bytes(10))),'event_type'=>'PAYMENT.CAPTURE.COMPLETED','resource'=>['id'=>$fixture['capture'],'amount'=>['currency_code'=>'EUR','value'=>payments_decimal((int)$fixture['invoice']['gross_cents'])],'supplementary_data'=>['related_ids'=>['order_id'=>$fixture['order']]]]];}
function fin_auto_headers():array{return ['paypal-auth-algo'=>'SHA256withRSA','paypal-cert-url'=>'https://api.paypal.com/v1/notifications/certs/SYNTHETIC','paypal-transmission-id'=>'SYNTHETIC','paypal-transmission-sig'=>'SYNTHETIC','paypal-transmission-time'=>gmdate('c')];}
function fin_status(PDO $pdo,int $invoice):string{$q=$pdo->prepare('SELECT status FROM market_invoices WHERE id=?');$q->execute([$invoice]);return(string)$q->fetchColumn();}
function fin_document_id(PDO $pdo,int $invoice):int{$q=$pdo->prepare('SELECT id FROM market_invoice_documents WHERE origin_invoice_id=?');$q->execute([$invoice]);return(int)$q->fetchColumn();}
function fin_browser():CurlHandle{$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>3]);return$c;}
function fin_request(CurlHandle $c,string $path,?array $fields=null):array{
 curl_setopt($c,CURLOPT_URL,'http://127.0.0.1:8081'.$path);if($fields===null)curl_setopt($c,CURLOPT_HTTPGET,true);else{curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($fields));}
 $body=curl_exec($c);fin_assert(is_string($body));return[(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE),$body,(string)curl_getinfo($c,CURLINFO_CONTENT_TYPE)];
}
function fin_csrf(string $body):string{fin_assert((bool)preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/',$body,$m));return$m[1];}
function fin_login(CurlHandle $browser,array $user):void{[$code,$body]=fin_request($browser,'/auth/login.php');fin_assert($code===200);[$code]=fin_request($browser,'/auth/login.php',['csrf_token'=>fin_csrf($body),'login'=>$user['email'],'password'=>$user['password']]);fin_assert($code===303);}

if(($argv[1]??'')==='--automatic-race'){
 try{
  $request=json_decode(stream_get_contents(STDIN,16001),true,12,JSON_THROW_ON_ERROR);
  fin_assert(is_array($request)&&in_array($request['channel']??null,['capture','webhook'],true)&&is_int($request['invoice_id']??null)&&is_int($request['user_id']??null)&&is_int($request['start']??null));
  fin_assert(is_string($request['capture']??null)&&preg_match('/\ACAPTURE[A-F0-9]{20}\z/',$request['capture'])===1);
  $q=$pdo->prepare("SELECT 1 FROM users WHERE id=? AND full_name='Synthetic finance fixture' AND email LIKE '%@example.invalid'");$q->execute([$request['user_id']]);fin_assert((bool)$q->fetchColumn());
  $invoice=payments_invoice($pdo,$request['invoice_id'],$request['user_id']);$q=$pdo->prepare("SELECT provider_order_id FROM market_payment_attempts WHERE invoice_id=? AND provider='paypal' AND mode='live' ORDER BY id DESC LIMIT 1");$q->execute([$invoice['id']]);$order=$q->fetchColumn();fin_assert(is_string($order)&&preg_match('/\AORDER[A-F0-9]{20}\z/',$order)===1);
  $fixture=['invoice'=>$invoice,'order'=>$order,'capture'=>$request['capture']];$delay=$request['start']-(int)(microtime(true)*1000);fin_assert($delay>=-5000&&$delay<=5000);if($delay>0)usleep($delay*1000);
  fin_provider_mode('live',static function()use($pdo,$request,$fixture):void{$calls=[];$client=fin_auto_client($fixture,$calls);if($request['channel']==='capture')payments_capture($pdo,$request['invoice_id'],$request['user_id'],$client);else fin_assert(payments_webhook($pdo,fin_auto_headers(),fin_auto_event($fixture),$client));});
  echo json_encode(['status'=>fin_status($pdo,$request['invoice_id']),'document'=>fin_document_id($pdo,$request['invoice_id'])],JSON_THROW_ON_ERROR);exit;
 }catch(Throwable $e){fwrite(STDERR,'Synthetic concurrent finance operation failed: '.get_class($e)."\n");exit(2);}
}
$configured=['manual_paypal_enabled'=>'1','manual_paypal_approved'=>'1','manual_paypal_recipient'=>'merchant@example.invalid','manual_tax_reviewed'=>'1','manual_tax_bps'=>'1900','finance_enabled'=>'1','operator_legal_name'=>'Synthetic fixture operator','operator_address_line1'=>'Teststraße 9','operator_address_line2'=>'No actual operator identity','operator_postcode'=>'10115','operator_city'=>'Testort','operator_country'=>'DE','operator_email'=>'operator@example.invalid','operator_vat_id'=>'SYNTHETIC-NOT-VALID','operator_tax_number'=>'','operator_registration'=>'Synthetic fixture only','invoice_prefix'=>'TEST','fee_bump_single_cents'=>'199'];
$saved=[];$q=$pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key=?');foreach($configured as $key=>$value){$q->execute([$key]);$saved[$key]=$q->fetchColumn();}
$taxes=$pdo->query("SELECT * FROM market_tax_rules WHERE country_code IN ('DE','AT')")->fetchAll();$country=$pdo->query("SELECT is_active FROM market_countries WHERE code='DE'")->fetchColumn();
$passed=0;$failed=false;
try{
 foreach($configured as $key=>$value)fin_setting($pdo,$key,$value);$pdo->exec("UPDATE market_countries SET is_active=1 WHERE code='DE'");
 $admin=fin_user($pdo,true);finance_save_tax($pdo,$admin['id'],'DE',1900,'Synthetic reviewed tax treatment for isolated tests only.');
 $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')->execute(['Finance test '.bin2hex(random_bytes(5)),'finance-test-'.bin2hex(random_bytes(8))]);$category=(int)$pdo->lastInsertId();
 $tests=[
  'concurrent_capture_and_webhook_create_exactly_one_financial_booking'=>static function()use($pdo):void{
   $user=fin_user($pdo);$fixture=fin_auto_order($pdo,$user,'live');$id=(int)$fixture['invoice']['id'];$start=(int)(microtime(true)*1000)+1500;$children=[];
   foreach(['capture','webhook'] as $channel){$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--automatic-race'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fin_assert(is_resource($process));fwrite($pipes[0],json_encode(['channel'=>$channel,'invoice_id'=>$id,'user_id'=>$user['id'],'start'=>$start,'capture'=>$fixture['capture']],JSON_THROW_ON_ERROR));fclose($pipes[0]);$children[]=[$process,$pipes];}
   $documents=[];foreach($children as [$process,$pipes]){$body=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);fin_assert(proc_close($process)===0&&$errors==='');$response=json_decode($body,true,8,JSON_THROW_ON_ERROR);fin_assert($response['status']==='paid'&&$response['document']>0);$documents[]=$response['document'];}
   fin_assert(count(array_unique($documents))===1);$q=$pdo->prepare("SELECT COUNT(*),SUM(amount_cents) FROM market_finance_events WHERE invoice_id=? AND kind='cash_in'");$q->execute([$id]);$row=$q->fetch(PDO::FETCH_NUM);fin_assert((int)$row[0]===1&&(int)$row[1]===199);
  },
  'automatic_capture_and_webhook_share_one_financial_booking'=>static function()use($pdo):void{fin_provider_mode('live',static function()use($pdo):void{
   $user=fin_user($pdo);$fixture=fin_auto_order($pdo,$user,'live');$id=(int)$fixture['invoice']['id'];$calls=[];$client=fin_auto_client($fixture,$calls,true,false);
   payments_capture($pdo,$id,$user['id'],$client);fin_assert(in_array('/v2/checkout/orders/'.$fixture['order'].'/capture',$calls,true));$document=fin_document_id($pdo,$id);fin_assert($document>0&&fin_status($pdo,$id)==='paid');
   $event=fin_auto_event($fixture);fin_assert(payments_webhook($pdo,fin_auto_headers(),$event,$client));fin_assert(payments_webhook($pdo,fin_auto_headers(),$event,$client));payments_capture($pdo,$id,$user['id'],$client);
   fin_assert(fin_document_id($pdo,$id)===$document);$q=$pdo->prepare("SELECT COUNT(*),SUM(amount_cents) FROM market_finance_events WHERE invoice_id=? AND kind='cash_in'");$q->execute([$id]);$row=$q->fetch(PDO::FETCH_NUM);fin_assert((int)$row[0]===1&&(int)$row[1]===199);
   $q=$pdo->prepare('SELECT COUNT(*) FROM market_payment_events e JOIN market_payment_attempts a ON a.id=e.attempt_id WHERE a.invoice_id=?');$q->execute([$id]);fin_assert((int)$q->fetchColumn()===2);
  });},
  'sandbox_confirmation_never_creates_real_invoice_or_cash_entry'=>static function()use($pdo):void{fin_provider_mode('sandbox',static function()use($pdo):void{
   $user=fin_user($pdo);$fixture=fin_auto_order($pdo,$user,'sandbox');$id=(int)$fixture['invoice']['id'];$calls=[];payments_capture($pdo,$id,$user['id'],fin_auto_client($fixture,$calls));fin_assert(fin_status($pdo,$id)==='paid'&&fin_document_id($pdo,$id)===0);$q=$pdo->prepare('SELECT COUNT(*) FROM market_finance_events WHERE invoice_id=?');$q->execute([$id]);fin_assert((int)$q->fetchColumn()===0);
  });},
  'invalid_signature_or_amount_cannot_create_payment_or_invoice'=>static function()use($pdo):void{fin_provider_mode('live',static function()use($pdo):void{
   $user=fin_user($pdo);$fixture=fin_auto_order($pdo,$user,'live');$id=(int)$fixture['invoice']['id'];$calls=[];$event=fin_auto_event($fixture);fin_assert(!payments_webhook($pdo,fin_auto_headers(),$event,fin_auto_client($fixture,$calls,false)));
   $event['resource']['amount']['value']='2.00';fin_reject(fn()=>payments_webhook($pdo,fin_auto_headers(),$event,fin_auto_client($fixture,$calls)),PaymentConflict::class);fin_assert(fin_status($pdo,$id)==='issued'&&fin_document_id($pdo,$id)===0);
  });},
  'snapshot_tampering_blocks_capture_before_any_provider_call'=>static function()use($pdo):void{fin_provider_mode('live',static function()use($pdo):void{
   $user=fin_user($pdo);$fixture=fin_auto_order($pdo,$user,'live');$id=(int)$fixture['invoice']['id'];$pdo->prepare('UPDATE market_invoice_snapshots SET payload_hash=? WHERE invoice_id=?')->execute([str_repeat('0',64),$id]);$calls=[];$client=fin_auto_client($fixture,$calls);fin_reject(fn()=>payments_capture($pdo,$id,$user['id'],$client),RuntimeException::class);fin_assert($calls===[]&&fin_status($pdo,$id)==='issued'&&fin_document_id($pdo,$id)===0);
   fin_reject(fn()=>payments_webhook($pdo,fin_auto_headers(),fin_auto_event($fixture),$client),RuntimeException::class);$q=$pdo->prepare('SELECT COUNT(*) FROM market_payment_events e JOIN market_payment_attempts a ON a.id=e.attempt_id WHERE a.invoice_id=?');$q->execute([$id]);fin_assert((int)$q->fetchColumn()===0&&fin_status($pdo,$id)==='issued');
  });},
  'changed_invoice_amount_cannot_override_original_contract'=>static function()use($pdo):void{fin_provider_mode('live',static function()use($pdo):void{
   $user=fin_user($pdo);$fixture=fin_auto_order($pdo,$user,'live');$id=(int)$fixture['invoice']['id'];$pdo->prepare('UPDATE market_invoices SET gross_cents=200 WHERE id=?')->execute([$id]);$calls=[];$client=fin_auto_client($fixture,$calls);fin_reject(fn()=>payments_capture($pdo,$id,$user['id'],$client),PaymentConflict::class);fin_assert($calls===[]&&fin_status($pdo,$id)==='issued');
  });},
  'disabled_finance_never_claims_an_issued_document'=>static function()use($pdo,$admin,$category):void{
   fin_setting($pdo,'finance_enabled','0');try{$user=fin_user($pdo);$invoice=fin_order($pdo,$user,$category,false);fin_pay($pdo,$admin['id'],$invoice);fin_assert(fin_document_id($pdo,$invoice)===0&&finance_issue_invoice($pdo,$invoice)===0);}finally{fin_setting($pdo,'finance_enabled','1');}
  },
  'billing_profile_is_required_and_rejected_order_rolls_back'=>static function()use($pdo,$category):void{
   $user=fin_user($pdo);fin_reject(static fn()=>fin_order($pdo,$user,$category,false),PaymentUnavailable::class);$q=$pdo->prepare('SELECT COUNT(*) FROM market_invoices WHERE user_id=?');$q->execute([$user['id']]);fin_assert((int)$q->fetchColumn()===0);
  },
  'operator_and_country_tax_rules_are_required'=>static function()use($pdo,$category):void{
   $user=fin_user($pdo);fin_setting($pdo,'operator_email','');try{fin_reject(static fn()=>fin_order($pdo,$user,$category),InvalidArgumentException::class);}finally{fin_setting($pdo,'operator_email','operator@example.invalid');}
   fin_profile($pdo,$user['id'],'AT');$pdo->exec("DELETE FROM market_tax_rules WHERE country_code='AT'");fin_reject(static fn()=>fin_order($pdo,$user,$category,false),PaymentUnavailable::class);
  },
  'billing_validation_rejects_control_characters_and_wrong_postcodes'=>static function()use($pdo):void{
   $user=fin_user($pdo);fin_reject(static fn()=>fin_profile($pdo,$user['id'],'DE',"Injected\r\nHeader"),InvalidArgumentException::class);
   fin_reject(static fn()=>finance_save_profile($pdo,$user['id'],['legal_name'=>'Synthetic','address_line1'=>'Only test','postal_code'=>'123','city'=>'Only test','country_code'=>'DE']),InvalidArgumentException::class);fin_assert(finance_billing_profile($pdo,$user['id'])===null);
  },
  'snapshots_use_reviewed_exact_tax_and_encrypted_personal_data'=>static function()use($pdo,$category):void{
   $user=fin_user($pdo);$invoice=fin_order($pdo,$user,$category);$snapshot=finance_snapshot($pdo,$invoice);fin_assert($snapshot['gross_cents']===199&&$snapshot['net_cents']===167&&$snapshot['tax_cents']===32&&$snapshot['tax_bps']===1900&&$snapshot['test_mode']===true);
   $q=$pdo->prepare('SELECT payload_ciphertext FROM market_invoice_snapshots WHERE invoice_id=?');$q->execute([$invoice]);$cipher=$q->fetchColumn();fin_assert(str_starts_with($cipher,'gx1:')&&!str_contains($cipher,$snapshot['buyer']['legal_name']));fin_reject(static fn()=>crypto_decrypt('chat',$cipher),RuntimeException::class);
  },
  'later_profile_price_operator_and_tax_changes_do_not_mutate_snapshot'=>static function()use($pdo,$admin,$category):void{
   $user=fin_user($pdo);$invoice=fin_order($pdo,$user,$category);$before=finance_snapshot($pdo,$invoice);fin_profile($pdo,$user['id'],'DE','Changed synthetic name');fin_setting($pdo,'operator_legal_name','Changed synthetic operator');fin_setting($pdo,'fee_bump_single_cents','250');finance_save_tax($pdo,$admin['id'],'DE',0,'Synthetic zero tax rule change, not tax advice.');
   try{fin_assert(finance_snapshot($pdo,$invoice)===$before);}finally{fin_setting($pdo,'operator_legal_name','Synthetic fixture operator');fin_setting($pdo,'fee_bump_single_cents','199');finance_save_tax($pdo,$admin['id'],'DE',1900,'Synthetic reviewed tax treatment for isolated tests only.');}
  },
  'paid_document_and_cash_entry_are_issued_once'=>static function()use($pdo,$admin,$category):void{
   $user=fin_user($pdo);$invoice=fin_order($pdo,$user,$category);$transaction=fin_pay($pdo,$admin['id'],$invoice);$id=fin_document_id($pdo,$invoice);fin_assert($id>0);fin_pay($pdo,$admin['id'],$invoice,$transaction);fin_assert(finance_issue_invoice($pdo,$invoice,$admin['id'])===$id);
   $q=$pdo->prepare('SELECT COUNT(*) FROM market_finance_events WHERE reference=?');$q->execute(['paid:'.$invoice]);fin_assert((int)$q->fetchColumn()===1);$doc=finance_document($pdo,$id,$user['id']);fin_assert(preg_match('/\ATEST-[0-9]{4}-[0-9]{6,}\z/',$doc['document_number'])===1&&$doc['payload']['gross_cents']===199);
  },
  'unpaid_cancellation_cannot_later_be_manually_approved'=>static function()use($pdo,$admin,$category):void{
   $user=fin_user($pdo);$invoice=fin_order($pdo,$user,$category);fin_reject(static fn()=>finance_issue_invoice($pdo,$invoice),PaymentConflict::class);finance_cancel($pdo,$invoice,$user['id']);fin_assert(fin_status($pdo,$invoice)==='void'&&fin_document_id($pdo,$invoice)===0);fin_reject(static fn()=>fin_pay($pdo,$admin['id'],$invoice),PaymentConflict::class);
  },
  'actual_pdf_is_locally_rendered_from_escaped_snapshot'=>static function()use($pdo,$admin,$category):void{
   $user=fin_user($pdo);fin_profile($pdo,$user['id'],'DE','<script>Synthetic invoice fixture</script>');$invoice=fin_order($pdo,$user,$category,false);fin_pay($pdo,$admin['id'],$invoice);$doc=finance_document($pdo,fin_document_id($pdo,$invoice),$user['id']);$pdf=finance_pdf($doc);fin_assert(str_starts_with($pdf,'%PDF-')&&str_contains(substr($pdf,-40),'%%EOF')&&strlen($pdf)>1000&&strlen($pdf)<2000000);
  },
  'document_owner_and_integrity_are_enforced'=>static function()use($pdo,$admin,$category):void{
   $user=fin_user($pdo);$foreign=fin_user($pdo);$invoice=fin_order($pdo,$user,$category);fin_pay($pdo,$admin['id'],$invoice);$id=fin_document_id($pdo,$invoice);fin_reject(static fn()=>finance_document($pdo,$id,$foreign['id']),PaymentConflict::class);fin_reject(static fn()=>finance_document($pdo,$id,$foreign['id'],true),PaymentConflict::class);
   $before=finance_document($pdo,$id,$user['id']);$pdo->prepare('UPDATE market_invoice_documents SET content_hash=? WHERE id=?')->execute([str_repeat('0',64),$id]);try{fin_reject(static fn()=>finance_document($pdo,$id,$user['id']),RuntimeException::class);}finally{$pdo->prepare('UPDATE market_invoice_documents SET content_hash=? WHERE id=?')->execute([$before['content_hash'],$id]);}
  },
  'refund_request_is_owner_bound_and_never_sends_or_records_money'=>static function()use($pdo,$admin,$category):void{
   $user=fin_user($pdo);$foreign=fin_user($pdo);$invoice=fin_order($pdo,$user,$category);fin_pay($pdo,$admin['id'],$invoice);fin_reject(static fn()=>finance_request_refund($pdo,$foreign['id'],$invoice,50,'Synthetic refund request'),PaymentConflict::class);$id=finance_request_refund($pdo,$user['id'],$invoice,50,'Synthetic refund request');fin_assert($id>0&&fin_status($pdo,$invoice)==='paid');fin_reject(static fn()=>finance_request_refund($pdo,$user['id'],$invoice,50,'Synthetic duplicate request'),PaymentConflict::class);$q=$pdo->prepare('SELECT COUNT(*) FROM market_refunds WHERE invoice_id=?');$q->execute([$invoice]);fin_assert((int)$q->fetchColumn()===0);
  },
  'partial_full_refunds_preserve_exact_tax_and_original_document'=>static function()use($pdo,$admin,$category):void{
   $user=fin_user($pdo);$invoice=fin_order($pdo,$user,$category);$pay=fin_pay($pdo,$admin['id'],$invoice);$original=finance_document($pdo,fin_document_id($pdo,$invoice),$user['id']);$a='FINREF'.strtoupper(bin2hex(random_bytes(12)));$first=finance_record_refund($pdo,$admin['id'],$invoice,1,$a,'Synthetic verified external refund');fin_assert(fin_status($pdo,$invoice)==='partially_refunded');fin_assert(finance_record_refund($pdo,$admin['id'],$invoice,1,$a,'Synthetic repeat record')===$first);
   fin_reject(static fn()=>finance_record_refund($pdo,$admin['id'],$invoice,1,$pay,'Original payment is not refund proof'),PaymentConflict::class);finance_record_refund($pdo,$admin['id'],$invoice,198,'FINREF'.strtoupper(bin2hex(random_bytes(12))),'Synthetic second verified external refund');fin_assert(fin_status($pdo,$invoice)==='refunded');
   $q=$pdo->prepare("SELECT id FROM market_invoice_documents WHERE invoice_id=? AND document_type='credit_note' ORDER BY id");$q->execute([$invoice]);$net=0;$tax=0;$gross=0;foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id){$doc=finance_document($pdo,(int)$id,$user['id']);$net+=$doc['payload']['net_cents'];$tax+=$doc['payload']['tax_cents'];$gross+=$doc['payload']['gross_cents'];fin_assert((int)$doc['related_document_id']===(int)$original['id']);}fin_assert($net===167&&$tax===32&&$gross===199&&finance_document($pdo,(int)$original['id'],$user['id'])['content_hash']===$original['content_hash']);
   $q=$pdo->prepare('SELECT SUM(amount_cents) FROM market_finance_events WHERE invoice_id=?');$q->execute([$invoice]);fin_assert((int)$q->fetchColumn()===0);$q=$pdo->prepare('SELECT remaining FROM market_bump_credits WHERE invoice_id=?');$q->execute([$invoice]);fin_assert((int)$q->fetchColumn()===0);
  },
  'unprivileged_refunds_overrefunds_and_paid_cancellation_are_rejected'=>static function()use($pdo,$admin,$category):void{
   $user=fin_user($pdo);$invoice=fin_order($pdo,$user,$category);fin_pay($pdo,$admin['id'],$invoice);fin_reject(static fn()=>finance_record_refund($pdo,$user['id'],$invoice,1,'FINREF'.strtoupper(bin2hex(random_bytes(12))),'Synthetic unauthorized refund'),DomainException::class);fin_reject(static fn()=>finance_record_refund($pdo,$admin['id'],$invoice,200,'FINREF'.strtoupper(bin2hex(random_bytes(12))),'Synthetic overpayment correction'),PaymentConflict::class);fin_reject(static fn()=>finance_cancel($pdo,$invoice,$user['id']),PaymentConflict::class);fin_assert(fin_status($pdo,$invoice)==='paid');
  },
  'http_download_requires_login_and_enforces_document_ownership'=>static function()use($pdo,$admin,$category):void{
   $user=fin_user($pdo);$other=fin_user($pdo);$invoice=fin_order($pdo,$user,$category);fin_pay($pdo,$admin['id'],$invoice);$path='/user/invoices.php?download='.fin_document_id($pdo,$invoice);$browser=fin_browser();$foreign=fin_browser();
   try{[$code]=fin_request($browser,$path);fin_assert($code===303);fin_login($browser,$user);[$code,$body,$type]=fin_request($browser,$path);fin_assert($code===200&&str_starts_with($body,'%PDF-')&&str_starts_with($type,'application/pdf'));fin_login($foreign,$other);[$code]=fin_request($foreign,$path);fin_assert($code===404);[$code]=fin_request($foreign,$path.'&staff=1');fin_assert($code===403);}finally{curl_close($browser);curl_close($foreign);}
  },
  'http_finance_mutations_require_csrf_and_owner'=>static function()use($pdo,$admin,$category):void{
   $user=fin_user($pdo);$other=fin_user($pdo);$invoice=fin_order($pdo,$user,$category);fin_pay($pdo,$admin['id'],$invoice);$browser=fin_browser();
   try{fin_login($browser,$other);[$code,$body]=fin_request($browser,'/user/invoices.php');fin_assert($code===200);[$code]=fin_request($browser,'/user/invoices.php',['action'=>'refund','invoice_id'=>(string)$invoice,'amount'=>'0.01','reason'=>'Synthetic foreign refund']);fin_assert($code===403);[$code]=fin_request($browser,'/user/invoices.php',['csrf_token'=>fin_csrf($body),'action'=>'refund','invoice_id'=>(string)$invoice,'amount'=>'0.01','reason'=>'Synthetic foreign refund']);fin_assert($code===422);[$code]=fin_request($browser,'/admin/finance.php');fin_assert($code===403);fin_assert(fin_status($pdo,$invoice)==='paid');}finally{curl_close($browser);}
  },
 ];
 foreach($tests as $name=>$run){$run();++$passed;fwrite(STDOUT,'PASS '.$name.PHP_EOL);}fwrite(STDOUT,'Finance checks passed: '.$passed.PHP_EOL);
}catch(Throwable $e){fwrite(STDERR,'FAIL '.($name??'initialization').' ('.get_class($e).' at '.basename($e->getFile()).':'.$e->getLine().')'.PHP_EOL);$failed=true;}
finally{
 if($pdo->inTransaction())$pdo->rollBack();$restore=$pdo->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');foreach($saved as $key=>$value){if($value===false)$pdo->prepare('DELETE FROM system_settings WHERE setting_key=?')->execute([$key]);else$restore->execute([$key,$value]);}site_settings($pdo,true);
 $pdo->exec("DELETE FROM market_tax_rules WHERE country_code IN ('DE','AT')");foreach($taxes as $row)$pdo->prepare('INSERT INTO market_tax_rules(country_code,basis_points,explanation,reviewed_by,reviewed_at) VALUES (?,?,?,?,?)')->execute([$row['country_code'],$row['basis_points'],$row['explanation'],$row['reviewed_by'],$row['reviewed_at']]);$pdo->prepare("UPDATE market_countries SET is_active=? WHERE code='DE'")->execute([$country]);
}
if($failed)exit(1);
