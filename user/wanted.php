<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/discovery.php';
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$user=require_login($pdo);$userId=(int)$user['id'];$pageTitle=t('Meine Gesuche');$error='';
$identifier=static function(mixed $value):?int{if($value===null||$value==='')return null;if(!is_string($value)&&!is_int($value))abort_request(404,t('wanted.missing'));$id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$id)abort_request(404,t('wanted.missing'));return(int)$id;};
$selectedId=$identifier($_GET['id']??null);$selected=null;
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','HEAD','POST'],true)) { header('Allow: GET, HEAD, POST'); abort_request(405,t('account.method')); }
$wantedSaved=$method==='GET'&&$selectedId!==null&&($_SESSION['saved_wanted_id']??null)===$selectedId;
if($wantedSaved)unset($_SESSION['saved_wanted_id']);
$values=['title'=>'','description'=>'','keywords'=>'','manufacturer'=>'','model'=>'','category_id'=>'','min_price'=>'','max_price'=>'','condition_label'=>'any','country_code'=>(string)($user['country_code']??'DE'),'scope'=>'country','zip'=>'','radius_km'=>'','notify_mode'=>'instant'];
if($selectedId!==null){try{$selected=discovery_owner_query($pdo,'wanted',$selectedId,$userId);}catch(DomainException){abort_request(404,t('wanted.missing'));}
    foreach($values as $key=>$_)if(array_key_exists($key,$selected))$values[$key]=(string)($selected[$key]??'');
    foreach(['min'=>'min_price','max'=>'max_price'] as $prefix=>$field){$amount=$selected[$prefix.'_cents'];$values[$field]=$amount===null?'':intdiv((int)$amount,100).'.'.str_pad((string)((int)$amount%100),2,'0',STR_PAD_LEFT);}
}
if($method==='POST'){
    require_csrf();
    try{
        $action=discovery_text($_POST,'action',16,'save');$id=$identifier($_POST['id']??null);
        if($action==='save'){
            foreach($values as $key=>$_)$values[$key]=discovery_text($_POST,$key,$key==='description'?10000:($key==='keywords'?500:200));
            $saved=discovery_save_wanted($pdo,$userId,$_POST,$id);
            try{discovery_scan_query($pdo,'wanted',$saved,50);}catch(Throwable){error_log('Initial wanted backfill deferred to discovery worker.');}
            $_SESSION['saved_wanted_id']=$saved;
            redirect('/user/wanted.php?id='.$saved);
        }
        if($id===null)throw new InvalidArgumentException(t('wanted.missing'));
        discovery_transition($pdo,'wanted',$id,$userId,$action);redirect('/user/wanted.php?id='.$id);
    }catch(InvalidArgumentException|DomainException $exception){http_response_code(422);$error=$exception->getMessage();}
}
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000]])?:1;
$q=$pdo->prepare('SELECT COUNT(*) FROM market_wanted WHERE user_id=?');$q->execute([$userId]);$total=(int)$q->fetchColumn();$page=min($page,max(1,(int)ceil($total/20)));
$q=$pdo->prepare('SELECT id,title,status,notify_mode,country_code,scope,created_at FROM market_wanted WHERE user_id=? ORDER BY id DESC LIMIT 20 OFFSET '.(($page-1)*20));$q->execute([$userId]);$owned=$q->fetchAll();
$matchPage=filter_var($_GET['match_page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>21]])?:1;
$matches=$selected?discovery_matches($pdo,'wanted',$selectedId,$userId,(int)$matchPage):[];
$categories=catalog_categories($pdo,true);$countries=geo_countries();$modes=['instant'=>'Sofort','daily'=>'Tägliche Zusammenfassung','off'=>'Aus'];$states=['active'=>'Aktiv','paused'=>'saved_search.paused','archived'=>'saved_search.archived','review'=>'saved_search.review'];
$label=static fn(string $text):string=>function_exists('t')?t($text):$text;
$reasonLabel=static function(string $reason):string {
    if(preg_match('/\ASuchbegriffe: ([0-9]+)\/([0-9]+)\z/',$reason,$parts)) {
        return t('wanted.match_words',['matched'=>$parts[1],'total'=>$parts[2]]);
    }
    if(preg_match('/\ACa\. ([0-9.,]+) km Luftlinie\z/',$reason,$parts)) {
        return t('wanted.match_distance',['distance'=>$parts[1]]);
    }
    return t($reason);
};
include __DIR__.'/../includes/header.php';
?>
<main class="container"><div class="page-heading"><h1><?=h($label('wanted.own'))?></h1><p><?=h($label('wanted.own_help'))?></p><div class="button-row"><a class="button secondary" href="/user/wanted.php"><?=h($label('Neues Gesuch'))?></a><a class="button secondary" href="/user/saved-searches.php"><?=h($label('saved_search.heading'))?></a><a class="button secondary" href="/user/notification-settings.php"><?=h($label('Benachrichtigungen einstellen'))?></a><a href="/marketplace/wanted.php"><?=h($label('Öffentliche Gesuche'))?></a></div></div>
<?php if($error):?><p class="notice error" role="alert"><?=h($label($error))?></p><?php endif;?><?php if($wantedSaved):?><p class="notice success" role="status"><?=h($label('wanted.saved'))?></p><?php endif;?>
<?php if(!$selected||$selected['status']!=='archived'):?><section class="panel"><h2><?=h($label($selected?'wanted.edit':'wanted.create'))?></h2><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$selectedId??''?>">
<label class="field"><?=h($label('Titel'))?><input name="title" required minlength="5" maxlength="150" value="<?=h($values['title'])?>"></label>
<label class="field"><?=h($label('Kategorie'))?><select name="category_id"><option value=""><?=h($label('Alle Kategorien'))?></option><?php foreach($categories as $category):?><option value="<?=(int)$category['id']?>" <?=($values['category_id']===(string)$category['id'])?'selected':''?>><?=h(str_repeat('— ',(int)($category['depth']??0)).i18n_category($pdo,$category))?></option><?php endforeach;?></select></label>
<label class="field full-width"><?=h($label('Beschreibung'))?><textarea name="description" required minlength="20" maxlength="10000" rows="4"><?=h($values['description'])?></textarea><small><?=h($label('wanted.public_fields'))?></small></label>
<label class="field"><?=h($label('wanted.keywords'))?><input name="keywords" maxlength="500" value="<?=h($values['keywords'])?>"><small><?=h($label('wanted.keywords_help'))?></small></label>
<label class="field"><?=h($label('wanted.manufacturer'))?><input name="manufacturer" maxlength="100" value="<?=h($values['manufacturer'])?>"></label><label class="field"><?=h($label('wanted.model'))?><input name="model" maxlength="100" value="<?=h($values['model'])?>"></label>
<label class="field"><?=h($label('saved_search.min_price'))?><input name="min_price" inputmode="decimal" maxlength="13" value="<?=h($values['min_price'])?>"></label><label class="field"><?=h($label('saved_search.max_price'))?><input name="max_price" inputmode="decimal" maxlength="13" value="<?=h($values['max_price'])?>"></label>
<label class="field"><?=h($label('Zustand'))?><select name="condition_label"><?php foreach(['any'=>'Beliebig','new'=>'Neu','like_new'=>'Wie neu','used'=>'Gebraucht'] as $value=>$name):?><option value="<?=h($value)?>" <?=$values['condition_label']===$value?'selected':''?>><?=h($label($name))?></option><?php endforeach;?></select></label>
<label class="field"><?=h($label('wanted.country'))?><select name="country_code"><?php foreach($countries as $code=>$name):?><option value="<?=h($code)?>" <?=$values['country_code']===$code?'selected':''?>><?=h($name)?></option><?php endforeach;?></select></label>
<label class="field"><?=h($label('Suchbereich'))?><select name="scope"><option value="country" <?=$values['scope']==='country'?'selected':''?>><?=h($label('Nur dieses Land'))?></option><option value="international" <?=$values['scope']==='international'?'selected':''?>><?=h($label('International'))?></option></select></label>
<label class="field"><?=h($label('Bezugs-Postleitzahl'))?><input name="zip" maxlength="16" value="<?=h($values['zip'])?>" autocomplete="postal-code"></label><label class="field"><?=h($label('Radius in km'))?><input name="radius_km" type="number" min="1" max="2000" value="<?=h($values['radius_km'])?>"><small><?=h($label('wanted.radius_help'))?></small></label>
<label class="field"><?=h($label('Suchalarm'))?><select name="notify_mode"><?php foreach($modes as $value=>$name):?><option value="<?=h($value)?>" <?=$values['notify_mode']===$value?'selected':''?>><?=h($label($name))?></option><?php endforeach;?></select></label>
<p><?=h($label('wanted.alerts_help'))?></p><button class="button" type="submit"><?=h($label('wanted.save'))?></button></form></section><?php endif;?>
<?php if($selected):?><section class="panel"><div class="page-heading"><h2><?=h($label('Passende Angebote'))?></h2><span class="badge"><?=h($label($states[$selected['status']]??'saved_search.reviewing'))?></span></div><p><?=h($label('wanted.matches_help'))?></p>
<?php if(!$matches):?><p><?=h($label('saved_search.results_empty'))?></p><?php else:?><div class="cards"><?php foreach($matches as $match):?><article class="panel"><h3><a href="/marketplace/<?=$match['type']==='auction'?'view-auction':'view-item'?>.php?id=<?=(int)$match['id']?>"><?=h($match['title'])?></a></h3><p><?=h(money_eur((int)($match['current_bid_cents']??$match['price_cents'])))?> · <?=h($match['city'])?></p><p><?=h($label('classification.relevance'))?>: <?=(int)$match['relevance_score']?> / 100</p><ul><?php foreach($match['match_reasons'] as $reason):?><li><?=h($reasonLabel($reason))?></li><?php endforeach;?></ul></article><?php endforeach;?></div><?php endif;?>
<nav class="pagination" aria-label="<?=h(t('saved_search.result_page'))?>"><?php if($matchPage>1):?><a href="?id=<?=$selectedId?>&amp;match_page=<?=$matchPage-1?>"><?=h($label('Zurück'))?></a><?php endif;?><span><?=h($label('saved_search.result_page'))?> <?=(int)$matchPage?></span><?php if(count($matches)===24&&$matchPage<21):?><a href="?id=<?=$selectedId?>&amp;match_page=<?=$matchPage+1?>"><?=h($label('Weiter'))?></a><?php endif;?></nav></section><?php endif;?>
<section class="panel"><h2><?=h($label('Meine Gesuche'))?></h2><?php if(!$owned):?><p><?=h($label('wanted.empty'))?></p><?php else:?><div class="table-wrap"><table class="data-table"><thead><tr><th><?=h($label('wanted.request'))?></th><th><?=h($label('saved_search.status'))?></th><th><?=h($label('Suchalarm'))?></th><th><?=h($label('saved_search.actions'))?></th></tr></thead><tbody><?php foreach($owned as $wanted):?><tr><td><a href="?id=<?=(int)$wanted['id']?>"><?=h($wanted['title'])?></a><br><small><?=h($wanted['scope']==='international'?$label('International'):($countries[$wanted['country_code']]??$wanted['country_code']))?></small></td><td><?=h($label($states[$wanted['status']]??'saved_search.reviewing'))?></td><td><?=h($label($modes[$wanted['notify_mode']]??'Aus'))?></td><td><?php if($wanted['status']!=='archived'):?><form method="post"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$wanted['id']?>"><select name="action" aria-label="<?=h(t('saved_search.actions'))?>"><option value="<?=$wanted['status']==='active'?'pause':'resume'?>"><?=h($label($wanted['status']==='active'?'saved_search.pause':'saved_search.resume'))?></option><option value="archive"><?=h($label('saved_search.archive'))?></option></select><button class="button secondary" type="submit"><?=h($label('saved_search.apply'))?></button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?><nav class="pagination" aria-label="<?=h($pageTitle)?>"><?php if($page>1):?><a href="?page=<?=$page-1?>"><?=h($label('Zurück'))?></a><?php endif;?><span><?=h($label('saved_search.page'))?> <?=$page?> / <?=max(1,(int)ceil($total/20))?></span><?php if($page*20<$total):?><a href="?page=<?=$page+1?>"><?=h($label('Weiter'))?></a><?php endif;?></nav></section></main>
<?php include __DIR__.'/../includes/footer.php';?>
