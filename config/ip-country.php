<?php
declare(strict_types=1);

/** Country-only hints supported by the marketplace or its interface languages. */
function ip_country_locales(): array
{
    return [
        'AT'=>'de', 'BE'=>'fr', 'CH'=>'de', 'CZ'=>'cs', 'DE'=>'de', 'DK'=>'da', 'FR'=>'fr',
        'IT'=>'it', 'LI'=>'de', 'LU'=>'fr', 'NL'=>'nl', 'PL'=>'pl',
        'AU'=>'en', 'CA'=>'en', 'GB'=>'en', 'IE'=>'en', 'NZ'=>'en', 'US'=>'en',
    ];
}

/** Read only the web server's socket peer. Request forwarding headers are not used. */
function ip_country_lookup(array $server): ?string
{
    $peer = $server['REMOTE_ADDR'] ?? null;
    if (!is_string($peer) || strlen($peer) > 45 || filter_var($peer, FILTER_VALIDATE_IP) === false) return null;
    $address = inet_pton($peer);
    if ($address === false) return null;

    if (strlen($address) === 16 && substr($address, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff") {
        $address = substr($address, 12);
        $peer = inet_ntop($address);
    }
    if (filter_var($peer, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) return null;
    $addressLength = strlen($address);
    if ($addressLength === 16 && (ord($address[0]) & 0xe0) !== 0x20) return null;

    $stream = @fopen(__DIR__ . '/ip-country/ipv' . ($addressLength === 4 ? '4' : '6') . '.bin', 'rb');
    if ($stream === false) return null;
    try {
        $recordLength = $addressLength * 2 + 2;
        $size = fstat($stream)['size'] ?? 0;
        if ($size < $recordLength || $size > 24 * 1024 * 1024 || $size % $recordLength !== 0) return null;
        $lower = 0;
        $upper = intdiv($size, $recordLength) - 1;
        while ($lower <= $upper) {
            $middle = intdiv($lower + $upper, 2);
            if (fseek($stream, $middle * $recordLength) !== 0) return null;
            $record = fread($stream, $recordLength);
            if ($record === false || strlen($record) !== $recordLength) return null;
            if (strcmp($address, substr($record, 0, $addressLength)) < 0) {
                $upper = $middle - 1;
            } elseif (strcmp($address, substr($record, $addressLength, $addressLength)) > 0) {
                $lower = $middle + 1;
            } else {
                $country = substr($record, -2);
                return isset(ip_country_locales()[$country]) ? $country : null;
            }
        }
        return null;
    } finally {
        fclose($stream);
    }
}
