<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';

$user = require_login($pdo);
$userId = (int)$user['id'];
$pageTitle = t('banner_ads.dealer_title');
$method = $_SERVER['REQUEST_METHOD']??'GET';
header('Cache-Control: private, no-store');
if (isset($_GET['image'])) {
    if (!in_array($method,['GET','HEAD'],true)) abort_request(405,t('account.method'));
    $id = is_string($_GET['image']) ? filter_var($_GET['image'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) : false;
    if (!$id) abort_request(404,t('admin_campaigns.banner_missing'));
    $q = $pdo->prepare("SELECT storage_key,creative_format FROM market_campaigns WHERE id=? AND user_id=? AND operator_owned=0 AND status<>'removed'");
    $q->execute([$id,$userId]);
    $campaign = $q->fetch();
    if (!$campaign) abort_request(404,t('admin_campaigns.banner_missing'));
    banner_ads_serve($campaign,($_GET['still']??null)==='1');
}
$error = '';
if ($method==='POST') {
    require_csrf();
    try {
        $action = post_string('action',12);
        if ($action==='submit') {
            $offer = post_string('offer',16);
            if (!preg_match('/\A(country)_(7|14|30)\z/',$offer,$selection)) throw new InvalidArgumentException(t('promotion.invalid_selection'));
            $submitted = ['placement'=>$selection[1],'term_days'=>$selection[2]]+$_POST;
            [$campaign,$invoice] = banner_ads_submit($pdo,$userId,$submitted,$_FILES['creative']??[]);
            if ($invoice!==null) redirect('/user/billing.php?order='.$invoice);
            $_SESSION['banner_ads_notice'] = 'submitted';
            redirect('/user/banner-advertising.php');
        } elseif ($action==='start') {
            $id = post_string('booking_id',20);
            if (!ctype_digit($id) || (int)$id<1) throw new InvalidArgumentException(t('promotion.booking_missing'));
            $q = $pdo->prepare("SELECT 1 FROM market_premium_bookings WHERE id=? AND user_id=? AND scope IN ('banner_country','banner_home','banner_search')");
            $q->execute([(int)$id,$userId]);
            if (!$q->fetchColumn()) throw new DomainException(t('promotion.booking_missing'));
            business_start_booking($pdo,$userId,(int)$id,post_string('immediate_start')==='1',$_POST);
            $_SESSION['banner_ads_notice'] = 'started';
            redirect('/user/banner-advertising.php');
        } else {
            throw new InvalidArgumentException(t('promotion.invalid_selection'));
        }
    } catch (PaymentUnavailable|PaymentConflict|InvalidArgumentException|DomainException|MarketAccessException|MaintenancePaused $exception) {
        http_response_code(422);
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        http_response_code(503);
        $error = t('banner_ads.retry');
        error_log('Dealer campaign failed at '.basename($exception->getFile()).':'.$exception->getLine());
    }
}
$notice = $_SESSION['banner_ads_notice']??null;
unset($_SESSION['banner_ads_notice']);
$profile = business_dealer_profile($pdo,$userId);
$enabled = site_setting($pdo,'dealer_profiles_enabled','1')==='1' && site_setting($pdo,'business_promotions_enabled','0')==='1';
$paymentReason = $enabled ? business_reason($pdo) : null;
$documents = [];
if ($enabled && $profile && $paymentReason===null) {
    try { $documents=consent_documents($pdo,'checkout',i18n_locale()); }
    catch (DomainException $exception) { $paymentReason=$exception->getMessage(); }
}
$quotes = [];
if ($enabled && $profile) {
    foreach (BANNER_AD_PLACEMENTS as $place) foreach (BANNER_AD_TERMS as $days) {
        $quotes[$place.'_'.$days] = banner_ads_quote($pdo,$place,$days);
    }
}
$before = filter_var($_GET['before']??'0',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:null;
$rows = banner_ads_rows($pdo,$userId,$before);
$more = count($rows)>25;
if ($more) array_pop($rows);
include __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/banner-advertising.css">
<main class="container">
    <div class="page-heading"><h1><?=h($pageTitle)?></h1><p><?=h(t('banner_ads.dealer_intro'))?></p><a href="/user/dealer.php"><?=h(t('Händler-Dashboard'))?></a></div>
    <?php if ($error!==''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif; ?>
    <?php if ($notice==='submitted' || $notice==='started'): ?><p class="notice success" role="status"><?=h(t($notice==='started'?'promotion.started':'banner_ads.submitted'))?></p><?php endif; ?>
    <section class="panel">
        <h2><?=h(t('banner_ads.new_request'))?></h2>
        <?php if (!$enabled): ?><p><?=h(t('promotion.disabled'))?></p>
        <?php elseif (!$profile): ?><p><?=h(t('dealer_dashboard.banner_requires_profile'))?></p>
        <?php else: ?>
            <?php if ($paymentReason!==null): ?><p class="notice"><?=h($paymentReason)?></p><?php endif; ?>
            <?php if ($paymentReason===null || array_filter($quotes,static fn(array $quote):bool=>$quote['gross_cents']===0)): ?>
            <form method="post" enctype="multipart/form-data" class="form-grid">
                <?=csrf_field()?><input name="action" value="submit" type="hidden"><input name="request_key" value="<?=h(bin2hex(random_bytes(32)))?>" type="hidden">
                <?php banner_ads_fields($pdo,['country_code'=>$profile['country_code']]); ?>
                <fieldset class="span-2"><legend><?=h(t('banner_ads.rates'))?></legend>
                    <?php foreach ($quotes as $offer=>$quote): ?>
                        <p><label><input type="radio" name="offer" value="<?=h($offer)?>" <?=($quote['gross_cents']>0 && $paymentReason!==null)?'disabled':'required'?>> <?=h($quote['terms'])?></label></p>
                        <input type="hidden" name="quote[<?=h($offer)?>]" value="<?=h($quote['approval_hash'])?>">
                    <?php endforeach; ?>
                </fieldset>
                <?php consent_form($documents,i18n_locale(),!array_filter($quotes,static fn(array $quote):bool=>$quote['gross_cents']===0)); ?>
                <label><input type="checkbox" name="accept_terms" value="1" required> <?=h(t('billing_customer.confirm'))?></label>
                <button class="button" type="submit"><?=h(t('banner_ads.submit'))?></button>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <section class="panel"><h2><?=h(t('dealer_dashboard.campaigns'))?></h2>
        <?php if (!$rows): ?><p><?=h(t('dealer_dashboard.no_campaigns'))?></p><?php endif; ?>
        <?php foreach ($rows as $campaign): $id=(int)$campaign['id']; ?>
            <article class="panel">
                <h3><?=h($campaign['title'])?></h3><p><?=h($campaign['country_code'].' · '.banner_ads_status_label($campaign['view_state']))?></p>
                <?php if ($campaign['status']!=='removed') banner_ads_preview($campaign,'/user/banner-advertising.php?image='.$id); ?>
                <?php banner_ads_details($campaign); ?>
                <?php if ($campaign['moderation_reason']!==''): ?><p><?=h(t('admin_campaigns.last_reason',['reason'=>$campaign['moderation_reason']]))?></p><?php endif; ?>
                <?php if ($campaign['invoice_id']!==null): ?><p><a href="/user/billing.php?order=<?=(int)$campaign['invoice_id']?>"><?=h(t('billing_customer.order_title',['reference'=>$campaign['invoice_number']]))?></a></p><?php endif; ?>
                <?php if ($campaign['view_state']==='awaiting_start' && $campaign['placement']!=='legacy' && $enabled): ?>
                    <form method="post" class="form-grid">
                        <?=csrf_field()?><input name="action" value="start" type="hidden"><input name="booking_id" value="<?=(int)$campaign['booking_id']?>" type="hidden">
                        <?php consent_start_fields('promotion'); ?><button class="button" type="submit"><?=h(t('banner_ads.start'))?></button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
    <?php if ($more || $before!==null): ?><nav class="pagination" aria-label="<?=h(t('dashboard.pages'))?>">
        <?php if ($before!==null): ?><a href="/user/banner-advertising.php"><?=h(t('Zurück'))?></a><?php endif; ?>
        <?php if ($more): ?><a href="?before=<?=(int)$rows[array_key_last($rows)]['id']?>"><?=h(t('Weiter'))?></a><?php endif; ?>
    </nav><?php endif; ?>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
