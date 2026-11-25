<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__.'/../config/geo.php';

function austrian_source(string $path, string $sha256, int $limit): string
{
    if (!is_file($path) || is_link($path) || !preg_match('/\A[0-9a-f]{64}\z/', $sha256) || filesize($path) > $limit) {
        throw new InvalidArgumentException('Provide verified input files with lowercase SHA-256 digests.');
    }
    $bytes = file_get_contents($path, false, null, 0, $limit + 1);
    if ($bytes === false || strlen($bytes) > $limit || !hash_equals($sha256, hash('sha256', $bytes))) {
        throw new RuntimeException('Input does not match its reviewed SHA-256 digest.');
    }
    return $bytes;
}

try {
    $options = getopt('', ['geonames:', 'rtr:', 'geonames-sha256:', 'rtr-sha256:', 'date:', 'output:']);
    foreach (['geonames','rtr','geonames-sha256','rtr-sha256','date','output'] as $option) {
        if (!is_string($options[$option] ?? null)) throw new InvalidArgumentException("Missing --$option.");
    }
    $date = $options['date'];
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Use a snapshot date as YYYY-MM-DD.');
    $register = json_decode(austrian_source($options['rtr'], $options['rtr-sha256'], 3000000), true, 32, JSON_THROW_ON_ERROR);
    $data = $register['data'] ?? null;
    if (($register['status'] ?? null) !== 200 || !is_array($data) || !$data
        || count($data) !== ($register['version']['total_rows'] ?? null)
        || !is_string($register['version']['published'] ?? null)
        || substr($register['version']['published'], 0, 10) > $date) {
        throw new RuntimeException('Expected a complete RTR register published by the snapshot date.');
    }
    $names = [];
    foreach ($data as $row) {
        if (($row['plztyp'] ?? null) !== 'PLZ-Adressierung' || ($row['adressierbar'] ?? null) !== 'Ja') continue;
        [$start, $end] = [$row['gueltigab'] ?? null, $row['gueltigbis'] ?? null];
        if (!is_string($start) || $start > $date || ($end !== null && $end < $date)) continue;
        $postal = (string)($row['plz'] ?? '');
        $name = trim((string)($row['ort'] ?? ''));
        if (!geo_validate_postcode('AT', $postal) || !preg_match('/\A[^\x00-\x1f\x7f]{1,150}\z/u', $name)) {
            throw new RuntimeException('The register contains an invalid postcode or town name.');
        }
        if (!isset($names[$postal]) || strcmp($name, $names[$postal]) < 0) $names[$postal] = $name;
    }
    $archivePath = $options['geonames'];
    austrian_source($archivePath, $options['geonames-sha256'], 1048576);
    $zip = new ZipArchive();
    if ($zip->open($archivePath, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) throw new RuntimeException('Cannot read GeoNames zip.');
    try {
        $entry = $zip->statName('AT.txt');
        if (!$entry || $entry['size'] > 5000000) throw new RuntimeException('Unexpected size of GeoNames AT.txt.');
        $contents = $zip->getFromName('AT.txt');
    } finally {
        $zip->close();
    }
    if (!is_string($contents) || !preg_match('//u', $contents)
        || !hash_equals($options['geonames-sha256'], hash_file('sha256', $archivePath))) {
        throw new RuntimeException('GeoNames archive failed integrity or encoding checks.');
    }
    $points = [];
    foreach (preg_split('/\r?\n/', rtrim($contents, "\r\n")) as $line) {
        $columns = explode("\t", $line);
        if (count($columns) !== 12 || $columns[0] !== 'AT' || !geo_validate_postcode('AT', $columns[1])) {
            throw new RuntimeException('Invalid GeoNames AT.txt record.');
        }
        $postal = $columns[1];
        if (!isset($names[$postal]) || !in_array($columns[11], ['4','6'], true)) continue;
        [$latitude, $longitude] = [$columns[9], $columns[10]];
        if (!geo_coordinates_valid($latitude, $longitude) || (float)$latitude < 46.3 || (float)$latitude > 49.1
            || (float)$longitude < 9.4 || (float)$longitude > 17.3) {
            throw new RuntimeException('Selected GeoNames record is outside the expected Austrian bounds.');
        }
        $key = number_format((float)$latitude, 7, '.', '').','.number_format((float)$longitude, 7, '.', '');
        $points[$postal][(int)$columns[11]][$key] = [(float)$latitude, (float)$longitude];
    }
    if (!$points) throw new RuntimeException('No active postcodes with eligible coordinates found.');
    ksort($points, SORT_STRING);
    $target = fopen($options['output'], 'xb');
    if ($target === false) throw new RuntimeException('Choose a new output file.');
    try {
        $rows = [['postal_code','city','latitude','longitude']];
        foreach ($points as $postal => $candidates) {
            $best = $candidates[6] ?? $candidates[4];
            ksort($best, SORT_STRING);
            $rows[] = [$postal, $names[$postal],
                number_format(array_sum(array_column($best, 0)) / count($best), 7, '.', ''),
                number_format(array_sum(array_column($best, 1)) / count($best), 7, '.', '')];
        }
        foreach ($rows as $row) {
            if (fputcsv($target, $row, ';', '"', '', "\n") === false) throw new RuntimeException('Could not write all postcode rows.');
        }
        if (!fflush($target)) throw new RuntimeException('Could not flush postcode output.');
    } finally {
        fclose($target);
    }
    printf("Prepared %d of %d active RTR postcodes with accepted GeoNames points.\n", count($points), count($names));
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
