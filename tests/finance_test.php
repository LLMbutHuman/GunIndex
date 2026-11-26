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
 $quote=manual_checkout_quote($pdo,'bump_single');
 return manual_order($pdo,(int)$user['id'],$listing,'bump_single',bin2hex(random_bytes(32)),null,['accept_terms'=>'1','service_terms'=>['bump_single'=>$quote['approval_hash']]]);
}
function fin_pay(PDO $pdo,int $admin,int $invoice,?string $transaction=null):string{
 $q=$pdo->prepare('SELECT o.reference,i.gross_cents FROM market_manual_orders o JOIN market_invoices i ON i.id=o.invoice_id WHERE o.invoice_id=?');$q->execute([$invoice]);$order=$q->fetch();fin_assert((bool)$order);
 $transaction??='FINPAY'.strtoupper(bin2hex(random_bytes(12)));manual_review($pdo,$admin,$invoice,$transaction,$order['reference'],(int)$order['gross_cents'],'approve','Synthetic direct account verification, not an actual payment.');return$transaction;
}
/** Only synthetic adapters below; no network transport or provider credentials are used. */
function fin_provider_mode(string $mode,callable $run,array $overrides=[]):void{
 $values=array_replace(['PAYPAL_ENABLED'=>'1','PAYPAL_MODE'=>$mode,'PAYPAL_ACCOUNT_TYPE'=>'business','PAYPAL_BUSINESS_SCOPE'=>'general_only','PLATFORM_BILLING_APPROVED'=>'1','PAYPAL_LIVE_APPROVED'=>'1','PAYPAL_REFUNDS_ENABLED'=>'0','PAYPAL_REFUNDS_LIVE_APPROVED'=>'0','PAYPAL_CLIENT_ID'=>'synthetic-client','PAYPAL_CLIENT_SECRET'=>'synthetic-secret','PAYPAL_MERCHANT_ID'=>'SYNTHETICMERCH','PAYPAL_WEBHOOK_ID'=>'SYNTHETICHOOK','APP_URL'=>'https://synthetic.example.invalid'],$overrides);$before=[];
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
  return ['status'=>200,'body'=>fin_provider_order($fixture,$completed)];
 });
}
function fin_provider_order(array $fixture,bool $completed=true,string $captureStatus='COMPLETED'):array{
 $i=$fixture['invoice'];$amount=['currency_code'=>'EUR','value'=>payments_decimal((int)$i['gross_cents'])];
 $unit=['reference_id'=>'invoice:'.$i['id'],'custom_id'=>(string)$i['id'],'invoice_id'=>$i['invoice_number'],'payee'=>['merchant_id'=>'SYNTHETICMERCH'],'amount'=>$amount];
 if($completed)$unit['payments']=['captures'=>[['id'=>$fixture['capture'],'status'=>$captureStatus,'final_capture'=>true,'amount'=>$amount]]];
 return ['id'=>$fixture['order'],'intent'=>'CAPTURE','status'=>$completed?'COMPLETED':'APPROVED','purchase_units'=>[$unit]];
}
function fin_refund_fixture(PDO $pdo,array $admin,int $amount):array{
 $fixture=fin_auto_order($pdo,fin_user($pdo),payments_config()['mode']);$calls=[];
 $invoice=(int)$fixture['invoice']['id'];$owner=(int)$fixture['invoice']['user_id'];
 payments_capture($pdo,$invoice,$owner,fin_auto_client($fixture,$calls));
 $request=finance_request_refund($pdo,$owner,$invoice,$amount,'Synthetic requested PayPal API refund.');
 $reason='Synthetic approval for isolated refund tests.';
 $jobId=paypal_refund_queue($pdo,$admin['id'],$request,$amount,$reason);
 fin_assert(paypal_refund_queue($pdo,$admin['id'],$request,$amount,$reason)===$jobId);
 return [$fixture,paypal_refund_find($pdo,$jobId)];
}
function fin_refund_resource(array $job,string $status):array{
 $host=$job['mode']==='live'?'api.paypal.com':'api.sandbox.paypal.com';
 return ['id'=>'RF'.strtoupper(substr(hash('sha256',$job['reference']),0,22)),'status'=>$status,
  'amount'=>['currency_code'=>'EUR','value'=>payments_decimal((int)$job['amount_cents'])],'custom_id'=>$job['reference'],
  'links'=>[['method'=>'GET','rel'=>'up','href'=>'https://'.$host.'/v2/payments/captures/'.$job['capture_id']]]];
}
function fin_refund_transport(array $fixture,callable $refund,bool $signature=true): MarketPayPal {
 return new MarketPayPal(payments_config(),static function($method,$url,$headers,$body)use($fixture,$refund,$signature):array{
  $path=parse_url($url,PHP_URL_PATH);
  if($path==='/v1/oauth2/token')return ['status'=>200,'body'=>['access_token'=>'synthetic-token']];
  if($path==='/v1/notifications/verify-webhook-signature')return ['status'=>200,'body'=>['verification_status'=>$signature?'SUCCESS':'FAILURE']];
  if($path==='/v2/checkout/orders/'.$fixture['order']&&$method==='GET')return ['status'=>200,'body'=>fin_provider_order($fixture)];
  return $refund($method,$path,$headers,$body);
 });
}
function fin_auto_event(array $fixture):array{return ['id'=>'EVENT-'.strtoupper(bin2hex(random_bytes(10))),'event_type'=>'PAYMENT.CAPTURE.COMPLETED','resource'=>['id'=>$fixture['capture'],'amount'=>['currency_code'=>'EUR','value'=>payments_decimal((int)$fixture['invoice']['gross_cents'])],'supplementary_data'=>['related_ids'=>['order_id'=>$fixture['order']]]]];}
function fin_auto_headers():array{return ['paypal-auth-algo'=>'SHA256withRSA','paypal-cert-url'=>'https://api.paypal.com/v1/notifications/certs/SYNTHETIC','paypal-transmission-id'=>'SYNTHETIC','paypal-transmission-sig'=>'SYNTHETIC','paypal-transmission-time'=>gmdate('c')];}
function fin_status(PDO $pdo,int $invoice):string{$q=$pdo->prepare('SELECT status FROM market_invoices WHERE id=?');$q->execute([$invoice]);return(string)$q->fetchColumn();}
function fin_document_id(PDO $pdo,int $invoice):int{$q=$pdo->prepare('SELECT id FROM market_invoice_documents WHERE origin_invoice_id=?');$q->execute([$invoice]);return(int)$q->fetchColumn();}
function fin_browser():CurlHandle{$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>3]);return$c;}
function fin_request(CurlHandle $c,string $path,?array $fields=null,int $port=8081,bool $multipart=false):array{
 curl_setopt($c,CURLOPT_URL,'http://127.0.0.1:'.$port.$path);if($fields===null)curl_setopt($c,CURLOPT_HTTPGET,true);else{curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,$multipart?$fields:http_build_query($fields));}
 $body=curl_exec($c);fin_assert(is_string($body));return[(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE),$body,(string)curl_getinfo($c,CURLINFO_CONTENT_TYPE)];
}
function fin_csrf(string $body):string{fin_assert((bool)preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/',$body,$m));return$m[1];}
function fin_login(CurlHandle $browser,array $user,int $port=8081):void{[$code,$body]=fin_request($browser,'/auth/login.php',null,$port);fin_assert($code===200);[$code]=fin_request($browser,'/auth/login.php',['csrf_token'=>fin_csrf($body),'login'=>$user['email'],'password'=>$user['password']],$port);fin_assert($code===303);}

function fin_bank_mode(callable $run,array $overrides=[]):void{
 $config=array_replace(['BANK_TRANSFER_ENABLED'=>'1','BANK_TRANSFER_APPROVED'=>'1','BANK_TRANSFER_BENEFICIARY'=>'Synthetic test recipient','BANK_TRANSFER_IBAN'=>'DE89370400440532013000'],$overrides);
 $saved=[];foreach($config as $key=>$value){$saved[$key]=getenv($key);putenv($key.'='.$value);}
 try{$run();}finally{foreach($saved as $key=>$value)putenv($value===false?$key:$key.'='.$value);}
}
function fin_bank_entry(string $source,string $reference,int $amount,bool $reversal=false):string{
 $direction=$reversal?'DBIT':'CRDT';
 return '<Ntry><Amt Ccy="EUR">'.payments_decimal($amount).'</Amt><CdtDbtInd>'.$direction.'</CdtDbtInd>'
  .($reversal?'<RvslInd>true</RvslInd>':'')
  .'<Sts><Cd>BOOK</Cd></Sts><BookgDt><Dt>'.gmdate('Y-m-d').'</Dt></BookgDt><AcctSvcrRef>'.h($source).'</AcctSvcrRef>'
  .'<BkTxCd><Domn><Cd>PMNT</Cd><Fmly><Cd>RCDT</Cd><SubFmlyCd>ESCT</SubFmlyCd></Fmly></Domn></BkTxCd>'
  .'<NtryDtls><TxDtls><RmtInf><Ustrd>'.h($reference).'</Ustrd></RmtInf></TxDtls></NtryDtls></Ntry>';
}
function fin_bank_xml(string $entries,string $iban='DE89370400440532013000',string $tag='TEST'):string{
 return '<Document xmlns="'.BANK_STATEMENT_NAMESPACE.'"><BkToCstmrStmt><GrpHdr><MsgId>'.$tag.'</MsgId><CreDtTm>'.gmdate('c').'</CreDtTm>'
  .'</GrpHdr><Stmt><Id>'.$tag.'</Id><Acct><Id><IBAN>'.$iban.'</IBAN></Id><Ccy>EUR</Ccy></Acct>'.$entries.'</Stmt></BkToCstmrStmt></Document>';
}
function fin_bank_entry_id(PDO $pdo,string $source,string $direction='credit'):int{
 $q=$pdo->prepare('SELECT id FROM market_bank_entries WHERE source_reference=? AND direction=?');
 $q->execute([$source,$direction]);return(int)$q->fetchColumn();
}
function fin_bank_http_server(callable $run):void{
 $listener=stream_socket_server('tcp://127.0.0.1:0');
 fin_assert(is_resource($listener));
 $address=stream_socket_get_name($listener,false);
 fclose($listener);
 $port=(int)substr(strrchr($address,':'),1);
 $process=proc_open([PHP_BINARY,'-d','upload_max_filesize=5M','-d','post_max_size=8M','-S',$address,'-t','public','public/index.php'],
  [0=>['pipe','r'],1=>['file','/dev/null','a'],2=>['file','/dev/null','a']],$pipes,dirname(__DIR__));
 fin_assert(is_resource($process));
 fclose($pipes[0]);
 try{
  $connected=false;
  for($attempt=0;$attempt<50;$attempt++){
   $probe=@stream_socket_client('tcp://'.$address,$error,$message,0.1);
   if(is_resource($probe)){fclose($probe);$connected=true;break;}
   usleep(100000);
  }
  fin_assert($connected);
  $run($port);
 }finally{proc_terminate($process);proc_close($process);}
}
function fin_bitcoin_mode(string $chain,callable $run,array $overrides=[]):void{
 $config=array_replace(['BITCOIN_ENABLED'=>'1','BITCOIN_LIVE_APPROVED'=>'1','BITCOIN_NETWORK'=>$chain,
  'BITCOIN_RPC_URL'=>'http://127.0.0.1:18443','BITCOIN_RPC_USER'=>'fixture-test','BITCOIN_RPC_PASSWORD'=>'synthetic-node-test',
  'BITCOIN_WALLET'=>'fixture-wallet','BITCOIN_RATE_CENTS'=>'6100000','BITCOIN_RATE_UNTIL'=>gmdate('Y-m-d\TH:i:s\Z',time()+2400),
  'BITCOIN_CONFIRMATIONS'=>'2','APP_ENV'=>'development'],$overrides);
 $saved=[];foreach($config as $key=>$value){$saved[$key]=getenv($key);putenv($key.'='.$value);}
 try{$run();}finally{foreach($saved as $key=>$value)putenv($value===false?$key:$key.'='.$value);}
}
function fin_bitcoin_apply(PDO $pdo,int $invoice,array $receipts):void{
 $order=bitcoin_order($pdo,$invoice);
 $lease=bin2hex(random_bytes(16));
 $q=$pdo->prepare("UPDATE market_bitcoin_orders SET lease_token=?,status=IF(status='allocating','pending',status) WHERE id=?");
 $q->execute([$lease,$order['id']]);
 bitcoin_apply_receipts($pdo,$order,$receipts,$lease);
}
function fin_bitcoin_receipt(array $order,int $confirmations=2):array{
 $txid=hash('sha256','synthetic bitcoin tx '.$order['reference']);
 return ['txid'=>$txid,'vout'=>0,'satoshis'=>(int)$order['amount_sats'],'confirmations'=>$confirmations,
  'block_hash'=>$confirmations>0?hash('sha256','synthetic block '.$txid):null,'received_at'=>time()];
}

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
  'paypal_refund_live_gate_and_finance_permission_are_independent'=>static function()use($pdo,$admin):void{
   fin_provider_mode('live',static function()use($pdo,$admin):void{
    fin_reject(fn()=>paypal_refund_queue($pdo,$admin['id'],1,25,'Synthetic approval without live gate.'),PaymentUnavailable::class);
    fin_assert(!paypal_refund_next($pdo,payments_config(),new MarketPayPal(payments_config(),static function(){throw new RuntimeException('Disabled worker contacted provider.');})));
   },['PAYPAL_REFUNDS_ENABLED'=>'1']);
   fin_provider_mode('sandbox',static function()use($pdo):void{
    $user=fin_user($pdo);
    fin_reject(fn()=>paypal_refund_queue($pdo,$user['id'],1,25,'Synthetic approval without finance role.'),DomainException::class);
   },['PAYPAL_REFUNDS_ENABLED'=>'1']);
  },
  'paypal_pending_webhook_and_replays_post_one_verified_credit_document'=>static function()use($pdo,$admin):void{
   fin_provider_mode('live',static function()use($pdo,$admin):void{
    [$fixture,$job]=fin_refund_fixture($pdo,$admin,50);
    $invoice=(int)$job['invoice_id'];$request=(int)$job['request_id'];$id=(int)$job['id'];
    $original=fin_document_id($pdo,$invoice);$resource=fin_refund_resource($job,'PENDING');$posts=0;$gets=0;
    $client=fin_refund_transport($fixture,static function($method,$path,$headers,$body)use($pdo,$job,&$resource,&$posts,&$gets):array{
     if($method==='POST'){
      ++$posts;fin_assert($path==='/v2/payments/captures/'.$job['capture_id'].'/refund');
      fin_assert(in_array('PayPal-Request-Id: '.$job['reference'],$headers,true));
      fin_assert(json_decode($body,true,8,JSON_THROW_ON_ERROR)===['amount'=>['currency_code'=>'EUR','value'=>'0.50'],'custom_id'=>$job['reference']]);
      fin_assert(!paypal_refund_next($pdo,payments_config(),new MarketPayPal(payments_config(),static function(){throw new RuntimeException('Worker lease allowed double submission.');})));
     }else{++$gets;fin_assert($path==='/v2/payments/refunds/'.$resource['id']);}
     return ['status'=>200,'body'=>$resource];
    });
    fin_reject(fn()=>finance_record_refund($pdo,$admin['id'],$invoice,50,'EXTERNALREFUND123','Synthetic competing manual transaction.'),PaymentConflict::class);
    fin_assert(paypal_refund_next($pdo,payments_config(),$client)&&$posts===1);
    fin_assert(paypal_refund_find($pdo,$id)['status']==='pending'&&finance_refunded_cents($pdo,$invoice)===0&&fin_status($pdo,$invoice)==='paid');
    $resource['status']='COMPLETED';
    $event=['event_type'=>'PAYMENT.CAPTURE.REFUNDED','resource'=>$resource];
    $invalid=fin_refund_transport($fixture,static function(){throw new RuntimeException('Invalid webhook reached the refund endpoint.');},false);
    fin_assert(!payments_webhook($pdo,fin_auto_headers(),$event,$invalid)&&finance_refunded_cents($pdo,$invoice)===0);
    fin_assert(payments_webhook($pdo,fin_auto_headers(),$event,$client)&&payments_webhook($pdo,fin_auto_headers(),$event,$client));
    fin_assert($posts===1&&$gets===1&&paypal_refund_find($pdo,$id)['status']==='completed');
    fin_assert(fin_status($pdo,$invoice)==='partially_refunded'&&finance_refunded_cents($pdo,$invoice)===50&&fin_document_id($pdo,$invoice)===$original);
    $q=$pdo->prepare("SELECT credit_document_id FROM market_refunds WHERE request_id=? AND transaction_id=? AND amount_cents=50");
    $q->execute([$request,$resource['id']]);$credit=(int)$q->fetchColumn();
    $doc=finance_document($pdo,$credit,(int)$fixture['invoice']['user_id']);
    fin_assert($doc['document_type']==='credit_note'&&(int)$doc['related_document_id']===$original&&str_starts_with(finance_pdf($doc),'%PDF'));
    $q=$pdo->prepare("SELECT COUNT(*) FROM market_finance_events WHERE invoice_id=? AND kind='cash_refund'");
    $q->execute([$invoice]);fin_assert((int)$q->fetchColumn()===1);
    payments_capture($pdo,$invoice,(int)$fixture['invoice']['user_id'],$client);
    fin_assert(fin_status($pdo,$invoice)==='partially_refunded');
   },['PAYPAL_REFUNDS_ENABLED'=>'1','PAYPAL_REFUNDS_LIVE_APPROVED'=>'1']);
  },
  'paypal_timeout_reuses_key_then_stops_posting_and_recovers_from_verified_get'=>static function()use($pdo,$admin):void{
   fin_provider_mode('live',static function()use($pdo,$admin):void{
    [$fixture,$job]=fin_refund_fixture($pdo,$admin,199);$invoice=(int)$job['invoice_id'];$id=(int)$job['id'];$seen=[];
    $resource=fin_refund_resource($job,'COMPLETED');
    $client=fin_refund_transport($fixture,static function($method,$path,$headers,$body)use(&$seen,&$resource):array{
     if($method==='POST'){
      $seen[]=array_values(array_filter($headers,static fn($header)=>str_starts_with($header,'PayPal-Request-Id:')))[0];
      return ['status'=>503,'body'=>[]];
     }
     return ['status'=>200,'body'=>$resource];
    });
    fin_assert(paypal_refund_next($pdo,payments_config(),$client)&&!paypal_refund_next($pdo,payments_config(),$client));
    $q=$pdo->prepare('UPDATE market_paypal_refund_jobs SET next_check_at=UTC_TIMESTAMP() WHERE id=?');$q->execute([$id]);
    fin_assert(paypal_refund_next($pdo,payments_config(),$client)&&$seen===array_fill(0,2,'PayPal-Request-Id: '.$job['reference']));
    $pdo->prepare('UPDATE market_paypal_refund_jobs SET first_dispatch_at=UTC_TIMESTAMP()-INTERVAL 2 HOUR,next_check_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);
    fin_assert(paypal_refund_next($pdo,payments_config(),$client)&&count($seen)===2&&paypal_refund_find($pdo,$id)['status']==='review');
    fin_assert(finance_refunded_cents($pdo,$invoice)===0);
    $event=['event_type'=>'PAYMENT.CAPTURE.REFUNDED','resource'=>$resource];
    $resource['amount']['value']='1.98';
    fin_reject(fn()=>payments_webhook($pdo,fin_auto_headers(),$event,$client),PaymentConflict::class);
    fin_assert(finance_refunded_cents($pdo,$invoice)===0);
    $resource['amount']['value']='1.99';
    fin_assert(payments_webhook($pdo,fin_auto_headers(),$event,$client)&&finance_refunded_cents($pdo,$invoice)===199);
    fin_assert(fin_status($pdo,$invoice)==='refunded'&&count($seen)===2);
   },['PAYPAL_REFUNDS_ENABLED'=>'1','PAYPAL_REFUNDS_LIVE_APPROVED'=>'1']);
  },
  'paypal_sandbox_refund_uses_worker_polling_without_live_accounting'=>static function()use($pdo,$admin):void{
   fin_provider_mode('sandbox',static function()use($pdo,$admin):void{
    [$fixture,$job]=fin_refund_fixture($pdo,$admin,199);$invoice=(int)$job['invoice_id'];$id=(int)$job['id'];$resource=fin_refund_resource($job,'PENDING');$gets=0;
    $client=fin_refund_transport($fixture,static function($method,$path)use(&$resource,&$gets):array{
     if($method==='GET'){++$gets;fin_assert($path==='/v2/payments/refunds/'.$resource['id']);}
     return ['status'=>200,'body'=>$resource];
    });
    fin_assert(paypal_refund_next($pdo,payments_config(),$client)&&paypal_refund_find($pdo,$id)['status']==='pending');
    $resource['status']='COMPLETED';
    $pdo->prepare('UPDATE market_paypal_refund_jobs SET next_check_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);
    fin_assert(paypal_refund_next($pdo,payments_config(),$client)&&$gets===1&&fin_status($pdo,$invoice)==='refunded'&&finance_refunded_cents($pdo,$invoice)===199);
    foreach(['market_finance_events','market_invoice_documents','market_refunds'] as $table){
     $q=$pdo->prepare('SELECT COUNT(*) FROM '.$table.' WHERE invoice_id=?');$q->execute([$invoice]);fin_assert((int)$q->fetchColumn()===0);
    }
   },['PAYPAL_REFUNDS_ENABLED'=>'1']);
  },
  'paypal_cancelled_job_releases_request_without_issuing_credit'=>static function()use($pdo,$admin):void{
   fin_provider_mode('live',static function()use($pdo,$admin):void{
    [$fixture,$job]=fin_refund_fixture($pdo,$admin,25);
    $client=fin_refund_transport($fixture,static fn()=>['status'=>200,'body'=>fin_refund_resource($job,'CANCELLED')]);
    fin_assert(paypal_refund_next($pdo,payments_config(),$client)&&paypal_refund_find($pdo,(int)$job['id'])['status']==='cancelled');
    $q=$pdo->prepare('SELECT status FROM market_refund_requests WHERE id=?');$q->execute([$job['request_id']]);
    fin_assert($q->fetchColumn()==='requested'&&finance_refunded_cents($pdo,(int)$job['invoice_id'])===0);
   },['PAYPAL_REFUNDS_ENABLED'=>'1','PAYPAL_REFUNDS_LIVE_APPROVED'=>'1']);
  },
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
  'http_paypal_refund_requires_finance_mfa_csrf_and_enabled_environment'=>static function()use($pdo,$admin,$category):void{
   $seller=fin_user($pdo);$staff=fin_user($pdo,true);$secret=random_bytes(20);
   $pdo->prepare('UPDATE users SET mfa_secret=? WHERE id=?')->execute([auth_secret_encrypt($secret),$staff['id']]);
   $invoice=fin_order($pdo,$seller,$category);fin_pay($pdo,$admin['id'],$invoice);
   $request=finance_request_refund($pdo,$seller['id'],$invoice,25,'Synthetic isolated HTTP refund request.');
   $browser=fin_browser();$path='/admin/finance.php';
   try{
    fin_login($browser,$staff);[$code]=fin_request($browser,$path);fin_assert(in_array($code,[303,403],true));
    [$code,$body]=fin_request($browser,'/auth/mfa.php');fin_assert($code===200);
    [$code]=fin_request($browser,'/auth/mfa.php',['csrf_token'=>fin_csrf($body),'code'=>auth_totp($secret,intdiv(time(),30))]);fin_assert($code===303);
    [$code,$body]=fin_request($browser,$path);fin_assert($code===200&&str_contains($body,t('paypal_refunds.disabled')));
    $fields=['action'=>'paypal_refund','request_id'=>(string)$request,'amount'=>'0.25','reason'=>'Synthetic disabled refund attempt.','review_confirmed'=>'1'];
    [$code]=fin_request($browser,$path,$fields);fin_assert($code===403);
    [$code]=fin_request($browser,$path,$fields+['csrf_token'=>fin_csrf($body)]);fin_assert($code===422);
    $q=$pdo->prepare('SELECT COUNT(*) FROM market_paypal_refund_jobs WHERE request_id=?');$q->execute([$request]);fin_assert((int)$q->fetchColumn()===0);
   }finally{curl_close($browser);}
  },
  'bank_statement_reconciliation_is_exact_idempotent_and_keeps_payment_channels_exclusive'=>static function()use($pdo,$admin,$category):void{
   fin_bank_mode(static function()use($pdo,$admin,$category):void{
    $seller=fin_user($pdo);$other=fin_user($pdo);
    fin_setting($pdo,'manual_paypal_enabled','0');
    try{$id=fin_order($pdo,$seller,$category);}finally{fin_setting($pdo,'manual_paypal_enabled','1');}
    fin_reject(fn()=>bank_start($pdo,$id,$other['id']),PaymentConflict::class);
    bank_start($pdo,$id,$seller['id']);
    $order=bank_order($pdo,$id);
    bank_start($pdo,$id,$seller['id']);
    fin_assert($order===bank_order($pdo,$id)&&fin_status($pdo,$id)==='pending');
    fin_reject(fn()=>manual_claim($pdo,$seller['id'],$id),PaymentConflict::class);
    fin_reject(fn()=>fin_pay($pdo,$admin['id'],$id),PaymentConflict::class);
    $source='SYNTHETIC-BANK-'.bin2hex(random_bytes(8));
    $entry=fin_bank_entry($source,$order['reference'],199);
    $xml=fin_bank_xml($entry);
    fin_reject(fn()=>bank_import($pdo,$other['id'],$xml),DomainException::class);
    fin_reject(fn()=>bank_import($pdo,$admin['id'],fin_bank_xml($entry,'GB29NWBK60161331926819')),PaymentConflict::class);
    fin_assert(bank_import($pdo,$admin['id'],$xml)===['imported'=>1,'duplicates'=>0,'skipped'=>0]);
    fin_assert(bank_import($pdo,$admin['id'],$xml)===['imported'=>0,'duplicates'=>1,'skipped'=>0]);
    fin_assert(bank_import($pdo,$admin['id'],fin_bank_xml($entry,tag:'EXPORTED-AGAIN'))===['imported'=>0,'duplicates'=>1,'skipped'=>0]);
    fin_reject(fn()=>bank_import($pdo,$admin['id'],fin_bank_xml(fin_bank_entry($source,$order['reference'],198),tag:'ALTERED')),PaymentConflict::class);
    fin_assert(fin_status($pdo,$id)==='pending'&&fin_document_id($pdo,$id)===0);
    $entryId=fin_bank_entry_id($pdo,$source);
    fin_reject(fn()=>bank_approve($pdo,$other['id'],$entryId),DomainException::class);
    fin_assert(bank_approve($pdo,$admin['id'],$entryId)===$id&&bank_approve($pdo,$admin['id'],$entryId)===$id);
    $q=$pdo->prepare("SELECT COUNT(*) FROM market_finance_events WHERE invoice_id=? AND kind='cash_in' AND amount_cents=199");
    $q->execute([$id]);fin_assert((int)$q->fetchColumn()===1);
    $q=$pdo->prepare('SELECT remaining FROM market_bump_credits WHERE invoice_id=?');
    $q->execute([$id]);fin_assert((int)$q->fetchColumn()===1);
    fin_assert(fin_status($pdo,$id)==='paid'&&str_starts_with(finance_pdf(finance_document($pdo,fin_document_id($pdo,$id),$seller['id'])),'%PDF'));
    $request=finance_request_refund($pdo,$seller['id'],$id,30,'Synthetic bank refund requested.');
    fin_reject(fn()=>finance_record_refund($pdo,$admin['id'],$id,30,'PAYPALREFUND123456','Synthetic incompatible provider refund.',$request),PaymentConflict::class);
    $bankRefund=finance_record_refund($pdo,$admin['id'],$id,30,'Bank debit reference '.$source,'Synthetic completed bank return.',$request,'bank');
    fin_assert(finance_record_refund($pdo,$admin['id'],$id,30,'Bank debit reference '.$source,'Synthetic completed bank return.',$request,'bank')===$bankRefund);
    fin_assert(fin_status($pdo,$id)==='partially_refunded'&&finance_refunded_cents($pdo,$id)===30);
   });
  },
  'bank_reversals_wrong_amounts_and_cancelled_orders_cannot_pay'=>static function()use($pdo,$admin,$category):void{
   fin_bank_mode(static function()use($pdo,$admin,$category):void{
    $seller=fin_user($pdo);$id=fin_order($pdo,$seller,$category);bank_start($pdo,$id,$seller['id']);
    $reference=bank_order($pdo,$id)['reference'];
    $short='SYNTHETIC-SHORT-'.bin2hex(random_bytes(8));$correct='SYNTHETIC-FULL-'.bin2hex(random_bytes(8));
    $unknown='SYNTHETIC-UNKNOWN-'.bin2hex(random_bytes(8));
    bank_import($pdo,$admin['id'],fin_bank_xml(fin_bank_entry($short,$reference,198).fin_bank_entry($correct,$reference,199).fin_bank_entry($unknown,'Ordinary non-market entry',199)));
    foreach([$short,$unknown] as $source)fin_reject(fn()=>bank_approve($pdo,$admin['id'],fin_bank_entry_id($pdo,$source)),PaymentConflict::class);
    bank_import($pdo,$admin['id'],fin_bank_xml(fin_bank_entry($correct,$reference,199,true)));
    fin_reject(fn()=>bank_approve($pdo,$admin['id'],fin_bank_entry_id($pdo,$correct)),PaymentConflict::class);
    fin_reject(fn()=>bank_approve($pdo,$admin['id'],fin_bank_entry_id($pdo,$correct,'reversal')),PaymentConflict::class);
    finance_cancel($pdo,$id,$seller['id']);
    $late='SYNTHETIC-LATE-'.bin2hex(random_bytes(8));
    bank_import($pdo,$admin['id'],fin_bank_xml(fin_bank_entry($late,$reference,199)));
    fin_reject(fn()=>bank_approve($pdo,$admin['id'],fin_bank_entry_id($pdo,$late)),PaymentConflict::class);
    fin_assert(fin_status($pdo,$id)==='void'&&fin_document_id($pdo,$id)===0);
   });
  },
  'dealer_subscription_requires_live_configuration_and_verified_payment_then_renews_and_refunds_exact_term'=>static function()use($pdo,$admin):void{
   $env=['DEALER_SUBSCRIPTION_ENABLED'=>'1','DEALER_SUBSCRIPTION_APPROVED'=>'1','DEALER_SUBSCRIPTION_CENTS'=>'1190',
    'BANK_TRANSFER_ENABLED'=>'0','PAYPAL_ENABLED'=>'0','BITCOIN_ENABLED'=>'0'];
   $savedEnv=[];
   foreach($env as $key=>$value){$savedEnv[$key]=getenv($key);putenv($key.'='.$value);}
   $savedSettings=[];
   $previousSession=$_SESSION??null;
   try{
    $_SESSION['locale']='de';
    $query=$pdo->prepare('SELECT setting_value,updated_at FROM system_settings WHERE setting_key=?');
    foreach(['legal_publication_enabled','legal_checkout_documents'] as $key){$query->execute([$key]);$savedSettings[$key]=$query->fetch();}
    fin_setting($pdo,'legal_publication_enabled','1');
    fin_setting($pdo,'legal_checkout_documents','');
    $seller=fin_user($pdo);$owner=$seller['id'];$other=fin_user($pdo)['id'];
    fin_profile($pdo,$owner,name:'Synthetic original subscription buyer');
    $pdo->prepare("INSERT INTO market_dealer_profiles(user_id,company_name,address_text,country_code,contact_email,legal_notice,status)
        VALUES (?,'Synthetic subscription dealer','Synthetic dealer address','DE',?,'Synthetic dealer subscription fixture only.','published')")
        ->execute([$owner,$seller['email']]);
    $item=dealer_inventory_create($pdo,$owner,$owner,'SUBSCRIPTION-01','=Synthetic office chair');
    dealer_inventory_adjust($pdo,$owner,$owner,$item,1,bin2hex(random_bytes(16)),'3','Synthetic subscription export fixture');
    $terms=dealer_subscription_terms();$version=$terms['version'];$cents=$terms['gross_cents'];
    fin_assert($terms['term_days']===30&&$cents===1190&&$terms['currency']==='EUR');
    fin_bank_mode(static function()use($pdo,$owner,$version):void{
     fin_reject(fn()=>dealer_subscription_order($pdo,$owner,bin2hex(random_bytes(16)),$version,['confirm_subscription'=>'1']),PaymentUnavailable::class);
    });

    $code='subscription-test-'.bin2hex(random_bytes(8));
    $body=str_repeat('Synthetic subscription agreement for accounting integration tests only. ',5);
    $document=legal_create($pdo,$admin['id'],$code,'1.0','de','Synthetic subscription agreement',$body);
    legal_approve($pdo,$admin['id'],$document,'Synthetic fixture reviewer; no legal approval',gmdate('Y-m-d'),array_fill(0,5,'1'));
    fin_setting($pdo,'legal_checkout_documents',$code);
    $hash=hash('sha256',$body);
    $input=['confirm_subscription'=>'1','legal_confirm'=>'1','legal_language'=>'de','legal_documents'=>[$document=>$hash]];
    $assertUnavailable=static function()use($pdo,$owner,$version,$input):void{
     fin_assert(!dealer_subscription_available($pdo));
     fin_reject(fn()=>dealer_subscription_order($pdo,$owner,bin2hex(random_bytes(16)),$version,$input),PaymentUnavailable::class);
    };
    fin_provider_mode('sandbox',$assertUnavailable);
    fin_bitcoin_mode('regtest',$assertUnavailable,['BITCOIN_LIVE_APPROVED'=>'0']);
    fin_assert(dealer_subscription_history($pdo,$owner)===[]);

    fin_bank_mode(static function()use($pdo,$admin,$owner,$other,$item,$version,$cents,$input,$document,$hash):void{
     putenv('DEALER_SUBSCRIPTION_APPROVED=0');
     fin_assert(!dealer_subscription_available($pdo));
     fin_reject(fn()=>dealer_subscription_order($pdo,$owner,bin2hex(random_bytes(16)),$version,$input),PaymentUnavailable::class);
     putenv('DEALER_SUBSCRIPTION_APPROVED=1');
     fin_assert(dealer_subscription_available($pdo));
     fin_reject(fn()=>dealer_subscription_order($pdo,$other,bin2hex(random_bytes(16)),$version,$input),DomainException::class);
     $request=bin2hex(random_bytes(16));
     fin_reject(fn()=>dealer_subscription_order($pdo,$owner,$request,$version,array_replace($input,['legal_documents'=>[]])),InvalidArgumentException::class);
     $id=dealer_subscription_order($pdo,$owner,$request,$version,$input);
     fin_assert(dealer_subscription_order($pdo,$owner,$request,$version,$input)===$id);
     fin_reject(fn()=>dealer_subscription_order($pdo,$owner,$request,'superseded-version',$input),PaymentConflict::class);
     fin_reject(fn()=>dealer_subscription_order($pdo,$owner,bin2hex(random_bytes(16)),$version,$input),PaymentConflict::class);
     $invoice=payments_invoice($pdo,$id,$owner);
     $snapshot=finance_snapshot($pdo,$id);
     fin_assert($invoice['service_code']==='dealer_subscription'&&$invoice['status']==='issued'&&$snapshot['fee_version']===$version
      &&$snapshot['buyer']['legal_name']==='Synthetic original subscription buyer'&&(int)$snapshot['buyer']['user_id']===$owner
      &&$snapshot['gross_cents']===$cents&&$snapshot['currency']==='EUR'&&$snapshot['net_cents']===1000&&$snapshot['tax_cents']===190);
     $query=$pdo->prepare("SELECT user_id,document_id,content_hash,context_key FROM market_contract_receipts WHERE invoice_id=? AND scope='checkout'");
     $query->execute([$id]);$receipts=$query->fetchAll();
     fin_assert(count($receipts)===1&&(int)$receipts[0]['user_id']===$owner&&(int)$receipts[0]['document_id']===$document
      &&$receipts[0]['content_hash']===$hash&&$receipts[0]['context_key']==='invoice:'.$id);
     $unpaid=dealer_subscription_history($pdo,$owner);
     fin_assert(count($unpaid)===1&&$unpaid[0]['starts_at']===null&&$unpaid[0]['ends_at']===null&&!dealer_subscription_active($pdo,$owner));
     fin_reject(fn()=>dealer_subscription_export($pdo,$owner,$owner),MarketAccessException::class);
     fin_reject(fn()=>finance_issue_invoice($pdo,$id,$admin['id']),PaymentConflict::class);
     fin_profile($pdo,$owner,name:'Synthetic updated subscription buyer');

     $pay=static function(int $invoice)use($pdo,$admin,$owner,$cents):void{
      bank_start($pdo,$invoice,$owner);
      $order=bank_order($pdo,$invoice);
      $source='SYNTHETIC-SUBSCRIPTION-'.bin2hex(random_bytes(8));
      fin_assert((int)$order['amount_cents']===$cents&&bank_import($pdo,$admin['id'],fin_bank_xml(fin_bank_entry($source,$order['reference'],$cents)))
       ===['imported'=>1,'duplicates'=>0,'skipped'=>0]);
      $entry=fin_bank_entry_id($pdo,$source);
      fin_assert(fin_status($pdo,$invoice)==='pending'&&fin_document_id($pdo,$invoice)===0);
      fin_assert(bank_approve($pdo,$admin['id'],$entry)===$invoice);
      $documentId=fin_document_id($pdo,$invoice);
      fin_assert($documentId>0&&bank_approve($pdo,$admin['id'],$entry)===$invoice&&fin_document_id($pdo,$invoice)===$documentId);
     };
     $pay($id);
     $first=dealer_subscription_history($pdo,$owner)[0];
     fin_assert($first['status']==='paid'&&(int)$first['term_days']===30&&$first['revoked_at']===null
      &&strtotime($first['ends_at'].' UTC')-strtotime($first['starts_at'].' UTC')===30*86400
      &&finance_snapshot($pdo,$id)===$snapshot&&dealer_subscription_active($pdo,$owner));
     $export=dealer_subscription_export($pdo,$owner,$owner);
     fin_assert(count($export['rows'])===1&&(int)$export['rows'][0]['id']===$item&&$export['rows'][0]['sku']==='SUBSCRIPTION-01'
      &&(int)$export['rows'][0]['quantity']===3&&$export['next']===null);
     $csv=dealer_subscription_csv($export['rows']);
     fin_assert(str_starts_with($csv,"\xEF\xBB\xBF"));
     $lines=explode("\n",rtrim(substr($csv,3),"\r\n"));
     fin_assert(count($lines)===2&&str_getcsv($lines[0],',','"','')===['id','sku','label','quantity','updated_at']
      &&str_getcsv($lines[1],',','"','')===[(string)$item,'SUBSCRIPTION-01',"'=Synthetic office chair",'3',$export['rows'][0]['updated_at']]);
     fin_reject(fn()=>dealer_subscription_export($pdo,$other,$owner),MarketAccessException::class);

     $renewal=dealer_subscription_order($pdo,$owner,bin2hex(random_bytes(16)),$version,$input);
     fin_assert($renewal!==$id&&dealer_subscription_history($pdo,$owner)[0]['starts_at']===null);
     $pay($renewal);
     $second=dealer_subscription_history($pdo,$owner)[0];
     fin_assert((int)$second['invoice_id']===$renewal&&$second['starts_at']===$first['ends_at']
      &&strtotime($second['ends_at'].' UTC')-strtotime($second['starts_at'].' UTC')===30*86400);
     $refund=static function(int $invoice,int $amount)use($pdo,$admin):int{
      $reference='Synthetic bank subscription debit '.bin2hex(random_bytes(8));
      $reason='Synthetic completed subscription refund for isolated tests.';
      $booked=finance_record_refund($pdo,$admin['id'],$invoice,$amount,$reference,$reason,null,'bank');
      fin_assert(finance_record_refund($pdo,$admin['id'],$invoice,$amount,$reference,$reason,null,'bank')===$booked);
      return $booked;
     };
     $refund($renewal,$cents);
     [$renewed,$original]=dealer_subscription_history($pdo,$owner);
     fin_assert($renewed['status']==='refunded'&&$renewed['revoked_at']!==null&&$original===$first&&dealer_subscription_active($pdo,$owner));
     $refund($id,190);
     fin_assert(fin_status($pdo,$id)==='partially_refunded'&&dealer_subscription_active($pdo,$owner));
     $refund($id,1000);
     fin_assert(fin_status($pdo,$id)==='refunded'&&!dealer_subscription_active($pdo,$owner));
     fin_reject(fn()=>dealer_subscription_export($pdo,$owner,$owner),MarketAccessException::class);
    });
   }finally{
    foreach($savedSettings as $key=>$row){
     if(!$row)$pdo->prepare('DELETE FROM system_settings WHERE setting_key=?')->execute([$key]);
     else $pdo->prepare('UPDATE system_settings SET setting_value=?,updated_at=? WHERE setting_key=?')->execute([$row['setting_value'],$row['updated_at'],$key]);
    }
    site_settings($pdo,true);
    foreach($savedEnv as $key=>$value)putenv($value===false?$key:$key.'='.$value);
    if($previousSession===null)unset($_SESSION);else $_SESSION=$previousSession;
   }
  },
  'bank_http_checkout_upload_review_and_approval_require_owner_mfa_csrf_and_actual_confirmation'=>static function()use($pdo,$category):void{
   fin_bank_mode(static function()use($pdo,$category):void{
    $seller=fin_user($pdo);$staff=fin_user($pdo,true);$secret=random_bytes(20);
    $pdo->prepare('UPDATE users SET mfa_secret=? WHERE id=?')->execute([auth_secret_encrypt($secret),$staff['id']]);
    $invoice=fin_order($pdo,$seller,$category);$path='/user/billing.php?order='.$invoice;
    $account=fin_browser();$manager=fin_browser();$file=null;
    try{
     fin_bank_http_server(static function(int $port)use($pdo,$seller,$staff,$secret,$invoice,$path,$account,$manager,&$file):void{
      [$code]=fin_request($account,$path,null,$port);fin_assert($code===303);
      fin_login($account,$seller,$port);
      [$code,$body]=fin_request($account,$path,null,$port);fin_assert($code===200&&str_contains($body,'name="provider" value="bank"'));
      $form=['invoice_id'=>(string)$invoice,'provider'=>'bank'];
      [$code]=fin_request($account,'/actions/payment-checkout.php',$form,$port);fin_assert($code===403);
      [$code]=fin_request($account,'/actions/payment-checkout.php',$form+['csrf_token'=>fin_csrf($body)],$port);fin_assert($code===303);
      $order=bank_order($pdo,$invoice);
      [$code,$body]=fin_request($account,$path,null,$port);
      fin_assert($code===200&&str_contains($body,$order['reference'])&&str_contains($body,$order['iban'])&&!str_contains($body,'name="cmd" value="_xclick"'));
      [$code]=fin_request($account,'/admin/bank.php',null,$port);fin_assert($code===403);
      fin_login($manager,$staff,$port);[$code]=fin_request($manager,'/admin/bank.php',null,$port);fin_assert(in_array($code,[303,403],true));
      [$code,$body]=fin_request($manager,'/auth/mfa.php',null,$port);fin_assert($code===200);
      [$code]=fin_request($manager,'/auth/mfa.php',['csrf_token'=>fin_csrf($body),'code'=>auth_totp($secret,intdiv(time(),30))],$port);fin_assert($code===303);
      [$code,$body]=fin_request($manager,'/admin/bank.php',null,$port);fin_assert($code===200&&str_contains($body,'name="statement"'));
      $source='SYNTHETIC-UPLOAD-'.bin2hex(random_bytes(8));
      $file=tempnam(sys_get_temp_dir(),'gunindex-bank-test-');fin_assert(is_string($file));
      fin_assert(file_put_contents($file,fin_bank_xml(fin_bank_entry($source,$order['reference'],199)))!==false);
      $fields=['action'=>'import','csrf_token'=>fin_csrf($body),'statement'=>new CURLFile($file,'application/xml','statement.xml')];
      [$code]=fin_request($manager,'/admin/bank.php',$fields,$port,true);fin_assert($code===422);
      [$code]=fin_request($manager,'/admin/bank.php',$fields+['source_confirmed'=>'1'],$port,true);
      fin_assert($code===303&&fin_status($pdo,$invoice)==='pending');
      [$code,$body]=fin_request($manager,'/admin/bank.php',null,$port);
      fin_assert($code===200&&str_contains($body,$source)&&str_contains($body,$order['reference']));
      $approve=['action'=>'approve','entry_id'=>(string)fin_bank_entry_id($pdo,$source),'csrf_token'=>fin_csrf($body)];
      [$code]=fin_request($manager,'/admin/bank.php',$approve,$port);
      fin_assert($code===422&&fin_status($pdo,$invoice)==='pending');
      [$code]=fin_request($manager,'/admin/bank.php',$approve+['review_confirmed'=>'1'],$port);
      fin_assert($code===303&&fin_status($pdo,$invoice)==='paid');
      [$code,$body]=fin_request($account,$path,null,$port);fin_assert($code===200&&str_contains($body,t('bank.completed')));
     });
    }finally{if(is_string($file)&&is_file($file))unlink($file);curl_close($account);curl_close($manager);}
   });
  },
  'bitcoin_quote_and_payment_channel_are_exclusive_and_require_live_approval'=>static function()use($pdo,$admin,$category):void{
   $seller=fin_user($pdo);$id=fin_order($pdo,$seller,$category);
   fin_bitcoin_mode('main',static function()use($pdo,$id,$seller):void{
    fin_reject(fn()=>bitcoin_start($pdo,$id,$seller['id']),PaymentUnavailable::class);
    fin_assert(bitcoin_order($pdo,$id)===null);
   },['BITCOIN_LIVE_APPROVED'=>'0']);
   fin_bank_mode(static function()use($pdo,$id,$seller,$admin):void{
    fin_bitcoin_mode('main',static function()use($pdo,$id,$seller,$admin):void{
     fin_assert(bitcoin_quote_sats(199,6100000)===3263&&bitcoin_decimal(3263)==='0.00003263');
     bitcoin_start($pdo,$id,$seller['id']);
     $order=bitcoin_order($pdo,$id);
     bitcoin_start($pdo,$id,$seller['id']);
     fin_assert($order===bitcoin_order($pdo,$id)&&(int)$order['amount_sats']===3263);
     fin_reject(fn()=>bank_start($pdo,$id,$seller['id']),PaymentConflict::class);
     fin_reject(fn()=>manual_claim($pdo,$seller['id'],$id),PaymentConflict::class);
     fin_reject(fn()=>fin_pay($pdo,$admin['id'],$id),PaymentConflict::class);
    });
   });
  },
  'bitcoin_exact_output_booked_once_and_reorg_suspends_remaining_service'=>static function()use($pdo,$category):void{
   fin_bitcoin_mode('main',static function()use($pdo,$category):void{
    $seller=fin_user($pdo);$id=fin_order($pdo,$seller,$category);bitcoin_start($pdo,$id,$seller['id']);
    $order=bitcoin_order($pdo,$id);
    $observed=fin_bitcoin_receipt($order,1);
    fin_bitcoin_apply($pdo,$id,[$observed]);
    fin_assert(fin_status($pdo,$id)==='pending'&&fin_document_id($pdo,$id)===0);
    $observed['confirmations']=2;
    fin_bitcoin_apply($pdo,$id,[$observed]);
    fin_bitcoin_apply($pdo,$id,[$observed]);
    fin_assert(fin_status($pdo,$id)==='paid'&&fin_document_id($pdo,$id)>0);
    $q=$pdo->prepare("SELECT COUNT(*) FROM market_finance_events WHERE invoice_id=? AND kind='cash_in' AND amount_cents=199");
    $q->execute([$id]);fin_assert((int)$q->fetchColumn()===1);
    $q=$pdo->prepare('SELECT remaining FROM market_bump_credits WHERE invoice_id=?');
    $q->execute([$id]);fin_assert((int)$q->fetchColumn()===1);
    $observed['confirmations']=0;$observed['block_hash']=null;
    fin_bitcoin_apply($pdo,$id,[$observed]);
    $after=bitcoin_order($pdo,$id);
    fin_assert($after['status']==='review'&&$after['review_reason']==='reorg'&&$after['txid']===$observed['txid']&&fin_status($pdo,$id)==='paid');
    $q->execute([$id]);fin_assert((int)$q->fetchColumn()===0);
   });
  },
  'bitcoin_late_wrong_multiple_and_cancelled_outputs_stay_for_review'=>static function()use($pdo,$category):void{
   fin_bitcoin_mode('main',static function()use($pdo,$category):void{
    foreach(['late','amount','multiple','cancelled'] as $reason){
     $seller=fin_user($pdo);$id=fin_order($pdo,$seller,$category);bitcoin_start($pdo,$id,$seller['id']);
     $order=bitcoin_order($pdo,$id);$receipt=fin_bitcoin_receipt($order);
     if($reason==='late')$receipt['received_at']=strtotime($order['expires_at'].' UTC')+1;
     if($reason==='amount')$receipt['satoshis']++;
     if($reason==='cancelled')finance_cancel($pdo,$id,$seller['id']);
     $receipts=[$receipt];if($reason==='multiple')$receipts[]=array_replace($receipt,['vout'=>1]);
     fin_bitcoin_apply($pdo,$id,$receipts);
     $after=bitcoin_order($pdo,$id);
     fin_assert($after['status']==='review'&&$after['review_reason']===$reason&&fin_document_id($pdo,$id)===0);
    }
   });
  },
  'bitcoin_regtest_receipt_creates_no_real_service_or_accounting'=>static function()use($pdo,$category):void{
   fin_bitcoin_mode('regtest',static function()use($pdo,$category):void{
    $seller=fin_user($pdo);$id=fin_order($pdo,$seller,$category);bitcoin_start($pdo,$id,$seller['id']);
    $receipt=fin_bitcoin_receipt(bitcoin_order($pdo,$id));fin_bitcoin_apply($pdo,$id,[$receipt]);
    fin_assert(bitcoin_order($pdo,$id)['status']==='confirmed'&&fin_status($pdo,$id)==='paid');
    foreach(['market_finance_events','market_invoice_documents','market_bump_credits'] as $table){
     $q=$pdo->prepare('SELECT COUNT(*) FROM '.$table.' WHERE invoice_id=?');$q->execute([$id]);fin_assert((int)$q->fetchColumn()===0);
    }
   },['BITCOIN_LIVE_APPROVED'=>'0']);
  },
  'customer_payment_and_invoice_pages_use_eight_selected_languages'=>static function()use($pdo):void{
   $customer=fin_user($pdo);
   $statement=$pdo->prepare("INSERT INTO market_invoices(user_id,invoice_number,service_code,fee_version,gross_cents,status) VALUES (?,?,'bump_single','synthetic-locale-1',199,'issued')");
   $statement->execute([$customer['id'],'GI-LOCALE-'.strtoupper(bin2hex(random_bytes(8)))]);
   $invoice=(int)$pdo->lastInsertId();
   $path='/user/payments.php?invoice_id='.$invoice;
   $browser=fin_browser();
   try{
    fin_login($browser,$customer);
    foreach(i18n_locales() as $locale=>$label){
     [$status,$page]=fin_request($browser,$path);
     fin_assert($status===200);
     [$changed]=fin_request($browser,'/user/language.php',['csrf_token'=>fin_csrf($page),'locale'=>$locale,'market_country'=>'DE','return_path'=>$path]);
     fin_assert($changed===303);
     $copy=i18n_dictionary($locale);
     [$status,$page]=fin_request($browser,$path);
     fin_assert($status===200&&str_contains($page,'<html lang="'.$locale.'"')
      &&str_contains($page,'<h1>'.h($copy['payment_customer.title']).'</h1>')
      &&str_contains($page,h($copy['payment_customer.state_issued']))
      &&str_contains($page,h($copy['payment_customer.service_bump_single'])));
     [$status,$page]=fin_request($browser,'/actions/payment-return.php?invoice_id='.$invoice.'&cancel=1');
     fin_assert($status===200&&str_contains($page,'<html lang="'.$locale.'"')
      &&str_contains($page,'<h1>'.h($copy['payment_customer.return_heading']).'</h1>')
      &&str_contains($page,h($copy['payment_customer.return_cancel'])));
     [$status,$page]=fin_request($browser,'/user/invoices.php');
     fin_assert($status===200&&str_contains($page,'<h1>'.h($copy['finance_customer.heading']).'</h1>')
      &&str_contains($page,h($copy['finance_customer.cancel_order'])));
     [$status,$page]=fin_request($browser,'/user/invoices.php',['csrf_token'=>fin_csrf($page),'action'=>'refund',
      'invoice_id'=>(string)$invoice,'amount'=>'invalid','reason'=>'Synthetic invalid refund amount.']);
     fin_assert($status===422&&str_contains($page,h($copy['finance_customer.amount_invalid'])));
     [$status,$page]=fin_request($browser,'/user/billing-details.php');
     fin_assert($status===200&&str_contains($page,'<h1>'.h($copy['finance_customer.billing_heading']).'</h1>')
      &&str_contains($page,h($copy['finance_customer.save'])));
     [$status,$page]=fin_request($browser,'/user/billing-details.php',['csrf_token'=>fin_csrf($page),'country_code'=>'DE','postal_code'=>'000']);
     fin_assert($status===422&&str_contains($page,h($copy['finance_customer.postal_mismatch'])));
     [$status,$page]=fin_request($browser,'/user/billing.php');
     fin_assert($status===200&&str_contains($page,'<h1>'.h($copy['billing_customer.heading']).'</h1>')
      &&str_contains($page,h($copy['billing_customer.order'])));
     [$status,$page]=fin_request($browser,'/user/billing.php',['csrf_token'=>fin_csrf($page),'action'=>'order']);
     fin_assert($status===422&&str_contains($page,h($copy['billing_customer.terms_required'])));
     [$status,$page]=fin_request($browser,'/user/promotions.php');
     fin_assert($status===200&&str_contains($page,'<h1>'.h($copy['promotion.title']).'</h1>')
      &&str_contains($page,h($copy['promotion.bookings']))&&str_contains($page,h($copy['promotion.empty'])));
     [$status,$page]=fin_request($browser,'/user/promotions.php',['csrf_token'=>fin_csrf($page),'action'=>'start']);
     fin_assert($status===422&&str_contains($page,h($copy['promotion.start_confirm'])));
    }
    fin_assert(fin_status($pdo,$invoice)==='issued');
   }finally{curl_close($browser);}
  },
  'finance_admin_translations_validation_and_refund_rejection_are_consistent'=>static function()use($pdo,$admin,$category):void{
   $seller=fin_user($pdo);$staff=fin_user($pdo,true);$secret=random_bytes(20);
   $pdo->prepare('UPDATE users SET mfa_secret=? WHERE id=?')->execute([auth_secret_encrypt($secret),$staff['id']]);
   $invoice=fin_order($pdo,$seller,$category);fin_pay($pdo,$admin['id'],$invoice);
   $request=finance_request_refund($pdo,$seller['id'],$invoice,1,'Synthetic request for an isolated rejection.');
   $reason='Synthetic explicitly reviewed rejection.';
   $systemEvent='synthetic.audit.'.bin2hex(random_bytes(6));$eventDetails=json_encode(['detail'=>'Reserved <sample> & symbols'],JSON_THROW_ON_ERROR);
   $pdo->prepare('INSERT INTO admin_logs(admin_id,action_type,details) VALUES (NULL,?,?)')->execute([$systemEvent,$eventDetails]);
   fin_reject(fn()=>finance_reject_refund_request($pdo,$seller['id'],$request,$reason),DomainException::class);
   $taxBefore=$pdo->query("SELECT * FROM market_tax_rules WHERE country_code='DE'")->fetch();
   $browser=fin_browser();$path='/admin/finance.php';
   try{
    fin_login($browser,$staff);[$code]=fin_request($browser,$path);fin_assert(in_array($code,[303,403],true));
    [$code,$page]=fin_request($browser,'/auth/mfa.php');fin_assert($code===200);
    [$code]=fin_request($browser,'/auth/mfa.php',['csrf_token'=>fin_csrf($page),'code'=>auth_totp($secret,intdiv(time(),30))]);fin_assert($code===303);
    foreach(array_keys(i18n_locales()) as $locale){
     [$code,$page]=fin_request($browser,$path);fin_assert($code===200);
     [$code]=fin_request($browser,'/user/language.php',['csrf_token'=>fin_csrf($page),'locale'=>$locale,'market_country'=>'DE','return_path'=>$path]);fin_assert($code===303);
     [$code,$page]=fin_request($browser,$path);
     $copy=i18n_dictionary($locale);
     fin_assert($code===200&&str_contains($page,'<html lang="'.$locale.'"')&&str_contains($page,'<h1>'.h($copy['finance_admin.heading']).'</h1>')
      &&str_contains($page,h($copy['finance_admin.tax_save']))&&str_contains($page,h($copy['finance_admin.reject_request'])));
     fin_assert(str_contains($page,'aria-label="'.h($copy['admin_nav.sections']).'"')
      &&str_contains($page,'href="/admin/finance.php" aria-current="page">'.h($copy['admin_nav.finance']).'</a>'));
     [$code,$page]=fin_request($browser,$path,['csrf_token'=>fin_csrf($page),'action'=>'tax','country_code'=>'DE','basis_points'=>'1900X',
      'explanation'=>'Synthetic invalid tax rate regression.','review_confirmed'=>'1']);
     fin_assert($code===422&&str_contains($page,h($copy['finance_admin.tax_invalid'])));
     [$code,$audit]=fin_request($browser,'/admin/logs.php');
     fin_assert($code===200&&str_contains($audit,'<h1>'.h($copy['admin_nav.audit']).'</h1>')&&str_contains($audit,h($systemEvent))
      &&str_contains($audit,h($copy['admin_audit.system']))&&str_contains($audit,h($eventDetails)));
    }
    fin_assert($taxBefore===$pdo->query("SELECT * FROM market_tax_rules WHERE country_code='DE'")->fetch());
    $fields=['action'=>'reject','request_id'=>(string)$request,'reason'=>$reason];
    [$code]=fin_request($browser,$path,$fields);fin_assert($code===403);
    $fields['csrf_token']=fin_csrf($page);
    [$code]=fin_request($browser,'/admin/logs.php',$fields);fin_assert($code===405);
    for($retry=0;$retry<2;$retry++){
     [$code,$page]=fin_request($browser,$path,$fields);
     fin_assert($code===200&&str_contains($page,h($copy['finance_admin.decision_saved'])));
    }
    [$code]=fin_request($browser,$path,array_replace($fields,['reason'=>'Synthetic different decision reason.']));fin_assert($code===422);
    $q=$pdo->prepare('SELECT status,decision_reason FROM market_refund_requests WHERE id=?');$q->execute([$request]);
    fin_assert($q->fetch()===['status'=>'rejected','decision_reason'=>$reason]&&fin_status($pdo,$invoice)==='paid');
    $q=$pdo->prepare("SELECT admin_id FROM admin_logs WHERE action_type='finance.refund_rejected' AND target_id=?");$q->execute([$request]);
    fin_assert(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN))===[$staff['id']]);
   }finally{curl_close($browser);}
  },
  'new_pdf_labels_are_frozen_in_the_invoice_language'=>static function()use($pdo,$admin,$category):void{
   $original=$_SESSION['locale']??null;
   try{
    foreach(i18n_locales() as $locale=>$label){
     $_SESSION['locale']=$locale;
     $customer=fin_user($pdo);
     $id=fin_order($pdo,$customer,$category);
     $snapshot=finance_snapshot($pdo,$id);
     fin_assert($snapshot['locale']===$locale&&$snapshot['render_labels']===finance_pdf_labels($locale));
     fin_pay($pdo,$admin['id'],$id);
     $document=finance_document($pdo,fin_document_id($pdo,$id),$customer['id']);
     $markup=finance_pdf_markup($document);
     fin_assert(str_contains($markup,'<html lang="'.$locale.'">')
      &&str_contains($markup,'<th>'.h($snapshot['render_labels']['quantity']).'</th>')
      &&str_contains($markup,h($snapshot['tax_explanation'])));
     $_SESSION['locale']=$locale==='de'?'en':'de';
     fin_assert(finance_pdf_markup($document)===$markup&&str_starts_with(finance_pdf($document),'%PDF-'));
     unset($document['payload']['render_labels']);
     fin_assert(str_contains(finance_pdf_markup($document),'<th>Leistung / Service</th>'));
    }
   }finally{if($original===null)unset($_SESSION['locale']);else$_SESSION['locale']=$original;}
  },
 ];
 foreach($tests as $name=>$run){$run();++$passed;fwrite(STDOUT,'PASS '.$name.PHP_EOL);}fwrite(STDOUT,'Finance checks passed: '.$passed.PHP_EOL);
}catch(Throwable $e){fwrite(STDERR,'FAIL '.($name??'initialization').' ('.get_class($e).' at '.basename($e->getFile()).':'.$e->getLine().')'.PHP_EOL);$failed=true;}
finally{
 if($pdo->inTransaction())$pdo->rollBack();$restore=$pdo->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');foreach($saved as $key=>$value){if($value===false)$pdo->prepare('DELETE FROM system_settings WHERE setting_key=?')->execute([$key]);else$restore->execute([$key,$value]);}site_settings($pdo,true);
 $pdo->exec("DELETE FROM market_tax_rules WHERE country_code IN ('DE','AT')");foreach($taxes as $row)$pdo->prepare('INSERT INTO market_tax_rules(country_code,basis_points,explanation,reviewed_by,reviewed_at) VALUES (?,?,?,?,?)')->execute([$row['country_code'],$row['basis_points'],$row['explanation'],$row['reviewed_by'],$row['reviewed_at']]);$pdo->prepare("UPDATE market_countries SET is_active=? WHERE code='DE'")->execute([$country]);
}
if($failed)exit(1);
