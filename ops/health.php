<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
require_once __DIR__.'/../config/db.php';
function ops_health(PDO $pdo,bool $coreOnly=false):array {
 $checks=['database'=>false,'private_storage'=>false];$warnings=[];$queues=[];$workers=[];
 try{$checks['database']=(int)$pdo->query('SELECT 1')->fetchColumn()===1;}catch(Throwable){}
 $directories=[getenv('AUTH_RATE_LIMIT_DIR')?:'/var/lib/gunindex/limits',getenv('GENERAL_IMAGE_DIR')?:'/var/lib/gunindex/images',getenv('GENERAL_PRIVATE_DIR')?:'/var/lib/gunindex/private'];
 $checks['private_storage']=true;foreach($directories as $directory){if(!is_dir($directory)||is_link($directory)||!is_writable($directory)){$checks['private_storage']=false;break;} $file=$directory.'/.health-'.bin2hex(random_bytes(6));$handle=@fopen($file,'x');if(!$handle){$checks['private_storage']=false;break;}fclose($handle);unlink($file);}
 if(!$coreOnly){
  if((getenv('MAIL_TRANSPORT')?:'none')==='none')$warnings[]='mail_not_configured';
  try{$exists=$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='market_mail_queue'")->fetchColumn();if($exists){$queues['mail']=$pdo->query('SELECT status,COUNT(*) AS count FROM market_mail_queue GROUP BY status')->fetchAll();$old=(int)$pdo->query("SELECT COUNT(*) FROM market_mail_queue WHERE status IN('pending','processing') AND created_at<UTC_TIMESTAMP()-INTERVAL 1 HOUR")->fetchColumn();if($old>0)$warnings[]='old_mail_backlog';}}catch(Throwable){$warnings[]='queue_check_failed';}
  if(getenv('APP_ENV')==='production')foreach(['auction'=>120,'mail'=>180,'discovery'=>240,'business'=>180,'privacy'=>900] as $worker=>$maximum){if(!is_file(__DIR__.'/'.$worker.'-worker.php'))continue;$ok='/var/lib/gunindex/heartbeats/'.$worker.'.ok';$failed='/var/lib/gunindex/heartbeats/'.$worker.'.failed';$time=is_file($ok)?(int)file_get_contents($ok):0;$workers[$worker]=['last_success_age_seconds'=>$time?max(0,time()-$time):null,'recent_failure'=>is_file($failed)&&(int)file_get_contents($failed)>$time];if(!$time||time()-$time>$maximum||$workers[$worker]['recent_failure'])$warnings[]='worker_'.$worker.'_needs_review';}
 }
 $healthy=!in_array(false,$checks,true);return['status'=>!$healthy?'unavailable':($warnings?'degraded':'ok'),'checked_at'=>gmdate('c'),'checks'=>$checks,'warnings'=>$warnings,'queues'=>$queues,'workers'=>$workers];
}
if(!defined('GUNINDEX_HEALTH_LIBRARY')){$result=ops_health($pdo,in_array('--core-only',$argv,true));fwrite(STDOUT,json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL);exit($result['status']==='ok'?0:($result['status']==='degraded'?2:1));}
