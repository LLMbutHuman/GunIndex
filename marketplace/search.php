<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../includes/classification-fields.php';
$filters=[];
foreach(['q','category_id','type','seller_type','min_price','max_price','sort','country_code','scope','zip','origin_country','radius_km'] as $key){
    if(isset($_GET[$key])&&!is_string($_GET[$key]))abort_request(400,t('classification.search_invalid'));
    $filters[$key]=isset($_GET[$key])?trim($_GET[$key]):'';
}
$filters['q']=mb_strcut($filters['q'],0,200,'UTF-8');
if($filters['country_code']==='')$filters['country_code']=geo_country();
if($filters['scope']==='')$filters['scope']='country';
if($filters['origin_country']==='')$filters['origin_country']=$filters['country_code'];
if($filters['sort']==='')$filters['sort']='relevance';
$pageInput=$_GET['page']??'1';
$page=is_string($pageInput)?filter_var($pageInput,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]]):false;
if(!$page)abort_request(400,t('dashboard.watchlist_page_invalid'));
$categories=catalog_categories($pdo);$countries=geo_active_countries($pdo);$error='';
foreach(['attributes','attribute_min','attribute_max'] as $key){
    if(isset($_GET[$key])&&!is_array($_GET[$key]))abort_request(400,t('classification.invalid'));
    if(isset($_GET[$key]))$filters[$key]=$_GET[$key];
}
$attributeCategory=(int)$filters['category_id'];
try{$results=market_search($pdo,$filters,(int)$page,24);}catch(InvalidArgumentException $e){http_response_code(422);$error=t($e->getMessage());$results=['items'=>[],'total'=>0];}
$postcodeSourceCountries = $filters['scope'] === 'international' ? array_keys($countries) : [$filters['country_code'],$filters['origin_country']];
$pageTitle=$filters['q']?t('Suche: {query}',['query'=>$filters['q']]):t('Entdecken');
$publicBannerPlacement = true;
include __DIR__.'/../includes/header.php';
?>
<main class="page-width section search-page">
<div class="page-heading reveal"><p class="eyebrow"><?=h(t('Deine nächste Geschichte'))?></p><h1><?= $filters['q']?h(t('Suche: {query}',['query'=>$filters['q']])):h(t('Entdeckungen mit Charakter.'))?></h1><p><?=h(t('Ein guter Fund ist manchmal nur einen Filter entfernt.'))?></p></div>
<form action="/marketplace/search.php" method="get" class="search-toolbar panel">
<div class="search-input"><label class="sr-only" for="query"><?=h(t('Suchbegriff'))?></label><?=ui_icon('search')?><input id="query" name="q" type="search" value="<?=h($filters['q'])?>" maxlength="150" placeholder="<?=h(t('Was möchtest du entdecken?'))?>" data-market-suggest autocomplete="off"></div>
<div class="search-filter-grid">
<?php $sellerTypeSelection=$filters['seller_type']; include __DIR__.'/../includes/seller-filter.php'; ?>
<label class="field"><?=h(t('Ländermarkt'))?><select name="country_code"><?php foreach($countries as $code=>$name):?><option value="<?=h($code)?>" <?=$filters['country_code']===$code?'selected':''?>><?=h($name.' ('.$code.')')?></option><?php endforeach;?></select></label>
<label class="field"><?=h(t('Suchbereich'))?><select name="scope"><option value="country" <?=$filters['scope']==='country'?'selected':''?>><?=h(t('Nur ausgewähltes Land'))?></option><option value="international" <?=$filters['scope']==='international'?'selected':''?>><?=h(t('International'))?></option></select></label>
<label class="field"><?=h(t('Kategorie'))?><select name="category_id"><option value=""><?=h(t('Alle Kategorien'))?></option><?php foreach($categories as $category):?><option value="<?=(int)$category['id']?>" <?=(string)$category['id']===$filters['category_id']?'selected':''?>><?=h(str_repeat('— ',min((int)($category['depth']??0),12)).i18n_category($pdo,$category))?></option><?php endforeach;?></select></label>
<label class="field"><?=h(t('Angebotstyp'))?><select name="type"><option value=""><?=h(t('Alle Angebote'))?></option><option value="negotiated" <?=$filters['type']==='negotiated'?'selected':''?>><?=h(t('trade.negotiated'))?></option><option value="fixed" <?=$filters['type']==='fixed'?'selected':''?>><?=h(t('Festpreis'))?></option><option value="auction" <?=$filters['type']==='auction'?'selected':''?>><?=h(t('Auktion'))?></option></select></label>
<label class="field"><?=h(t('Preis ab (€)'))?><input name="min_price" value="<?=h($filters['min_price'])?>" inputmode="decimal" placeholder="0,00" maxlength="14"></label>
<label class="field"><?=h(t('Preis bis (€)'))?><input name="max_price" value="<?=h($filters['max_price'])?>" inputmode="decimal" maxlength="14"></label>
<label class="field"><?=h(t('Bezugsland'))?><select name="origin_country"><?php foreach($countries as $code=>$name):?><option value="<?=h($code)?>" <?=$filters['origin_country']===$code?'selected':''?>><?=h($name.' ('.$code.')')?></option><?php endforeach;?></select></label>
<label class="field"><?=h(t('Postleitzahl'))?><input name="zip" value="<?=h($filters['zip'])?>" autocomplete="postal-code" maxlength="16"></label>
<label class="field"><?=h(t('Suchradius'))?><select name="radius_km"><option value=""><?=h(t('Exakte PLZ'))?></option><?php foreach([5,10,25,50,100,250,500,1000,2000] as $radius):?><option value="<?=$radius?>" <?=$filters['radius_km']===(string)$radius?'selected':''?>><?=$radius?> km</option><?php endforeach;?></select></label>
<label class="field"><?=h(t('Sortierung'))?><select name="sort"><?php foreach(['relevance'=>'classification.relevance','newest'=>'Neueste zuerst','price_asc'=>'Preis aufsteigend','price_desc'=>'Preis absteigend','ending'=>'Bald endende Auktionen','distance'=>'Nächste zuerst'] as $value=>$label):?><option value="<?=h($value)?>" <?=$filters['sort']===$value?'selected':''?>><?=h(t($label))?></option><?php endforeach;?></select></label>
<button class="button" type="submit"><?=h(t('Anzeigen'))?> <?=ui_icon('arrow')?></button></div>
<div data-classification-mode="search" data-classification-error="<?=h(t('classification.reload'))?>">
<?php if(in_array($attributeCategory,array_column($categories,'id'),true))classification_fields(classification_definitions($pdo,$attributeCategory),$filters,true);?>
</div>
<p class="help"><?=h(t('Entfernungen sind ungefähre Luftlinien zwischen PLZ-Mittelpunkten, keine Straßenentfernungen.'))?></p>
<?php include __DIR__.'/../includes/postcode-attribution.php';?>
<p class="help"><?=h(t('Preise werden in EUR angezeigt. Es erfolgt keine automatische Währungsumrechnung.'))?></p>
</form>
<?php if($viewer && $error===''): ?><p><a class="button secondary" href="/user/saved-searches.php?<?=h(http_build_query($filters))?>"><?=h(t('dashboard.alerts'))?></a></p><?php endif; ?>
<?php if($error):?><div class="notice error" role="alert"><?=h($error)?></div><?php endif;?>
<?php if ($error === ''): ?>
    <?php banner_ads_render($pdo,'search',$filters['country_code'],$attributeCategory > 0 ? $attributeCategory : null); ?>
    <?php if ($attributeCategory > 0) ui_sponsored_cards($pdo,$filters['country_code'],$attributeCategory,'category_top',3,$filters['seller_type']); ?>
<?php endif; ?>
<div class="results-heading"><p><strong><?=(int)$results['total']?></strong> <?=h(t((int)$results['total']===1?'Entdeckung':'Entdeckungen'))?></p><a class="text-link" href="/marketplace/search.php"><?=h(t('Filter zurücksetzen'))?></a></div>
<?php if($results['items']):?><div class="cards"><?php foreach($results['items'] as $item)ui_listing_card($item);?></div><?php else:ui_empty(t('Noch kein passender Fund.'),t('Probiere einen anderen Suchbegriff oder lockere die Filter. Neue Angebote können jederzeit dazukommen.'));endif;?>
<?php $pages=max(1,(int)ceil((int)$results['total']/24));if($pages>1):?><nav class="pagination" aria-label="<?=h(t('Ergebnisseiten'))?>"><?php if($page>1):?><a class="button secondary" href="?<?=h(http_build_query(array_merge($filters,['page'=>$page-1])))?>"><?=h(t('Zurück'))?></a><?php endif;?><span><?=h(t('Seite {page} von {pages}',['page'=>(int)$page,'pages'=>$pages]))?></span><?php if($page<$pages):?><a class="button secondary" href="?<?=h(http_build_query(array_merge($filters,['page'=>$page+1])))?>"><?=h(t('Weiter'))?> <?=ui_icon('arrow')?></a><?php endif;?></nav><?php endif;?></main>
<?php include __DIR__.'/../includes/footer.php';?>
