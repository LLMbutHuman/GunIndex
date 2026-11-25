<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('GUNINDEX_CI_FIXTURE') !== 'swisstopo-postcodes' || getenv('DB_DATABASE') !== 'gunindex') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) exit(2);

function swisstopo_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$locations = $pdo->query("SELECT country_code,COUNT(*) AS count FROM geo_locations WHERE country_code IN ('CH','LI') GROUP BY country_code ORDER BY country_code")->fetchAll(PDO::FETCH_KEY_PAIR);
swisstopo_check(array_map('intval',$locations) === ['CH'=>3177,'LI'=>13], 'Expected both national swisstopo datasets.');
$attributions = geo_postcode_attributions($pdo, ['CH','LI']);
swisstopo_check(isset($attributions['CH']['©swisstopo (2026-11-01)'],$attributions['LI']['©swisstopo (2026-11-01)']),
    'Expected official source attribution for both countries.');
$zurich = geo_postcode($pdo, 'CH', '8001');
$vaduz = geo_postcode($pdo, 'LI', '9490');
swisstopo_check($zurich['city'] === 'Zürich' && $zurich['longitude'] === '8.5424538'
    && $vaduz['city'] === 'Vaduz' && $vaduz['latitude'] === '47.1431394', 'Expected actual Zurich and Vaduz points.');
swisstopo_check(geo_postcode($pdo,'CH','3001') === null && geo_postcode($pdo,'CH','9490') === null
    && geo_postcode($pdo,'LI','8001') === null, 'A non-addressing or foreign postcode entered the dataset.');

$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code) VALUES ('ci_swisstopo','fixture-ch-li@example.invalid',?,'Synthetic swisstopo fixture','9490','Vaduz','LI')")
        ->execute([auth_hash('Synthetic swisstopo postcode fixture '.bin2hex(random_bytes(16)))]);
    $seller = (int)$pdo->lastInsertId();
    $category = market_category_ids($pdo)[0];
    $insert = $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code) VALUES (?,?,'Synthetic desk','Synthetic radius fixture.','fixed',1000,'active',?,?,?)");
    $ids = [];
    foreach (['9490'=>['Vaduz','LI'],'9494'=>['Schaan','LI'],'8001'=>['Zürich','CH']] as $postal => [$city,$country]) {
        $insert->execute([$seller,$category,(string)$postal,$city,$country]);
        $ids[$postal] = (int)$pdo->lastInsertId();
    }
    $filters = ['seller_id'=>$seller,'country_code'=>'LI','zip'=>'9490','radius_km'=>5,'sort'=>'distance'];
    swisstopo_check(array_map('intval',array_column(market_search($pdo,$filters)['items'],'id')) === [$ids[9490],$ids[9494]],
        'Radius and country filters did not select the nearby Liechtenstein listings.');
    $pdo->exec("UPDATE market_countries SET is_active=0 WHERE code='LI'");
    swisstopo_check(geo_postcode_attributions($pdo, ['LI','CH']) === ['CH'=>$attributions['CH']] && geo_postcode($pdo, 'LI', '9490') === null,
        'Disabling one market should preserve the other market and its attribution.');
} finally {
    $pdo->rollBack();
}
fwrite(STDOUT, "Verified 3177 Swiss and 13 Liechtenstein postcode points, attribution, exact separation and actual radius search.\n");
