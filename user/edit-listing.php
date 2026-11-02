<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);
$raw=$_GET['id'] ?? null;
if (!is_string($raw) || !ctype_digit($raw)) abort_request(404,'Angebot nicht gefunden.');
$listingId=(int)$raw;
$q=$pdo->prepare('SELECT * FROM market_listings WHERE id=? AND seller_id=?'); $q->execute([$listingId,$user['id']]);
$listing=$q->fetch();
if (!$listing) abort_request(404,'Angebot nicht gefunden.');
if (!in_array($listing['status'],['draft','active','paused'],true) || market_has_bids($pdo,$listingId) || ($listing['type']==='auction' && $listing['status']==='active' && market_expired($listing))) abort_request(409,'Dieses Angebot kann nicht mehr bearbeitet werden.');
$decimal=static fn(int $amount):string=>intdiv($amount,100).'.'.str_pad((string)($amount%100),2,'0',STR_PAD_LEFT);
$values=array_map(static fn($value):string=>(string)($value ?? ''),$listing);
$values['price']=$decimal((int)$listing['price_cents']);
$values['buyout_price']=$listing['buyout_cents']===null?'':$decimal((int)$listing['buyout_cents']);
$values['duration_days']='7'; $values['status']=$listing['status']==='draft'?'draft':'active';
$pageTitle=t('Inserat bearbeiten'); $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_csrf(); $values=$_POST;
    $values['pickup']=$_POST['pickup'] ?? '0'; $values['shipping']=$_POST['shipping'] ?? '0';
    try {
        market_save_listing($pdo,(int)$user['id'],$_POST,$_FILES['images'] ?? [],$listingId);
        redirect('/user/dashboard.php?saved='.$listingId);
    } catch (InvalidArgumentException|MarketAccessException|MarketConflictException $exception) {
        http_response_code(422); $error=$exception->getMessage();
    }
}
$q=$pdo->prepare('SELECT COUNT(*) FROM market_images WHERE listing_id=?'); $q->execute([$listingId]); $existingImageCount=(int)$q->fetchColumn();
$q=$pdo->prepare('SELECT id,position FROM market_images WHERE listing_id=? ORDER BY position'); $q->execute([$listingId]); $existingImageRows=$q->fetchAll();
$categories=catalog_categories($pdo,true);
include __DIR__.'/../includes/header.php';
?>
<main class="page"><div class="page-heading"><p class="eyebrow">Dein Angebot</p><h1>Details, die den Unterschied machen.</h1></div>
<?php if ($error!==''): ?><div class="notice notice-error" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php include __DIR__.'/../includes/market-form.php'; ?>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
