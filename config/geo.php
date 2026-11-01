<?php
declare(strict_types=1);
/** Local geographic data only; postcode centroids are not exact addresses. */
function geo_countries(): array {
    $names=['DE'=>'Deutschland','AT'=>'Österreich','CH'=>'Schweiz','FR'=>'Frankreich','BE'=>'Belgien','NL'=>'Niederlande','LU'=>'Luxemburg','DK'=>'Dänemark','PL'=>'Polen','CZ'=>'Tschechien','LI'=>'Liechtenstein'];
    return function_exists('t')?array_map(static fn(string $name):string=>t($name),$names):$names;
}
function geo_country(?string $country=null): string {
    $country=$country??($_SESSION['market_country']??'DE');
    if(!is_string($country)||!preg_match('/\A[A-Za-z]{2}\z/',$country)||!array_key_exists(strtoupper($country),geo_countries()))throw new InvalidArgumentException('Bitte ein unterstütztes Land auswählen.');
    return strtoupper($country);
}
function geo_normalize_postcode(string $country,string $postal): string {
    $country=geo_country($country);$postal=strtoupper(trim($postal));
    if($country==='NL')$postal=preg_replace('/\s+/','',$postal);
    return $postal;
}
function geo_validate_postcode(string $country,string $postal): bool {
    try{$country=geo_country($country);}catch(InvalidArgumentException){return false;}
    $postal=geo_normalize_postcode($country,$postal);
    $pattern=match($country){'DE','FR'=>'/\A[0-9]{5}\z/','AT','CH','BE','LU','DK','LI'=>'/\A[0-9]{4}\z/','NL'=>'/\A[1-9][0-9]{3}[A-Z]{2}\z/','PL'=>'/\A[0-9]{2}-?[0-9]{3}\z/','CZ'=>'/\A[0-9]{3}\s?[0-9]{2}\z/',default=>'/\A\z/'};
    return preg_match($pattern,$postal)===1;
}
function geo_postcode_key(string $country,string $postal):string {
    $normalized=geo_normalize_postcode($country,$postal);
    if(in_array(geo_country($country),['PL','CZ'],true))$normalized=str_replace(['-',' '],'',$normalized);
    return $normalized;
}
function geo_country_enabled(PDO $pdo,string $country):bool {
    try{$country=geo_country($country);}catch(InvalidArgumentException){return false;}$q=$pdo->prepare('SELECT is_active FROM market_countries WHERE code=?');$q->execute([$country]);return (int)$q->fetchColumn()===1;
}
function geo_active_countries(PDO $pdo):array {
    $rows=$pdo->query('SELECT code FROM market_countries WHERE is_active=1 ORDER BY code')->fetchAll(PDO::FETCH_COLUMN);
    $names=geo_countries();$result=[];foreach($rows as $code)if(isset($names[$code]))$result[$code]=$names[$code];return $result;
}
function geo_postcode(PDO $pdo,string $country,string $postal):?array {
    $country=geo_country($country);
    if(!geo_validate_postcode($country,$postal))throw new InvalidArgumentException('Die Postleitzahl passt nicht zum ausgewählten Land.');
    $q=$pdo->prepare('SELECT p.country_code,p.postal_code,p.city,p.latitude,p.longitude FROM geo_locations p JOIN geo_imports i ON i.id=p.import_id JOIN market_countries c ON c.code=p.country_code WHERE p.country_code=? AND p.postal_code=? AND c.is_active=1 AND i.license_reviewed=1 AND i.is_active=1');
    $q->execute([$country,geo_postcode_key($country,$postal)]);$row=$q->fetch();return $row?:null;
}
function geo_coordinates_valid(mixed $latitude,mixed $longitude):bool {
    return is_numeric($latitude)&&is_numeric($longitude)&&is_finite((float)$latitude)&&is_finite((float)$longitude)&&(float)$latitude>=-90&&(float)$latitude<=90&&(float)$longitude>=-180&&(float)$longitude<=180;
}
function geo_distance_km(float $lat1,float $lon1,float $lat2,float $lon2):float {
    if(!geo_coordinates_valid($lat1,$lon1)||!geo_coordinates_valid($lat2,$lon2))throw new InvalidArgumentException('Ungültige Koordinaten.');
    $dLat=deg2rad($lat2-$lat1);$dLon=deg2rad($lon2-$lon1);$a=sin($dLat/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
    return 6371.0088*2*atan2(sqrt(max(0.0,min(1.0,$a))),sqrt(max(0.0,1-$a)));
}
function geo_distance_label(mixed $distance):string {
    if(!is_numeric($distance)||!is_finite((float)$distance)||(float)$distance<0)return function_exists('t')?t('Entfernung nicht verfügbar'):'Entfernung nicht verfügbar';
    $value=(float)$distance<10?number_format((float)$distance,1,',','.'):number_format((float)$distance,0,',','.');
    return function_exists('t')?t('ca. {distance} km Luftlinie',['distance'=>$value]):'ca. '.$value.' km Luftlinie';
}
function geo_request_origin(PDO $pdo,array $input):?array {
    $postal=$input['zip']??'';$country=$input['origin_country']??($input['country_code']??geo_country());
    if(!is_string($postal)||!is_string($country))throw new InvalidArgumentException('Ungültiger Standortfilter.');
    if(trim($postal)==='')return null;
    return geo_postcode($pdo,geo_country($country),$postal);
}

function geo_country_locked_enabled(PDO $pdo,string $country):bool {
 try{$country=geo_country($country);}catch(InvalidArgumentException){return false;}$q=$pdo->prepare('SELECT is_active FROM market_countries WHERE code=? FOR SHARE');$q->execute([$country]);return (int)$q->fetchColumn()===1;
}

function geo_bounding_box(float $latitude,float $longitude,float $radius):array {
 if(!geo_coordinates_valid($latitude,$longitude)||$radius<1||$radius>2000)throw new InvalidArgumentException('Ungültiger Suchradius.');
 $angle=$radius/6371.0088;$delta=rad2deg($angle)+0.00001;$minLat=max(-90.0,$latitude-$delta);$maxLat=min(90.0,$latitude+$delta);$lonDelta=180.0;
 if($minLat>-90&&$maxLat<90&&abs(cos(deg2rad($latitude)))>0.000001){$ratio=sin($angle)/cos(deg2rad($latitude));if(abs($ratio)<1)$lonDelta=rad2deg(asin($ratio))+0.00001;}
 return ['min_lat'=>$minLat,'max_lat'=>$maxLat,'min_lon'=>$longitude-$lonDelta,'max_lon'=>$longitude+$lonDelta];
}
