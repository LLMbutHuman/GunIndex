<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__.'/../config/geo.php';

try {
    $options=getopt('',['file:','file-sha256:','reference:','reference-sha256:','output:']);
    foreach (['file','reference'] as $key) {
        $path=$options[$key]??null;
        $hash=$options[$key.'-sha256']??null;
        if (!is_string($path)||!is_file($path)||is_link($path)||filesize($path)>16*1024**2||!is_string($hash)
            || !preg_match('/\A[a-f0-9]{64}\z/',$hash)||!hash_equals($hash,hash_file('sha256',$path))) {
            throw new InvalidArgumentException('Provide the verified postcode aggregates, GeoNames ZIP and their SHA-256 digests.');
        }
    }
    $output=$options['output']??null;
    if (!is_string($output)) throw new InvalidArgumentException('Choose a new output path.');
    $archive=new ZipArchive();
    if ($archive->open($options['reference'],ZipArchive::RDONLY|ZipArchive::CHECKCONS)!==true) throw new RuntimeException('Invalid GeoNames ZIP.');
    $entry=$archive->statName('PL.txt');
    if (!$entry||$entry['size']>16*1024**2) throw new RuntimeException('Unexpected GeoNames member.');
    $file=$archive->getStream('PL.txt');
    if (!$file) throw new RuntimeException('Cannot read GeoNames member.');
    $reference=[];
    try {
        while (($line=fgets($file,4096))!==false) {
            $fields=explode("\t",rtrim($line,"\r\n"));
            if (count($fields)!==12 || $fields[0]!=='PL' || !preg_match('/\A[0-9]{2}-[0-9]{3}\z/',$fields[1])) {
                throw new RuntimeException('Unexpected GeoNames row layout.');
            }
            if ($fields[11]!=='6') continue;
            if (!geo_coordinates_valid($fields[9],$fields[10])) throw new RuntimeException('Invalid GeoNames point.');
            $reference[str_replace('-','',$fields[1])][]=[(float)$fields[9],(float)$fields[10]];
        }
    } finally { fclose($file);$archive->close(); }
    if (!$reference) throw new RuntimeException('No GeoNames postal centroids found.');

    $file=fopen($options['file'],'rb');
    if (!$file) throw new RuntimeException('Cannot open PRG postcode aggregates.');
    $header=['postal_code','city','latitude','longitude'];
    $rows=[$header];
    $missing=$outside=0;
    $previous='';
    try {
        if (fgetcsv($file,0,';','"','')!==$header) throw new RuntimeException('Expected PRG aggregate CSV header.');
        while (($row=fgetcsv($file,0,';','"',''))!==false) {
            if (count($row)!==4) throw new RuntimeException('Unexpected PRG aggregate row.');
            [$code,$town,$lat,$lon]=$row;
            if (!preg_match('/\A[0-9]{5}\z/',$code)||strcmp($code,$previous)<=0
                || !preg_match('/\A[^\x00-\x1f\x7f\x{fffd}]{1,150}\z/u',$town)||!geo_coordinates_valid($lat,$lon)) {
                throw new RuntimeException('Invalid or unordered PRG aggregate.');
            }
            $previous=$code;
            if (!isset($reference[$code])) { $missing++;continue; }
            foreach ($reference[$code] as [$referenceLat,$referenceLon]) {
                if (geo_distance_km((float)$lat,(float)$lon,$referenceLat,$referenceLon)<=25.0) {
                    $rows[]=$row;continue 2;
                }
            }
            $outside++;
        }
    } finally { fclose($file); }
    if (count($rows)===1) throw new RuntimeException('No PRG postcode had a matching GeoNames centroid.');
    $target=fopen($output,'xb');
    if (!$target) throw new RuntimeException('Choose a new output path.');
    try {
        foreach ($rows as $row) {
            if (fputcsv($target,$row,';','"','',"\n")===false) throw new RuntimeException('Could not write postcodes.');
        }
    } finally { fclose($target); }
    printf("Kept %d PRG aggregates, omitted %d absent from reference and %d more than 25 km from every matching centroid.\n",
        count($rows)-1,$missing,$outside);
} catch(Throwable $error) {
    fwrite(STDERR,$error->getMessage()."\n");
    exit(1);
}
