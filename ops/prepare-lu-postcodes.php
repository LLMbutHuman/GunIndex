<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__.'/../config/geo.php';

try {
    $options = getopt('', ['file:', 'output:', 'expected-source-sha256:']);
    $source = $options['file'] ?? null;
    $output = $options['output'] ?? null;
    $sha256 = $options['expected-source-sha256'] ?? null;
    if (!is_string($source) || !is_file($source) || is_link($source) || filesize($source) > 33554432
        || !is_string($output) || !is_string($sha256) || !preg_match('/\A[0-9a-f]{64}\z/', $sha256)
        || !hash_equals($sha256, hash_file('sha256', $source))) {
        throw new InvalidArgumentException('Provide the verified BD-Adresses CSV, its expected SHA-256 and a new output path.');
    }
    $file = fopen($source, 'rb');
    if ($file === false) throw new RuntimeException('Cannot read BD-Adresses.');
    $header = ['rue','numero','localite','code_postal','id_caclr_rue','id_caclr_bat','lat_wgs84','lon_wgs84',
        'coord_est_luref','coord_nord_luref','id_geoportail','commune','lau2'];
    $schema = fgetcsv($file, 0, ';', '"', '');
    if (is_array($schema)) $schema[0] = preg_replace('/\A\xEF\xBB\xBF/', '', (string)$schema[0]);
    if ($schema !== $header) throw new RuntimeException('Expected the BD-Adresses CSV with WGS84 coordinates.');
    $postcodes = [];
    $records = 0;
    $missing = 0;
    while (($row = fgetcsv($file, 0, ';', '"', '')) !== false) {
        $records++;
        if (count($row) !== count($header)) throw new RuntimeException('A BD-Adresses row does not match the approved layout.');
        [,, $town, $postal,,, $lat, $lon] = $row;
        if ($postal === '' || $lat === '' || $lon === '') {
            $missing++;
            continue;
        }
        if (!geo_validate_postcode('LU', $postal) || !preg_match('/\A[^\x00-\x1f\x7f]{1,150}\z/u', $town)
            || !geo_coordinates_valid($lat, $lon) || (float)$lat < 49.4 || (float)$lat > 50.2
            || (float)$lon < 5.7 || (float)$lon > 6.6) {
            throw new RuntimeException('A BD-Adresses record is outside the expected postcode or coordinate range.');
        }
        $postcodes[$postal] ??= ['lat'=>0.0,'lon'=>0.0,'count'=>0,'names'=>[],'points'=>[]];
        $entry = &$postcodes[$postal];
        $entry['lat'] += (float)$lat;
        $entry['lon'] += (float)$lon;
        $entry['count']++;
        $entry['names'][$town] = 1 + ($entry['names'][$town] ?? 0);
        // A postcode supported by only one or two points is too close to individual addresses.
        if (count($entry['points']) < 3) {
            $key = number_format((float)$lat, 7, '.', '').','.number_format((float)$lon, 7, '.', '');
            $entry['points'][$key] = true;
        }
        unset($entry);
    }
    fclose($file);
    if (!$postcodes || !hash_equals($sha256, hash_file('sha256', $source))) throw new RuntimeException('Missing or changed BD-Adresses source.');
    ksort($postcodes, SORT_STRING);
    $rows = [['postal_code','city','latitude','longitude']];
    foreach ($postcodes as $postal => $entry) {
        if (count($entry['points']) < 3) continue;
        ksort($entry['names'], SORT_STRING);
        arsort($entry['names'], SORT_NUMERIC);
        $rows[] = [$postal, array_key_first($entry['names']),
            number_format($entry['lat'] / $entry['count'], 7, '.', ''),
            number_format($entry['lon'] / $entry['count'], 7, '.', '')];
    }
    if (count($rows) === 1) throw new RuntimeException('No postcode has enough coordinate points.');
    $target = fopen($output, 'xb');
    if ($target === false) throw new RuntimeException('Choose a new output file.');
    try {
        foreach ($rows as $row) {
            if (fputcsv($target, $row, ';', '"', '', "\n") === false) throw new RuntimeException('Could not write all postcode rows.');
        }
        if (!fflush($target)) throw new RuntimeException('Could not flush postcode output.');
    } finally {
        fclose($target);
    }
    printf("Prepared %d of %d postcodes from %d rows. %d source rows lacked a postcode or coordinates.\n",
        count($rows)-1, count($postcodes), $records, $missing);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
