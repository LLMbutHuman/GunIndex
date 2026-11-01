<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
require_once __DIR__.'/../config/geo.php';
$options=getopt('',['country:','file:','source:','license-note:','license-reviewed','format:','dry-run']);
try{
    if(!is_string($options['country']??null))throw new InvalidArgumentException('An explicit --country code is required.');
    $country=geo_country($options['country']);
    $file=$options['file']??null;$format=$options['format']??'standard';$dry=array_key_exists('dry-run',$options);
    if(!is_string($file)||!is_file($file)||is_link($file)||filesize($file)>33554432||!in_array($format,['standard','legacy-de'],true)||($format==='legacy-de'&&$country!=='DE'))throw new InvalidArgumentException('Expected a regular CSV file up to 32 MiB and a valid format/country.');
    $source=$options['source']??'';$license=$options['license-note']??'';
    if(!$dry&&(!array_key_exists('license-reviewed',$options)||!is_string($source)||strlen(trim($source))<3||strlen($source)>200||!is_string($license)||strlen(trim($license))<10||strlen($license)>2000||!preg_match('//u',$source.$license)))throw new InvalidArgumentException('A source description, license note and explicit --license-reviewed are required before importing.');
    $handle=fopen($file,'rb');if($handle===false)throw new RuntimeException('Cannot read CSV.');$records=[];$line=0;$duplicate=0;
    if($format==='standard'){$header=fgetcsv($handle,0,';','"','');$line++;if(!is_array($header))throw new InvalidArgumentException('CSV header missing.');$header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);if($header!==['postal_code','city','latitude','longitude'])throw new InvalidArgumentException('Required header: postal_code;city;latitude;longitude');}
    while(($row=fgetcsv($handle,0,';','"',''))!==false){
        $line++;if($row===[null])continue;if(count($row)!==4)throw new InvalidArgumentException('CSV column count is invalid at line '.$line.'.');
        [$postal,$city,$third,$fourth]=array_map('trim',$row);$postal=preg_replace('/^\xEF\xBB\xBF/','',$postal);
        if($format==='legacy-de'){if(!preg_match('/\A[0-9]{1,5}\z/',$postal))throw new InvalidArgumentException('Invalid legacy postcode at line '.$line.'.');$postal=str_pad($postal,5,'0',STR_PAD_LEFT);$latitude=$fourth;$longitude=$third;}else{$latitude=$third;$longitude=$fourth;}
        if(!geo_validate_postcode($country,$postal)||$city===''||!preg_match('/\A.{1,150}\z/us',$city)||preg_match('/[\x00-\x1f\x7f]/u',$city)||!geo_coordinates_valid($latitude,$longitude))throw new InvalidArgumentException('Invalid postal/location values at line '.$line.'.');
        $postal=geo_postcode_key($country,$postal);$value=[$city,number_format((float)$latitude,7,'.',''),number_format((float)$longitude,7,'.','')];
        if(isset($records[$postal])){if($records[$postal]!==$value)throw new InvalidArgumentException('Conflicting duplicate postcode at line '.$line.'. Aggregate or correct this source explicitly.');$duplicate++;continue;}
        $records[$postal]=$value;if(count($records)>100000)throw new InvalidArgumentException('Split this source into reviewed CSV parts of at most 100000 unique postcodes. Existing locations from other parts are retained.');
    }
    fclose($handle);if(!$records)throw new InvalidArgumentException('No valid locations in CSV.');$digest=hash_file('sha256',$file);
    if($dry){fwrite(STDOUT,'Validated '.count($records).' locations for '.$country.'; duplicates: '.$duplicate.'; SHA256 '.$digest.". No database changes.\n");exit(0);}
    require_once __DIR__.'/../config/bootstrap.php';$pdo->beginTransaction();
    $lock=$pdo->prepare('SELECT code FROM market_countries WHERE code=? FOR UPDATE');$lock->execute([$country]);if(!$lock->fetchColumn())throw new RuntimeException('Country schema is missing. Apply migrations first.');
    $q=$pdo->prepare('INSERT INTO geo_imports(country_code,source_name,source_sha256,license_note,license_reviewed,record_count) VALUES (?,?,?,?,1,?)');$q->execute([$country,trim($source),$digest,trim($license),count($records)]);$import=(int)$pdo->lastInsertId();
    $q=$pdo->prepare('INSERT INTO geo_locations(country_code,postal_code,city,latitude,longitude,import_id) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE city=VALUES(city),latitude=VALUES(latitude),longitude=VALUES(longitude),import_id=VALUES(import_id)');
    foreach($records as $postal=>$record)$q->execute([$country,(string)$postal,$record[0],$record[1],$record[2],$import]);
    $details=json_encode(['country'=>$country,'records'=>count($records),'source_sha256'=>$digest,'import_id'=>$import],JSON_THROW_ON_ERROR);$pdo->prepare("INSERT INTO admin_logs(admin_id,action_type,details) VALUES (NULL,'geography.imported',?)")->execute([$details]);$pdo->commit();
    fwrite(STDOUT,'Imported '.count($records).' locations for '.$country.' (import '.$import.'). No other postcodes were deleted.'.PHP_EOL);
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,($e instanceof InvalidArgumentException?$e->getMessage():'Import failed; no partial location changes were committed.').PHP_EOL);exit(1);}
