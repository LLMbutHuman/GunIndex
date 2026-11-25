<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);
$raw=$_GET['id'] ?? null;
if (!is_string($raw) || !ctype_digit($raw)) abort_request(404,t('admin_listings.missing'));
$listingId=(int)$raw;
$q=$pdo->prepare('SELECT * FROM market_listings WHERE id=? AND seller_id=?'); $q->execute([$listingId,$user['id']]);
$listing=$q->fetch();
if (!$listing) abort_request(404,t('admin_listings.missing'));
if (!in_array($listing['status'],['draft','active','paused'],true) || market_has_bids($pdo,$listingId) || ($listing['type']==='auction' && $listing['status']==='active' && market_expired($listing))) abort_request(409,t('listing_validation.uneditable'));
$values=listing_draft_form_values($pdo,$listing);
$existingImageRows=listing_draft_images($pdo,$listingId);
$existingImageCount=count($existingImageRows);
$draftContext=listing_draft_context($pdo,(int)$user['id'],$listingId,$listing,$existingImageRows);
if($_SERVER['REQUEST_METHOD']==='GET'&&!$draftContext['stale']&&is_array($draftContext['payload'])) $values=array_replace($values,$draftContext['payload']);
$pageTitle=t('Inserat bearbeiten'); $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_csrf(); $values=$_POST;
    $values['pickup']=$_POST['pickup'] ?? '0'; $values['shipping']=$_POST['shipping'] ?? '0';
    if (($_POST['load_attributes']??null)!=='1') try {
        market_save_listing($pdo,(int)$user['id'],$_POST,$_FILES['images'] ?? [],$listingId);
        redirect('/user/dashboard.php?saved='.$listingId);
    } catch (InvalidArgumentException|DomainException|PaymentUnavailable|MarketAccessException|MarketConflictException $exception) {
        http_response_code(422); $error=$exception->getMessage();
    }
}
$categories=catalog_categories($pdo,true);
include __DIR__.'/../includes/header.php';
?>
<main class="page"><div class="page-heading"><h1><?=h(t('Inserat bearbeiten'))?></h1></div>
<?php if ($error!==''): ?><div class="notice notice-error" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if (isset($_GET['relisted']) && $listing['status']==='draft'): ?><p class="notice" role="status"><?=h(t($listing['type']==='auction'?'relisting.draft_created':'relisting.general_draft_created'))?></p><?php endif; ?>
<?php include __DIR__.'/../includes/market-form.php'; ?>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
