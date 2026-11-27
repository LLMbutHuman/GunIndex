<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/dealer_admin.php';
$admin = require_permission($pdo,'dealers.manage');
header('Cache-Control: private, no-store');
$pageTitle = t('banner_ads.dealers_title');
$method = $_SERVER['REQUEST_METHOD']??'GET';
if (isset($_GET['logo'])) profile_image_serve($pdo,true);
$status = $_GET['status']??'pending';
$states = ['pending','published','rejected','disabled','all'];
if (!is_string($status) || !in_array($status,$states,true)) abort_request(422,t('admin_listings.invalid_filter'));
$page = filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]])?:1;
$focus = filter_var($_GET['id']??'0',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:null;
$route = '/admin/dealers.php';
$error = '';
if ($method==='POST') {
    require_csrf();
    try {
        $id = post_string('target_id',20);
        $action = post_string('action',24);
        if (!ctype_digit($id) || (int)$id<1) throw new InvalidArgumentException(t('admin_campaigns.invalid_id'));
        if ($action==='profile') {
            business_review_dealer($pdo,(int)$admin['id'],(int)$id,post_string('status',20),post_string('reason',1000),post_string('revision',64));
        } elseif ($action==='revoke') {
            $accessId = post_string('access_id',20);
            $version = post_string('version',12);
            if (!ctype_digit($accessId) || !ctype_digit($version)) throw new InvalidArgumentException(t('admin_campaigns.invalid_id'));
            dealer_admin_revoke($pdo,(int)$admin['id'],(int)$id,post_string('access_kind',16),(int)$accessId,(int)$version,post_string('reason',1000));
        } else {
            throw new InvalidArgumentException(t('admin_campaigns.invalid_decision'));
        }
        $_SESSION['dealer_reviewed'] = true;
        redirect($route.'?'.http_build_query(['status'=>$status,'page'=>$page,'id'=>$focus]));
    } catch (InvalidArgumentException|DomainException $exception) {
        http_response_code(422);
        $error = $exception->getMessage();
    } catch (Throwable) {
        http_response_code(503);
        $error = t('admin_campaigns.retry');
    }
}
$reviewed = $method==='GET' && ($_SESSION['dealer_reviewed']??false)===true;
if ($reviewed) unset($_SESSION['dealer_reviewed']);
$where = $focus!==null ? 'p.user_id=?' : ($status==='all' ? '1=1' : 'p.status=?');
$params = $focus!==null ? [$focus] : ($status==='all' ? [] : [$status]);
$q = $pdo->prepare('SELECT COUNT(*) FROM market_dealer_profiles p WHERE '.$where);
$q->execute($params);
$total = (int)$q->fetchColumn();
$page = min($page,max(1,(int)ceil($total/25)));
$q = $pdo->prepare('SELECT p.*,u.username,u.is_active FROM market_dealer_profiles p JOIN users u ON u.id=p.user_id WHERE '.$where
    .' ORDER BY p.updated_at DESC,p.user_id DESC LIMIT 25 OFFSET '.(($page-1)*25));
$q->execute($params);
$rows = $q->fetchAll();
if ($focus!==null && !$rows) abort_request(404,t('admin_campaigns.profile_missing'));
$access = $focus===null ? null : dealer_admin_access($pdo,$focus);
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page">
    <div class="page-heading"><h1><?=h($pageTitle)?></h1></div>
    <div class="admin-layout"><?php include __DIR__.'/../includes/admin-nav.php'; ?><div class="admin-content">
    <?php if ($error!==''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif; ?>
    <?php if ($reviewed): ?><p class="notice success" role="status"><?=h(t('admin_privacy.saved'))?></p><?php endif; ?>
    <section class="panel">
        <form method="get" action="/admin/dealers.php" class="form-grid">
            <label class="field"><?=h(t('dashboard.status'))?><select name="status">
                <?php foreach ($states as $state): ?><option value="<?=h($state)?>" <?=($state===$status)?'selected':''?>><?=h($state==='all'?t('admin_listings.all'):business_state_label($state))?></option><?php endforeach; ?>
            </select></label><button class="button secondary" type="submit"><?=h(t('admin_listings.apply'))?></button>
        </form>
    </section>
    <?php foreach ($rows as $row): $owner=(int)$row['user_id']; ?>
        <section class="panel">
            <p class="eyebrow"><?=h($row['country_code'].' · '.$row['username'].' · '.business_state_label($row['status']))?></p>
            <h2><?=h($row['company_name'])?></h2>
            <p><?=nl2br(h($row['address_text']))?></p><p><?=h($row['contact_email'])?></p>
            <?php if ($row['website']!==''): ?><p><?=h($row['website'])?></p><?php endif; ?>
            <p><?=nl2br(h($row['legal_notice']))?></p>
            <?php if ($row['logo_revision']!==null): ?><img width="120" height="120" src="<?=h(profile_image_url($owner,'logo',$row['logo_revision'],true))?>" alt="<?=h(t('profile_images.logo'))?>"><?php endif; ?>
            <?php if ($focus===null): ?><p><a href="?status=<?=h($status)?>&amp;id=<?=$owner?>"><?=h(t('banner_ads.dealer_access'))?></a></p><?php endif; ?>
            <?php if ($row['moderation_reason']!==''): ?><p><?=h(t('admin_campaigns.last_reason',['reason'=>$row['moderation_reason']]))?></p><?php endif; ?>
            <form method="post" class="form-grid">
                <?=csrf_field()?><input name="action" value="profile" type="hidden"><input name="target_id" value="<?=$owner?>" type="hidden">
                <input name="revision" value="<?=h(business_profile_revision($row))?>" type="hidden">
                <label class="field"><?=h(t('admin_privacy.decision'))?><select name="status">
                    <option value="published"><?=h(t('admin_campaigns.publish'))?></option>
                    <option value="rejected"><?=h(t('identity.reject'))?></option>
                    <option value="disabled"><?=h(t('banner_ads.suspend_dealer'))?></option>
                </select></label>
                <label class="field"><?=h(t('admin_campaigns.reason'))?><textarea name="reason" minlength="10" maxlength="1000" required></textarea></label>
                <button class="button" type="submit"><?=h(t('admin_privacy.submit'))?></button>
            </form>
        </section>
    <?php endforeach; ?>
    <?php if ($access!==null): ?>
        <section class="panel"><h2><?=h(t('banner_ads.dealer_access'))?></h2>
            <p><?=h(t($rows[0]['status']==='published' && (bool)$rows[0]['is_active']?'banner_ads.access_enabled':'banner_ads.access_suspended'))?></p>
            <p><?=h(t(dealer_subscription_active($pdo,$focus)?'banner_ads.subscription_active':'banner_ads.subscription_inactive'))?></p>
            <?php foreach (['member'=>$access['team'],'api_key'=>$access['api_keys']] as $kind=>$entries): ?>
                <h3><?=h(t($kind==='member'?'dealer_team.title':'dealer_api.title'))?></h3>
                <?php if (!$entries): ?><p><?=h(t('admin_campaigns.empty'))?></p><?php endif; ?>
                <?php foreach ($entries as $entry): ?>
                    <form method="post" class="form-grid">
                        <?=csrf_field()?><input type="hidden" name="action" value="revoke"><input type="hidden" name="target_id" value="<?=$focus?>">
                        <input type="hidden" name="access_kind" value="<?=h($kind)?>"><input type="hidden" name="access_id" value="<?=(int)($entry['member_id']??$entry['id'])?>"><input type="hidden" name="version" value="<?=(int)($entry['version']??0)?>">
                        <p><?=h($kind==='member' ? $entry['username'].' · '.t('dealer_team.role_'.$entry['role']) : $entry['label'].' · '.$entry['expires_at'])?></p>
                        <label class="field"><?=h(t('admin_campaigns.reason'))?><input name="reason" minlength="10" maxlength="1000" required></label>
                        <button type="submit" class="button secondary"><?=h(t('banner_ads.revoke_access'))?></button>
                    </form>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    <?php if (!$rows): ?><section class="panel"><p><?=h(t('admin_campaigns.empty'))?></p></section><?php endif; ?>
    <?php if ($page>1 || $page*25<$total): ?><nav class="pagination" aria-label="<?=h(t('dashboard.pages'))?>">
        <?php if ($page>1): ?><a href="?status=<?=h($status)?>&amp;page=<?=$page-1?>"><?=h(t('Zurück'))?></a><?php endif; ?>
        <span><?=h(t('admin_audit.page',['page'=>$page,'total'=>max(1,(int)ceil($total/25))]))?></span>
        <?php if ($page*25<$total): ?><a href="?status=<?=h($status)?>&amp;page=<?=$page+1?>"><?=h(t('Weiter'))?></a><?php endif; ?>
    </nav><?php endif; ?>
    </div></div>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
