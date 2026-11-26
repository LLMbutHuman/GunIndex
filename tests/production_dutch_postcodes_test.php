<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('GUNINDEX_CI_FIXTURE') !== 'dutch-postcodes' || getenv('DB_DATABASE') !== 'gunindex') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'gunindex' || (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) exit(2);

function dutch_postcodes_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$source=$pdo->query("SELECT record_count FROM geo_imports WHERE country_code='NL'")->fetchAll(PDO::FETCH_COLUMN);
$count=(int)$pdo->query("SELECT COUNT(*) FROM geo_locations WHERE country_code='NL'")->fetchColumn();
$amsterdam=geo_postcode($pdo,'NL','1012 aa');
dutch_postcodes_check(count($source)===1 && (int)$source[0]===$count && $count===436312
    && isset(geo_postcode_attributions($pdo,['NL'])['NL']) && $amsterdam['city']==='Amsterdam'
    && abs((float)$amsterdam['latitude']-52.37943)<0.000001 && abs((float)$amsterdam['longitude']-4.90145)<0.000001
    && geo_postcode($pdo,'NL','9999 ZZ')===null, 'Expected reviewed Amsterdam point and no point for the synthetic code.');

$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code) VALUES ('ci_nl_postcodes','fixture-nl@example.invalid',?,'Synthetic Dutch fixture','1012AA','Amsterdam','NL')")
        ->execute([auth_hash('Synthetic Dutch fixture '.bin2hex(random_bytes(16)))]);
    $seller=(int)$pdo->lastInsertId();
    $category=market_category_ids($pdo)[0];
    $insert=$pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code) VALUES (?,?,'Synthetic lamp','Synthetic Dutch postcode fixture.','fixed',1000,'active',?,?,?)");
    $ids=[];
    foreach ([['1012AA','Amsterdam','NL'],['1012AB','Amsterdam','NL'],['3511AB','Utrecht','NL'],['10115','Berlin','DE'],['9999ZZ','Synthetic locality','NL']] as [$postal,$town,$country]) {
        $insert->execute([$seller,$category,$postal,$town,$country]);
        $ids[$country.$postal]=(int)$pdo->lastInsertId();
    }
    $search=static fn(array $criteria):array=>array_map('intval',array_column(market_search($pdo,['seller_id'=>$seller,'country_code'=>'NL']+$criteria)['items'],'id'));
    dutch_postcodes_check($search(['zip'=>'1012 aa','radius_km'=>5,'sort'=>'distance'])===[$ids['NL1012AA'],$ids['NL1012AB']],
        'Expected Amsterdam offers and excluded Utrecht by the real coordinates.');
    dutch_postcodes_check($search(['zip'=>'1012 AA'])===[$ids['NL1012AA']] && $search(['zip'=>'9999 zz'])===[$ids['NL9999ZZ']]
        && !in_array($ids['DE10115'],$search([]),true), 'Expected exact postcode normalization and country isolation.');
} finally {
    $pdo->rollBack();
}
printf("Verified %d Dutch points, Amsterdam radius, formatted postcodes and country isolation.\n",$count);
