<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__ . '/../config/ip-country.php';

if ($argc !== 4 || !preg_match('/\A[0-9a-f]{64}\z/', $argv[3]) || !is_dir($argv[2])) {
    fwrite(STDERR, "Usage: php ops/build-ip-country.php CSV.gz NEW_OUTPUT_DIRECTORY EXPECTED_UNCOMPRESSED_SHA256\n");
    exit(2);
}

$source = gzopen($argv[1], 'rb');
if ($source === false) exit(2);
$countryLocales = ip_country_locales();
$ranges = [4=>'', 16=>''];
$lastEnds = [4=>null, 16=>null];
$digest = hash_init('sha256');

try {
    while (($line = gzgets($source, 256)) !== false) {
        hash_update($digest, $line);
        $columns = explode(',', trim($line));
        if (count($columns) !== 3 || !preg_match('/\A[A-Z]{2}\z/', $columns[2])) {
            throw new RuntimeException('Unexpected source columns.');
        }
        [$first, $last, $country] = $columns;
        $start = inet_pton($first);
        $end = inet_pton($last);
        if ($start === false || $end === false || strlen($start) !== strlen($end) || strcmp($start, $end) > 0) {
            throw new RuntimeException('Invalid range in source.');
        }
        $length = strlen($start);
        if ($lastEnds[$length] !== null && strcmp($start, $lastEnds[$length]) <= 0) {
            throw new RuntimeException('Source ranges are unordered or overlap.');
        }
        $lastEnds[$length] = $end;
        if (isset($countryLocales[$country])) $ranges[$length] .= $start . $end . $country;
    }
    if (!hash_equals($argv[3], hash_final($digest))) throw new RuntimeException('Source checksum mismatch.');
    foreach (['ipv4.bin'=>4, 'ipv6.bin'=>16] as $name => $length) {
        $path = $argv[2] . DIRECTORY_SEPARATOR . $name;
        if (file_exists($path) || is_link($path) || $ranges[$length] === '') {
            throw new RuntimeException('Use a new output directory for the verified country ranges.');
        }
    }
    foreach (['ipv4.bin'=>4, 'ipv6.bin'=>16] as $name => $length) {
        $path = $argv[2] . DIRECTORY_SEPARATOR . $name;
        $output = fopen($path, 'xb');
        if ($output === false) throw new RuntimeException('Cannot create country ranges.');
        $written = fwrite($output, $ranges[$length]);
        fclose($output);
        if ($written !== strlen($ranges[$length])) throw new RuntimeException('Incomplete country ranges.');
        printf("%s %d records sha256=%s\n", $name, intdiv($written, $length * 2 + 2), hash('sha256', $ranges[$length]));
    }
} catch (RuntimeException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
} finally {
    gzclose($source);
}
