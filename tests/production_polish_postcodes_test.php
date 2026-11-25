<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('GUNINDEX_CI_FIXTURE') !== 'polish-postcodes' || getenv('DB_DATABASE') !== 'gunindex') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) exit(2);

function polish_postcodes_check(bool $condition,string $message):void
{
    if (!$condition) throw new RuntimeException($message);
}

$source=$pdo->query("SELECT record_count FROM geo_imports WHERE country_code='PL'")->fetchAll(PDO::FETCH_COLUMN);
$count=(int)$pdo->query("SELECT COUNT(*) FROM geo_locations WHERE country_code='PL'")->fetchColumn();
$warsaw=geo_postcode($pdo,'PL','01-067');
polish_postcodes_check(count($source)===1 && (int)$source[0]===$count && $count===18644
    && isset(geo_postcode_attributions($pdo,['PL'])['PL']) && $warsaw['city']==='Warszawa'
    && abs((float)$warsaw['latitude']-52.25533)<0.000001 && abs((float)$warsaw['longitude']-20.97664)<0.000001
    && geo_postcode($pdo,'PL','00-010')===null, 'Expected Warsaw points and exclusion of the discrepant source point.');

$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code) VALUES ('ci_pl_postcodes','fixture-pl@example.invalid',?,'Synthetic Polish fixture','01067','Warszawa','PL')")
        ->execute([auth_hash('Synthetic Polish fixture '.bin2hex(random_bytes(16)))]);
    $seller=(int)$pdo->lastInsertId();
    $category=market_category_ids($pdo)[0];
    $insert=$pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code) VALUES (?,?,'Synthetic lamp','Synthetic postcode radius fixture.','fixed',1000,'active',?,?,?)");
    $ids=[];
    foreach ([['01067','Warszawa','PL'],['01068','Warszawa','PL'],['31001','Kraków','PL'],['01067','Dresden','DE'],['00010','Warszawa','PL']] as [$postal,$town,$country]) {
        $insert->execute([$seller,$category,$postal,$town,$country]);
        $ids[$country.$postal]=(int)$pdo->lastInsertId();
    }
    $search=static fn(array $criteria):array=>array_map('intval',array_column(market_search($pdo,['seller_id'=>$seller,'country_code'=>'PL']+$criteria)['items'],'id'));
    polish_postcodes_check($search(['zip'=>'01-067','radius_km'=>5,'sort'=>'distance'])===[$ids['PL01067'],$ids['PL01068']],
        'Expected the nearby Warsaw listings inside the actual radius.');
    polish_postcodes_check($search(['zip'=>'01-067'])===[$ids['PL01067']] && $search(['zip'=>'00-010'])===[$ids['PL00010']],
        'Exact search must constrain the country and retain valid codes without a point.');
} finally {
    $pdo->rollBack();
}
printf("Verified %d Polish points, Warsaw radius, formatted postcodes, attribution and country isolation.\n",$count);
