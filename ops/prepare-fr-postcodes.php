<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__ . '/../config/geo.php';

try {
    $options = getopt('', ['file:', 'output:', 'expected-source-sha256:']);
    $source = $options['file'] ?? null;
    $output = $options['output'] ?? null;
    $expected = $options['expected-source-sha256'] ?? null;
    if (!is_string($source) || !is_file($source) || is_link($source) || filesize($source) > 16777216
        || !is_string($output) || !is_string($expected) || !preg_match('/\A[0-9a-f]{64}\z/', $expected)) {
        throw new InvalidArgumentException('Provide --file, --output and --expected-source-sha256 for the La Poste CSV export.');
    }
    $file = fopen($source, 'rb');
    if ($file === false) throw new RuntimeException('Cannot open source CSV.');
    $bytes = stream_get_contents($file, 16777217);
    fclose($file);
    if ($bytes === false || strlen($bytes) > 16777216 || !hash_equals($expected, hash('sha256', $bytes))) {
        throw new RuntimeException('La Poste export does not match the supplied source digest.');
    }
    $file = fopen('php://temp', 'w+b');
    fwrite($file, $bytes);
    unset($bytes);
    rewind($file);
    $header = ['code_commune_insee', 'nom_de_la_commune', 'code_postal', 'libelle_d_acheminement', 'ligne_5', '_geopoint', 'latitude', 'longitude'];
    if (fgetcsv($file, 0, ',', '"', '') !== $header) throw new RuntimeException('Unexpected La Poste CSV header.');
    $postcodes = [];
    for ($line = 2; ($row = fgetcsv($file, 0, ',', '"', '')) !== false; $line++) {
        if (count($row) !== count($header)) throw new RuntimeException("Invalid columns at source row $line.");
        [$commune, , $postal, $name, , , $latitude, $longitude] = $row;
        // Metropolitan INSEE codes including Corsica, excluding Monaco and overseas territories.
        if (!preg_match('/\A(?:0[1-9]|[1-8][0-9]|9[0-5]|2[AB])[0-9]{3}\z/', $commune)) continue;
        $name = trim($name);
        if (!geo_validate_postcode('FR', $postal) || !preg_match('/\A[^\x00-\x1f\x7f]{1,150}\z/u', $name)
            || !geo_coordinates_valid($latitude, $longitude) || (float)$latitude < 41 || (float)$latitude > 52
            || (float)$longitude < -6 || (float)$longitude > 10) {
            throw new RuntimeException("Invalid metropolitan values at source row $line.");
        }
        $point = [(float)$latitude, (float)$longitude, $name];
        if (isset($postcodes[$postal][$commune]) && $postcodes[$postal][$commune] !== $point) {
            throw new RuntimeException("Conflicting values for one commune and postcode at source row $line.");
        }
        $postcodes[$postal][$commune] = $point;
    }
    fclose($file);
    if (!$postcodes) throw new RuntimeException('The La Poste export contains no valid metropolitan postcode.');
    ksort($postcodes, SORT_STRING);
    $target = fopen($output, 'xb');
    if ($target === false) throw new RuntimeException('Choose a new output file.');
    fputcsv($target, ['postal_code', 'city', 'latitude', 'longitude'], ';', '"', '', "\n");
    foreach ($postcodes as $postal => $communes) {
        ksort($communes, SORT_STRING);
        $names = array_count_values(array_column($communes, 2));
        ksort($names, SORT_STRING);
        arsort($names, SORT_NUMERIC);
        $count = count($communes);
        fputcsv($target, [$postal, array_key_first($names),
            number_format(array_sum(array_column($communes, 0)) / $count, 7, '.', ''),
            number_format(array_sum(array_column($communes, 1)) / $count, 7, '.', '')], ';', '"', '', "\n");
    }
    fclose($target);
    printf("Prepared %d metropolitan postcodes from the reviewed La Poste export.\n", count($postcodes));
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
