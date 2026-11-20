<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli'||getenv('GUNINDEX_CI_FIXTURE')!=='postal-reference'||getenv('DB_DATABASE')!=='gunindex') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='gunindex'||(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()!==0) exit(2);

function postal_fixture_assert(bool $condition,string $message):void
{
    if (!$condition) throw new RuntimeException($message);
}

$source=$pdo->query("SELECT id,source_sha256,record_count FROM geo_imports WHERE country_code='DE'")->fetchAll();
postal_fixture_assert(count($source)===1&&$source[0]['source_sha256']==='3f8a041a81caceb948ba30b285ba80281d464cd22fc4a049eba4bebdd8aab59b'
    &&(int)$source[0]['record_count']===8289&&(int)$pdo->query("SELECT COUNT(*) FROM geo_locations WHERE country_code='DE'")->fetchColumn()===8289,
    'Postcode import does not match the reviewed file and count.');
$eilenburg=geo_postcode($pdo,'DE','04838');
postal_fixture_assert($eilenburg!==null&&$eilenburg['city']==='Eilenburg'&&$eilenburg['latitude']==='51.4957577'&&$eilenburg['longitude']==='12.6860934',
    'Leading zero or legacy longitude and latitude mapping changed.');

$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code) VALUES ('ci_postal_reference','fixture@example.invalid',?,'Synthetic postal fixture','10115','Berlin','DE')")
        ->execute([auth_hash('Synthetic postal reference fixture '.bin2hex(random_bytes(16)))]);
    $seller=(int)$pdo->lastInsertId();
    $categories=market_category_ids($pdo);
    $insert=$pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code) VALUES (?,?,'Synthetic folding table','Synthetic geodata search fixture.', 'fixed',1000,'active',?,?,?)");
    $ids=[];
    foreach (['10115'=>['Berlin','DE'],'10117'=>['Berlin','DE'],'80331'=>['Munich','DE'],'1010'=>['Vienna','AT']] as $postal=>[$city,$country]) {
        $insert->execute([$seller,$categories[0],(string)$postal,$city,$country]);$ids[$postal]=(int)$pdo->lastInsertId();
    }
    $search=static fn(array $filters):array=>array_map('intval',array_column(market_search($pdo,['seller_id'=>$seller]+$filters)['items'],'id'));
    postal_fixture_assert($search(['country_code'=>'DE','zip'=>'10117'])===[$ids[10117]],'Exact German postcode filter selected the wrong listing.');
    postal_fixture_assert($search(['country_code'=>'AT'])===[$ids[1010]],'Country filter mixed markets.');
    $radius=['country_code'=>'DE','zip'=>'10115','radius_km'=>5,'sort'=>'distance'];
    postal_fixture_assert($search($radius)===[$ids[10115],$ids[10117]],'Real postcode centroids did not separate Berlin from Munich by radius.');
    $pdo->prepare('UPDATE geo_imports SET is_active=0 WHERE id=?')->execute([$source[0]['id']]);
    postal_fixture_assert(geo_postcode($pdo,'DE','10115')===null&&$search(['country_code'=>'DE','zip'=>'10117'])===[$ids[10117]],
        'Source deactivation changed the exact postcode fallback.');
} finally {
    $pdo->rollBack();
}
fwrite(STDOUT,"Verified 8289 source postcodes, leading zeros, country isolation, exact search and actual coordinate radius.\n");
