<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('GUNINDEX_CI_FIXTURE') !== 'danish-postcodes' || getenv('DB_DATABASE') !== 'gunindex') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'gunindex' || (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) exit(2);

function danish_postcodes_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$source=$pdo->query("SELECT record_count FROM geo_imports WHERE country_code='DK'")->fetchAll(PDO::FETCH_COLUMN);
$count=(int)$pdo->query("SELECT COUNT(*) FROM geo_locations WHERE country_code='DK'")->fetchColumn();
$copenhagen=geo_postcode($pdo,'DK','1050');
danish_postcodes_check(count($source)===1 && (int)$source[0]===$count && $count===972
    && isset(geo_postcode_attributions($pdo,['DK'])['DK']) && $copenhagen['city']==='København K'
    && abs((float)$copenhagen['latitude']-55.68041)<0.000001 && abs((float)$copenhagen['longitude']-12.58600)<0.000001
    && geo_postcode($pdo,'DK','6853')===null, 'Expected Copenhagen point and exclusion of oversized offshore district.');

$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code) VALUES ('ci_dk_postcodes','fixture-dk@example.invalid',?,'Synthetic Danish fixture','1050','København K','DK')")
        ->execute([auth_hash('Synthetic Danish fixture '.bin2hex(random_bytes(16)))]);
    $seller=(int)$pdo->lastInsertId();
    $category=market_category_ids($pdo)[0];
    $insert=$pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code) VALUES (?,?,'Synthetic lamp','Synthetic Danish postcode fixture.','fixed',1000,'active',?,?,?)");
    $ids=[];
    foreach ([['1050','København K','DK'],['1051','København K','DK'],['5000','Odense C','DK'],['1050','Wien','AT'],['6853','Vejers Strand','DK']] as [$postal,$town,$country]) {
        $insert->execute([$seller,$category,$postal,$town,$country]);
        $ids[$country.$postal]=(int)$pdo->lastInsertId();
    }
    $search=static fn(array $criteria):array=>array_map('intval',array_column(market_search($pdo,['seller_id'=>$seller,'country_code'=>'DK']+$criteria)['items'],'id'));
    danish_postcodes_check($search(['zip'=>'1050','radius_km'=>5,'sort'=>'distance'])===[$ids['DK1050'],$ids['DK1051']],
        'Expected only the two Copenhagen listings in the radius.');
    danish_postcodes_check($search(['zip'=>'1050'])===[$ids['DK1050']] && $search(['zip'=>'6853'])===[$ids['DK6853']],
        'Expected exact country-scoped search with or without coordinate data.');
} finally {
    $pdo->rollBack();
}
printf("Verified %d Danish points, Copenhagen radius, exact search and country isolation.\n",$count);
