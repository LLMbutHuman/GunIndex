<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$admin=require_permission($pdo,'campaigns.manage');
header('Cache-Control: private, no-store');
$pageTitle=t('admin_nav.campaigns');
$method=$_SERVER['REQUEST_METHOD']??'GET';
$error='';
if (isset($_GET['logo'])) profile_image_serve($pdo,true);
if(isset($_GET['image'])){
    if(!in_array($method,['GET','HEAD'],true)){
        header('Allow: GET, HEAD');
        abort_request(405,t('admin_listings.readonly'));
    }
    $id=is_string($_GET['image'])?filter_var($_GET['image'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;
    if(!$id)abort_request(404,t('admin_campaigns.banner_missing'));
    $q=$pdo->prepare('SELECT storage_key FROM market_campaigns WHERE id=?');
    $q->execute([$id]);
    $key=$q->fetchColumn();
    if(!is_string($key))abort_request(404,t('admin_campaigns.banner_missing'));
    business_output_banner($key,true,static fn()=>audit_log($pdo,(int)$admin['id'],'campaign.previewed',(int)$id));
}
$kind=$_GET['kind']??'campaigns';
if(!is_string($kind)||!in_array($kind,['campaigns','profiles'],true))abort_request(422,t('admin_campaigns.invalid_section'));
$status=$_GET['status']??'pending';
$allowed=$kind==='profiles'?['pending','published','rejected','all']:['pending','approved','rejected','disabled','all'];
if(!is_string($status)||!in_array($status,$allowed,true))abort_request(422,t('admin_listings.invalid_filter'));
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]])?:1;
if($method==='POST'){
    require_csrf();
    try{
        $action=post_string('action',24);$id=post_string('target_id',20);if(!ctype_digit($id)||(int)$id<1)throw new InvalidArgumentException(t('admin_campaigns.invalid_id'));
        if($action==='profile')business_review_dealer($pdo,(int)$admin['id'],(int)$id,post_string('status',20),post_string('reason',1000),post_string('revision',64));
        elseif($action==='campaign')business_review_campaign($pdo,(int)$admin['id'],(int)$id,post_string('status',20),post_string('reason',1000));
        else throw new InvalidArgumentException(t('admin_campaigns.invalid_decision'));
        $_SESSION['campaign_reviewed']=true;
        redirect('/admin/campaigns.php?'.http_build_query(['kind'=>$kind,'status'=>$status,'page'=>$page]));
    }catch(InvalidArgumentException|DomainException $exception){http_response_code(422);$error=$exception->getMessage();}
    catch(Throwable){http_response_code(503);$error=t('admin_campaigns.retry');}
}
$reviewed=$method==='GET'&&($_SESSION['campaign_reviewed']??false)===true;
if($reviewed)unset($_SESSION['campaign_reviewed']);
$table=$kind==='profiles'?'market_dealer_profiles':'market_campaigns';$where=$status==='all'?'1=1':'p.status=?';$params=$status==='all'?[]:[$status];
$q=$pdo->prepare('SELECT COUNT(*) FROM '.$table.' p WHERE '.$where);$q->execute($params);$total=(int)$q->fetchColumn();$page=min($page,max(1,(int)ceil($total/25)));
$q=$pdo->prepare('SELECT p.*,u.username FROM '.$table.' p JOIN users u ON u.id=p.user_id WHERE '.$where.' ORDER BY p.updated_at DESC,p.user_id LIMIT 25 OFFSET '.(($page-1)*25));$q->execute($params);$rows=$q->fetchAll();
$reviews=rbac_has_permission($pdo,(int)$admin['id'],'finance.manage')
    ? (int)$pdo->query("SELECT COUNT(*) FROM market_premium_bookings WHERE status='paid_unfulfilled'")->fetchColumn()
    : 0;
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page"><div class="page-heading"><h1><?=h($pageTitle)?></h1><p><?=h(t('admin_campaigns.scope'))?></p></div><div class="admin-layout"><?php include __DIR__.'/../includes/admin-nav.php';?><div class="admin-content">
<?php if($error!==''):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?><?php if($reviewed):?><p class="notice success" role="status"><?=h(t('admin_privacy.saved'))?></p><?php endif;?>
<?php if($reviews):?><section class="panel"><h2><?=h(t('admin_campaigns.unfulfilled', ['count'=>$reviews]))?></h2><p><?=h(t('admin_campaigns.fulfilment_help'))?></p><a class="button secondary" href="/admin/payments.php?status=all"><?=h(t('admin_overview.review_payments'))?></a></section><?php endif;?>
<section class="panel"><nav class="button-row" aria-label="<?=h(t('admin_campaigns.sections'))?>"><a class="button secondary" href="?kind=campaigns" <?=$kind==='campaigns'?'aria-current="page"':''?>><?=h(t('admin_campaigns.banners'))?></a><a class="button secondary" href="?kind=profiles" <?=$kind==='profiles'?'aria-current="page"':''?>><?=h(t('admin_campaigns.profiles'))?></a></nav><form method="get" class="form-grid"><input type="hidden" name="kind" value="<?=h($kind)?>"><label class="field"><?=h(t('dashboard.status'))?><select name="status"><?php foreach($allowed as $value):?><option value="<?=h($value)?>" <?=$value===$status?'selected':''?>><?=h($value==='all'?t('admin_listings.all'):business_state_label($value))?></option><?php endforeach;?></select></label><button class="button secondary" type="submit"><?=h(t('admin_listings.apply'))?></button></form><p><?=h(t('promotion.capacity'))?></p></section>
<?php foreach($rows as $row):?><section class="panel"><p class="eyebrow"><?=h($row['country_code'].' · '.$row['username'].' · '.business_state_label($row['status']))?></p>
<?php if($kind==='profiles'):?><h2><?=h($row['company_name'])?></h2><p style="white-space:pre-wrap;overflow-wrap:anywhere"><?=h($row['address_text'])?></p><p><?=h($row['contact_email'])?></p><?php if($row['website']):?><p><?=h($row['website'])?></p><?php endif;?><p style="white-space:pre-wrap;overflow-wrap:anywhere"><?=h($row['legal_notice'])?></p>
<?php if($row['logo_revision']!==null):?><img src="<?=h(profile_image_url((int)$row['user_id'],'logo',$row['logo_revision'],true))?>" alt="<?=h(t('profile_images.logo'))?>" width="120" height="120" style="object-fit:contain"><?php endif;?>
<form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="profile"><input type="hidden" name="target_id" value="<?=(int)$row['user_id']?>"><input type="hidden" name="revision" value="<?=h(business_profile_revision($row))?>"><label class="field"><?=h(t('admin_privacy.decision'))?><select name="status"><option value="published"><?=h(t('admin_campaigns.publish'))?></option><option value="rejected"><?=h(t('identity.reject'))?></option></select></label>
<?php else:?><h2><?=h($row['title'])?></h2><img src="?image=<?=(int)$row['id']?>" alt="<?=h(t('admin_campaigns.banner_preview'))?>" width="480" height="200" loading="lazy" style="max-height:200px;object-fit:contain"><p><?=h(t('admin_campaigns.target',['url'=>$row['target_url']]))?><br><?=h(t('admin_campaigns.category',['id'=>(int)$row['category_id']]))?></p><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="campaign"><input type="hidden" name="target_id" value="<?=(int)$row['id']?>"><label class="field"><?=h(t('admin_privacy.decision'))?><select name="status"><option value="approved"><?=h(t('admin_campaigns.approve_banner'))?></option><option value="rejected"><?=h(t('identity.reject'))?></option><option value="disabled"><?=h(t('admin_campaigns.disable_banner'))?></option></select></label>
<?php endif;?><label class="field"><?=h(t('admin_campaigns.reason'))?><textarea name="reason" minlength="10" maxlength="1000" required></textarea></label><button class="button" type="submit"><?=h(t('admin_privacy.submit'))?></button></form>
<?php if($row['moderation_reason']):?><p><?=h(t('admin_campaigns.last_reason',['reason'=>$row['moderation_reason']]))?></p><?php endif;?></section><?php endforeach;?>
<?php if(!$rows):?><section class="panel"><p><?=h(t('admin_campaigns.empty'))?></p></section><?php endif;?>
<nav class="pagination" aria-label="<?=h(t('dashboard.pages'))?>"><?php if($page>1):?><a href="?kind=<?=h($kind)?>&amp;status=<?=h($status)?>&amp;page=<?=$page-1?>"><?=h(t('Zurück'))?></a><?php endif;?><span><?=h(t('admin_audit.page',['page'=>$page,'total'=>max(1,(int)ceil($total/25))]))?></span><?php if($page*25<$total):?><a href="?kind=<?=h($kind)?>&amp;status=<?=h($status)?>&amp;page=<?=$page+1?>"><?=h(t('Weiter'))?></a><?php endif;?></nav></div></div></main>
<?php include __DIR__.'/../includes/footer.php';?>
