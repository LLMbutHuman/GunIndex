<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$filters=[];
foreach(['q','category_id','type','min_price','max_price','sort','country_code','scope','zip','origin_country','radius_km'] as $key){
    if(isset($_GET[$key])&&!is_string($_GET[$key]))abort_request(400,t('Ungültiger Suchfilter.'));
    $filters[$key]=isset($_GET[$key])?trim($_GET[$key]):'';
}
$filters['q']=substr($filters['q'],0,150);
if($filters['country_code']==='')$filters['country_code']=geo_country();
if($filters['scope']==='')$filters['scope']='country';
if($filters['origin_country']==='')$filters['origin_country']=$filters['country_code'];
if($filters['sort']==='')$filters['sort']='newest';
$page=isset($_GET['page'])&&is_string($_GET['page'])?filter_var($_GET['page'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]]):1;
if(!$page)abort_request(400,t('Ungültige Ergebnisseite.'));
$categories=catalog_categories($pdo);$countries=geo_active_countries($pdo);$error='';
try{$results=market_search($pdo,$filters,(int)$page,24);}catch(InvalidArgumentException $e){http_response_code(422);$error=t($e->getMessage());$results=['items'=>[],'total'=>0];}
$pageTitle=$filters['q']?t('Suche: {query}',['query'=>$filters['q']]):t('Entdecken');
include __DIR__.'/../includes/header.php';
?>
<main class="page-width section search-page">
<div class="page-heading reveal"><p class="eyebrow"><?=h(t('Deine nächste Geschichte'))?></p><h1><?= $filters['q']?h(t('Suche: {query}',['query'=>$filters['q']])):h(t('Entdeckungen mit Charakter.'))?></h1><p><?=h(t('Ein guter Fund ist manchmal nur einen Filter entfernt.'))?></p></div>
<form action="/marketplace/search.php" method="get" class="search-toolbar panel">
<div class="search-input"><label class="sr-only" for="query"><?=h(t('Suchbegriff'))?></label><?=ui_icon('search')?><input id="query" name="q" type="search" value="<?=h($filters['q'])?>" maxlength="150" placeholder="<?=h(t('Was möchtest du entdecken?'))?>"></div>
<div class="search-filter-grid">
<label class="field"><?=h(t('Ländermarkt'))?><select name="country_code"><?php foreach($countries as $code=>$name):?><option value="<?=h($code)?>" <?=$filters['country_code']===$code?'selected':''?>><?=h($name.' ('.$code.')')?></option><?php endforeach;?></select></label>
<label class="field"><?=h(t('Suchbereich'))?><select name="scope"><option value="country" <?=$filters['scope']==='country'?'selected':''?>><?=h(t('Nur ausgewähltes Land'))?></option><option value="international" <?=$filters['scope']==='international'?'selected':''?>><?=h(t('International'))?></option></select></label>
<label class="field"><?=h(t('Kategorie'))?><select name="category_id"><option value=""><?=h(t('Alle Kategorien'))?></option><?php foreach($categories as $category):?><option value="<?=(int)$category['id']?>" <?=(string)$category['id']===$filters['category_id']?'selected':''?>><?=h(str_repeat('— ',min((int)($category['depth']??0),12)).i18n_category($pdo,$category))?></option><?php endforeach;?></select></label>
<label class="field"><?=h(t('Angebotstyp'))?><select name="type"><option value=""><?=h(t('Alle Angebote'))?></option><option value="fixed" <?=$filters['type']==='fixed'?'selected':''?>><?=h(t('Festpreis'))?></option><option value="auction" <?=$filters['type']==='auction'?'selected':''?>><?=h(t('Auktion'))?></option></select></label>
<label class="field"><?=h(t('Preis ab (€)'))?><input name="min_price" value="<?=h($filters['min_price'])?>" inputmode="decimal" placeholder="0,00" maxlength="14"></label>
<label class="field"><?=h(t('Preis bis (€)'))?><input name="max_price" value="<?=h($filters['max_price'])?>" inputmode="decimal" maxlength="14"></label>
<label class="field"><?=h(t('Bezugsland'))?><select name="origin_country"><?php foreach($countries as $code=>$name):?><option value="<?=h($code)?>" <?=$filters['origin_country']===$code?'selected':''?>><?=h($name.' ('.$code.')')?></option><?php endforeach;?></select></label>
<label class="field"><?=h(t('Postleitzahl'))?><input name="zip" value="<?=h($filters['zip'])?>" autocomplete="postal-code" maxlength="16"></label>
<label class="field"><?=h(t('Suchradius'))?><select name="radius_km"><option value=""><?=h(t('Exakte PLZ'))?></option><?php foreach([5,10,25,50,100,250,500,1000,2000] as $radius):?><option value="<?=$radius?>" <?=$filters['radius_km']===(string)$radius?'selected':''?>><?=$radius?> km</option><?php endforeach;?></select></label>
<label class="field"><?=h(t('Sortierung'))?><select name="sort"><?php foreach(['newest'=>'Neueste zuerst','price_asc'=>'Preis aufsteigend','price_desc'=>'Preis absteigend','ending'=>'Bald endende Auktionen','distance'=>'Nächste zuerst'] as $value=>$label):?><option value="<?=h($value)?>" <?=$filters['sort']===$value?'selected':''?>><?=h(t($label))?></option><?php endforeach;?></select></label>
<button class="button" type="submit"><?=h(t('Anzeigen'))?> <?=ui_icon('arrow')?></button></div>
<p class="help"><?=h(t('Entfernungen sind ungefähre Luftlinien zwischen PLZ-Mittelpunkten, keine Straßenentfernungen.'))?></p>
<p class="help"><?=h(t('Preise werden in EUR angezeigt. Es erfolgt keine automatische Währungsumrechnung.'))?></p>
</form>
<?php if($error):?><div class="notice error" role="alert"><?=h($error)?></div><?php endif;?>
<?php if(function_exists('ui_country_ads'))ui_country_ads($pdo,geo_country(),ctype_digit($filters['category_id'])?(int)$filters['category_id']:null);?>
<?php if(ctype_digit($filters['category_id'])&&function_exists('ui_sponsored_cards'))ui_sponsored_cards($pdo,geo_country(),(int)$filters['category_id'],'category_top',3);?>
<div class="results-heading"><p><strong><?=(int)$results['total']?></strong> <?=h(t((int)$results['total']===1?'Entdeckung':'Entdeckungen'))?></p><a class="text-link" href="/marketplace/search.php"><?=h(t('Filter zurücksetzen'))?></a></div>
<?php if($results['items']):?><div class="cards"><?php foreach($results['items'] as $item)ui_listing_card($item);?></div><?php else:ui_empty(t('Noch kein passender Fund.'),t('Probiere einen anderen Suchbegriff oder lockere die Filter. Neue Angebote können jederzeit dazukommen.'));endif;?>
<?php $pages=max(1,(int)ceil((int)$results['total']/24));if($pages>1):?><nav class="pagination" aria-label="<?=h(t('Ergebnisseiten'))?>"><?php if($page>1):?><a class="button secondary" href="?<?=h(http_build_query(array_merge($filters,['page'=>$page-1])))?>"><?=h(t('Zurück'))?></a><?php endif;?><span><?=h(t('Seite {page} von {pages}',['page'=>(int)$page,'pages'=>$pages]))?></span><?php if($page<$pages):?><a class="button secondary" href="?<?=h(http_build_query(array_merge($filters,['page'=>$page+1])))?>"><?=h(t('Weiter'))?> <?=ui_icon('arrow')?></a><?php endif;?></nav><?php endif;?></main>
<?php include __DIR__.'/../includes/footer.php';?>
