<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../config/crypto.php';
require_once __DIR__.'/../config/mail.php';
require_once __DIR__.'/../config/mail_setup.php';
require_once __DIR__.'/../config/worker_health.php';
function ops_health(PDO $pdo,bool $coreOnly=false):array {
 $checks=['database'=>false,'private_storage'=>false];$warnings=[];$queues=[];$workers=[];$maintenance=null;
 try{$checks['database']=(int)$pdo->query('SELECT 1')->fetchColumn()===1;}catch(Throwable){}
 $directories=[getenv('AUTH_RATE_LIMIT_DIR')?:'/var/lib/gunindex/limits',getenv('GENERAL_IMAGE_DIR')?:'/var/lib/gunindex/images',getenv('GENERAL_PRIVATE_DIR')?:'/var/lib/gunindex/private'];
 $checks['private_storage']=true;foreach($directories as $directory){if(!is_dir($directory)||is_link($directory)||!is_writable($directory)){$checks['private_storage']=false;break;} $file=$directory.'/.health-'.bin2hex(random_bytes(6));$handle=@fopen($file,'x');if(!$handle){$checks['private_storage']=false;break;}fclose($handle);unlink($file);}
 if(!$coreOnly){
  try{
   $state=$pdo->query("SELECT s.active,s.window_id,w.started_at,w.ended_at,COALESCE((SELECT setting_value FROM system_settings WHERE setting_key='maintenance_enabled'),'0') AS configured FROM market_maintenance_state s LEFT JOIN market_maintenance_windows w ON w.id=s.window_id WHERE s.id=1")->fetch();
   $checks['maintenance_state']=$state&&in_array($state['configured'],['0','1'],true)&&(int)$state['active']===(int)$state['configured']&&((int)$state['active']===1?($state['window_id']!==null&&$state['ended_at']===null):($state['window_id']===null||$state['ended_at']!==null));
   if($checks['maintenance_state'])$maintenance=['active'=>(int)$state['active']===1,'window_id'=>$state['window_id']===null?null:(int)$state['window_id'],'started_at'=>(int)$state['active']===1?$state['started_at']:null];
  }catch(Throwable){$checks['maintenance_state']=false;}
  try {
   $transport = mail_runtime_config($pdo);
   if ($transport['mode'] === 'off') $warnings[] = 'mail_not_configured';
   elseif (mail_transport_reason($pdo, $transport) !== null) $warnings[] = 'mail_configuration_invalid';
  } catch (Throwable) {
   $warnings[] = 'mail_configuration_invalid';
  }
  try{$exists=$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='market_mail_queue'")->fetchColumn();if($exists){$queues['mail']=$pdo->query('SELECT status,COUNT(*) AS count FROM market_mail_queue GROUP BY status')->fetchAll();$old=(int)$pdo->query("SELECT COUNT(*) FROM market_mail_queue WHERE status IN('pending','processing') AND created_at<UTC_TIMESTAMP()-INTERVAL 1 HOUR")->fetchColumn();if($old>0)$warnings[]='old_mail_backlog';}}catch(Throwable){$warnings[]='queue_check_failed';}
  if(getenv('APP_ENV')==='production'){
   $now=time();
   foreach(worker_heartbeat_limits() as $worker=>$maximum){
    $base='/var/lib/gunindex/heartbeats/'.$worker;
    $workers[$worker]=worker_heartbeat_status(worker_read_heartbeat($base.'.ok'),worker_read_heartbeat($base.'.failed'),$now,$maximum);
    if(!$workers[$worker]['ready'])$warnings[]='worker_'.$worker.'_needs_review';
   }
  }
 }
 foreach($workers as &$workerState)$workerState['mode']=($maintenance['active']??false)?'paused':'running';unset($workerState);
 $healthy=!in_array(false,$checks,true);return['status'=>!$healthy?'unavailable':($warnings?'degraded':'ok'),'checked_at'=>gmdate('c'),'checks'=>$checks,'warnings'=>$warnings,'queues'=>$queues,'workers'=>$workers,'maintenance'=>$maintenance];
}
if (!defined('GUNINDEX_HEALTH_LIBRARY')) {
 $installation = in_array('--installation', $argv, true);
 $result = ops_health($pdo, !$installation && in_array('--core-only', $argv, true));
 fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);
 if ($installation) {
  $ready = $result['status'] !== 'unavailable'
   && $result['workers'] !== []
   && !in_array(false, array_column($result['workers'], 'ready'), true);
  exit($ready ? 0 : 1);
 }
 exit($result['status'] === 'ok' ? 0 : ($result['status'] === 'degraded' ? 2 : 1));
}
