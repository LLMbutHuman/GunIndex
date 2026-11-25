<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }

function prg_region($stream, array &$postcodes): array
{
    $namespace='https://geoportal.gov.pl/schemas/prgad/1.0';
    $gml='http://www.opengis.net/gml/3.2';
    $reader=XMLReader::fromStream($stream,null,LIBXML_NONET);
    $localities=[];
    $feature=null;
    $stats=['addresses'=>0,'skipped'=>0,'unresolved_locality'=>0];
    $collection=false;
    try {
        while ($reader->read()) {
            if ($reader->nodeType===XMLReader::DOC_TYPE) throw new RuntimeException('Document types are not supported.');
            if ($reader->nodeType===XMLReader::ELEMENT && $reader->depth===0) {
                $collection=$reader->localName==='FeatureCollection' && $reader->namespaceURI===$gml;
                if (!$collection) throw new RuntimeException('Unexpected PRG collection.');
            }
            if ($reader->nodeType===XMLReader::ELEMENT && $reader->depth===2 && $reader->namespaceURI===$namespace) {
                $feature=$reader->localName;
                $id=$reader->getAttributeNs('id',$gml);
                $name=$postal=$reference=$coordinates=null;
                $archived=false;
                continue;
            }
            if ($feature==='AD_Miejscowosc') {
                if ($reader->nodeType===XMLReader::ELEMENT && $reader->depth===3 && $reader->namespaceURI===$namespace
                    && $reader->localName==='nazwa') $name=trim($reader->readString());
                if ($reader->nodeType===XMLReader::END_ELEMENT && $reader->depth===2) {
                    if ($id && $name) $localities[$id]=$name;
                    $feature=null;
                }
                continue;
            }
            if ($feature!=='AD_PunktAdresowy') continue;
            if ($reader->nodeType===XMLReader::ELEMENT && $reader->depth===3 && $reader->namespaceURI===$namespace) {
                if ($reader->localName==='kodPocztowy') $postal=trim($reader->readString());
                if ($reader->localName==='miejscowosc') $reference=$reader->getAttributeNs('href','http://www.w3.org/1999/xlink');
                if ($reader->localName==='koniecWersjiObiektu') $archived=trim($reader->readString())!=='';
            }
            if ($reader->nodeType===XMLReader::ELEMENT && $reader->depth===4 && $reader->namespaceURI===$gml && $reader->localName==='Point') {
                if ($reader->getAttribute('srsName')!=='urn:ogc:def:crs:EPSG::2180') throw new RuntimeException('Unsupported PRG CRS.');
            }
            if ($reader->nodeType===XMLReader::ELEMENT && $reader->depth===5 && $reader->namespaceURI===$gml && $reader->localName==='pos') {
                $coordinates=trim($reader->readString());
            }
            if ($reader->nodeType!==XMLReader::END_ELEMENT || $reader->depth!==2) continue;
            $feature=null;
            $stats['addresses']++;
            if ($archived || !is_string($postal) || !preg_match('/\A[0-9]{2}-?[0-9]{3}\z/',$postal)
                || !is_string($coordinates) || !preg_match('/\A([0-9]+(?:\.[0-9]+)?)\s+([0-9]+(?:\.[0-9]+)?)\z/',$coordinates,$position)) {
                $stats['skipped']++;
                continue;
            }
            $town=is_string($reference) && str_starts_with($reference,'#') ? ($localities[substr($reference,1)]??null) : null;
            if ($town===null) { $stats['unresolved_locality']++; continue; }
            // EPSG:2180 GML lists northing first and easting second.
            $north=(int)round(100*(float)$position[1]);
            $east=(int)round(100*(float)$position[2]);
            if ($east<8000000 || $east>90000000 || $north<8000000 || $north>84000000) {
                $stats['skipped']++; continue;
            }
            $key=str_replace('-','',$postal);
            $postcodes[$key]??=['north'=>0,'east'=>0,'count'=>0,'names'=>[],'points'=>[]];
            $entry=&$postcodes[$key];
            $entry['north']+=$north;
            $entry['east']+=$east;
            $entry['count']++;
            $entry['names'][$town]=1+($entry['names'][$town]??0);
            if (count($entry['points'])<3) $entry['points'][$north.':'.$east]=true;
            unset($entry);
        }
        if (!$collection || $stats['addresses']===0 || libxml_get_errors()!==[]) throw new RuntimeException('Incomplete or invalid PRG region.');
        return $stats;
    } finally { $reader->close(); libxml_clear_errors(); }
}

try {
    $options=getopt('',['file:','output:','expected-source-sha256:']);
    $source=$options['file']??null;
    $output=$options['output']??null;
    $sha256=$options['expected-source-sha256']??null;
    if (!is_string($source)||!is_file($source)||is_link($source)||filesize($source)>2*1024**3
        || !is_string($output)||!is_string($sha256)||!preg_match('/\A[a-f0-9]{64}\z/',$sha256)
        || !hash_equals($sha256,hash_file('sha256',$source))) throw new InvalidArgumentException('Provide the verified PRG ZIP, its SHA-256 and a new output path.');
    $archive=new ZipArchive();
    if ($archive->open($source,ZipArchive::RDONLY|ZipArchive::CHECKCONS)!==true || $archive->numFiles!==16) {
        throw new RuntimeException('Expected the national PRG archive with 16 regions.');
    }
    libxml_use_internal_errors(true);
    libxml_set_external_entity_loader(static fn()=>null);
    $postcodes=[];
    $regions=[];
    $uncompressed=0;
    $totals=['addresses'=>0,'skipped'=>0,'unresolved_locality'=>0];
    for ($index=0;$index<$archive->numFiles;$index++) {
        $member=$archive->statIndex($index);
        if (!$member || !preg_match('/\A(0[2468]|1[02468]|2[02468]|3[02])_[a-z_-]+_[0-9]{2}\.[0-9]{2}\.[0-9]{4}_[0-9]{2}\.[0-9]{2}\.[0-9]{2}\.gml\z/',$member['name'],$match)
            || isset($regions[$match[1]]) || $member['size']>2*1024**3 || ($uncompressed+=$member['size'])>16*1024**3) {
            throw new RuntimeException('Unexpected regional member in the PRG archive.');
        }
        $regions[$match[1]]=true;
        $stream=$archive->getStreamIndex($index);
        if (!$stream) throw new RuntimeException('Could not read PRG member.');
        try { $stats=prg_region($stream,$postcodes); }
        finally { fclose($stream); }
        foreach ($stats as $key=>$value) $totals[$key]+=$value;
        printf("Region %s: %d addresses, %d skipped, %d unresolved localities.\n",$match[1],$stats['addresses'],$stats['skipped'],$stats['unresolved_locality']);
    }
    $archive->close();
    if (!$postcodes || !hash_equals($sha256,hash_file('sha256',$source))) throw new RuntimeException('Missing or changed PRG source.');
    ksort($postcodes,SORT_STRING);
    $rows=[];
    foreach ($postcodes as $postal=>$entry) {
        if (count($entry['points'])<3) continue;
        ksort($entry['names'],SORT_STRING);
        arsort($entry['names'],SORT_NUMERIC);
        $town=(string)array_key_first($entry['names']);
        if (!preg_match('/\A[^\x00-\x1f\x7f;"\x{fffd}]{1,150}\z/u',$town)) throw new RuntimeException('Unexpected Polish locality name.');
        $rows[]=[(string)$postal,$town,$entry['east']/(100*$entry['count']),$entry['north']/(100*$entry['count'])];
    }
    if (!$rows) throw new RuntimeException('No postcode had sufficient distinct coordinates.');
    $json=json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $target=fopen($output,'xb');
    if (!$target) throw new RuntimeException('Choose a new output file.');
    try { if (fwrite($target,$json)!==strlen($json)) throw new RuntimeException('Could not write aggregated points.'); }
    finally { fclose($target); }
    printf("Aggregated %d of %d postcodes from %d addresses (%d skipped, %d unresolved localities).\n",
        count($rows),count($postcodes),$totals['addresses'],$totals['skipped'],$totals['unresolved_locality']);
} catch (Throwable $error) {
    fwrite(STDERR,$error->getMessage()."\n");
    exit(1);
}
