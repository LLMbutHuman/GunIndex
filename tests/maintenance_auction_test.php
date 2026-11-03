<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('DB_DATABASE')!=='gunindex_rebuild_test')exit(2);
require_once __DIR__.'/../config/bootstrap.php';
define('GUNINDEX_HEALTH_LIBRARY',true);require_once __DIR__.'/../ops/health.php';
if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='gunindex_rebuild_test')exit(2);
function ma_check(bool $value,string $reason):void{if(!$value)throw new RuntimeException($reason);}
function ma_scalar(PDO $pdo,string $sql,array $args=[]):mixed{$q=$pdo->prepare($sql);$q->execute($args);return $q->fetchColumn();}
function ma_set(PDO $pdo,int $actor,string $value):void{
 $pdo->beginTransaction();try{maintenance_transition($pdo,$actor,site_settings($pdo,true)['maintenance_enabled'],$value);$pdo->commit();site_settings($pdo,true);}
 catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}
function ma_user(PDO $pdo,bool $admin):int{
 $name='ma_'.bin2hex(random_bytes(8));$pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,is_admin) VALUES (?,?,?,'Synthetic maintenance auction fixture','10115','Synthetic city',?)")->execute([$name,$name.'@example.invalid',auth_hash('Synthetic '.bin2hex(random_bytes(20))),(int)$admin]);return(int)$pdo->lastInsertId();
}
$original=$pdo->query("SELECT setting_value,updated_at FROM system_settings WHERE setting_key='maintenance_enabled'")->fetch();
ma_check(($original['setting_value']??'0')==='0','Initially available isolated runtime required.');
$admin=ma_user($pdo,true);$seller=ma_user($pdo,false);$buyer=ma_user($pdo,false);$passed=0;$failure=null;$name='setup';
$pdo->prepare("INSERT INTO market_categories(name,slug) VALUES ('Synthetic folding tables',?)")->execute(['ma-'.bin2hex(random_bytes(8))]);$category=(int)$pdo->lastInsertId();
$listing=static function(string $status,string $type,int $seconds)use($pdo,$seller,$category):int{
 $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,buyout_cents,status,zip,city,country_code,ends_at) VALUES (?,?,'Synthetic folding table','Synthetic ordinary fixture for maintenance clock tests.',?,1000,2000,?,'10115','Synthetic city','DE',TIMESTAMPADD(SECOND,?,UTC_TIMESTAMP()))")->execute([$seller,$category,$type,$status,$seconds]);return(int)$pdo->lastInsertId();
};
try{
 $active=$listing('active','auction',3600);$paused=$listing('paused','auction',3600);$expired=$listing('active','auction',-3600);$ended=$listing('ended','auction',3600);$fixed=$listing('active','fixed',3600);
 $ids=[$active,$paused,$expired,$ended,$fixed];$before=[];foreach($ids as $id)$before[$id]=ma_scalar($pdo,'SELECT ends_at FROM market_listings WHERE id=?',[$id]);
 $tests=[
  'read_lease_blocks_activation_until_inflight_work_finishes'=>static function()use($pdo,$admin):void{
   $lease=maintenance_acquire_lease();ma_check($lease instanceof PDO,'Available runtime did not grant lease.');
   $timeout=(int)$pdo->query('SELECT @@SESSION.innodb_lock_wait_timeout')->fetchColumn();$pdo->exec('SET SESSION innodb_lock_wait_timeout=1');
   try{
    $blocked=false;try{ma_set($pdo,$admin,'1');}catch(PDOException $error){if((int)($error->errorInfo[1]??0)!==1205)throw $error;$blocked=true;}
    ma_check($blocked,'Exclusive transition did not wait for the independent read lease.');
    ma_check(ma_scalar($pdo,'SELECT active FROM market_maintenance_state WHERE id=1')==0,'Timed-out activation changed state.');
   }finally{if($pdo->inTransaction())$pdo->rollBack();$lease->rollBack();unset($GLOBALS['maintenance_runtime_lease']);$pdo->exec('SET SESSION innodb_lock_wait_timeout='.$timeout);}
  },
  'unprivileged_transition_rolls_back_without_opening_a_window'=>static function()use($pdo,$seller):void{
   $count=(int)$pdo->query('SELECT COUNT(*) FROM market_maintenance_windows')->fetchColumn();$rejected=false;
   try{ma_set($pdo,$seller,'1');}catch(DomainException){$rejected=true;}
   ma_check($rejected&&(int)$pdo->query('SELECT COUNT(*) FROM market_maintenance_windows')->fetchColumn()===$count,'Unauthorized maintenance mutation.');
  },
  'activation_snapshots_only_unexpired_live_or_paused_auctions'=>static function()use($pdo,$admin,$active,$paused,$expired,$ended,$fixed):void{
   ma_set($pdo,$admin,'1');$window=(int)ma_scalar($pdo,'SELECT window_id FROM market_maintenance_state WHERE id=1');
   foreach([$active,$paused,$expired,$ended,$fixed] as $id){$expected=in_array($id,[$active,$paused],true)?1:0;ma_check((int)ma_scalar($pdo,'SELECT COUNT(*) FROM market_maintenance_auctions WHERE window_id=? AND listing_id=?',[$window,$id])===$expected,'Incorrect deadline snapshot eligibility.');}
   ma_check(maintenance_acquire_lease()===null,'Active maintenance granted a new lease.');
   $health=ops_health($pdo);ma_check($health['checks']['maintenance_state']&&$health['maintenance']['active']&&$health['maintenance']['window_id']===$window,'Monitoring does not identify the active pause.');
   ma_set($pdo,$admin,'1');ma_check((int)ma_scalar($pdo,'SELECT window_id FROM market_maintenance_state WHERE id=1')===$window,'Repeated activation opened a second window.');
  },
  'direct_market_calls_and_close_worker_cannot_trade_while_paused'=>static function()use($pdo,$active,$seller,$buyer):void{
   foreach([fn()=>market_bid($pdo,$active,$buyer,1500),fn()=>market_buy_now($pdo,$active,$buyer),fn()=>market_transition($pdo,$active,$seller,'pause')] as $operation){$rejected=false;try{$operation();}catch(MaintenancePaused){$rejected=true;}ma_check($rejected&&!$pdo->inTransaction(),'Market mutation escaped the barrier or leaked transaction.');}
   ma_check(market_close_due($pdo)===0,'Paused close worker changed auctions.');
   ma_check((int)ma_scalar($pdo,'SELECT COUNT(*) FROM market_bids WHERE listing_id=?',[$active])===0,'Paused trade stored a bid.');
  },
  'all_scheduler_entrypoints_defer_without_side_effects'=>static function():void{
   foreach(['auction','mail','discovery','business','privacy'] as $worker){
    $process=proc_open([PHP_BINARY,dirname(__DIR__).'/ops/'.$worker.'-worker.php','--once'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);ma_check(is_resource($process),'Worker process unavailable.');fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);ma_check(proc_close($process)===0&&$error===''&&str_contains($out,'Worker deferred:'),'Paused worker did not safely defer: '.$worker);
   }
  },
  'resume_extends_each_captured_deadline_exactly_once'=>static function()use($pdo,$admin,$active,$paused,$expired,$ended,$fixed,$before):void{
   $window=(int)ma_scalar($pdo,'SELECT window_id FROM market_maintenance_state WHERE id=1');
   // Move only this synthetic window's start to test elapsed-time restoration without sleeping.
   $pdo->prepare('UPDATE market_maintenance_windows SET started_at=TIMESTAMPADD(SECOND,-61,UTC_TIMESTAMP(6)) WHERE id=?')->execute([$window]);
   ma_set($pdo,$admin,'0');$seconds=(int)ma_scalar($pdo,'SELECT duration_seconds FROM market_maintenance_windows WHERE id=?',[$window]);ma_check($seconds>=61&&$seconds<=65,'Unexpected synthetic duration.');
   foreach([$active,$paused,$expired,$ended,$fixed] as $id){$actual=ma_scalar($pdo,'SELECT ends_at FROM market_listings WHERE id=?',[$id]);$expected=in_array($id,[$active,$paused],true)?gmdate('Y-m-d H:i:s',strtotime($before[$id].' UTC')+$seconds):$before[$id];ma_check($actual===$expected,'Deadline extended incorrectly.');}
   $after=ma_scalar($pdo,'SELECT ends_at FROM market_listings WHERE id=?',[$active]);ma_set($pdo,$admin,'0');ma_check(ma_scalar($pdo,'SELECT ends_at FROM market_listings WHERE id=?',[$active])===$after,'Repeated resume extended twice.');
   ma_check(ops_health($pdo)['maintenance']['active']===false,'Monitoring still reports a pause after resume.');
   ma_check((int)ma_scalar($pdo,"SELECT COUNT(*) FROM admin_logs WHERE action_type='maintenance.ended' AND target_id=?",[$window])===1,'Missing/duplicate close audit.');
  },
  'a_mismatched_raw_flag_fails_closed'=>static function()use($pdo):void{
   $pdo->beginTransaction();try{
    $pdo->exec("INSERT INTO system_settings(setting_key,setting_value) VALUES ('maintenance_enabled','1') ON DUPLICATE KEY UPDATE setting_value='1'");$rejected=false;
    try{maintenance_assert_market_available($pdo);}catch(MaintenanceStateConflict){$rejected=true;}ma_check($rejected,'Raw configuration bypassed state integrity.');
   }finally{$pdo->rollBack();}
  },
  'normal_trading_resumes_without_changing_existing_bids'=>static function()use($pdo,$active,$buyer):void{
   market_bid($pdo,$active,$buyer,1500);ma_check((int)ma_scalar($pdo,'SELECT COUNT(*) FROM market_proxy_bids WHERE listing_id=?',[$active])===1,'Trading did not resume.');
  },
  'an_open_window_cannot_masquerade_as_resumed'=>static function()use($pdo):void{
   $pdo->beginTransaction();try{
    $pdo->exec('UPDATE market_maintenance_windows SET ended_at=NULL WHERE id=(SELECT window_id FROM market_maintenance_state WHERE id=1)');$rejected=false;
    try{maintenance_state($pdo);}catch(MaintenanceStateConflict){$rejected=true;}
    ma_check($rejected&&ops_health($pdo)['checks']['maintenance_state']===false,'Inconsistent open window was accepted by guard or monitoring.');
   }finally{$pdo->rollBack();}
  },
 ];
 foreach($tests as $name=>$run){$run();$passed++;fwrite(STDOUT,'PASS '.$name.PHP_EOL);}fwrite(STDOUT,'Maintenance auction checks passed: '.$passed.PHP_EOL);
}catch(Throwable $error){$failure=$error;}
finally{
 if($pdo->inTransaction())$pdo->rollBack();$lease=$GLOBALS['maintenance_runtime_lease']??null;if($lease instanceof PDO&&$lease->inTransaction())$lease->rollBack();unset($GLOBALS['maintenance_runtime_lease']);
 ma_set($pdo,$admin,'0');
 if($original===false)$pdo->exec("DELETE FROM system_settings WHERE setting_key='maintenance_enabled'");
 else $pdo->prepare("UPDATE system_settings SET setting_value=?,updated_at=? WHERE setting_key='maintenance_enabled'")->execute([$original['setting_value'],$original['updated_at']]);
 site_settings($pdo,true);
}
if($failure){fwrite(STDERR,'FAIL '.$name.' ('.get_class($failure).' at '.$failure->getLine().'): '.$failure->getMessage().PHP_EOL);exit(1);}
