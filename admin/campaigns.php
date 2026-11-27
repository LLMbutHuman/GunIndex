<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';

// Older bookmarks still require the dedicated dealer permission.
if (($_GET['kind']??null)==='profiles' || isset($_GET['logo'])) {
    require __DIR__.'/dealers.php';
    return;
}
$admin = require_permission($pdo,'campaigns.manage');
header('Cache-Control: private, no-store');
$actor = (int)$admin['id'];
$pageTitle = t('banner_ads.admin_title');
$method = $_SERVER['REQUEST_METHOD']??'GET';
$error = '';
if (isset($_GET['image'])) {
    if (!in_array($method,['GET','HEAD'],true)) {
        header('Allow: GET, HEAD');
        abort_request(405,t('admin_listings.readonly'));
    }
    $id = is_string($_GET['image']) ? filter_var($_GET['image'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) : false;
    if (!$id) abort_request(404,t('admin_campaigns.banner_missing'));
    $q = $pdo->prepare("SELECT storage_key,creative_format FROM market_campaigns WHERE id=? AND status<>'removed'");
    $q->execute([$id]);
    $campaign = $q->fetch();
    if (!$campaign) abort_request(404,t('admin_campaigns.banner_missing'));
    banner_ads_serve($campaign,($_GET['still']??null)==='1',static fn()=>audit_log($pdo,$actor,'campaign.previewed',(int)$id));
}
$states = ['all','pending','awaiting_payment','awaiting_review','awaiting_start','scheduled','active','approved','disabled','rejected','removed','expired','cancelled','paid_unfulfilled'];
$filter = $_GET['status']??'pending';
if (!is_string($filter) || !in_array($filter,$states,true)) abort_request(422,t('admin_listings.invalid_filter'));
$before = filter_var($_GET['before']??'0',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:null;
if ($method==='POST') {
    require_csrf();
    try {
        $action = post_string('action',24);
        $raw = post_string('target_id',20);
        $id = ctype_digit($raw) && (int)$raw>0 ? (int)$raw : null;
        if ($action==='rate') {
            [$place,$days] = banner_ads_selection(post_string('placement',12),post_string('term_days',2));
            banner_ads_save_rate($pdo,$actor,$place,$days,money_parse(post_string('gross',12)));
        } elseif ($action==='operator') {
            if ($raw!=='' && $id===null) throw new InvalidArgumentException(t('admin_campaigns.invalid_id'));
            $id = banner_ads_operator_save($pdo,$actor,$id,$_POST,$_FILES['creative']??[]);
        } elseif ($id!==null && $action==='campaign') {
            business_review_campaign($pdo,$actor,$id,post_string('status',20),post_string('reason',1000));
        } elseif ($id!==null && $action==='review') {
            banner_ads_review($pdo,$actor,$id,post_string('status',20),post_string('reason',1000),post_string('revision',64));
        } else {
            throw new InvalidArgumentException(t('admin_campaigns.invalid_decision'));
        }
        $_SESSION['campaign_reviewed'] = true;
        redirect('/admin/campaigns.php?'.http_build_query(['status'=>$filter,'before'=>$before]));
    } catch (InvalidArgumentException|DomainException $exception) {
        http_response_code(422);
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        http_response_code(503);
        $error = t('admin_campaigns.retry');
        error_log('Campaign administration failed at '.basename($exception->getFile()).':'.$exception->getLine());
    }
}
$reviewed = $method==='GET' && ($_SESSION['campaign_reviewed']??false)===true;
if ($reviewed) unset($_SESSION['campaign_reviewed']);
$rows = banner_ads_rows($pdo,null,$before,$filter);
$more = count($rows)>25;
if ($more) array_pop($rows);
$canReviewPayments = rbac_has_permission($pdo,$actor,'finance.manage');
$reviews = $canReviewPayments ? (int)$pdo->query("SELECT COUNT(*) FROM market_premium_bookings WHERE status='paid_unfulfilled'")->fetchColumn() : 0;
include __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/banner-advertising.css">
<main class="container admin-page">
    <div class="page-heading"><h1><?=h($pageTitle)?></h1><p><?=h(t('banner_ads.admin_intro'))?></p></div>
    <div class="admin-layout"><?php include __DIR__.'/../includes/admin-nav.php'; ?><div class="admin-content">
        <?php if ($error!==''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif; ?>
        <?php if ($reviewed): ?><p class="notice success" role="status"><?=h(t('admin_privacy.saved'))?></p><?php endif; ?>
        <?php if ($reviews): ?><section class="panel"><h2><?=h(t('admin_campaigns.unfulfilled',['count'=>$reviews]))?></h2><p><?=h(t('admin_campaigns.fulfilment_help'))?></p><a class="button secondary" href="/admin/payments.php?status=all"><?=h(t('admin_overview.review_payments'))?></a></section><?php endif; ?>
        <section class="panel">
            <?php if (rbac_has_permission($pdo,$actor,'dealers.manage')): ?><p><a href="/admin/dealers.php"><?=h(t('banner_ads.dealers_title'))?></a></p><?php endif; ?>
            <details><summary><?=h(t('banner_ads.new_operator'))?></summary>
                <form method="post" enctype="multipart/form-data" class="form-grid">
                    <?=csrf_field()?><input name="action" value="operator" type="hidden">
                    <?php banner_ads_fields($pdo,[],true); ?><button class="button" type="submit"><?=h(t('banner_ads.save_draft'))?></button>
                </form>
            </details>
            <details><summary><?=h(t('banner_ads.rates'))?></summary>
                <?php foreach (BANNER_AD_PLACEMENTS as $place): foreach (BANNER_AD_TERMS as $days):
                    $quote = banner_ads_quote($pdo,$place,$days); ?>
                    <form method="post" class="form-grid">
                        <?=csrf_field()?><input name="action" value="rate" type="hidden"><input name="placement" value="<?=h($place)?>" type="hidden"><input name="term_days" value="<?=$days?>" type="hidden">
                        <label class="field"><?=h(t('banner_ads.'.$place).' · '.t('banner_ads.days',['days'=>$days]))?>
                            <input name="gross" inputmode="decimal" value="<?=h(number_format($quote['gross_cents']/100,2,'.',''))?>" maxlength="12" required>
                            <small><?=h(t('banner_ads.gross_eur'))?></small>
                        </label><button class="button secondary" type="submit"><?=h(t('banner_ads.save_rate'))?></button>
                    </form>
                <?php endforeach; endforeach; ?>
            </details>
            <form method="get" action="/admin/campaigns.php" class="form-grid">
                <label class="field"><?=h(t('dashboard.status'))?><select name="status">
                    <?php foreach ($states as $state): ?><option value="<?=h($state)?>" <?=$state===$filter?'selected':''?>><?=h(banner_ads_status_label($state))?></option><?php endforeach; ?>
                </select></label><button class="button secondary" type="submit"><?=h(t('admin_listings.apply'))?></button>
            </form>
        </section>
        <?php foreach ($rows as $row):
            $id = (int)$row['id'];
            $legacy = $row['placement']==='legacy';
            $removed = $row['status']==='removed'; ?>
            <section class="panel">
                <p class="eyebrow"><?=h($row['country_code'].' · '.$row['username'].' · '.banner_ads_status_label($row['view_state']))?></p>
                <h2><?=h($row['title'])?></h2>
                <?php if (!$removed) banner_ads_preview($row,'/admin/campaigns.php?image='.$id); ?>
                <?php banner_ads_details($row); ?>
                <?php if ($canReviewPayments && $row['invoice_id']!==null): ?><p><a href="/admin/payments.php?status=all"><?=h(t('admin_overview.review_payments'))?></a></p><?php endif; ?>
                <?php if ($row['moderation_reason']!==''): ?><p><?=h(t('admin_campaigns.last_reason',['reason'=>$row['moderation_reason']]))?></p><?php endif; ?>
                <?php if (!$removed): ?>
                    <form method="post" class="form-grid">
                        <?=csrf_field()?><input name="action" value="<?=$legacy?'campaign':'review'?>" type="hidden"><input name="target_id" value="<?=$id?>" type="hidden"><input name="revision" value="<?=h(banner_ads_revision($row))?>" type="hidden">
                        <label class="field"><?=h(t('admin_privacy.decision'))?><select name="status">
                            <option value="approved"><?=h(t('admin_campaigns.approve_banner'))?></option>
                            <option value="rejected"><?=h(t('identity.reject'))?></option>
                            <option value="disabled"><?=h(t('banner_ads.pause'))?></option>
                            <?php if (!$legacy): ?><option value="removed"><?=h(t('banner_ads.remove'))?></option><?php endif; ?>
                        </select></label>
                        <label class="field"><?=h(t('admin_campaigns.reason'))?><textarea name="reason" minlength="10" maxlength="1000" required></textarea></label>
                        <button class="button" type="submit"><?=h(t('admin_privacy.submit'))?></button>
                    </form>
                    <?php if ((bool)$row['operator_owned']): ?>
                        <details><summary><?=h(t('banner_ads.edit_operator'))?></summary>
                            <form method="post" enctype="multipart/form-data" class="form-grid">
                                <?=csrf_field()?><input name="action" value="operator" type="hidden"><input name="target_id" value="<?=$id?>" type="hidden">
                                <?php banner_ads_fields($pdo,$row,true); ?><button class="button secondary" type="submit"><?=h(t('banner_ads.save_draft'))?></button>
                            </form>
                        </details>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
        <?php if (!$rows): ?><section class="panel"><p><?=h(t('admin_campaigns.empty'))?></p></section><?php endif; ?>
        <?php if ($more || $before!==null): ?><nav class="pagination" aria-label="<?=h(t('dashboard.pages'))?>">
            <?php if ($before!==null): ?><a href="?status=<?=h($filter)?>"><?=h(t('Zurück'))?></a><?php endif; ?>
            <?php if ($more): ?><a href="?status=<?=h($filter)?>&amp;before=<?=(int)$rows[array_key_last($rows)]['id']?>"><?=h(t('Weiter'))?></a><?php endif; ?>
        </nav><?php endif; ?>
    </div></div>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
