<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit(1);}
require_once __DIR__.'/../config/bootstrap.php';
try {
    maintenance_worker_gate();
    if (paypal_refund_next($pdo,payments_config())) fwrite(STDOUT,"PayPal refund queue advanced.\n");
} catch (Throwable) {
    fwrite(STDERR,"PayPal refund worker failed; inspect restricted logs.\n");
    exit(1);
}
