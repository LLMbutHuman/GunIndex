<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }

try {
    $options = getopt('', ['file:', 'output:', 'expected-source-sha256:']);
    $source = $options['file'] ?? null;
    $output = $options['output'] ?? null;
    $sha256 = $options['expected-source-sha256'] ?? null;
    if (!is_string($source) || !is_file($source) || is_link($source) || filesize($source) > 128*1024**2
        || !is_string($output) || !is_string($sha256) || !preg_match('/\A[0-9a-f]{64}\z/', $sha256)
        || !hash_equals($sha256, hash_file('sha256', $source))) {
        throw new InvalidArgumentException('Provide the RÚIAN ZIP, its expected SHA-256 and a new output path.');
    }
    $archive = new ZipArchive();
    if ($archive->open($source, ZipArchive::RDONLY|ZipArchive::CHECKCONS) !== true || $archive->numFiles > 10000) {
        throw new RuntimeException('Invalid or oversized RÚIAN archive.');
    }
    $postcodes = [];
    $records = $missing = $uncompressed = 0;
    for ($index=0; $index<$archive->numFiles; $index++) {
        $member = $archive->statIndex($index);
        if (!$member || !preg_match('~\ACSV/[0-9]{8}_OB_[0-9]{6}_ADR\.csv\z~', $member['name'])
            || $member['size'] > 32*1024**2 || ($uncompressed += $member['size']) > 512*1024**2) {
            throw new RuntimeException('Unexpected entry in RÚIAN archive.');
        }
        $csv = $archive->getStreamIndex($index);
        if (!$csv) throw new RuntimeException('Cannot read RÚIAN entry.');
        try {
            $header = fgetcsv($csv,0,';','"','');
            if (!is_array($header) || count($header)!==19 || iconv('CP1250','UTF-8',$header[2])!=='Název obce'
                || iconv('CP1250','UTF-8',$header[15])!=='PSČ'
                || iconv('CP1250','UTF-8',$header[16])!=='Souřadnice Y'
                || iconv('CP1250','UTF-8',$header[17])!=='Souřadnice X') {
                throw new RuntimeException('Unexpected RÚIAN CSV layout.');
            }
            while (($row = fgetcsv($csv,0,';','"','')) !== false) {
                $records++;
                if (count($row)!==19) throw new RuntimeException('Invalid RÚIAN row length.');
                $postal=$row[15]; $west=$row[16]; $south=$row[17]; $town=$row[2];
                if ($postal==='' || $west==='' || $south==='') { $missing++; continue; }
                if (!preg_match('/\A[1-7][0-9]{4}\z/',$postal) || !is_numeric($west) || !is_numeric($south)
                    || $west<400000 || $west>950000 || $south<900000 || $south>1250000 || $town==='') {
                    throw new RuntimeException('RÚIAN postcode or coordinates are outside their expected range.');
                }
                $postcodes[$postal] ??= ['west'=>0,'south'=>0,'count'=>0,'names'=>[],'points'=>[]];
                $entry = &$postcodes[$postal];
                $entry['west'] += (int)round(100*(float)$west);
                $entry['south'] += (int)round(100*(float)$south);
                $entry['count']++;
                $entry['names'][$town] = 1 + ($entry['names'][$town] ?? 0);
                if (count($entry['points'])<3) $entry['points'][(float)$west.':'.(float)$south] = true;
                unset($entry);
            }
        } finally { fclose($csv); }
    }
    $archive->close();
    if (!$postcodes || !hash_equals($sha256, hash_file('sha256',$source))) throw new RuntimeException('Missing or changed RÚIAN source.');
    ksort($postcodes,SORT_STRING);
    $rows=[];
    foreach ($postcodes as $postal=>$entry) {
        if (count($entry['points'])<3) continue;
        $names=[];
        foreach ($entry['names'] as $town=>$count) {
            $name=iconv('CP1250','UTF-8',(string)$town);
            if ($name===false) throw new RuntimeException('Invalid RÚIAN municipality encoding.');
            $names[$name]=$count;
        }
        ksort($names,SORT_STRING);
        arsort($names,SORT_NUMERIC);
        $town=array_key_first($names);
        if (!preg_match('/\A[^\x00-\x1f\x7f;"\x{fffd}]{1,150}\z/u',$town)) throw new RuntimeException('Unexpected RÚIAN municipality name.');
        $rows[]=[(string)$postal,$town,-$entry['west']/(100*$entry['count']),-$entry['south']/(100*$entry['count'])];
    }
    if (!$rows) throw new RuntimeException('No postcode had sufficient independent points.');
    $json=json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $target=fopen($output,'xb');
    if (!$target) throw new RuntimeException('Choose a new output file.');
    try { if (fwrite($target,$json)!==strlen($json)) throw new RuntimeException('Could not write aggregated points.'); }
    finally { fclose($target); }
    printf("Aggregated %d of %d postcodes from %d rows (%d missing postcode or coordinates).\n",count($rows),count($postcodes),$records,$missing);
} catch (Throwable $error) {
    fwrite(STDERR,$error->getMessage()."\n");
    exit(1);
}
