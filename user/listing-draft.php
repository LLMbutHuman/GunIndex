<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../includes/classification-fields.php';
$user=require_login($pdo);
$owner=(int)$user['id'];
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','HEAD','POST'],true)) { header('Allow: GET, HEAD, POST'); abort_request(405,t('account.method')); }
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
if ($method==='POST') require_csrf();
try { $editor=listing_draft_id(($method==='POST'?$_POST:$_GET)['editor']??'0'); }
catch (InvalidArgumentException) { abort_request(404,t('draft.unavailable')); }
if ($method==='POST') {
    $automatic=($_POST['autosave']??null)==='1';
    $discard=($_POST['discard_draft']??null)==='1';
    $uploads=($_POST['clear_draft_images']??null)==='1'?[]:($_FILES['images']??[]);
    try {
        $saved=listing_draft_save($pdo,$owner,$editor,$_POST,$uploads,$discard);
        if (!$automatic) redirect($discard?'/user/dashboard.php':'/user/listing-draft.php?editor='.$editor);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($saved,JSON_THROW_ON_ERROR);
        exit;
    } catch (InvalidArgumentException|DomainException|MarketConflictException|MarketNotFoundException|MarketAccessException $e) {
        $status=match (true) {
            $e instanceof MarketConflictException=>409,
            $e instanceof MarketNotFoundException,$e instanceof MarketAccessException=>404,
            default=>422,
        };
        if (!$automatic) abort_request($status,$e->getMessage());
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error'=>$e->getMessage()],JSON_THROW_ON_ERROR);
        exit;
    }
}
try { $source=listing_draft_source($pdo,$owner,$editor,false,false); }
catch (MarketNotFoundException) { abort_request(404,t('draft.unavailable')); }
$sourceImages=listing_draft_images($pdo,$editor);
$draft=listing_draft_context($pdo,$owner,$editor,$source,$sourceImages);
$values=$source===null?[]:listing_draft_form_values($pdo,$source);
if ($draft['payload']!==null) $values=array_replace($values,$draft['payload']);
$field=static fn(string $key,string $default=''):string=>is_string($values[$key]??null)?$values[$key]:$default;
$category=(int)$field('category_id');
$categories=catalog_rows($pdo);
$attributes=[];
foreach (classification_definitions($pdo,$category) as $id=>$definition) {
    $entry=$values['attributes'][$id]??'';
    if ($entry==='') continue;
    try { $attributes[]=array_replace($definition,['value'=>classification_value($definition,$entry)]); }
    catch (InvalidArgumentException) { /* Invalid draft values stay editable in the form. */ }
}
$warning='';
if ($values) try {
    $check=$values;
    $check['accept_rules']='1'; // Test product fields only. Consent is collected on the editor form.
    $data=market_validate($pdo,$check);
    classification_values(classification_definitions($pdo,$category),$values['attributes']??[],$data['status']==='active');
} catch (InvalidArgumentException $e) { $warning=$e->getMessage(); }
$auction=$field('type')==='auction';
$duration=$field($auction?'duration_days':'listing_duration_days',$auction?'7':'30');
$ends=$source['ends_at']??null;
$preservesEnd=$source!==null&&$source['status']!=='draft'&&($source['type']==='auction')===$auction;
if (!$draft['stale']&&$sourceImages&&isset($values['image_positions'])) {
    try {
        $positions=image_order_validate($sourceImages,$values['image_positions'],$values['image_revision']??null);
        $byId=array_column($sourceImages,null,'id');
        $sourceImages=array_map(static fn($id)=>$byId[$id],$positions);
    } catch (InvalidArgumentException $e) { if ($warning==='') $warning=$e->getMessage(); }
}
$pageTitle=t('draft.preview');
include __DIR__.'/../includes/header.php';
?>
<main class="page" data-listing-preview>
<div class="page-heading"><h1><?=h($pageTitle)?></h1><p><?=h(t('draft.private'))?></p>
<a class="button secondary" href="<?=h(listing_draft_editor_url($editor))?>"><?=h(t('draft.back'))?></a></div>
<?php if($draft['stale']):?><p class="notice error"><?=h(t('draft.conflict'))?></p><?php endif;?>
<?php if($warning!==''):?><p class="notice" role="status"><?=h($warning)?></p><?php endif;?>
<?php if(!$values):?><p><?=h(t('draft.empty'))?></p><?php else:?>
<div class="listing-layout"><div>
<section class="panel listing-gallery">
<?php foreach($sourceImages as $image):?>
<img src="/user/listing-image.php?id=<?=$editor?>&amp;position=<?=(int)$image['position']?>&amp;size=large" alt="<?=h(t('Produktbilder'))?>" width="480" height="360">
<?php endforeach;?>
<?php for($i=0;$i<$draft['images'];$i++):?>
<img src="/user/listing-draft-image.php?editor=<?=$editor?>&amp;position=<?=$i?>&amp;revision=<?=h($draft['revision'])?>" alt="<?=h(t('Produktbilder'))?>" width="480" height="360">
<?php endfor;?>
</section>
<section class="panel listing-story"><h2><?=h(t('Beschreibung'))?></h2><div class="listing-description"><?=h($field('description'))?></div></section>
<?php classification_summary($attributes); ?>
</div><aside class="panel listing-information">
<h2><?=h($field('title')!==''?$field('title'):t('draft.untitled'))?></h2>
<p><?=h(t(match($field('type')){'auction'=>'Auktion','negotiated'=>'trade.negotiated',default=>'Festpreis'}))?></p>
<dl class="detail-facts">
<div><dt><?=h(t('Preis / Startpreis in Euro'))?></dt><dd><?=h($field('price'))?> EUR</dd></div>
<?php if($auction&&$field('buyout_price')!==''):?><div><dt><?=h(t('Sofortkaufpreis in Euro'))?></dt><dd><?=h($field('buyout_price'))?> EUR</dd></div><?php endif;?>
<div><dt><?=h(t('Kategorie'))?></dt><dd><?=h(isset($categories[$category])?i18n_category($pdo,$categories[$category]):'')?></dd></div>
<div><dt><?=h(t('Zustand'))?></dt><dd><?=h(t(match($field('condition_label')){'new'=>'Neu','like_new'=>'Wie neu',default=>'Gebraucht'}))?></dd></div>
<div><dt><?=h(t('Ort'))?></dt><dd><?=h($field('zip').' '.$field('city').' · '.$field('country_code'))?></dd></div>
<?php if($field('pickup')==='1'):?><div><dt><?=h(t('trade.delivery'))?></dt><dd><?=h(t('trade.delivery.pickup'))?></dd></div><?php endif;?>
<?php if($field('shipping')==='1'):?><div><dt><?=h(t('trade.shipping_cost'))?></dt><dd><?=h($field('shipping_price','0'))?> EUR</dd></div><?php endif;?>
<div><dt><?=h(t('draft.duration'))?></dt><dd><?=h($preservesEnd?($ends===null?t('draft.unlimited'):$ends.' UTC'):$duration.' '.t('Tage'))?></dd></div>
<?php if(!$auction&&$field('trade_checkout_enabled')==='1'):?><div><dt><?=h(t('trade.checkout'))?></dt><dd><?=h(t('draft.checkout_enabled'))?></dd></div><?php endif;?>
</dl>
</aside></div>
<?php endif;?>
<?php if($draft['payload']!==null):?>
<form method="post" action="/user/listing-draft.php" class="panel">
<?=csrf_field()?><input type="hidden" name="editor" value="<?=$editor?>"><input type="hidden" name="draft_revision" value="<?=h($draft['revision'])?>">
<label class="check"><input type="checkbox" name="discard_draft" value="1" required><?=h(t('draft.confirm_discard'))?></label>
<button class="button secondary" type="submit"><?=h(t('draft.discard'))?></button></form>
<?php endif;?>
</main><?php include __DIR__.'/../includes/footer.php'; ?>
