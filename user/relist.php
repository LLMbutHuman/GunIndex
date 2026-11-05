<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','HEAD','POST'],true)){
    header('Allow: GET, HEAD, POST');abort_request(405,t('account.method'));
}
if($method==='POST')require_csrf();
try{$parentId=relisting_integer($method==='POST'?($_POST['listing_id']??null):($_GET['id']??null));}
catch(DomainException){abort_request(404,t('relisting.unavailable'));}
$seller=(int)$user['id'];$error='';
try{$source=relisting_source($pdo,$seller,$parentId);}
catch(DomainException){abort_request(404,t('relisting.unavailable'));}
if($method==='POST'){
    try{
        if(($_POST['confirm_relist']??null)!=='1')throw new DomainException(t('relisting.confirm'));
        $childId=relisting_create_draft($pdo,$seller,$parentId);
        redirect('/user/edit-listing.php?id='.$childId.'&relisted=1');
    }catch(DomainException|MarketAccessException|MarketConflictException $exception){
        http_response_code(422);$error=$exception->getMessage();
    }
}
$context=relisting_context($pdo,$seller,$parentId);
$rootId=$context['root_listing_id']??$parentId;
$q=$pdo->prepare('SELECT free_listing_id FROM market_relisting_chains WHERE root_listing_id=? AND seller_id=?');
$q->execute([$rootId,$seller]);$chain=$q->fetch();
$freeAvailable=!$chain||$chain['free_listing_id']===null;
$q=$pdo->prepare('SELECT l.id,l.status FROM market_relistings r JOIN market_listings l ON l.id=r.child_listing_id WHERE r.parent_listing_id=? AND r.seller_id=? AND l.seller_id=?');
$q->execute([$parentId,$seller,$seller]);$child=$q->fetch();
$pageTitle=t('relisting.heading');
include __DIR__.'/../includes/header.php';
?>
<main class="page"><section class="panel">
<h1><?=h(t('relisting.heading'))?></h1>
<h2><?=h($source['title'])?></h2>
<p><a href="/marketplace/view-auction.php?id=<?=$parentId?>"><?=h(t('relisting.source',['listing'=>$parentId]))?></a></p>
<p><?=h(t('relisting.help'))?></p>
<p><?=h(t($freeAvailable?'relisting.free_available':'relisting.free_used'))?></p>
<?php if($error!==''): ?><p class="notice notice-error" role="alert"><?=h($error)?></p><?php endif; ?>
<?php if($child): ?>
<a class="button" href="<?=$child['status']==='draft'?'/user/edit-listing.php?id='.(int)$child['id']:'/user/dashboard.php'?>"><?=h(t($child['status']==='draft'?'relisting.open_draft':'dashboard.title'))?></a>
<?php else: ?>
<form method="post" action="/user/relist.php">
<?=csrf_field()?><input type="hidden" name="listing_id" value="<?=$parentId?>">
<label class="check"><input type="checkbox" name="confirm_relist" value="1" required> <?=h(t('relisting.confirm'))?></label>
<button class="button" type="submit"><?=h(t('relisting.action'))?></button>
</form>
<?php endif; ?>
</section></main>
<?php include __DIR__.'/../includes/footer.php'; ?>
