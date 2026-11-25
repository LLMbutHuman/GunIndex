<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('GUNINDEX_CI_FIXTURE') !== 'czech-postcodes' || getenv('DB_DATABASE') !== 'gunindex') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) exit(2);

function czech_postcodes_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$source = $pdo->query("SELECT record_count FROM geo_imports WHERE country_code='CZ'")->fetchAll(PDO::FETCH_COLUMN);
$prague = geo_postcode($pdo,'CZ','110 00');
czech_postcodes_check(count($source) === 1 && (int)$source[0] === 2675
    && (int)$pdo->query("SELECT COUNT(*) FROM geo_locations WHERE country_code='CZ'")->fetchColumn() === 2675
    && isset(geo_postcode_attributions($pdo,['CZ'])['CZ'])
    && $prague['city'] === 'Praha' && abs((float)$prague['latitude']-50.08537)<0.000001
    && abs((float)$prague['longitude']-14.42336)<0.000001, 'Expected verified Czech postcode points and attribution.');

$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code) VALUES ('ci_cz_postcodes','fixture-cz@example.invalid',?,'Synthetic Czech fixture','11000','Praha','CZ')")
        ->execute([auth_hash('Synthetic Czech fixture '.bin2hex(random_bytes(16)))]);
    $seller = (int)$pdo->lastInsertId();
    $category = market_category_ids($pdo)[0];
    $insert = $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code) VALUES (?,?,'Synthetic lamp','Synthetic postcode radius fixture.','fixed',1000,'active',?,?,?)");
    $ids = [];
    foreach ([['11000','Praha','CZ'],['11800','Praha','CZ'],['60200','Brno','CZ'],['11000','Carcassonne','FR']] as [$postal,$town,$country]) {
        $insert->execute([$seller,$category,$postal,$town,$country]);
        $ids[$country.$postal] = (int)$pdo->lastInsertId();
    }
    $search = static fn(array $criteria):array => array_map('intval',array_column(market_search($pdo,['seller_id'=>$seller,'country_code'=>'CZ']+$criteria)['items'],'id'));
    czech_postcodes_check($search(['zip'=>'11000','radius_km'=>5,'sort'=>'distance']) === [$ids['CZ11000'],$ids['CZ11800']],
        'Expected nearby Prague listings inside their real radius.');
    czech_postcodes_check($search(['zip'=>'110 00']) === [$ids['CZ11000']], 'Exact search must normalize spaces and constrain the country.');
} finally {
    $pdo->rollBack();
}
fwrite(STDOUT, "Verified 2675 Czech postcode points, Prague radius, formatted postcode lookup and country isolation.\n");
