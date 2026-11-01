<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
define('GUNINDEX_HEALTH_LIBRARY',true);require_once __DIR__.'/health.php';
$result=ops_health($pdo,false);fwrite(STDOUT,json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL);
if($result['status']!=='ok')error_log('GunIndex monitor: '.$result['status'].'; '.implode(',',$result['warnings']));
exit($result['status']==='ok'?0:($result['status']==='degraded'?2:1));
