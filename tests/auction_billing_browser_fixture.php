<?php
declare(strict_types=1);
/** CLI-only synthetic browser fixtures; no provider requests or arbitrary SQL. */
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('DB_DATABASE')!=='gunindex_rebuild_test')exit(2);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/relisting.php';
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
function abf_assert(bool $condition):void{
 if(!$condition)throw new RuntimeException('Synthetic auction browser fixture invariant failed.');
}
function abf_owner(PDO $pdo,mixed $name):array{
 if(!is_string($name)||!preg_match('/\Aabweb_[a-f0-9]{16}\z/',$name))throw new InvalidArgumentException('Synthetic owner required.');
 $q=$pdo->prepare("SELECT id,username FROM users WHERE username=? AND email=CONCAT(username,'@example.invalid') AND full_name='Synthetic auction browser fixture' AND is_active=1 AND is_admin=0");
 $q->execute([$name]);$owner=$q->fetch();abf_assert(is_array($owner));return $owner;
}
function abf_listing(PDO $pdo,array $owner,mixed $id,bool $lock=false):array{
 if(!is_int($id)||$id<1)throw new InvalidArgumentException('Synthetic listing identifier required.');
 if($lock&&!$pdo->inTransaction())throw new LogicException('Fixture locking requires a transaction.');
 $q=$pdo->prepare('SELECT * FROM market_listings WHERE id=? AND seller_id=?'.($lock?' FOR UPDATE':''));
 $q->execute([$id,$owner['id']]);$listing=$q->fetch();abf_assert(is_array($listing));return $listing;
}
/** Return image identities as fingerprints, never private storage keys or paths. */
function abf_images(PDO $pdo,int $listing):array{
 $q=$pdo->prepare('SELECT id,storage_key,position,width,height FROM market_images WHERE listing_id=? ORDER BY position,id');$q->execute([$listing]);
 $images=[];$directory=null;
 foreach($q->fetchAll() as $row){
  abf_assert(is_string($row['storage_key'])&&preg_match('/\A[a-f0-9]{48}\z/',$row['storage_key'])===1);
  $directory??=market_image_directory();
  $image=['id'=>(int)$row['id'],'position'=>(int)$row['position'],'width'=>(int)$row['width'],'height'=>(int)$row['height'],
   'key_fingerprint'=>hash('sha256',$row['storage_key'])];
  foreach(['large'=>'.webp','thumb'=>'-thumb.webp'] as $size=>$suffix){
   $path=$directory.'/'.$row['storage_key'].$suffix;clearstatcache(true,$path);
   abf_assert(is_file($path)&&!is_link($path)&&realpath($path)===$path&&filesize($path)>0&&filesize($path)<=8*1024*1024);
   $info=getimagesize($path);abf_assert(is_array($info)&&($info['mime']??'')==='image/webp');
   if($size==='large')abf_assert($info[0]===$image['width']&&$info[1]===$image['height']);
   $digest=hash_file('sha256',$path);abf_assert(is_string($digest));$image[$size.'_sha256']=$digest;
  }
  $images[]=$image;
 }
 return $images;
}
/** Read-only state is deliberately limited to non-secret synthetic listing facts. */
function abf_state(PDO $pdo,array $owner,int $id,string $locale='de'):array{
 abf_assert(isset(i18n_locales()[$locale]));$_SESSION['locale']=$locale;
 $listing=abf_listing($pdo,$owner,$id);
 $out=array_intersect_key($listing,array_flip(['id','type','status','title','description','ends_at','highest_bidder_id','current_bid_cents','created_at']));
 $out['id']=(int)$listing['id'];
 $q=$pdo->prepare('SELECT c.kind,c.invoice_id,c.gross_cents,i.invoice_number FROM market_auction_fee_charges c LEFT JOIN market_invoices i ON i.id=c.invoice_id WHERE c.listing_id=? ORDER BY c.id');$q->execute([$id]);$out['charges']=$q->fetchAll();
 $q=$pdo->prepare('SELECT event_type,idempotency_key,user_id,amount_cents FROM market_bids WHERE listing_id=? ORDER BY id');$q->execute([$id]);$out['events']=$q->fetchAll();
 $q=$pdo->prepare('SELECT COUNT(*) FROM market_proxy_bids WHERE listing_id=?');$q->execute([$id]);$out['proxy_count']=(int)$q->fetchColumn();
 $out['images']=abf_images($pdo,$id);
 $out['relisting']=relisting_context($pdo,(int)$owner['id'],$id);
 $contract=auction_billing_contract($pdo,$id);
 $out['contract']=$contract===null?null:['tariff'=>$contract['tariff'],'locale'=>$contract['locale'],'fee_version'=>$contract['fee_version'],'relisting'=>$contract['relisting']??null];
 $out['quote']=null;
 if($listing['status']==='draft'&&$listing['type']==='auction'){
  $quote=relisting_discount_quote($pdo,(int)$owner['id'],$id,auction_billing_quote($pdo,(int)$owner['id']),false);
  if($quote!==null)$out['quote']=['hash'=>$quote['hash'],'tariff'=>$quote['payload']['tariff'],'terms'=>$quote['payload']['terms'],'locale'=>$quote['payload']['locale']];
 }
 return $out;
}
/** New synthetic GD output only; existing files cannot be overwritten. */
function abf_image(PDO $pdo,array $owner,int $id):void{
 $listing=abf_listing($pdo,$owner,$id);abf_assert($listing['status']==='active'&&$listing['type']==='auction');
 abf_assert(extension_loaded('gd')&&function_exists('imagewebp'));
 $directory=market_image_directory();$key=bin2hex(random_bytes(24));
 $paths=[$directory.'/'.$key.'.webp',$directory.'/'.$key.'-thumb.webp'];
 $image=imagecreatetruecolor(96,64);abf_assert($image instanceof GdImage);
 $committing=false;$rolledBack=false;$created=[];
 try{
  imagefill($image,0,0,imagecolorallocate($image,42,133,114));
  imagefilledrectangle($image,15,18,80,44,imagecolorallocate($image,188,143,95));
  foreach($paths as $path){market_write_webp($image,$path);$created[]=$path;}
  $pdo->beginTransaction();abf_listing($pdo,$owner,$id,true);
  $q=$pdo->prepare('SELECT COUNT(*) FROM market_images WHERE listing_id=?');$q->execute([$id]);abf_assert((int)$q->fetchColumn()===0);
  $pdo->prepare('INSERT INTO market_images(listing_id,storage_key,position,width,height) VALUES (?,?,0,96,64)')->execute([$id,$key]);
  $committing=true;$pdo->commit();
 }catch(Throwable $error){
  if($pdo->inTransaction()){$pdo->rollBack();$rolledBack=true;}
  if(!$committing||$rolledBack)market_remove_prepared_images([['paths'=>$created]]);
  throw $error;
 }finally{imagedestroy($image);}
}
/** The worker is paused by the Node suite. Close precisely this owned, bid-free fixture. */
function abf_expire(PDO $pdo,array $owner,int $id):void{
 $pdo->beginTransaction();
 try{
  $listing=abf_listing($pdo,$owner,$id,true);
  abf_assert($listing['type']==='auction'&&$listing['status']==='active'&&$listing['highest_bidder_id']===null&&$listing['current_bid_cents']===null);
  foreach(['market_bids','market_proxy_bids'] as $table){$q=$pdo->prepare('SELECT COUNT(*) FROM '.$table.' WHERE listing_id=?');$q->execute([$id]);abf_assert((int)$q->fetchColumn()===0);}
  $pdo->prepare("UPDATE market_listings SET ends_at='1000-01-01 00:00:00' WHERE id=?")->execute([$id]);
  $first=$pdo->query("SELECT id FROM market_listings WHERE type='auction' AND status='active' AND ends_at<=UTC_TIMESTAMP() ORDER BY ends_at,id LIMIT 1")->fetchColumn();
  abf_assert((int)$first===$id);$pdo->commit();
 }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
 try{
  $first=$pdo->query("SELECT id FROM market_listings WHERE type='auction' AND status='active' AND ends_at<=UTC_TIMESTAMP() ORDER BY ends_at,id LIMIT 1")->fetchColumn();
  abf_assert((int)$first===$id&&market_close_due($pdo,1)===1);
  relisting_source($pdo,(int)$owner['id'],$id);
 }catch(Throwable $error){
  $pdo->prepare("UPDATE market_listings SET ends_at=?,updated_at=? WHERE id=? AND seller_id=? AND status='active' AND ends_at='1000-01-01 00:00:00'")->execute([$listing['ends_at'],$listing['updated_at'],$id,$owner['id']]);
  throw $error;
 }
}
function abf_unsold(PDO $pdo,array $owner,mixed $category,bool $images):int{
 if(!is_int($category)||$category<1)throw new InvalidArgumentException('Synthetic category required.');
 $q=$pdo->prepare("SELECT id FROM market_categories WHERE id=? AND slug=? AND is_active=1");$q->execute([$category,'abweb-'.substr($owner['username'],6)]);abf_assert((int)$q->fetchColumn()===$category);
 $_SESSION['locale']='de';$quote=auction_billing_quote($pdo,(int)$owner['id']);
 abf_assert(is_array($quote)&&$quote['payload']['tariff']['listing_cents']===50);
 $input=['title'=>'Synthetic browser relisting '.bin2hex(random_bytes(6)),
  'description'=>'Synthetic ordinary folding table for mobile relisting regression tests.',
  'category_id'=>(string)$category,'type'=>'auction','price'=>'10.00','buyout_price'=>'100.00','status'=>'active',
  'zip'=>'10115','city'=>'Synthetic city','country_code'=>'DE','condition_label'=>'used','pickup'=>'1','shipping'=>'1','duration_days'=>'7','accept_rules'=>'1',
  'auction_fee_action'=>'publish','accept_auction_fees'=>'1','auction_fee_quote'=>$quote['hash'],'legal_confirm'=>'1','legal_language'=>'de',
  'legal_documents'=>array_column($quote['documents'],'content_hash','id')];
 $id=market_save_listing($pdo,(int)$owner['id'],$input);
 if($images)abf_image($pdo,$owner,$id);
 abf_expire($pdo,$owner,$id);return $id;
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
   $labels=[];$relistingLabels=[];
   foreach(array_keys(i18n_locales()) as $locale){
    $doc=legal_create($pdo,$admin,$settings['legal_checkout_documents'],'browser-1',$locale,'Synthetic terms '.$locale,str_repeat('Synthetic test document. No actual legal approval. ',10));
    legal_approve($pdo,$admin,$doc,'Synthetic browser fixture review only',gmdate('Y-m-d'),array_fill(0,5,'1'));
    $dictionary=i18n_dictionary($locale);$labels[$locale]=$dictionary['auction_billing.publish'];
    $relistingLabels[$locale]=[];
    foreach(['action','heading','help','confirm','draft_created','free_available','free_used'] as $key){
     abf_assert(is_string($dictionary['relisting.'.$key]??null));$relistingLabels[$locale][$key]=$dictionary['relisting.'.$key];
    }
   }
   $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')->execute(['Synthetic folding tables','abweb-'.$token]);
   $out=['saved'=>$saved,'username'=>$username,'password'=>$password,'category'=>(int)$pdo->lastInsertId(),'labels'=>$labels,'relisting_labels'=>$relistingLabels];
  }catch(Throwable $error){abf_restore($pdo,$saved);throw $error;}
 }elseif($operation==='restore'){
  abf_restore($pdo,$input['saved']);$out=['restored'=>true];
 }elseif(in_array($operation,['state','unsold','expire'],true)){
  $owner=abf_owner($pdo,$input['username']??null);$locale=$input['locale']??'de';
  abf_assert(is_string($locale)&&isset(i18n_locales()[$locale]));
  if($operation==='unsold'){
   $images=$input['images']??false;abf_assert(is_bool($images));
   $id=abf_unsold($pdo,$owner,$input['category']??null,$images);
  }else{
   $id=$input['id']??null;if(!is_int($id)||$id<1)throw new InvalidArgumentException('Synthetic listing identifier required.');
   if($operation==='expire')abf_expire($pdo,$owner,$id);
  }
  $out=abf_state($pdo,$owner,$id,$locale);
 }else throw new InvalidArgumentException('Unknown fixture operation.');
 fwrite(STDOUT,json_encode($out,JSON_THROW_ON_ERROR));
}catch(Throwable $error){fwrite(STDERR,'Auction browser fixture failed: '.get_class($error).' at line '.$error->getLine().PHP_EOL);exit(1);}
