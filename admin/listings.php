<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$admin=require_permission($pdo,'content.moderate');
header('Cache-Control: private, no-store');
$pageTitle=t('admin_nav.listings');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','HEAD'], true) || isset($_GET['action']) || isset($_GET['delete_id'])) {
 header('Allow: GET, HEAD');
 abort_request(405,t('admin_listings.readonly'));
}
$states=[
 'draft'=>t('dashboard.status_draft'),'active'=>t('Aktiv'),'paused'=>t('dashboard.status_paused'),
 'ended'=>t('dashboard.status_ended'),'sold'=>t('dashboard.status_sold'),'archived'=>t('dashboard.status_archived'),'review'=>t('dashboard.status_review'),
];
$listingTypes=['fixed'=>t('Festpreis'),'auction'=>t('Auktion'),'negotiated'=>t('trade.negotiated')];
$status=$_GET['status']??'';
if (!is_string($status)||($status!==''&&!isset($states[$status]))) abort_request(422,t('admin_listings.invalid_filter'));
$detail=null;$events=[];
if (isset($_GET['id'])) {
 $id=filter_var($_GET['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if ($id===false) abort_request(422,t('admin_listings.invalid_listing'));
 $q=$pdo->prepare('SELECT l.*,u.username,c.name AS category_name FROM market_listings l JOIN users u ON u.id=l.seller_id JOIN market_categories c ON c.id=l.category_id WHERE l.id=?');$q->execute([$id]);$detail=$q->fetch();
 if (!$detail) abort_request(404,t('admin_listings.missing'));
 $q=$pdo->prepare('SELECT event_type,amount_cents,created_at FROM market_bids WHERE listing_id=? ORDER BY id DESC LIMIT 40');$q->execute([$id]);$events=$q->fetchAll();
}
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]])?:1;
$where=$status===''?'1=1':'l.status=?';$parameters=$status===''?[]:[$status];
$q=$pdo->prepare('SELECT COUNT(*) FROM market_listings l WHERE '.$where);$q->execute($parameters);$total=(int)$q->fetchColumn();$page=min($page,max(1,(int)ceil($total/30)));
$q=$pdo->prepare('SELECT l.id,l.title,l.type,l.status,l.price_cents,l.current_bid_cents,l.category_id,u.username,c.name AS category_name FROM market_listings l JOIN users u ON u.id=l.seller_id JOIN market_categories c ON c.id=l.category_id WHERE '.$where.' ORDER BY l.id DESC LIMIT 30 OFFSET '.(($page-1)*30));$q->execute($parameters);$items=$q->fetchAll();
$eventTypes = [
 'bid'=>t('admin_listings.bid'),'buyout'=>t('admin_listings.buyout'),'close'=>t('admin_listings.close'),
 'close_no_bid'=>t('admin_listings.close_no_bid'),'close_review'=>t('dashboard.status_review'),
];
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page">
    <div class="page-heading"><h1><?=h($pageTitle)?></h1></div>
    <div class="admin-layout">
        <?php include __DIR__.'/../includes/admin-nav.php';?>
        <div class="admin-content">
            <?php if ($detail):?>
                <section class="panel">
                    <p class="eyebrow"><?=h(t('admin_listings.number', ['id'=>(int)$detail['id']]))?></p>
                    <h2><?=h($detail['title'])?></h2>
                    <p><span class="status"><?=h($states[$detail['status']] ?? $detail['status'])?></span> · <?=h($detail['username'])?> · <?=h(i18n_category($pdo, $detail))?></p>
                    <p><?=h($detail['description'])?></p>
                    <?php if ($detail['status'] === 'review'):?>
                        <div class="notice error">
                            <h3><?=h(t('admin_listings.conflict'))?></h3>
                            <p><?=h(t('admin_listings.conflict_help'))?></p>
                        </div>
                    <?php endif;?>
                    <h3><?=h(t('admin_listings.events'))?></h3>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th scope="col"><?=h(t('admin_listings.event'))?></th><th scope="col"><?=h(t('admin_listings.public_amount'))?></th><th scope="col"><?=h(t('admin_audit.timestamp'))?></th></tr></thead>
                            <tbody>
                            <?php foreach ($events as $event):?>
                                <tr><td><?=h($eventTypes[$event['event_type']] ?? $event['event_type'])?></td><td><?=$event['amount_cents'] === null ? '—' : h(money_eur((int)$event['amount_cents']))?></td><td><?=h($event['created_at'])?></td></tr>
                            <?php endforeach;?>
                            <?php if ($events === []):?><tr><td colspan="3"><?=h(t('admin_listings.no_events'))?></td></tr><?php endif;?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif;?>
            <section class="panel">
                <form method="get" action="/admin/listings.php" class="toolbar">
                    <div class="field">
                        <label for="listing-status"><?=h(t('dashboard.status'))?></label>
                        <select id="listing-status" name="status">
                            <option value=""><?=h(t('admin_listings.all'))?></option>
                            <?php foreach ($states as $value => $stateLabel):?>
                                <option value="<?=h($value)?>"<?=$status === $value ? ' selected' : ''?>><?=h($stateLabel)?></option>
                            <?php endforeach;?>
                        </select>
                    </div>
                    <button class="button secondary" type="submit"><?=h(t('admin_listings.apply'))?></button>
                </form>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th scope="col"><?=h(t('admin_listings.listing'))?></th><th scope="col"><?=h(t('admin_listings.seller'))?></th><th scope="col"><?=h(t('admin_listings.type_status'))?></th><th scope="col"><?=h(t('admin_listings.price'))?></th><th scope="col"><?=h(t('dashboard.actions'))?></th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $item):?>
                            <tr>
                                <td><strong><?=h($item['title'])?></strong><br><span class="muted"><?=h(i18n_category($pdo, $item))?> · #<?=(int)$item['id']?></span></td>
                                <td><?=h($item['username'])?></td>
                                <td><span><?=h($listingTypes[$item['type']] ?? $item['type'])?></span><br><span class="status"><?=h($states[$item['status']] ?? $item['status'])?></span></td>
                                <td><?=h(money_eur((int)($item['current_bid_cents'] ?? $item['price_cents'])))?></td>
                                <td><a href="/admin/listings.php?<?=h(http_build_query(['id'=>$item['id'],'status'=>$status,'page'=>$page]))?>"><?=h(t('admin_listings.details'))?></a></td>
                            </tr>
                        <?php endforeach;?>
                        <?php if ($items === []):?><tr><td colspan="5"><?=h(t('admin_listings.empty'))?></td></tr><?php endif;?>
                        </tbody>
                    </table>
                </div>
            </section>
            <nav class="pagination" aria-label="<?=h(t('dashboard.pages'))?>">
                <?php if ($page > 1):?><a href="?<?=h(http_build_query(['status'=>$status,'page'=>$page-1]))?>">← <?=h(t('Zurück'))?></a><?php endif;?>
                <span><?=h(t('admin_audit.page', ['page'=>$page,'total'=>max(1,(int)ceil($total/30))]))?></span>
                <?php if ($page*30 < $total):?><a href="?<?=h(http_build_query(['status'=>$status,'page'=>$page+1]))?>"><?=h(t('Weiter'))?> →</a><?php endif;?>
            </nav>
        </div>
    </div>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
