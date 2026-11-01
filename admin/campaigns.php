<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/business.php';
$admin=function_exists('require_permission')?require_permission($pdo,'campaigns.manage'):require_admin($pdo);$pageTitle='Händler & Werbekampagnen';$error='';$success='';
if(isset($_GET['image'])){
    $id=is_string($_GET['image'])?filter_var($_GET['image'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;
    if(!$id)abort_request(404,'Banner nicht gefunden.');$q=$pdo->prepare('SELECT storage_key FROM market_campaigns WHERE id=?');$q->execute([$id]);$key=$q->fetchColumn();if(!is_string($key))abort_request(404,'Banner nicht gefunden.');
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')audit_log($pdo,(int)$admin['id'],'campaign.previewed',(int)$id);
    business_output_banner($key,true);
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    require_csrf();
    try{
        $action=post_string('action',24);$id=post_string('target_id',20);if(!ctype_digit($id)||(int)$id<1)throw new InvalidArgumentException('Ungültige Kennung.');
        if($action==='profile')business_review_dealer($pdo,(int)$admin['id'],(int)$id,post_string('status',20),post_string('reason',1000),post_string('revision',64));
        elseif($action==='campaign')business_review_campaign($pdo,(int)$admin['id'],(int)$id,post_string('status',20),post_string('reason',1000));
        else throw new InvalidArgumentException('Ungültige Entscheidung.');
        $success='Die Prüfentscheidung wurde gespeichert und protokolliert.';
    }catch(InvalidArgumentException|DomainException $exception){http_response_code(422);$error=$exception->getMessage();}
    catch(Throwable){http_response_code(503);$error='Die Entscheidung konnte gerade nicht gespeichert werden. Bitte erneut versuchen.';}
}
$kind=is_string($_GET['kind']??null)?$_GET['kind']:'campaigns';if(!in_array($kind,['campaigns','profiles'],true))abort_request(422,'Ungültiger Bereich.');
$status=is_string($_GET['status']??null)?$_GET['status']:'pending';$allowed=$kind==='profiles'?['pending','published','rejected','all']:['pending','approved','rejected','disabled','all'];if(!in_array($status,$allowed,true))abort_request(422,'Ungültiger Statusfilter.');
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]])?:1;
$table=$kind==='profiles'?'market_dealer_profiles':'market_campaigns';$where=$status==='all'?'1=1':'p.status=?';$params=$status==='all'?[]:[$status];
$q=$pdo->prepare('SELECT COUNT(*) FROM '.$table.' p WHERE '.$where);$q->execute($params);$total=(int)$q->fetchColumn();$page=min($page,max(1,(int)ceil($total/25)));
$q=$pdo->prepare('SELECT p.*,u.username FROM '.$table.' p JOIN users u ON u.id=p.user_id WHERE '.$where.' ORDER BY p.updated_at DESC,p.user_id LIMIT 25 OFFSET '.(($page-1)*25));$q->execute($params);$rows=$q->fetchAll();
$q=$pdo->query("SELECT COUNT(*) FROM market_premium_bookings WHERE status='paid_unfulfilled'");$reviews=(int)$q->fetchColumn();
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page"><div class="page-heading"><p class="eyebrow">Inhalte prüfen, Kontingente schützen</p><h1>Händler & Werbung.</h1><p>Eine Inhaltsfreigabe ist keine Identitäts-, Unternehmens- oder behördliche Zulassungsbestätigung.</p></div><div class="admin-layout"><?php include __DIR__.'/../includes/admin-nav.php';?><div class="admin-content">
<?php if($error!==''):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?><?php if($success!==''):?><p class="notice success" role="status"><?=h($success)?></p><?php endif;?>
<?php if($reviews):?><section class="panel"><h2><?=$reviews?> bezahlte Buchung(en) ohne automatische Erfüllung</h2><p>Abgelaufene Reservierungen dürfen nicht nachträglich überbucht werden. Bitte Erfüllung oder Erstattung unter Berücksichtigung der tatsächlichen Zahlung klären.</p><a class="button secondary" href="/admin/payments.php?status=all">Zahlungsaufträge prüfen</a></section><?php endif;?>
<section class="panel"><form method="get" class="form-grid"><label class="field">Bereich<select name="kind"><option value="campaigns" <?=$kind==='campaigns'?'selected':''?>>Banner</option><option value="profiles" <?=$kind==='profiles'?'selected':''?>>Händlerprofile</option></select></label><label class="field">Status<select name="status"><?php foreach($allowed as $value):?><option value="<?=h($value)?>" <?=$value===$status?'selected':''?>><?=h($value==='all'?'Alle':business_state_label($value))?></option><?php endforeach;?></select></label><button class="button secondary" type="submit">Anzeigen</button></form><p>Maximale aktive Kontingente: drei Kategorie-Top, zwei Startseiten-Top und zwei Banner je zugehörigem Land/Kategoriebereich. Die Datenbank reserviert die physischen Plätze unter Sperre.</p></section>
<?php foreach($rows as $row):?><section class="panel"><p class="eyebrow"><?=h($row['country_code'].' · '.$row['username'].' · '.business_state_label($row['status']))?></p>
<?php if($kind==='profiles'):?><h2><?=h($row['company_name'])?></h2><p style="white-space:pre-wrap;overflow-wrap:anywhere"><?=h($row['address_text'])?></p><p><?=h($row['contact_email'])?></p><?php if($row['website']):?><p><?=h($row['website'])?></p><?php endif;?><p style="white-space:pre-wrap;overflow-wrap:anywhere"><?=h($row['legal_notice'])?></p>
<form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="profile"><input type="hidden" name="target_id" value="<?=(int)$row['user_id']?>"><input type="hidden" name="revision" value="<?=h(business_profile_revision($row))?>"><label class="field">Entscheidung<select name="status"><option value="published">Geschäftsangaben veröffentlichen</option><option value="rejected">Nicht veröffentlichen</option></select></label>
<?php else:?><h2><?=h($row['title'])?></h2><img src="?image=<?=(int)$row['id']?>" alt="Eingereichtes Werbebanner" width="480" height="200" loading="lazy" style="max-height:200px;object-fit:contain"><p>Zieladresse: <?=h($row['target_url'])?><br>Kategorie #<?=(int)$row['category_id']?></p><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="campaign"><input type="hidden" name="target_id" value="<?=(int)$row['id']?>"><label class="field">Entscheidung<select name="status"><option value="approved">Banner inhaltlich freigeben</option><option value="rejected">Banner ablehnen</option><option value="disabled">Banner deaktivieren</option></select></label>
<?php endif;?><label class="field">Nachvollziehbare Begründung<textarea name="reason" minlength="10" maxlength="1000" required></textarea></label><button class="button" type="submit">Prüfentscheidung speichern</button></form>
<?php if($row['moderation_reason']):?><p>Letzte Begründung: <?=h($row['moderation_reason'])?></p><?php endif;?></section><?php endforeach;?>
<?php if(!$rows):?><section class="panel"><p>Keine passenden Einträge.</p></section><?php endif;?>
<nav class="pagination" aria-label="Prüfseiten"><?php if($page>1):?><a href="?kind=<?=h($kind)?>&amp;status=<?=h($status)?>&amp;page=<?=$page-1?>">Zurück</a><?php endif;?><span>Seite <?=$page?> von <?=max(1,(int)ceil($total/25))?></span><?php if($page*25<$total):?><a href="?kind=<?=h($kind)?>&amp;status=<?=h($status)?>&amp;page=<?=$page+1?>">Weiter</a><?php endif;?></nav></div></div></main>
<?php include __DIR__.'/../includes/footer.php';?>
