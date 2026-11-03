<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
try{
    require_once __DIR__.'/../config/bootstrap.php';require_once __DIR__.'/../config/discovery.php';
    maintenance_worker_gate();
    $arguments=array_slice($argv,1);if(($arguments[0]??'')==='--once')array_shift($arguments);
    if(count($arguments)>1||(isset($arguments[0])&&(!ctype_digit($arguments[0])||(int)$arguments[0]<1||(int)$arguments[0]>100)))throw new InvalidArgumentException('Query batch must be1–100.');
    $result=discovery_worker_once($pdo,isset($arguments[0])?(int)$arguments[0]:20,50);
    fwrite(STDOUT,json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL);
    if($result['review']>0){fwrite(STDERR,'Discovery queries require review: '.$result['review'].PHP_EOL);exit(2);}
}catch(Throwable){fwrite(STDERR,"Discovery worker failed; no search criteria or private values were logged.\n");exit(1);}
