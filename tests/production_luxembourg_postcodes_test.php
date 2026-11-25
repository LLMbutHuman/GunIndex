<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('GUNINDEX_CI_FIXTURE') !== 'luxembourg-postcodes' || getenv('DB_DATABASE') !== 'gunindex') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) exit(2);

function luxembourg_postcodes_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$source = $pdo->query("SELECT id,record_count FROM geo_imports WHERE country_code='LU'")->fetchAll();
luxembourg_postcodes_check(count($source) === 1 && (int)$source[0]['record_count'] === 4068
    && (int)$pdo->query("SELECT COUNT(*) FROM geo_locations WHERE country_code='LU'")->fetchColumn() === 4068
    && isset(geo_postcode_attributions($pdo,['LU'])['LU']), 'Expected the Luxembourg bundle and its attribution.');
$city = geo_postcode($pdo,'LU','1111');
luxembourg_postcodes_check($city['city'] === 'Luxembourg' && $city['latitude'] === '49.6008279'
    && geo_postcode($pdo,'LU','1125') === null && geo_postcode($pdo,'LU','1019') === null,
    'Expected actual Luxembourg points, with low-count and non-addressing codes excluded.');

$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code) VALUES ('ci_lu_postcodes','fixture-lu@example.invalid',?,'Synthetic Luxembourg postcode fixture','1111','Luxembourg','LU')")
        ->execute([auth_hash('Synthetic Luxembourg postcode fixture '.bin2hex(random_bytes(16)))]);
    $seller = (int)$pdo->lastInsertId();
    $category = market_category_ids($pdo)[0];
    $insert = $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code) VALUES (?,?,'Synthetic stool','Synthetic postcode radius fixture.','fixed',1000,'active',?,?,?)");
    $ids = [];
    foreach ([['1111','Luxembourg','LU'],['1234','Luxembourg','LU'],['9706','Clervaux','LU'],['1234','Vessy','CH'],['1125','Luxembourg','LU']] as [$postal,$town,$country]) {
        $insert->execute([$seller,$category,$postal,$town,$country]);
        $ids[$country.$postal] = (int)$pdo->lastInsertId();
    }
    $search = static fn(array $criteria):array => array_map('intval',array_column(market_search($pdo,['seller_id'=>$seller,'country_code'=>'LU']+$criteria)['items'],'id'));
    luxembourg_postcodes_check($search(['zip'=>'1111','radius_km'=>5,'sort'=>'distance']) === [$ids['LU1111'],$ids['LU1234']],
        'Expected only nearby Luxembourg listings for the real radius.');
    luxembourg_postcodes_check($search(['zip'=>'1234']) === [$ids['LU1234']] && $search(['zip'=>'1125']) === [$ids['LU1125']],
        'Exact search must constrain country and keep postcodes without a point.');
} finally {
    $pdo->rollBack();
}
fwrite(STDOUT, "Verified 4068 Luxembourg postcode points, attribution, radius and country-bound exact search.\n");
