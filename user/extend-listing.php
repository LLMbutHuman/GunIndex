<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo); $seller=(int)$user['id'];
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','HEAD','POST'],true)){
    header('Allow: GET, HEAD, POST'); abort_request(405,t('account.method'));
}
if($method==='POST')require_csrf();
try{$listingId=relisting_integer($method==='POST'?($_POST['listing_id']??null):($_GET['id']??null));}
catch(DomainException){abort_request(404,t('relisting.extension_unavailable'));}
$error='';
if($method==='POST'){
    try{
        if(($_POST['confirm_extend']??null)!=='1')throw new DomainException(t('relisting.extension_confirm'));
        $days=relisting_integer($_POST['days']??null,1,30);
        if(!is_string($_POST['snapshot_hash']??null))throw new DomainException(t('relisting.extension_conflict'));
        relisting_extend($pdo,$seller,$listingId,$days,$_POST['snapshot_hash']);
        redirect('/user/extend-listing.php?id='.$listingId);
    }catch(DomainException|MarketAccessException|MarketConflictException $exception){
        http_response_code(422); $error=$exception->getMessage();
    }
}
try{$context=relisting_extension_context($pdo,$seller,$listingId);}
catch(DomainException){abort_request($method==='POST'?422:404,t('relisting.extension_unavailable'));}
$listing=$context['listing']; $pageTitle=t('relisting.extension_heading');
include __DIR__.'/../includes/header.php';
?>
<main class="page"><section class="panel">
<h1><?=h($pageTitle)?></h1><h2><?=h($listing['title'])?></h2>
<p><?=h(t('relisting.extension_help'))?></p>
<?php if($error!==''): ?><p class="notice notice-error" role="alert"><?=h($error)?></p><?php endif; ?>
<?php if($context['event']): ?>
<p class="notice" role="status"><?=h(t('relisting.extension_success',['deadline'=>$context['event']['extended_ends_at']]))?></p>
<p><?=h(t('relisting.extension_original',['deadline'=>$context['event']['original_ends_at']]))?></p>
<?php else: ?>
<p><?=h(t('relisting.extension_current',['deadline'=>$listing['ends_at']]))?></p>
<form method="post" action="/user/extend-listing.php">
<?=csrf_field()?><input type="hidden" name="listing_id" value="<?=$listingId?>">
<input type="hidden" name="snapshot_hash" value="<?=h($context['hash'])?>">
<label for="extension-days"><?=h(t('relisting.extension_days'))?></label>
<select id="extension-days" name="days" required>
<?php foreach($context['days'] as $days): ?>
<option value="<?=$days?>"><?=h(t('relisting.extension_choice',['days'=>$days,'deadline'=>relisting_extension_deadline($listing,$days)]))?></option>
<?php endforeach; ?>
</select>
<label class="check"><input type="checkbox" name="confirm_extend" value="1" required> <?=h(t('relisting.extension_confirm'))?></label>
<button class="button" type="submit"><?=h(t('relisting.extension_action'))?></button>
</form>
<?php endif; ?>
<p><a href="/user/dashboard.php"><?=h(t('dashboard.title'))?></a></p>
</section></main>
<?php include __DIR__.'/../includes/footer.php'; ?>
