<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }

try {
    $regions = ['bevlg'=>['BE-VLG',10,9,8], 'bebru'=>['BE-BRU',9,10,8], 'bewal'=>['BE-WAL',9,8,10]];
    $options = getopt('', ['source-dir:', 'output:', 'bevlg-sha256:', 'bebru-sha256:', 'bewal-sha256:']);
    $directory = $options['source-dir'] ?? null;
    $output = $options['output'] ?? null;
    if (!is_string($directory) || !is_dir($directory) || !is_string($output)) {
        throw new InvalidArgumentException('Provide the three official BOSA archives, their SHA-256 digests and a new output path.');
    }
    $header = ['EPSG:3812_x','EPSG:3812_y','EPSG:4326_lat','EPSG:4326_lon','address_id','box_number','house_number',
        'municipality_id','municipality_name_de','municipality_name_fr','municipality_name_nl','postcode','postname_fr','postname_nl',
        'street_id','streetname_de','streetname_fr','streetname_nl','region_code','status'];
    $postcodes = [];
    $total = 0;
    foreach ($regions as $region => [$code,$preferred,$fallback,$last]) {
        $source = $directory.'/openaddress-'.$region.'.zip';
        $sha256 = $options[$region.'-sha256'] ?? null;
        if (!is_string($sha256) || !preg_match('/\A[a-f0-9]{64}\z/',$sha256) || !is_file($source)
            || is_link($source) || filesize($source)>256*1024**2 || !hash_equals($sha256,hash_file('sha256',$source))) {
            throw new InvalidArgumentException('Invalid or changed BOSA source for '.$code.'.');
        }
        $archive = new ZipArchive();
        if ($archive->open($source,ZipArchive::RDONLY|ZipArchive::CHECKCONS)!==true || $archive->numFiles!==1) {
            throw new RuntimeException('Unexpected BOSA archive for '.$code.'.');
        }
        $member = $archive->statIndex(0);
        if (!$member || $member['name']!=='openaddress-'.$region.'.csv' || $member['size']>1024**3) {
            throw new RuntimeException('Unexpected BOSA CSV member for '.$code.'.');
        }
        $csv = $archive->getStreamIndex(0);
        if (!$csv) throw new RuntimeException('Cannot read BOSA CSV.');
        $records = $excluded = $invalidPostcode = $invalidPoint = 0;
        try {
            $columns = fgetcsv($csv,0,',','"','');
            if (is_array($columns)) $columns[0] = preg_replace('/\A\xEF\xBB\xBF/','',$columns[0]);
            if ($columns!==$header) throw new RuntimeException('Unexpected BOSA CSV layout for '.$code.'.');
            while (($row=fgetcsv($csv,0,',','"',''))!==false) {
                $records++;
                if (count($row)!==20 || $row[18]!==$code) throw new RuntimeException('Invalid BOSA row layout or region.');
                [$lat,$lon,$postal] = [$row[2],$row[3],$row[11]];
                $town = $row[$preferred] ?: ($row[$fallback] ?: $row[$last]);
                if ($row[19]!=='current' || $lat==='' || $lon==='' || $postal==='' || $town==='') { $excluded++; continue; }
                if (!preg_match('/\A[1-9][0-9]{3}\z/',$postal)) { $invalidPostcode++; continue; }
                if (!is_numeric($lat) || !is_numeric($lon) || $lat<49.4 || $lat>51.6 || $lon<2.4 || $lon>6.5) {
                    $invalidPoint++; continue;
                }
                $postcodes[$postal] ??= ['lat'=>0,'lon'=>0,'count'=>0,'names'=>[],'points'=>[]];
                $entry = &$postcodes[$postal];
                $north = (int)round((float)$lat*10000000);
                $east = (int)round((float)$lon*10000000);
                $entry['lat'] += $north;
                $entry['lon'] += $east;
                $entry['count']++;
                $entry['names'][$town] = 1 + ($entry['names'][$town] ?? 0);
                if (count($entry['points'])<3) $entry['points'][$north.':'.$east] = true;
                unset($entry);
            }
        } finally { fclose($csv); $archive->close(); }
        if ($records===$excluded+$invalidPostcode+$invalidPoint || !hash_equals($sha256,hash_file('sha256',$source))) {
            throw new RuntimeException('Missing or changed BOSA data.');
        }
        printf("%s: %d source rows, %d excluded (status or missing fields), %d invalid postcodes, %d invalid or out-of-bounds points.\n",
            $code,$records,$excluded,$invalidPostcode,$invalidPoint);
        $total += $records;
    }
    ksort($postcodes,SORT_STRING);
    $rows = [['postal_code','city','latitude','longitude']];
    foreach ($postcodes as $postal=>$entry) {
        if (count($entry['points'])<3) continue;
        ksort($entry['names'],SORT_STRING);
        arsort($entry['names'],SORT_NUMERIC);
        $town = array_key_first($entry['names']);
        if (!preg_match('/\A[^\x00-\x1f\x7f\x{fffd}]{1,150}\z/u',$town)) throw new RuntimeException('Invalid Belgian municipality name.');
        $divisor = 10000000*$entry['count'];
        $rows[] = [(string)$postal,$town,number_format($entry['lat']/$divisor,7,'.',''),number_format($entry['lon']/$divisor,7,'.','')];
    }
    if (count($rows)===1) throw new RuntimeException('No postcode had sufficient distinct points.');
    $target = fopen($output,'xb');
    if (!$target) throw new RuntimeException('Choose a new output path.');
    try {
        foreach ($rows as $row) {
            if (fputcsv($target,$row,';','"','',"\n")===false) throw new RuntimeException('Could not write postcode output.');
        }
    } finally { fclose($target); }
    printf("Aggregated %d of %d postcodes from %d rows.\n",count($rows)-1,count($postcodes),$total);
} catch (Throwable $error) {
    fwrite(STDERR,$error->getMessage()."\n");
    exit(1);
}
