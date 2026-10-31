<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
try {
    require_once __DIR__.'/../config/bootstrap.php';
    $args=array_slice($argv,1);
    if (($args[0] ?? '')==='--once') array_shift($args);
    if (count($args)>1 || (isset($args[0]) && (!ctype_digit($args[0]) || (int)$args[0]<1 || (int)$args[0]>1000))) throw new InvalidArgumentException('Batch size must be 1–1000.');
    $closed=market_close_due($pdo,isset($args[0])?(int)$args[0]:100);
    fwrite(STDOUT,'Closed general auctions: '.$closed.PHP_EOL);
    $conflicts=market_worker_conflicts();
    if ($conflicts) {
        fwrite(STDERR,'Auctions moved to manual review: '.count($conflicts).PHP_EOL);
        exit(2);
    }
} catch (Throwable $error) {
    fwrite(STDERR,'Auction worker failed; no private bid values were logged.'.PHP_EOL);
    exit(1);
}
