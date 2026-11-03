<?php
declare(strict_types=1);
/** CLI-only synthetic browser fixtures; no provider requests or arbitrary SQL. */
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('DB_DATABASE')!=='gunindex_rebuild_test')exit(2);
require_once __DIR__.'/../config/bootstrap.php';
if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='gunindex_rebuild_test')exit(2);
function abf_settings():array{return [
 'auction_fees_enabled'=>'1','auction_fees_approved'=>'1','fee_auction_listing_cents'=>'50',
 'manual_paypal_enabled'=>'1','manual_paypal_approved'=>'1','manual_paypal_recipient'=>'browser-merchant@example.invalid',
 'manual_tax_reviewed'=>'1','manual_tax_bps'=>'1900','finance_enabled'=>'1',
 'operator_legal_name'=>'Synthetic browser operator','operator_address_line1'=>'Synthetic Street 1',
 'operator_address_line2'=>'','operator_postcode'=>'10115','operator_city'=>'Synthetic city',
 'operator_country'=>'DE','operator_email'=>'browser-operator@example.invalid','operator_vat_id'=>'SYNTHETIC-NOT-VALID',
 'operator_tax_number'=>'','operator_registration'=>'Synthetic fixture only','invoice_prefix'=>'ABWEB',
 'legal_publication_enabled'=>'1','legal_checkout_documents'=>'','maintenance_enabled'=>'0',
];}
function abf_restore(PDO $pdo,array $saved):void{
 if(array_keys($saved['settings']??[])!==array_keys(abf_settings()))throw new RuntimeException('Invalid fixture restore keys.');
 $pdo->beginTransaction();try{
  foreach($saved['settings'] as $key=>$row){
   if($row===null)$pdo->prepare('DELETE FROM system_settings WHERE setting_key=?')->execute([$key]);
   else $pdo->prepare('INSERT INTO system_settings(setting_key,setting_value,updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=VALUES(updated_at)')->execute([$key,$row['setting_value'],$row['updated_at']]);
  }
  $tax=$saved['tax'];
  if($tax===null)$pdo->exec("DELETE FROM market_tax_rules WHERE country_code='DE'");
  else $pdo->prepare("INSERT INTO market_tax_rules(country_code,basis_points,explanation,reviewed_by,reviewed_at) VALUES ('DE',?,?,?,?) ON DUPLICATE KEY UPDATE basis_points=VALUES(basis_points),explanation=VALUES(explanation),reviewed_by=VALUES(reviewed_by),reviewed_at=VALUES(reviewed_at)")->execute([$tax['basis_points'],$tax['explanation'],$tax['reviewed_by'],$tax['reviewed_at']]);
  $pdo->prepare("UPDATE market_countries SET is_active=?,updated_at=? WHERE code='DE'")->execute([$saved['country']['is_active'],$saved['country']['updated_at']]);
  $pdo->commit();site_settings($pdo,true);
 }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}
function abf_user(PDO $pdo,string $name,string $password,bool $admin):int{
 $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code,preferred_locale,is_admin,mfa_secret) VALUES (?,?,?,'Synthetic auction browser fixture','10115','Synthetic city','DE','de',?,?)")->execute([$name,$name.'@example.invalid',auth_hash($password),(int)$admin,$admin?auth_secret_encrypt(random_bytes(20)):null]);return(int)$pdo->lastInsertId();
}
try{
 $raw=stream_get_contents(STDIN,65537);if(strlen($raw)>65536)throw new InvalidArgumentException('Fixture request too large.');
 $input=json_decode($raw,true,24,JSON_THROW_ON_ERROR);$operation=$input['operation']??'';$out=null;
 if($operation==='setup'){
  $saved=['settings'=>[],'tax'=>$pdo->query("SELECT * FROM market_tax_rules WHERE country_code='DE'")->fetch()?:null,'country'=>$pdo->query("SELECT is_active,updated_at FROM market_countries WHERE code='DE'")->fetch()];
  foreach(abf_settings() as $key=>$_){$q=$pdo->prepare('SELECT setting_value,updated_at FROM system_settings WHERE setting_key=?');$q->execute([$key]);$saved['settings'][$key]=$q->fetch()?:null;}
  try{
   $token=bin2hex(random_bytes(8));$username='abweb_'.$token;$password='Synthetic browser '.bin2hex(random_bytes(20));
   $admin=abf_user($pdo,'abadmin_'.$token,'Synthetic admin '.bin2hex(random_bytes(20)),true);$seller=abf_user($pdo,$username,$password,false);
   $settings=abf_settings();$settings['legal_checkout_documents']='abweb-'.$token;
   foreach($settings as $key=>$value)$pdo->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,$value]);
   site_settings($pdo,true);$pdo->exec("UPDATE market_countries SET is_active=1 WHERE code='DE'");
   finance_save_tax($pdo,$admin,'DE',1900,'Synthetic browser tax fixture only; no actual approval.');
   finance_save_profile($pdo,$seller,['legal_name'=>'Synthetic seller invoice','address_line1'=>'Synthetic Street 2','address_line2'=>'','postal_code'=>'10115','city'=>'Synthetic city','country_code'=>'DE','vat_id'=>'']);
   $labels=[];
   foreach(array_keys(i18n_locales()) as $locale){
    $doc=legal_create($pdo,$admin,$settings['legal_checkout_documents'],'browser-1',$locale,'Synthetic terms '.$locale,str_repeat('Synthetic test document. No actual legal approval. ',10));
    legal_approve($pdo,$admin,$doc,'Synthetic browser fixture review only',gmdate('Y-m-d'),array_fill(0,5,'1'));
    $dictionary=i18n_dictionary($locale);$labels[$locale]=$dictionary['auction_billing.publish'];
   }
   $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')->execute(['Synthetic folding tables','abweb-'.$token]);
   $out=['saved'=>$saved,'username'=>$username,'password'=>$password,'category'=>(int)$pdo->lastInsertId(),'labels'=>$labels];
  }catch(Throwable $error){abf_restore($pdo,$saved);throw $error;}
 }elseif($operation==='restore'){
  abf_restore($pdo,$input['saved']);$out=['restored'=>true];
 }elseif($operation==='state'){
  $name=$input['username']??null;$id=$input['id']??null;
  if(!is_string($name)||!preg_match('/\Aabweb_[a-f0-9]{16}\z/',$name)||!is_int($id)||$id<1)throw new InvalidArgumentException('Synthetic listing identity required.');
  $q=$pdo->prepare("SELECT l.id,l.type,l.status FROM market_listings l JOIN users u ON u.id=l.seller_id WHERE l.id=? AND u.username=? AND u.email=CONCAT(u.username,'@example.invalid') AND u.full_name='Synthetic auction browser fixture'");$q->execute([$id,$name]);$out=$q->fetch();if(!$out)throw new RuntimeException('Fixture listing not found.');
  $q=$pdo->prepare('SELECT c.kind,c.invoice_id,c.gross_cents,i.invoice_number FROM market_auction_fee_charges c LEFT JOIN market_invoices i ON i.id=c.invoice_id WHERE c.listing_id=? ORDER BY c.id');$q->execute([$id]);$out['charges']=$q->fetchAll();
 }else throw new InvalidArgumentException('Unknown fixture operation.');
 fwrite(STDOUT,json_encode($out,JSON_THROW_ON_ERROR));
}catch(Throwable $error){fwrite(STDERR,'Auction browser fixture failed: '.get_class($error).' at line '.$error->getLine().PHP_EOL);exit(1);}
