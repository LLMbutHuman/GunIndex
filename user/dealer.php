<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);$userId=(int)$user['id'];$pageTitle=t('Händler-Dashboard');$error='';$success='';$report=null;
header('Cache-Control: private, no-store');
$profile=business_dealer_profile($pdo,$userId,false);
$values=$profile?:['company_name'=>'','address_text'=>'','country_code'=>$user['country_code']??'DE','contact_email'=>'','website'=>'','legal_notice'=>''];
if(isset($_GET['banner_image'])){
    $id=is_string($_GET['banner_image'])?filter_var($_GET['banner_image'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;
    if(!$id)abort_request(404,t('dealer_dashboard.banner_not_found'));$q=$pdo->prepare('SELECT storage_key FROM market_campaigns WHERE id=? AND user_id=?');$q->execute([$id,$userId]);$key=$q->fetchColumn();if(!is_string($key))abort_request(404,t('dealer_dashboard.banner_not_found'));business_output_banner($key,true);
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    require_csrf();
    try{
        $action=post_string('action',24);
        if($action==='profile'){
            foreach(array_keys($values) as $key)if(isset($_POST[$key])&&is_string($_POST[$key]))$values[$key]=$_POST[$key];
            business_save_dealer($pdo,$userId,$_POST);$success=t('dealer_dashboard.profile_saved');$profile=business_dealer_profile($pdo,$userId,false);
        }elseif($action==='campaign'){
            business_create_campaign($pdo,$userId,$_POST,$_FILES['banner']??[]);$success=t('dealer_dashboard.banner_saved');
        }elseif($action==='bulk'){
            $ids=$_POST['listing_ids']??null;if(!is_array($ids))throw new InvalidArgumentException(t('dealer_dashboard.select_own'));
            $count=business_bulk_transition($pdo,$userId,$ids,post_string('transition',20));$success=t('dealer_dashboard.bulk_saved',['count'=>$count]);
        }elseif($action==='import'){
            $report=business_import_csv($pdo,$userId,$_FILES['csv']??[],post_string('accept_rules')==='1');$success=t('dealer_dashboard.import_result',['created'=>$report['created'],'duplicates'=>$report['duplicates']]);
        }elseif($action==='export'){
            $raw=post_string('after_id',20);if($raw!==''&&!ctype_digit($raw))throw new InvalidArgumentException(t('dealer_dashboard.invalid_export_start'));$after=(int)$raw;
            $q=$pdo->prepare('SELECT l.id,l.title,l.description,c.slug,l.type,l.price_cents,l.buyout_cents,l.country_code,l.zip,l.city,l.condition_label,l.pickup,l.shipping,l.status FROM market_listings l JOIN market_categories c ON c.id=l.category_id WHERE l.seller_id=? AND l.id>? ORDER BY l.id LIMIT 1000');$q->execute([$userId,$after]);
            header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="gunindex-angebote-ab-'.$after.'.csv"');header('Cache-Control: no-store');
            $stream=fopen('php://output','wb');fwrite($stream,"\xEF\xBB\xBF");fputcsv($stream,['id','title','description','category_slug','type','price','buyout_price','country_code','zip','city','condition_label','pickup','shipping','status'],',','"','');
            while($row=$q->fetch()){$row['price_cents']=intdiv((int)$row['price_cents'],100).'.'.str_pad((string)((int)$row['price_cents']%100),2,'0',STR_PAD_LEFT);$row['buyout_cents']=$row['buyout_cents']===null?'':intdiv((int)$row['buyout_cents'],100).'.'.str_pad((string)((int)$row['buyout_cents']%100),2,'0',STR_PAD_LEFT);fputcsv($stream,array_map('business_csv_cell',array_values($row)),',','"','');}fclose($stream);exit;
        }else throw new InvalidArgumentException(t('dealer_dashboard.invalid_action'));
    }catch(InvalidArgumentException|DomainException|MarketAccessException|MarketConflictException $exception){http_response_code(422);$error=$exception->getMessage();}
    catch(Throwable){http_response_code(503);$error=t('dealer_dashboard.request_unavailable');}
}
$countries=geo_countries();
$submittedCountry=strtoupper((string)($values['country_code']??'DE'));
$country=isset($countries[$submittedCountry]) ? $submittedCountry : (string)($profile['country_code']??$user['country_code']??'DE');
$categories=catalog_categories($pdo,true);
$q=$pdo->prepare('SELECT status,COUNT(*) AS count FROM market_listings WHERE seller_id=? GROUP BY status');$q->execute([$userId]);$listingStats=array_column($q->fetchAll(),'count','status');
$q=$pdo->prepare('SELECT COUNT(*) FROM market_watchlist w JOIN market_listings l ON l.id=w.listing_id WHERE l.seller_id=?');$q->execute([$userId]);$watchCount=(int)$q->fetchColumn();
$q=$pdo->prepare('SELECT COUNT(*) FROM market_bids b JOIN market_listings l ON l.id=b.listing_id WHERE l.seller_id=? AND b.event_type=\'bid\'');$q->execute([$userId]);$bidCount=(int)$q->fetchColumn();
$q=$pdo->prepare('SELECT COUNT(DISTINCT CONCAT(m.listing_id,\':\',m.sender_id)) FROM market_messages m JOIN market_listings l ON l.id=m.listing_id WHERE l.seller_id=? AND m.receiver_id=? AND m.sender_id<>?');$q->execute([$userId,$userId,$userId]);$contacts=(int)$q->fetchColumn();
$q=$pdo->prepare("SELECT COALESCE(SUM(gross_cents),0) FROM market_invoices WHERE user_id=? AND status='paid'");$q->execute([$userId]);$paid=(int)$q->fetchColumn();
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]])?:1;$total=array_sum(array_map('intval',$listingStats));$page=min($page,max(1,(int)ceil($total/25)));
$q=$pdo->prepare('SELECT l.*,(SELECT COUNT(*) FROM market_proxy_bids b WHERE b.listing_id=l.id) AS has_bids FROM market_listings l WHERE seller_id=? ORDER BY l.id DESC LIMIT 25 OFFSET ?');$q->bindValue(1,$userId,PDO::PARAM_INT);$q->bindValue(2,($page-1)*25,PDO::PARAM_INT);$q->execute();$listings=$q->fetchAll();
$q=$pdo->prepare('SELECT c.*,COALESCE((SELECT SUM(s.impressions) FROM market_ad_daily_stats s WHERE s.campaign_id=c.id),0) AS impressions,COALESCE((SELECT SUM(s.clicks) FROM market_ad_daily_stats s WHERE s.campaign_id=c.id),0) AS clicks FROM market_campaigns c WHERE c.user_id=? ORDER BY c.id DESC LIMIT 100');$q->execute([$userId]);$campaigns=$q->fetchAll();
$listingStatusKeys = [
    'draft'=>'dashboard.status_draft','active'=>'Aktiv','paused'=>'dashboard.status_paused','ended'=>'dashboard.status_ended',
    'sold'=>'dashboard.status_sold','archived'=>'dashboard.status_archived','review'=>'dashboard.status_review',
];
include __DIR__.'/../includes/header.php';
?>
<main class="container">
    <div class="page-heading">
        <h1><?=h($pageTitle)?></h1>
        <p><?=h(t('dealer_dashboard.summary'))?></p>
        <div class="button-row">
            <a class="button" href="/user/promotions.php"><?=h(t('Premiumplätze & Händlerwerbung'))?></a>
            <a class="button secondary" href="/user/dealer-team.php"><?=h(t('dealer_team.title'))?></a>
            <a class="button secondary" href="/user/dealer-stock.php"><?=h(t('dealer_stock.title'))?></a>
        </div>
    </div>
    <?php if ($profile): ?><p><a class="button secondary" href="/user/profile-image.php?kind=logo"><?=h(t('profile_images.logo'))?></a></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif; ?>
    <?php if ($success !== ''): ?><p class="notice success" role="status"><?=h($success)?></p><?php endif; ?>

    <div class="stats-grid">
        <article class="panel"><strong><?=(int)($listingStats['active'] ?? 0)?></strong><p><?=h(t('dealer_dashboard.active_listings'))?></p></article>
        <article class="panel"><strong><?=$watchCount?></strong><p><?=h(t('dealer_dashboard.watch_entries'))?></p></article>
        <article class="panel"><strong><?=$contacts?></strong><p><?=h(t('dealer_dashboard.contacts'))?></p></article>
        <article class="panel"><strong><?=$bidCount?></strong><p><?=h(t('dealer_dashboard.bids'))?></p></article>
        <article class="panel"><strong><?=h(money_eur($paid))?></strong><p><?=h(t('dealer_dashboard.payments'))?></p></article>
    </div>

    <section class="panel">
        <h2><?=h(t('dealer_dashboard.profile'))?></h2>
        <p><?=h(t('dealer_dashboard.profile_help'))?></p>
        <?php if ($profile): ?>
            <p><?=h(t('dealer_dashboard.profile_status',['status'=>business_state_label($profile['status'])]))?><?php if ($profile['moderation_reason']): ?> · <?=h($profile['moderation_reason'])?><?php endif; ?></p>
        <?php endif; ?>
        <form method="post" class="form-grid">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="profile">
            <label class="field"><?=h(t('dealer_dashboard.company'))?><input name="company_name" minlength="2" maxlength="150" value="<?=h($values['company_name'])?>" required></label>
            <label class="field"><?=h(t('dealer_dashboard.company_country'))?><select name="country_code">
                <?php foreach ($countries as $code=>$label): ?>
                    <option value="<?=h($code)?>" <?=$code===$country?'selected':''?>><?=h(is_array($label)?($label['name']??$code):$label)?></option>
                <?php endforeach; ?>
            </select></label>
            <label class="field"><?=h(t('dealer_dashboard.address'))?><textarea name="address_text" minlength="10" maxlength="500" required><?=h($values['address_text'])?></textarea></label>
            <label class="field"><?=h(t('dealer_dashboard.contact_email'))?><input name="contact_email" type="email" maxlength="190" value="<?=h($values['contact_email'])?>" required></label>
            <label class="field"><?=h(t('dealer_dashboard.website'))?><input name="website" type="url" maxlength="1000" value="<?=h($values['website'])?>" placeholder="https://..."></label>
            <label class="field"><?=h(t('dealer_dashboard.legal_notice'))?><textarea name="legal_notice" minlength="20" maxlength="10000" required><?=h($values['legal_notice'])?></textarea></label>
            <p><?=h(t('dealer_dashboard.profile_recheck'))?></p>
            <button class="button" type="submit"><?=h(t('dealer_dashboard.save_profile'))?></button>
        </form>
    </section>

    <section class="panel">
        <h2><?=h(t('dealer_dashboard.banner'))?></h2>
        <?php if (!$profile || $profile['status'] !== 'published'): ?>
            <p><?=h(t('dealer_dashboard.banner_requires_profile'))?></p>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data" class="form-grid">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="campaign">
                <label class="field"><?=h(t('Titel'))?><input name="title" minlength="5" maxlength="120" required></label>
                <label class="field"><?=h(t('dealer_dashboard.target_url'))?><input name="target_url" type="url" maxlength="1000" required placeholder="https://..."></label>
                <label class="field"><?=h(t('dealer_dashboard.target_market'))?><select name="country_code">
                    <?php foreach ($countries as $code=>$label): ?>
                        <option value="<?=h($code)?>" <?=$code===$country?'selected':''?>><?=h(is_array($label)?($label['name']??$code):$label)?></option>
                    <?php endforeach; ?>
                </select></label>
                <label class="field"><?=h(t('Kategorie'))?><select name="category_id">
                    <?php foreach ($categories as $category): ?>
                        <option value="<?=(int)$category['id']?>"><?=h(i18n_category($pdo,$category))?></option>
                    <?php endforeach; ?>
                </select></label>
                <label class="field"><?=h(t('dealer_dashboard.banner_image'))?><input type="file" name="banner[]" accept="image/jpeg,image/png,image/webp" required><small><?=h(t('dealer_dashboard.banner_image_help'))?></small></label>
                <button class="button" type="submit"><?=h(t('dealer_dashboard.save_banner'))?></button>
            </form>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2><?=h(t('dealer_dashboard.campaigns'))?></h2>
        <?php if (!$campaigns): ?>
            <p><?=h(t('dealer_dashboard.no_campaigns'))?></p>
        <?php else: ?>
            <div class="cards">
                <?php foreach ($campaigns as $campaign): ?>
                    <article class="panel">
                        <img src="?banner_image=<?=(int)$campaign['id']?>" alt="<?=h(t('dealer_dashboard.campaign_preview'))?>" width="400" height="180" loading="lazy" style="max-height:180px;object-fit:contain">
                        <h3><?=h($campaign['title'])?></h3>
                        <p><?=h($campaign['country_code'].' · '.business_state_label($campaign['status']))?></p>
                        <p><?=h(t('dealer_dashboard.campaign_metrics',['impressions'=>(int)$campaign['impressions'],'clicks'=>(int)$campaign['clicks']]))?></p>
                        <p class="muted"><?=h(t('dealer_dashboard.campaign_metrics_help'))?></p>
                        <?php if ($campaign['moderation_reason']): ?><p><?=h($campaign['moderation_reason'])?></p><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2><?=h(t('dealer_dashboard.bulk'))?></h2>
        <form method="post" id="bulk-listings">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="bulk">
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th><?=h(t('dealer_dashboard.selection'))?></th><th><?=h(t('dealer_dashboard.listing'))?></th><th><?=h(t('dealer_dashboard.market'))?></th><th><?=h(t('dashboard.status'))?></th></tr></thead>
                    <tbody>
                        <?php foreach ($listings as $listing): ?>
                            <?php $eligible=in_array($listing['status'],['draft','active','paused'],true)&&!(int)$listing['has_bids']&&!($listing['type']==='auction'&&$listing['status']==='active'&&market_expired($listing)); ?>
                            <tr>
                                <td><input type="checkbox" name="listing_ids[]" value="<?=(int)$listing['id']?>" aria-label="<?=h(t('dealer_dashboard.select_listing',['id'=>(int)$listing['id']]))?>" <?=$eligible?'':'disabled'?>></td>
                                <td><?=h($listing['title'])?><br><small>#<?=(int)$listing['id']?></small></td>
                                <td><?=h($listing['country_code'])?></td>
                                <td><?=h(t($listingStatusKeys[$listing['status']] ?? 'dealer_dashboard.status_unknown'))?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <label class="field"><?=h(t('dealer_dashboard.action'))?><select name="transition">
                <option value="pause"><?=h(t('dealer_dashboard.pause'))?></option>
                <option value="archive"><?=h(t('dealer_dashboard.archive'))?></option>
            </select></label>
            <p><?=h(t('dealer_dashboard.bulk_help'))?></p>
            <button class="button" type="submit"><?=h(t('dealer_dashboard.apply_bulk'))?></button>
        </form>
        <nav class="pagination" aria-label="<?=h(t('dealer_dashboard.listing_pages'))?>">
            <?php if ($page > 1): ?><a href="?page=<?=$page-1?>"><?=h(t('Zurück'))?></a><?php endif; ?>
            <span><?=h(t('dealer_dashboard.page',['page'=>$page,'total'=>max(1,(int)ceil($total/25))]))?></span>
            <?php if ($page*25 < $total): ?><a href="?page=<?=$page+1?>"><?=h(t('Weiter'))?></a><?php endif; ?>
        </nav>
    </section>

    <section class="panel">
        <h2><?=h(t('dealer_dashboard.csv'))?></h2>
        <p><?=h(t('dealer_dashboard.csv_format'))?></p>
        <pre style="overflow:auto">title,description,category_slug,type,price,buyout_price,duration_days,country_code,zip,city,condition_label,pickup,shipping</pre>
        <p><?=h(t('dealer_dashboard.csv_format_help'))?></p>
        <form method="post" enctype="multipart/form-data" class="form-grid">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="import">
            <label class="field"><?=h(t('dealer_dashboard.csv_file'))?><input type="file" name="csv" accept="text/csv,.csv" required></label>
            <label><input type="checkbox" name="accept_rules" value="1" required> <?=h(t('dealer_dashboard.csv_rules'))?></label>
            <button class="button" type="submit"><?=h(t('dealer_dashboard.import_csv'))?></button>
        </form>
        <?php if ($report && $report['errors']): ?>
            <div class="table-wrap"><table>
                <thead><tr><th><?=h(t('dealer_dashboard.csv_row'))?></th><th><?=h(t('dealer_dashboard.csv_error'))?></th></tr></thead>
                <tbody><?php foreach ($report['errors'] as $row): ?><tr><td><?=(int)$row['row']?></td><td><?=h($row['message'])?></td></tr><?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
        <form method="post" class="form-grid">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="export">
            <label class="field"><?=h(t('dealer_dashboard.export_start'))?> <small><?=h(t('dealer_dashboard.export_start_help'))?></small><input name="after_id" inputmode="numeric" value="0" pattern="[0-9]+" required></label>
            <button class="button secondary" type="submit"><?=h(t('dealer_dashboard.export_csv'))?></button>
        </form>
        <p><?=h(t('dealer_dashboard.export_help'))?></p>
    </section>
</main>
<?php include __DIR__.'/../includes/footer.php';?>
