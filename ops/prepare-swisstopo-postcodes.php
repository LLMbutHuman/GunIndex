<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__.'/../config/geo.php';

try {
    $options = getopt('', ['file:', 'country:', 'output:', 'expected-source-sha256:']);
    $path = $options['file'] ?? null;
    $country = $options['country'] ?? null;
    $output = $options['output'] ?? null;
    $sha256 = $options['expected-source-sha256'] ?? null;
    if (!is_string($path) || !is_file($path) || is_link($path) || filesize($path) > 1048576
        || !in_array($country, ['CH','LI'], true) || !is_string($output)
        || !is_string($sha256) || !preg_match('/\A[0-9a-f]{64}\z/', $sha256)
        || !hash_equals($sha256, hash_file('sha256', $path))) {
        throw new InvalidArgumentException('Provide the verified swisstopo WGS84 archive, CH or LI, a new output path and its expected SHA-256.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) throw new RuntimeException('Cannot read the swisstopo archive.');
    try {
        $entryPath = 'AMTOVZ_CSV_WGS84/AMTOVZ_CSV_WGS84.csv';
        $entry = $zip->statName($entryPath);
        if (!$entry || $entry['size'] > 3000000) throw new RuntimeException('Unexpected swisstopo CSV file or size.');
        $contents = $zip->getFromName($entryPath);
    } finally {
        $zip->close();
    }
    if (!is_string($contents) || !preg_match('//u', $contents) || !hash_equals($sha256, hash_file('sha256', $path))) {
        throw new RuntimeException('swisstopo archive failed integrity or encoding checks.');
    }
    $file = fopen('php://temp', 'w+b');
    fwrite($file, preg_replace('/\A\xEF\xBB\xBF/', '', $contents));
    rewind($file);
    $header = ['Ortschaftsname','PLZ4','Zusatzziffer','ZIP_ID','Gemeindename','BFS-Nr','Kantonskürzel','Adressenanteil','E','N','Sprache','Validity'];
    if (fgetcsv($file, 0, ';', '"', '') !== $header) throw new RuntimeException('Expected the swisstopo WGS84 CSV format from January 2026.');
    $postcodes = [];
    while (($row = fgetcsv($file, 0, ';', '"', '')) !== false) {
        if (count($row) !== count($header)) throw new RuntimeException('A swisstopo row does not match the approved layout.');
        [$town,$postal,,$zipId,,$municipalId,$canton,$share,$lon,$lat] = $row;
        // The cantonal field is empty for Liechtenstein.
        $rowCountry = $canton === '' ? 'LI' : 'CH';
        if ($rowCountry !== $country) continue;
        if (!geo_validate_postcode($country, $postal) || !ctype_digit($zipId) || !ctype_digit($municipalId)
            || !preg_match('/\A[^\x00-\x1f\x7f]{1,150}\z/u', $town)
            || !preg_match('/\A([0-9]{1,3}(?:\.[0-9]{1,3})?) %\z/', $share, $percentage)
            || (float)$percentage[1] > 100 || !geo_coordinates_valid($lat, $lon)
            || (float)$lat < 45.7 || (float)$lat > 48 || (float)$lon < 5.8 || (float)$lon > 10.7
            || ($country === 'LI' && ((int)$municipalId < 7001 || (int)$municipalId > 7011))) {
            throw new RuntimeException('A swisstopo row is outside the expected postal or coordinate range.');
        }
        // Where a ZIP6 spans municipalities, represent it by the largest address share.
        $point = [-(float)$percentage[1], $town, (float)$lat, (float)$lon];
        if (!isset($postcodes[$postal][$zipId]) || $point < $postcodes[$postal][$zipId]) $postcodes[$postal][$zipId] = $point;
    }
    fclose($file);
    if (!$postcodes) throw new RuntimeException('No postcodes for the requested country.');
    ksort($postcodes, SORT_STRING);
    $target = fopen($output, 'xb');
    if ($target === false) throw new RuntimeException('Choose a new output file.');
    try {
        $rows = [['postal_code','city','latitude','longitude']];
        foreach ($postcodes as $postal => $points) {
            ksort($points, SORT_STRING);
            $names = array_count_values(array_column($points, 1));
            ksort($names, SORT_STRING);
            arsort($names, SORT_NUMERIC);
            $rows[] = [$postal, array_key_first($names),
                number_format(array_sum(array_column($points, 2)) / count($points), 7, '.', ''),
                number_format(array_sum(array_column($points, 3)) / count($points), 7, '.', '')];
        }
        foreach ($rows as $row) {
            if (fputcsv($target, $row, ';', '"', '', "\n") === false) throw new RuntimeException('Could not write all postcode rows.');
        }
        if (!fflush($target)) throw new RuntimeException('Could not flush postcode output.');
    } finally {
        fclose($target);
    }
    printf("Prepared %d %s postcodes from the swisstopo WGS84 export.\n", count($postcodes), $country);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
