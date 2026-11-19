<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);
$owner=(int)$user['id'];
$kind=$_GET['kind']??'avatar';
if (!in_array($kind,['avatar','logo'],true)) abort_request(404,t('profile_images.missing'));
$method=$_SERVER['REQUEST_METHOD']??'GET';
header('Cache-Control: private, no-store');
if (!in_array($method,['GET','HEAD','POST'],true)) { header('Allow: GET, HEAD, POST'); abort_request(405,t('account.method')); }
$dealer=$kind==='logo' ? business_dealer_profile($pdo,$owner,false) : null;
$available=$kind==='avatar' || ($dealer && site_setting($pdo,'dealer_profiles_enabled','1')==='1');
$link='/user/profile-image.php?kind='.$kind;
$error='';
if ($method==='POST') {
    require_csrf();
    try {
        $action=post_string('action',10);
        if ($action==='save' && $available && is_array($_FILES['image']??null)) {
            auth_rate_limit('profile_image_upload',(string)$owner,12,3600);
            profile_image_change($pdo,$user,$kind,$_FILES['image']);
        } elseif ($action==='remove' && ($kind==='avatar' || $dealer)) {
            profile_image_change($pdo,$user,$kind,null);
        } else {
            throw new InvalidArgumentException(t('profile_images.invalid'));
        }
        redirect($link);
    } catch (InvalidArgumentException|DomainException $exception) {
        $error=$exception->getMessage();
        http_response_code(422);
    } catch (Throwable) {
        $error=t('account.unavailable');
        http_response_code(503);
    }
}
$revision=profile_image_revision($pdo,$owner,$kind);
$pageTitle=t('profile_images.'.$kind);
require __DIR__.'/../includes/header.php';
?>
<main class="container"><header class="page-heading"><h1><?=h($pageTitle)?></h1>
<a href="<?=$kind==='avatar'?'/user/profile.php':'/user/dealer.php'?>"><?=h(t('Zurück'))?></a></header>
<?php if ($error!==''): ?><p role="alert" class="notice error"><?=h($error)?></p><?php endif; ?>
<section class="panel">
<p><?=h(t('profile_images.'.$kind.'_help'))?></p>
<?php if ($revision!==null): ?>
<img src="<?=h(profile_image_url($owner,$kind,$revision))?>" alt="<?=h($pageTitle)?>" width="120" height="120" style="object-fit:contain">
<form method="post" action="<?=h($link)?>"><?=csrf_field()?>
<button class="button secondary" name="action" value="remove"><?=h(t('profile_images.remove'))?></button></form>
<?php endif; ?>
<?php if ($available): ?>
<form class="form-grid" method="post" action="<?=h($link)?>" enctype="multipart/form-data"><?=csrf_field()?>
<label class="field"><?=h(t('profile_images.file'))?><input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label>
<p><?=h(t('profile_images.limits'))?></p>
<button class="button" name="action" value="save"><?=h(t('Speichern'))?></button>
</form>
<?php else: ?><p><?=h(t('profile_images.dealer_required'))?></p><?php endif; ?>
</section></main>
<?php require __DIR__.'/../includes/footer.php'; ?>
