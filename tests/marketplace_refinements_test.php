<?php
declare(strict_types=1);
require_once __DIR__.'/../config/i18n.php';
require_once __DIR__.'/../config/market.php';

function refine_check(bool $check, string $description): void
{
    if (!$check) throw new RuntimeException($description);
}

$soldByDealer = market_seller_type_filter('dealer');
refine_check(market_seller_type_filter(null)===null && market_seller_type_filter('')===null,'Unfiltered searches must remain unchanged.');
refine_check(is_string($soldByDealer) && market_seller_type_filter('private')==='NOT '.$soldByDealer,'Seller types must be complementary.');
try {
    market_seller_type_filter(['dealer']);
    throw new RuntimeException('Array accepted as a seller type.');
} catch (InvalidArgumentException) {}
$first = require __DIR__.'/../locales/marketplace_refinements/de.php';
foreach (array_keys(i18n_locales()) as $locale) {
    $catalog = require __DIR__.'/../locales/marketplace_refinements/'.$locale.'.php';
    refine_check(array_keys($first)===array_keys($catalog),'Locale missing refinement labels: '.$locale);
    foreach ($first as $key=>$german) {
        preg_match_all('/\{[a-z]+\}/',$german,$expected);
        preg_match_all('/\{[a-z]+\}/',$catalog[$key],$found);
        refine_check($found[0]===$expected[0] && i18n_dictionary($locale)[$key]===$catalog[$key], 'Translation not loaded: '.$locale.' '.$key);
    }
}
echo "Seller filter validation and eight locales passed.\n";

if (!in_array('--database',$argv,true)) exit;
if (PHP_SAPI!=='cli' || PHP_OS_FAMILY==='Windows' || getenv('DB_DATABASE')!=='gunindex_rebuild_test') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='gunindex_rebuild_test') exit(2);

function refine_reject(callable $operation, string $exception): void
{
    try { $operation(); } catch (Throwable $error) { if ($error instanceof $exception) return; throw $error; }
    throw new RuntimeException('Expected refusal: '.$exception);
}

$token = bin2hex(random_bytes(7));
$user = $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,is_admin,country_code,preferred_locale) VALUES (?,?,?,'Synthetic fixture','10115','Synthetic city',?,'DE','de')");
$ids = [];
foreach (['admin','private','dealer','pending'] as $kind) {
    $name = 'refine_'.$kind.'_'.$token;
    $user->execute([$name,$name.'@example.invalid',auth_hash('Synthetic password '.$token),(int)($kind==='admin')]);
    $ids[$kind] = (int)$pdo->lastInsertId();
}
$profiles = $pdo->prepare("INSERT INTO market_dealer_profiles(user_id,company_name,address_text,country_code,contact_email,legal_notice,status) VALUES (?,'Synthetic company','Synthetic address','DE','fixture@example.invalid','Synthetic notice',?)");
$profiles->execute([$ids['dealer'],'published']);
$profiles->execute([$ids['pending'],'pending']);
$newCategory = static function (string $suffix, ?int $parent=null) use ($pdo,$token): int {
    $pdo->prepare('INSERT INTO market_categories(parent_id,name,slug,is_active) VALUES (?,?,?,1)')->execute([$parent,'Synthetic '.$suffix.' '.$token,'refine-'.$suffix.'-'.$token]);
    return (int)$pdo->lastInsertId();
};
$category = $newCategory('listing');
$list = $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code) VALUES (?,?,?,'Ordinary synthetic listing','fixed',?,'active','10115','Synthetic city','DE')");
$offers = [];
foreach (['private'=>1600,'dealer'=>1200,'pending'=>1500] as $kind=>$price) {
    $list->execute([$ids[$kind],$category,'Synthetic desk '.$token,$price]);
    $offers[$kind] = (int)$pdo->lastInsertId();
}
$filters = ['category_id'=>(string)$category,'type'=>'fixed','country_code'=>'DE','zip'=>'10115','min_price'=>'10,00','sort'=>'price_asc'];
refine_check(market_search($pdo,$filters)['total']===3,'Default filter hid an offer.');
$private = market_search($pdo,$filters+['seller_type'=>'private'],1,1);
refine_check($private['total']===2 && (int)$private['items'][0]['id']===$offers['pending'],'Private filter or sort failed.');
refine_check((int)market_search($pdo,$filters+['seller_type'=>'private'],2,1)['items'][0]['id']===$offers['private'],'Private filter did not survive paging.');
refine_check((int)market_search($pdo,$filters+['seller_type'=>'dealer'])['items'][0]['id']===$offers['dealer'],'Dealer filter failed.');
refine_check(discovery_validate_search($pdo,$filters+['seller_type'=>'private'])['seller_type']==='private','Saved search dropped seller type.');

$empty = $newCategory('empty');
refine_reject(fn()=>catalog_remove_category($pdo,$ids['private'],$empty,true),DomainException::class);
refine_reject(fn()=>catalog_remove_category($pdo,$ids['admin'],$category,true),DomainException::class);
$child = $newCategory('child',$category);
catalog_remove_category($pdo,$ids['admin'],$category,false);
refine_check(!catalog_category_allowed($pdo,$child) && isset(catalog_rows($pdo)[$category]),'Deactivation removed content or left its subtree exposed.');
catalog_remove_category($pdo,$ids['admin'],$empty,true);
refine_check(!isset(catalog_rows($pdo)[$empty]),'An empty category could not be permanently removed.');
$checkLog=$pdo->prepare("SELECT COUNT(*) FROM admin_logs WHERE admin_id=? AND ((action_type='category.deleted' AND target_id=?) OR (action_type='category.deactivated' AND target_id=?))");
$checkLog->execute([$ids['admin'],$empty,$category]);
refine_check((int)$checkLog->fetchColumn()===2,'Category changes need audit entries.');

$category = $newCategory('auction');
$pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code,ends_at,highest_bidder_id,current_bid_cents) VALUES (?,?,?,'Synthetic auction','auction',1200,'ended','10115','Synthetic city','DE',UTC_TIMESTAMP()-INTERVAL 1 HOUR,?,1300)")
    ->execute([$ids['private'],$category,'Synthetic auction '.$token,$ids['dealer']]);
$auction = (int)$pdo->lastInsertId();
refine_reject(fn()=>trade_rate_auction($pdo,$ids['dealer'],$auction,5,''),MarketAccessException::class);
$pdo->prepare('INSERT INTO market_proxy_bids(listing_id,user_id,max_cents) VALUES (?,?,2000)')->execute([$auction,$ids['dealer']]);
$pdo->prepare("INSERT INTO market_bids(listing_id,user_id,amount_cents,event_type,idempotency_key) VALUES (?,?,1300,'close',?)")->execute([$auction,$ids['dealer'],'close-'.$auction]);
refine_reject(fn()=>trade_rate_auction($pdo,$ids['pending'],$auction,5,''),MarketAccessException::class);
trade_rate_auction($pdo,$ids['dealer'],$auction,5,'');
refine_reject(fn()=>trade_rate_auction($pdo,$ids['dealer'],$auction,5,''),MarketConflictException::class);
$ratings = trade_public_ratings($pdo,$ids['private'],1);
refine_check($ratings['count']===1 && (float)$ratings['average']===5.0 && $ratings['rows'][0]['comment']==='', 'Public rating statistics or optional comment failed.');
$pdo->prepare("UPDATE market_ratings SET status='hidden' WHERE listing_id=? AND sender_id=?")->execute([$auction,$ids['dealer']]);
refine_check(trade_public_ratings($pdo,$ids['private'],1)['count']===0,'Hidden rating leaked into the public total.');
echo "Database checks passed for seller filters, category removal and auction ratings.\n";
