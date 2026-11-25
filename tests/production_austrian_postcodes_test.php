<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('GUNINDEX_CI_FIXTURE') !== 'austrian-postcodes' || getenv('DB_DATABASE') !== 'gunindex') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) exit(2);

function austrian_postcodes_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$source = $pdo->query("SELECT id,record_count FROM geo_imports WHERE country_code='AT'")->fetchAll();
austrian_postcodes_check(count($source) === 1 && (int)$source[0]['record_count'] === 2200
    && (int)$pdo->query("SELECT COUNT(*) FROM geo_locations WHERE country_code='AT'")->fetchColumn() === 2200
    && isset(geo_postcode_attributions($pdo, ['at'])['AT']), 'Expected the reviewed Austrian file and public attribution.');
$salzburg = geo_postcode($pdo, 'AT', '5020');
austrian_postcodes_check($salzburg['latitude'] === '47.7994000' && $salzburg['longitude'] === '13.0440000',
    'Expected the GeoNames Salzburg point.');
austrian_postcodes_check(geo_postcode($pdo, 'AT', '1010') === null && geo_postcode($pdo, 'AT', '1000') === null,
    'The seed included an excluded estimated point or post office box.');

$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code) VALUES ('ci_at_postcodes','fixture-at@example.invalid',?,'Synthetic Austrian postcode fixture','5020','Salzburg','AT')")
        ->execute([auth_hash('Synthetic Austrian postcode fixture '.bin2hex(random_bytes(16)))]);
    $seller = (int)$pdo->lastInsertId();
    $category = market_category_ids($pdo)[0];
    $insert = $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code) VALUES (?,?,'Synthetic folding table','Synthetic postcode search fixture.','fixed',1000,'active',?,?,?)");
    $ids = [];
    foreach (['5020'=>['Salzburg','AT'],'5400'=>['Hallein','AT'],'6020'=>['Innsbruck','AT'],'1010'=>['Wien','AT'],'10115'=>['Berlin','DE']] as $postal => [$city,$country]) {
        $insert->execute([$seller,$category,(string)$postal,$city,$country]);
        $ids[$postal] = (int)$pdo->lastInsertId();
    }
    $search = static fn(array $filters):array => array_map('intval',array_column(market_search($pdo,['seller_id'=>$seller,'country_code'=>'AT']+$filters)['items'],'id'));
    austrian_postcodes_check($search(['zip'=>'5020','radius_km'=>25,'sort'=>'distance']) === [$ids[5020],$ids[5400]],
        'Austria radius search did not separate nearby real coordinates from Innsbruck.');
    austrian_postcodes_check($search(['zip'=>'1010']) === [$ids[1010]], 'Exact postcode search needs to work without a point.');
    $pdo->prepare('UPDATE geo_imports SET is_active=0 WHERE id=?')->execute([$source[0]['id']]);
    austrian_postcodes_check(geo_postcode_attributions($pdo, ['AT']) === [] && geo_postcode($pdo, 'AT', '5020') === null
        && $search(['zip'=>'5020']) === [$ids[5020]], 'Deactivation must keep exact search and hide the distance source.');
} finally {
    $pdo->rollBack();
}
fwrite(STDOUT, "Verified 2200 Austrian postcodes, source attribution, radius search and exact search fallback.\n");
