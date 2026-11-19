<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY !== 'Linux') {
    http_response_code(404);
    exit(2);
}
require_once __DIR__.'/../config/payments.php';
require_once __DIR__.'/../config/bitcoin_rpc.php';

$phase = $argv[1] ?? '';
$port = filter_var($argv[2] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65535]]);
$cookie = $argv[3] ?? '';
if (!in_array($phase, ['allocate', 'pending', 'confirmed', 'reorg'], true)
    || $port === false || !preg_match('~\A/tmp/gunindex-bitcoin-regtest\.[A-Za-z0-9]{8}/node/regtest/\.cookie\z~D', $cookie)
    || !is_file($cookie) || is_link($cookie)) {
    exit(2);
}

try {
    $auth = file_get_contents($cookie, false, null, 0, 256);
    if (!is_string($auth) || !preg_match('/\A__cookie__:([0-9a-f]{64})\s*\z/D', $auth, $matches)) {
        throw new RuntimeException('Fixture cookie is invalid.');
    }
    $settings = [
        'rpc_url' => 'http://127.0.0.1:'.$port,
        'rpc_user' => '__cookie__',
        'rpc_password' => $matches[1],
        'wallet' => 'gunindex-watch',
        'chain' => 'regtest',
    ];
    $node = new MarketBitcoin($settings);
    if ($phase === 'allocate') {
        $settings['wallet'] = 'gunindex-funding';
        $privateWalletRejected = false;
        try {
            (new MarketBitcoin($settings))->assertReady();
        } catch (PaymentUnavailable|PaymentConflict) {
            $privateWalletRejected = true;
        }
        if (!$privateWalletRejected) {
            throw new RuntimeException('The adapter accepted a wallet with private keys.');
        }
        $reference = 'GI-BTC-'.strtoupper(bin2hex(random_bytes(12)));
        $address = $node->address($reference);
        if ($node->address($reference) !== $address || !str_starts_with($address, 'bcrt1q')) {
            throw new RuntimeException('Address allocation is not idempotent on regtest.');
        }
        echo $address;
        exit(0);
    }

    $address = $argv[4] ?? '';
    $txid = $argv[5] ?? '';
    if (!str_starts_with($address, 'bcrt1q') || !preg_match('/\A[0-9a-f]{64}\z/', $txid)) {
        throw new RuntimeException('Fixture address or transaction is invalid.');
    }
    $receipts = $node->receipts($address, $phase === 'reorg' ? $txid : null);
    if ($phase === 'reorg' && $receipts === []) {
        echo "Regtest receipt is absent after invalidation.\n";
        exit(0);
    }
    if (count($receipts) !== 1 || ($receipts[0]['txid'] ?? null) !== $txid
        || ($receipts[0]['satoshis'] ?? null) !== 123456 || !is_int($receipts[0]['vout'] ?? null)
        || ($receipts[0]['vout'] ?? -1) < 0 || ($receipts[0]['received_at'] ?? 0) <= 0
        || !array_key_exists('block_hash', $receipts[0])) {
        throw new RuntimeException('Receipt does not match the fixture output.');
    }
    $receipt = $receipts[0];
    if ($phase === 'confirmed') {
        $block = $argv[6] ?? '';
        if (!preg_match('/\A[0-9a-f]{64}\z/', $block) || $receipt['block_hash'] !== $block
            || $receipt['confirmations'] !== 1) {
            throw new RuntimeException('The output is not confirmed in the mined block.');
        }
    } elseif ($receipt['confirmations'] !== 0 || $receipt['block_hash'] !== null) {
        throw new RuntimeException('The unmined or invalidated output still has confirmations.');
    }
    printf("Regtest receipt passed during %s.\n", $phase);
} catch (Throwable $error) {
    if (get_class($error) === RuntimeException::class) {
        fprintf(STDERR, "Regtest probe: %s\n", $error->getMessage());
    } else {
        fprintf(STDERR, "Regtest probe %s failed (%s at line %d).\n", $phase, get_class($error), $error->getLine());
    }
    exit(1);
}
