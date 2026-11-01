<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
require_once __DIR__.'/../config/bootstrap.php';
try{
    $arguments=array_slice($argv,1);if(($arguments[0]??'')==='--once')array_shift($arguments);
    $limit=$arguments===[]?25:filter_var($arguments[0],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100]]);
    if(count($arguments)>1||$limit===false)throw new InvalidArgumentException();
    echo json_encode(mail_worker_once($pdo,(int)$limit),JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable){fwrite(STDERR,"Mail queue processing failed; no payloads were logged.\n");exit(1);}
