<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('GUNINDEX_CI_FIXTURE') !== 'belgian-postcodes' || getenv('DB_DATABASE') !== 'gunindex') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) exit(2);

function belgian_postcodes_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$source = $pdo->query("SELECT record_count FROM geo_imports WHERE country_code='BE'")->fetchAll(PDO::FETCH_COLUMN);
$count = (int)$pdo->query("SELECT COUNT(*) FROM geo_locations WHERE country_code='BE'")->fetchColumn();
$brussels = geo_postcode($pdo,'BE','1000');
belgian_postcodes_check(count($source)===1 && (int)$source[0]===$count && $count===1139
    && isset(geo_postcode_attributions($pdo,['BE'])['BE'])
    && $brussels['latitude']==='50.8465442' && $brussels['longitude']==='4.3571438',
    'Expected the Belgian aggregate points and attribution.');

$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code) VALUES ('ci_be_postcodes','fixture-be@example.invalid',?,'Synthetic Belgian fixture','1000','Bruxelles','BE')")
        ->execute([auth_hash('Synthetic Belgian fixture '.bin2hex(random_bytes(16)))]);
    $seller = (int)$pdo->lastInsertId();
    $category = market_category_ids($pdo)[0];
    $insert = $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code) VALUES (?,?,'Synthetic lamp','Synthetic postcode radius fixture.','fixed',1000,'active',?,?,?)");
    $ids = [];
    foreach ([['1000','Bruxelles','BE'],['1040','Etterbeek','BE'],['4000','Liège','BE'],['1000','Lausanne','CH']] as [$postal,$town,$country]) {
        $insert->execute([$seller,$category,$postal,$town,$country]);
        $ids[$country.$postal] = (int)$pdo->lastInsertId();
    }
    $search = static fn(array $criteria):array => array_map('intval',array_column(market_search($pdo,['seller_id'=>$seller,'country_code'=>'BE']+$criteria)['items'],'id'));
    belgian_postcodes_check($search(['zip'=>'1000','radius_km'=>10,'sort'=>'distance'])===[$ids['BE1000'],$ids['BE1040']],
        'Expected only nearby Brussels listings inside their real radius.');
    belgian_postcodes_check($search(['zip'=>'1000'])===[$ids['BE1000']], 'Exact search must constrain the country.');
} finally {
    $pdo->rollBack();
}
printf("Verified %d Belgian postcode points, Brussels radius, attribution and country isolation.\n",$count);
