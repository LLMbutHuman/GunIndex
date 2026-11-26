<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
require_once __DIR__.'/../config/geo.php';

/** Read validated location rows from the verified snapshot, never the original file. */
function postcode_import_rows($handle,string $country,string $format):Generator
{
    if(!rewind($handle))throw new RuntimeException('Cannot read CSV snapshot.');
    $line=0;
    if($format==='standard'){
        $header=fgetcsv($handle,0,';','"','');$line++;
        if(!is_array($header))throw new InvalidArgumentException('CSV header missing.');
        $header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);
        if($header!==['postal_code','city','latitude','longitude'])throw new InvalidArgumentException('Required header: postal_code;city;latitude;longitude');
    }
    while(($row=fgetcsv($handle,0,';','"',''))!==false){
        $line++;if($row===[null])continue;
        if(count($row)!==4)throw new InvalidArgumentException('CSV column count is invalid at line '.$line.'.');
        [$postal,$city,$latitude,$longitude]=array_map('trim',$row);$postal=preg_replace('/^\xEF\xBB\xBF/','',$postal);
        if($format==='legacy-de'){
            if(!preg_match('/\A[0-9]{1,5}\z/',$postal))throw new InvalidArgumentException('Invalid legacy postcode at line '.$line.'.');
            $postal=str_pad($postal,5,'0',STR_PAD_LEFT);[$latitude,$longitude]=[$longitude,$latitude];
        }
        if(!geo_validate_postcode($country,$postal)||$city===''||!preg_match('/\A.{1,150}\z/us',$city)||preg_match('/[\x00-\x1f\x7f]/u',$city)||!geo_coordinates_valid($latitude,$longitude))throw new InvalidArgumentException('Invalid postal/location values at line '.$line.'.');
        yield $line=>[geo_postcode_key($country,$postal),$city,number_format((float)$latitude,7,'.',''),number_format((float)$longitude,7,'.','')];
    }
    if(!feof($handle))throw new RuntimeException('Cannot read complete CSV snapshot.');
}

$options=getopt('',['country:','file:','source:','license-note:','license-reviewed','format:','expected-sha256:','dry-run','seed-if-empty','sorted']);
try{
    if(!is_string($options['country']??null))throw new InvalidArgumentException('An explicit --country code is required.');
    $country=geo_country($options['country']);
    $file=$options['file']??null;$format=$options['format']??'standard';$dry=array_key_exists('dry-run',$options);
    if(!is_string($file)||!is_file($file)||is_link($file)||filesize($file)>33554432||!in_array($format,['standard','legacy-de'],true)||($format==='legacy-de'&&$country!=='DE'))throw new InvalidArgumentException('Expected a regular CSV file up to 32 MiB and a valid format/country.');
    $source=$options['source']??'';$license=$options['license-note']??'';
    if(!$dry&&(!array_key_exists('license-reviewed',$options)||!is_string($source)||strlen(trim($source))<3||strlen($source)>200||!is_string($license)||strlen(trim($license))<10||strlen($license)>2000||!preg_match('//u',$source.$license)))throw new InvalidArgumentException('A source description, license note and explicit --license-reviewed are required before importing.');
    $expected=$options['expected-sha256']??null;
    $seed=array_key_exists('seed-if-empty',$options);
    if($expected!==null&&(!is_string($expected)||!preg_match('/\A[0-9a-f]{64}\z/i',$expected)))throw new InvalidArgumentException('Expected a SHA-256 digest of 64 hexadecimal characters.');
    if($seed&&$expected===null)throw new InvalidArgumentException('An expected SHA-256 digest is required for initial seeding.');
    $input=fopen($file,'rb');if($input===false)throw new InvalidArgumentException('Cannot read CSV.');
    $handle=fopen('php://temp','w+b');if($handle===false){fclose($input);throw new RuntimeException('Cannot store CSV snapshot.');}
    try{$bytes=stream_copy_to_stream($input,$handle,33554433);}finally{fclose($input);}
    if($bytes===false||$bytes>33554432||!rewind($handle))throw new InvalidArgumentException('Expected a readable CSV file up to 32 MiB.');
    $hash=hash_init('sha256');
    if(hash_update_stream($hash,$handle)!==$bytes||!rewind($handle))throw new RuntimeException('Cannot verify CSV snapshot.');
    $digest=hash_final($hash);
    if($expected!==null&&!hash_equals(strtolower($expected),$digest))throw new InvalidArgumentException('The CSV does not match the expected SHA-256 digest.');
    $sorted=array_key_exists('sorted',$options);
    $records=[];$previous=null;$count=0;$duplicate=0;$limit=$sorted?650000:100000;
    foreach(postcode_import_rows($handle,$country,$format) as $line=>$value){
        $postal=$value[0];
        if($sorted&&$previous!==null&&strcmp($postal,$previous[0])<0)throw new InvalidArgumentException('Postcode sorting is invalid at line '.$line.'. Sort by normalized postcode.');
        $existing=$sorted?($postal===($previous[0]??null)?$previous:null):($records[$postal]??null);
        if($existing!==null){
            if($existing!==$value)throw new InvalidArgumentException('Conflicting duplicate postcode at line '.$line.'. Aggregate or correct this source explicitly.');
            $duplicate++;continue;
        }
        if(++$count>$limit)throw new InvalidArgumentException('More than '.$limit.' unique postcodes. Use a reviewed sorted source or import smaller parts.');
        if($sorted)$previous=$value;
        else $records[$postal]=$value;
    }
    if(!$count)throw new InvalidArgumentException('No valid locations in CSV.');
    if($dry){fclose($handle);fwrite(STDOUT,'Validated '.$count.' locations for '.$country.'; duplicates: '.$duplicate.'; SHA256 '.$digest.". No database changes.\n");exit(0);}
    require_once __DIR__.'/../config/bootstrap.php';$pdo->beginTransaction();
    $lock=$pdo->prepare('SELECT code FROM market_countries WHERE code=? FOR UPDATE');$lock->execute([$country]);if(!$lock->fetchColumn())throw new RuntimeException('Country schema is missing. Apply migrations first.');
    if($seed){
        $existing=$pdo->prepare('SELECT 1 FROM geo_imports WHERE country_code=? LIMIT 1');
        $existing->execute([$country]);
        if($existing->fetchColumn()){
            $pdo->rollBack();
            fwrite(STDOUT,"Existing sources for $country retained.\n");
            exit(0);
        }
    }
    $q=$pdo->prepare('INSERT INTO geo_imports(country_code,source_name,source_sha256,license_note,license_reviewed,record_count) VALUES (?,?,?,?,1,?)');$q->execute([$country,trim($source),$digest,trim($license),$count]);$import=(int)$pdo->lastInsertId();
    $batchSize=200;
    $insert='INSERT INTO geo_locations(country_code,postal_code,city,latitude,longitude,import_id) VALUES ';
    $update=' ON DUPLICATE KEY UPDATE city=VALUES(city),latitude=VALUES(latitude),longitude=VALUES(longitude),import_id=VALUES(import_id)';
    $q=$pdo->prepare($insert.implode(',',array_fill(0,$batchSize,'(?,?,?,?,?,?)')).$update);
    $batch=[];$previousPostal=null;
    foreach($sorted?postcode_import_rows($handle,$country,$format):$records as [$postal,$city,$lat,$lon]){
        if($postal===$previousPostal)continue;
        $previousPostal=$postal;
        array_push($batch,$country,$postal,$city,$lat,$lon,$import);
        if(count($batch)===$batchSize*6){$q->execute($batch);$batch=[];}
    }
    if($batch)$pdo->prepare($insert.implode(',',array_fill(0,intdiv(count($batch),6),'(?,?,?,?,?,?)')).$update)->execute($batch);
    fclose($handle);
    $details=json_encode(['country'=>$country,'records'=>$count,'source_sha256'=>$digest,'import_id'=>$import],JSON_THROW_ON_ERROR);$pdo->prepare("INSERT INTO admin_logs(admin_id,action_type,details) VALUES (NULL,'geography.imported',?)")->execute([$details]);$pdo->commit();
    fwrite(STDOUT,'Imported '.$count.' locations for '.$country.' (import '.$import.'). No other postcodes were deleted.'.PHP_EOL);
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,($e instanceof InvalidArgumentException?$e->getMessage():'Import failed; no partial location changes were committed.').PHP_EOL);exit(1);}
