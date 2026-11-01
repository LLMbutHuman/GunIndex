<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/business.php';
try{$result=business_worker_once($pdo);fwrite(STDOUT,'Business expirations: '.$result['changed'].'; payment reviews: '.$result['payment_reviews'].PHP_EOL);}catch(Throwable){fwrite(STDERR,"Business worker failed; inspect restricted logs.\n");exit(1);}
