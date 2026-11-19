<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit(1);}
require_once __DIR__.'/../config/bootstrap.php';
try {
    maintenance_worker_gate();
    if (bitcoin_next($pdo,bitcoin_config())) fwrite(STDOUT,"Bitcoin payment queue advanced.\n");
} catch (Throwable) {
    fwrite(STDERR,"Bitcoin worker failed; inspect restricted logs.\n");
    exit(1);
}
