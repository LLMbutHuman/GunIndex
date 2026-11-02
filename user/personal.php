<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);$uid=(int)$user['id'];$error='';
header('Cache-Control: private, no-store');
if (!in_array($_SERVER['REQUEST_METHOD']??'', ['GET','HEAD','POST'],true)) abort_request(405,t('personal.invalid'));
if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_csrf();
    try {
        $action=$_POST['action']??null;
        if (!is_string($action)) throw new InvalidArgumentException(t('personal.invalid'));
        $id=null;
        if (in_array($action,['add','remove'],true)) {
            $raw=$_POST['listing_id']??null;
            $id=is_string($raw)?filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;
            if ($id===false) throw new InvalidArgumentException(t('personal.unavailable'));
            if ($action==='add') {
                $listing=market_find($pdo,$id,false);
                if ($listing===null||!personal_current($listing)) throw new InvalidArgumentException(t('personal.unavailable'));
                personal_items($pdo,$_SESSION,$uid,'compare');
            }
        }
        personal_change($_SESSION,$uid,$action,$id);
        redirect('/user/personal.php');
    } catch (InvalidArgumentException $exception) {http_response_code(422);$error=$exception->getMessage();}
}
$recent=personal_items($pdo,$_SESSION,$uid,'recent');
$compare=personal_items($pdo,$_SESSION,$uid,'compare');
$state=personal_state($_SESSION,$uid);$pageTitle=t('personal.title');
include __DIR__.'/../includes/header.php';
?>
<main class="page"><div class="page-heading"><h1><?=h(t('personal.title'))?></h1><p><?=h(t('personal.privacy'))?></p></div>
<?php if($error!==''):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?>
<section class="panel"><h2><?=h(t('personal.recent'))?></h2>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="<?=$state['recent_enabled']?'disable':'enable'?>"><button class="button secondary" type="submit"><?=h(t($state['recent_enabled']?'personal.disable':'personal.enable'))?></button></form>
<?php if($recent):?><div class="listing-grid"><?php foreach($recent as $listing):ui_listing_card($listing);endforeach;?></div>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="clear_recent"><button class="button secondary" type="submit"><?=h(t('personal.clear_recent'))?></button></form>
<?php else:?><p><?=h(t('personal.empty'))?></p><?php endif;?></section>
<section class="panel"><h2><?=h(t('personal.compare'))?></h2>
<?php if(!$compare):?><p><?=h(t('personal.compare_help'))?></p><?php else:?>
<div class="table-wrap"><table class="data-table"><caption><?=h(t('personal.compare'))?></caption><thead><tr><th scope="col"><?=h(t('personal.listing'))?></th>
<?php foreach($compare as $listing):?><th scope="col"><a href="/marketplace/<?=$listing['type']==='auction'?'view-auction':'view-item'?>.php?id=<?=(int)$listing['id']?>"><?=h($listing['title'])?></a></th><?php endforeach;?></tr></thead><tbody>
<?php foreach(['price'=>'personal.price','condition'=>'Zustand','category'=>'Kategorie','country'=>'Land','delivery'=>'Übergabe'] as $field=>$label):?><tr><th scope="row"><?=h(t($label))?></th>
<?php foreach($compare as $listing):?><td><?php
echo h(match($field){
 'price'=>money_eur((int)($listing['type']==='auction'?($listing['current_bid_cents']??$listing['price_cents']):$listing['price_cents'])).' · '.t($listing['type']==='auction'?($listing['current_bid_cents']===null?'Startpreis':'Aktuelles Gebot'):'Angebotspreis'),
 'condition'=>t($listing['condition_label']), 'category'=>i18n_category($pdo,$listing),
 'country'=>geo_countries()[$listing['country_code']]??$listing['country_code'],
 'delivery'=>implode(' / ',array_filter([$listing['pickup']?t('Abholung'):'',$listing['shipping']?t('Versand'):''])),
});?></td><?php endforeach;?></tr><?php endforeach;?>
<tr><th scope="row"><?=h(t('personal.remove'))?></th><?php foreach($compare as $listing):?><td><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="remove"><input type="hidden" name="listing_id" value="<?=(int)$listing['id']?>"><button class="button secondary" type="submit"><?=h(t('personal.remove'))?></button></form></td><?php endforeach;?></tr>
</tbody></table></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="clear_compare"><button class="button secondary" type="submit"><?=h(t('personal.clear_compare'))?></button></form><?php endif;?></section>
<a href="/user/dashboard.php"><?=h(t('Zum Dashboard'))?></a></main>
<?php include __DIR__.'/../includes/footer.php';?>
