<?php
declare(strict_types=1);
/** Read-only source, explicit dry-run/apply, no historical listings or privileges. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
function legacy_source_config(string $file):array {
 if(!is_file($file)||is_link($file)||(fileperms($file)&0077)!==0)throw new RuntimeException('Private regular source configuration required.');
 $allowed=['LEGACY_DB_DATABASE','LEGACY_DB_USERNAME','LEGACY_DB_PASSWORD','LEGACY_DB_HOST','LEGACY_DB_PORT','LEGACY_DB_SOCKET'];$config=[];
 foreach(file($file,FILE_IGNORE_NEW_LINES)?:[] as $line){if($line===''||str_starts_with(ltrim($line),'#'))continue;$pair=explode('=',$line,2);if(count($pair)!==2||!in_array($pair[0],$allowed,true))throw new RuntimeException('Invalid source configuration.');$config[$pair[0]]=$pair[1];}
 foreach(['LEGACY_DB_DATABASE','LEGACY_DB_USERNAME','LEGACY_DB_PASSWORD'] as $key)if(!isset($config[$key])||$config[$key]==='')throw new RuntimeException('Incomplete source configuration.');
 if(!preg_match('/\A[a-zA-Z0-9_]+\z/',$config['LEGACY_DB_DATABASE']))throw new RuntimeException('Invalid source database.');
 return$config;
}
function legacy_import_users(PDO $source,PDO $target,string $fingerprint,int $after,int $limit,bool $apply,bool $preserve):array {
 $columns=$source->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users'")->fetchAll(PDO::FETCH_COLUMN);
 foreach(['id','username','email'] as $required)if(!in_array($required,$columns,true))throw new RuntimeException('Unsupported source user schema.');
 $select=['id','username','email'];foreach(['full_name','zip','city','country_code'] as $name)$select[]=in_array($name,$columns,true)?$name:(($name==='country_code'?"'DE'":"''").' AS '.$name);
 $source->exec('SET SESSION TRANSACTION READ ONLY');$source->beginTransaction();
 try{$rows=$source->query('SELECT '.implode(',',$select).' FROM users WHERE id>'.max(0,$after).' ORDER BY id LIMIT '.max(1,min(1000,$limit)))->fetchAll();}finally{$source->rollBack();}
 $mapExists=(int)$target->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='market_legacy_user_map'")->fetchColumn()===1;
 $summary=['mode'=>$apply?'apply':'dry_run','read'=>count($rows),'importable'=>0,'already_mapped'=>0,'conflicts'=>0,'invalid'=>0,'imported'=>0,'next_after_id'=>$after,'password_reset_required'=>true,'historical_privileges_imported'=>false,'historical_listings_imported'=>false];$candidates=[];$names=[];$emails=[];
 foreach($rows as $row){$id=(int)$row['id'];$summary['next_after_id']=max($summary['next_after_id'],$id);
  if($mapExists){$q=$target->prepare('SELECT target_user_id FROM market_legacy_user_map WHERE source_fingerprint=? AND source_user_id=?');$q->execute([$fingerprint,$id]);if($q->fetchColumn()){$summary['already_mapped']++;continue;}}
  $name=(string)$row['username'];$email=(string)$row['email'];$country=strtoupper((string)$row['country_code']);
  if($id<1||$id>2147483647||!preg_match('/\A[\pL\pN_. -]{3,50}\z/u',$name)||trim($name)!==$name||strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL)||!preg_match('//u',(string)$row['full_name'].(string)$row['city'])||strlen((string)$row['full_name'])>150||strlen((string)$row['city'])>100||strlen((string)$row['zip'])>10||!in_array($country,['DE','AT','CH','FR','BE','NL','LU','DK','PL','CZ','LI'],true)){$summary['invalid']++;continue;}
  $foldedName=mb_strtolower($name,'UTF-8');$foldedEmail=mb_strtolower($email,'UTF-8');
  $q=$target->prepare('SELECT id FROM users WHERE username=? OR email=?'.($preserve?' OR id=?':''));$args=[$name,$email];if($preserve)$args[]=$id;$q->execute($args);
  if($q->fetchColumn()||isset($names[$foldedName])||isset($emails[$foldedEmail])){$summary['conflicts']++;continue;}
  $names[$foldedName]=true;$emails[$foldedEmail]=true;$row['country_code']=$country;$candidates[]=$row;$summary['importable']++;
 }
 if(!$apply)return$summary;
 if(!$mapExists||$summary['invalid']||$summary['conflicts'])throw new RuntimeException('Dry-run review or target migration required; nothing imported.');
 $target->beginTransaction();
 try{
  foreach($candidates as $row){$hash=auth_hash(bin2hex(random_bytes(32)));
   $fields='username,email,password_hash,full_name,zip,city,is_admin,is_verified,is_active,verification_status,auth_version,country_code';$values=[$row['username'],$row['email'],$hash,$row['full_name'],$row['zip'],$row['city'],0,0,1,'none',0,$row['country_code']];
   if($preserve){$fields='id,'.$fields;array_unshift($values,(int)$row['id']);}
   $q=$target->prepare('INSERT INTO users('.$fields.') VALUES('.implode(',',array_fill(0,count($values),'?')).')');$q->execute($values);$targetId=$preserve?(int)$row['id']:(int)$target->lastInsertId();
   $target->prepare('INSERT INTO market_legacy_user_map(source_fingerprint,source_user_id,target_user_id) VALUES(?,?,?)')->execute([$fingerprint,$row['id'],$targetId]);$summary['imported']++;
  }
  $target->commit();return$summary;
 }catch(Throwable $error){if($target->inTransaction())$target->rollBack();throw new RuntimeException('Import transaction stopped; no partial batch was committed.');}
}
if(!defined('GUNINDEX_LEGACY_IMPORT_LIBRARY')){
 $options=getopt('',['source-env:','apply','backup-confirmed','preserve-ids','remap-ids','after-id:','limit:','help']);
 if(isset($options['help'])){fwrite(STDOUT,"Usage: php ops/import-legacy.php --source-env=/private/legacy.env [--after-id=0 --limit=500] [--remap-ids] [--apply --backup-confirmed]\nDefault: read-only report, preserved IDs, no privilege or listing import. Source credentials are never CLI values or output.\n");exit(0);}
 try{
  if(isset($options['apply'])&&!isset($options['backup-confirmed']))throw new RuntimeException('Verified backup required.');
  if(isset($options['preserve-ids'],$options['remap-ids']))throw new RuntimeException('Conflicting ID modes.');
  $after=filter_var($options['after-id']??'0',FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);$limit=filter_var($options['limit']??'500',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000]]);if($after===false||$limit===false)throw new RuntimeException('Invalid batch bounds.');
  $config=legacy_source_config((string)($options['source-env']??''));require_once __DIR__.'/../config/db.php';require_once __DIR__.'/../config/functions.php';require_once __DIR__.'/../config/auth.php';
  $host=$config['LEGACY_DB_HOST']??'127.0.0.1';$port=$config['LEGACY_DB_PORT']??'3306';$socket=$config['LEGACY_DB_SOCKET']??'';
  if($socket===''&&(!preg_match('/\A[a-zA-Z0-9.:-]+\z/',$host)||!ctype_digit($port)))throw new RuntimeException('Invalid source endpoint.');
  $dsn=($socket!==''?'mysql:unix_socket='.$socket:'mysql:host='.$host.';port='.$port).';dbname='.$config['LEGACY_DB_DATABASE'].';charset=utf8mb4';
  $source=new PDO($dsn,$config['LEGACY_DB_USERNAME'],$config['LEGACY_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
  if($config['LEGACY_DB_DATABASE']===$database&&($socket!==''&&$socket===getenv('DB_SOCKET')||$host===(getenv('DB_HOST')?:'127.0.0.1')))throw new RuntimeException('Source and target must differ.');
  $fingerprint=hash('sha256',$host.':'.$port.':'.$socket.':'.$config['LEGACY_DB_DATABASE']);$summary=legacy_import_users($source,$pdo,$fingerprint,$after,$limit,isset($options['apply']),!isset($options['remap-ids']));
  fwrite(STDOUT,json_encode($summary,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL);
 }catch(Throwable){fwrite(STDERR,"Legacy import stopped. Review the aggregate dry-run and private configuration. No existing account, source data or privilege was overwritten.\n");exit(1);}
}
