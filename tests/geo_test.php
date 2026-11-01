<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
require_once __DIR__.'/../config/geo.php';require_once __DIR__.'/../config/i18n.php';
$passed=0;
function geo_test(string $name,callable $run):void {global $passed;try{$run();$passed++;fwrite(STDOUT,'PASS '.$name.PHP_EOL);}catch(Throwable $e){fwrite(STDERR,'FAIL '.$name.PHP_EOL);exit(1);}}
function geo_check(bool $value):void {if(!$value)throw new RuntimeException('Geographic assertion failed.');}
geo_test('eleven_supported_country_codes',static function():void {geo_check(count(geo_countries())===11);geo_check(geo_country('nl')==='NL');try{geo_country('ZZ');}catch(InvalidArgumentException){return;}throw new RuntimeException();});
geo_test('country_postal_formats_and_leading_zero',static function():void {
 foreach(['DE'=>'01067','AT'=>'1010','CH'=>'8001','FR'=>'75001','BE'=>'1000','NL'=>'1234 AB','LU'=>'1234','DK'=>'2100','PL'=>'00-001','CZ'=>'110 00','LI'=>'9490'] as $country=>$postal)geo_check(geo_validate_postcode($country,$postal));
 geo_check(!geo_validate_postcode('DE','1067'));geo_check(!geo_validate_postcode('AT','10115'));geo_check(!geo_validate_postcode('NL','0000AA'));geo_check(!geo_validate_postcode('DE','10115<script>'));
 geo_check(geo_postcode_key('NL','1234 ab')==='1234AB');geo_check(geo_postcode_key('PL','00-001')==='00001');geo_check(geo_postcode_key('CZ','110 00')==='11000');
});
geo_test('haversine_zero_known_degree_and_antimeridian',static function():void {geo_check(geo_distance_km(0,0,0,0)===0.0);geo_check(abs(geo_distance_km(0,0,1,0)-111.195080)<0.001);$wrapped=geo_distance_km(0,179.9,0,-179.9);geo_check($wrapped>22&&$wrapped<23);geo_check(!geo_coordinates_valid(91,0));geo_check(!geo_coordinates_valid('NaN',0));});
geo_test('bounding_box_keeps_large_circle_and_polar_edges',static function():void {$box=geo_bounding_box(50,6,2000);geo_check($box['min_lat']<33&&$box['max_lat']>67);geo_check($box['max_lon']>34);$polar=geo_bounding_box(89,179,500);geo_check($polar['max_lat']===90.0);geo_check($polar['max_lon']>180);});
geo_test('unknown_distance_is_never_reported_as_zero',static function():void {$_SESSION['locale']='de';geo_check(geo_distance_label(null)==='Entfernung nicht verfügbar');geo_check(str_contains(geo_distance_label(0),'0,0'));});
geo_test('eight_local_dictionaries_and_safe_fallback',static function():void {geo_check(count(i18n_locales())===8);foreach(i18n_locales() as $locale=>$name){geo_check(count(i18n_dictionary($locale))>=160);}$_SESSION['locale']='en';geo_check(t('Anmelden')==='Sign in');geo_check(geo_countries()['DE']==='Germany');geo_check(t('Not a translated user title')==='Not a translated user title');geo_check(i18n_dictionary('../../etc/passwd')===i18n_dictionary('de'));$_SESSION['locale']='de';});

geo_test('locale_key_and_placeholder_parity',static function():void {
 $base=i18n_dictionary('de');foreach(i18n_locales() as $locale=>$label){$dictionary=i18n_dictionary($locale);geo_check(array_keys($base)===array_keys($dictionary));foreach($base as $key=>$text){geo_check(is_string($dictionary[$key])&&preg_match('//u',$dictionary[$key])===1);preg_match_all('/\{[a-z_]+\}/',$text,$expected);preg_match_all('/\{[a-z_]+\}/',$dictionary[$key],$actual);sort($expected[0]);sort($actual[0]);geo_check($expected[0]===$actual[0]);}}
});

if(in_array('--database',$argv,true)){
 if(getenv('DB_DATABASE')!=='gunindex_rebuild_test'){fwrite(STDERR,"Database checks require the isolated synthetic schema.\n");exit(2);}
 require_once __DIR__.'/../config/bootstrap.php';
 geo_test('local_licensed_coordinates_country_isolation_and_radius',static function()use($pdo):void {
  $_SESSION['market_country']='DE';$suffix=bin2hex(random_bytes(8));$name='test_'.$suffix;
  $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city) VALUES (?,?,?,'Synthetic test fixture','00001','Synthetic town')")->execute([$name,$name.'@example.invalid',auth_hash('Synthetic postal test '.$suffix)]);$user=(int)$pdo->lastInsertId();
  $pdo->prepare("UPDATE users SET preferred_locale='fr' WHERE id=?")->execute([$user]);$oldLocale=$_SESSION['locale']??'de';geo_check(i18n_user_text($pdo,$user,'notification.outbid.title')==='Vous avez été surenchéri');geo_check(($_SESSION['locale']??'de')===$oldLocale);
  $countryRows=$pdo->query("SELECT code,is_active FROM market_countries WHERE code IN ('DE','NL')")->fetchAll(PDO::FETCH_KEY_PAIR);$pdo->exec("UPDATE market_countries SET is_active=1 WHERE code IN ('DE','NL')");
  try{
   $imports=[];foreach(['DE','NL'] as $country){$pdo->prepare("INSERT INTO geo_imports(country_code,source_name,source_sha256,license_note,license_reviewed,record_count) VALUES (?,'Synthetic geographic test fixture',?,'Synthetic coordinates only; not a production data source.',1,?)")->execute([$country,hash('sha256',$suffix.$country),$country==='DE'?2:1]);$imports[$country]=(int)$pdo->lastInsertId();}
   $points=[['DE','00001','Synthetic origin',50,6,$imports['DE']],['DE','00002','Synthetic far point',51,8,$imports['DE']],['NL','9999ZZ','Synthetic border point',50.03,6.04,$imports['NL']]];
   foreach($points as $point){$q=$pdo->prepare('SELECT i.source_name FROM geo_locations p JOIN geo_imports i ON i.id=p.import_id WHERE p.country_code=? AND p.postal_code=?');$q->execute([$point[0],$point[1]]);$existing=$q->fetchColumn();if($existing!==false&&$existing!=='Synthetic geographic test fixture')throw new RuntimeException('Synthetic postcode conflicts with existing non-fixture data.');}
   $insert=$pdo->prepare('INSERT INTO geo_locations(country_code,postal_code,city,latitude,longitude,import_id) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE city=VALUES(city),latitude=VALUES(latitude),longitude=VALUES(longitude),import_id=VALUES(import_id)');foreach($points as $point)$insert->execute($point);
   $category=(int)catalog_categories($pdo)[0]['id'];$ids=[];
   foreach([['DE','00001'],['DE','00002'],['NL','9999ZZ']] as [$country,$postal]){$pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code) VALUES (?,? ,?,'Synthetic ordinary geography regression listing.','fixed',1000,'active',?,'Synthetic town',?)")->execute([$user,$category,'Fixture '.bin2hex(random_bytes(8)).' '.$suffix,$postal,$country]);$ids[$country.$postal]=(int)$pdo->lastInsertId();}
   $criteria=['q'=>$suffix,'country_code'=>'DE','zip'=>'00001','origin_country'=>'DE','radius_km'=>'10','sort'=>'distance'];$national=market_search($pdo,$criteria);geo_check($national['total']===1);
   $international=market_search($pdo,array_merge($criteria,['scope'=>'international']));geo_check($international['total']===2);geo_check((float)$international['items'][0]['distance_km']<0.001);geo_check((float)$international['items'][1]['distance_km']>1&&(float)$international['items'][1]['distance_km']<10);
   $single=market_search($pdo,['listing_id'=>$ids['NL9999ZZ'],'country_code'=>'NL']);geo_check($single['total']===1);
   $pdo->prepare('UPDATE geo_imports SET is_active=0 WHERE id=?')->execute([$imports['NL']]);$withoutSource=market_search($pdo,array_merge($criteria,['scope'=>'international']));geo_check($withoutSource['total']===1);
   $pdo->exec("UPDATE market_countries SET is_active=0 WHERE code='NL'");geo_check(market_find($pdo,$ids['NL9999ZZ'])===null);
  }finally{foreach($countryRows as $country=>$active)$pdo->prepare('UPDATE market_countries SET is_active=? WHERE code=?')->execute([(int)$active,$country]);}
 });
 geo_test('radius_without_licensed_origin_fails_honestly',static function()use($pdo):void {try{market_search($pdo,['country_code'=>'DE','origin_country'=>'DE','zip'=>'00000','radius_km'=>'10']);}catch(InvalidArgumentException $e){geo_check(str_contains($e->getMessage(),'Koordinaten'));return;}throw new RuntimeException();});
}
fwrite(STDOUT,'Geo checks passed: '.$passed.PHP_EOL);
