<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/business.php';
$user=require_login($pdo);$pageTitle='Premiumplätze & Händlerwerbung';$error='';$success='';
$_SESSION['promotion_order_key']??=bin2hex(random_bytes(32));
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    require_csrf();
    try{
        $action=post_string('action');
        if($action==='order'){
            if(post_string('accept_terms')!=='1')throw new InvalidArgumentException('Bitte die Leistungsbedingungen ausdrücklich bestätigen.');
            $key=post_string('order_key',64);if(!hash_equals($_SESSION['promotion_order_key'],$key))throw new DomainException('Bitte das Bestellformular erneut laden.');
            $service=post_string('service',24);business_scope($service);
            $listing=post_string('listing_id',20);$campaign=post_string('campaign_id',20);
            if(!ctype_digit($listing)||($campaign!==''&&!ctype_digit($campaign)))throw new InvalidArgumentException('Ungültige Angebots- oder Kampagnenkennung.');
            $id=manual_order($pdo,(int)$user['id'],(int)$listing,$service,$key,$campaign!==''?(int)$campaign:null,$_POST);
            $_SESSION['promotion_order_key']=bin2hex(random_bytes(32));redirect('/user/billing.php?order='.$id);
        }elseif($action==='start'){
            business_start_booking($pdo,(int)$user['id'],(int)post_string('booking_id',20),post_string('immediate_start')==='1');$success='Die bezahlte Platzierung wurde gestartet.';
        }else throw new InvalidArgumentException('Ungültige Aktion.');
    }catch(InvalidArgumentException|DomainException|PaymentConflict|PaymentUnavailable|MarketAccessException|MarketConflictException $exception){http_response_code(422);$error=$exception->getMessage();}
    catch(Throwable){http_response_code(503);$error='Die Buchung konnte gerade nicht abgeschlossen werden. Es wurde keine erfolgreiche Zahlung behauptet. Bitte erneut versuchen.';}
}
$country=business_country($user['country_code']??'DE');
$q=$pdo->prepare("SELECT l.id,l.title,l.country_code,c.name AS category_name FROM market_listings l JOIN market_categories c ON c.id=l.category_id WHERE l.seller_id=? AND l.status='active' ORDER BY l.id DESC LIMIT 100");$q->execute([$user['id']]);$listings=$q->fetchAll();
$q=$pdo->prepare("SELECT id,title,country_code,category_id FROM market_campaigns WHERE user_id=? AND status='approved' ORDER BY id DESC LIMIT 100");$q->execute([$user['id']]);$campaigns=$q->fetchAll();
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]])?:1;
$q=$pdo->prepare('SELECT COUNT(*) FROM market_premium_bookings WHERE user_id=?');$q->execute([$user['id']]);$total=(int)$q->fetchColumn();$page=min($page,max(1,(int)ceil($total/25)));
$q=$pdo->prepare('SELECT b.*,i.invoice_number,i.gross_cents,l.title FROM market_premium_bookings b JOIN market_invoices i ON i.id=b.invoice_id JOIN market_listings l ON l.id=b.listing_id WHERE b.user_id=? ORDER BY b.id DESC LIMIT 25 OFFSET ?');$q->bindValue(1,(int)$user['id'],PDO::PARAM_INT);$q->bindValue(2,($page-1)*25,PDO::PARAM_INT);$q->execute();$bookings=$q->fetchAll();
$reason=business_reason($pdo);
try{$contractDocuments=consent_documents($pdo,'checkout',i18n_locale());}catch(InvalidArgumentException|DomainException $e){$reason=$e->getMessage();}
include __DIR__.'/../includes/header.php';
?>
<main class="container"><div class="page-heading"><p class="eyebrow">Sichtbarkeit mit festen Grenzen</p><h1>Dein Platz im richtigen Markt.</h1><p>Getrennte Kontingente je Land und Kategorie. Werbung bleibt als „Gesponsert“ erkennbar.</p><a class="button secondary" href="/user/dealer.php"><?=h(business_label('Händlerprofil'))?> &amp; <?=h(business_label('Banner'))?></a></div>
<?php if($error!==''):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?><?php if($success!==''):?><p class="notice success" role="status"><?=h($success)?></p><?php endif;?>
<section class="panel"><h2>Platzierung reservieren</h2><p>Kategorie-Top: höchstens drei Plätze pro Land und Kategorie. Startseite: höchstens zwei pro Land. Händlerbanner: höchstens zwei pro Land und Kategorie.</p>
<?php if($reason!==null):?><p class="notice"><?=h($reason)?></p><?php elseif(!$listings):?><p>Du benötigst zunächst ein eigenes aktives Angebot. Händlerbanner werden zusätzlich inhaltlich geprüft.</p><?php else:?>
<form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="order"><input type="hidden" name="order_key" value="<?=h($_SESSION['promotion_order_key'])?>">
<?php consent_form($contractDocuments,i18n_locale());?>
<label class="field">Eigenes Angebot<select name="listing_id" required><?php foreach($listings as $listing):?><option value="<?=(int)$listing['id']?>"><?=h($listing['title'].' · '.$listing['country_code'].' · '.$listing['category_name'])?></option><?php endforeach;?></select></label>
<label class="field">Leistung<select name="service"><?php foreach(['category_top','home_top','banner_monthly'] as $service):$quote=fees_quote($service,null,$pdo);?><option value="<?=h($service)?>"><?=h($quote['label'].' · '.money_eur($quote['gross_cents']))?></option><?php endforeach;?></select></label>
<label class="field">Freigegebenes Banner <small>Nur für Händlerbanner erforderlich, Land und Kategorie müssen passen.</small><select name="campaign_id"><option value="">Kein Banner</option><?php foreach($campaigns as $campaign):?><option value="<?=(int)$campaign['id']?>"><?=h($campaign['title'].' · '.$campaign['country_code'])?></option><?php endforeach;?></select></label>
<p>Die Verfügbarkeit wird beim Absenden unter Datenbanksperre erneut geprüft. Die Reservierung läuft <?=business_reservation_minutes($pdo)?> Minuten. Ein Zahlungsauftrag wird nur bei einem freien Platz angelegt. Nach tatsächlicher Zahlungsprüfung startest du die Laufzeit innerhalb von sieben Tagen gesondert. Ein verspätet bestätigter Eingang erzeugt keine Überbuchung; der Betreiber klärt dann Erfüllung oder Erstattung.</p>
<label><input type="checkbox" name="accept_terms" value="1" required> Ich bestelle die ausgewählte Leistung zum angegebenen Gesamtpreis, bestätige die <a href="/marketplace/policy.php">Bedingungen</a> und weiß, dass keine bestimmte Reichweite, Klickzahl oder Transaktion garantiert wird. Die Bestellung verlangt noch keinen vorzeitigen Leistungsbeginn.</label>
<button class="button" type="submit"><?=h(business_label('Zahlungspflichtig reservieren'))?></button></form><?php endif;?></section>
<section class="panel"><h2>Deine Buchungen</h2><?php if(!$bookings):?><p>Noch keine Platzierung gebucht.</p><?php else:?>
<?php foreach($bookings as $booking):$spec=business_scope($booking['scope']);?><article class="panel"><p class="eyebrow"><?=h($booking['country_code'].' · '.$spec['label'])?></p><h3><?=h($booking['title'])?></h3><p><?=h(business_state_label($booking['status']))?> · <?=h(money_eur((int)$booking['gross_cents']))?></p><p>Reserviert bis <?=h($booking['reserved_until'])?> UTC<?php if($booking['ends_at']):?> · Laufzeitende <?=h($booking['ends_at'])?> UTC<?php endif;?></p><a href="/user/billing.php?order=<?=(int)$booking['invoice_id']?>">Zahlungsauftrag <?=h($booking['invoice_number'])?></a>
<?php if($booking['status']==='awaiting_start'):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="start"><input type="hidden" name="booking_id" value="<?=(int)$booking['id']?>"><label><input type="checkbox" name="immediate_start" value="1" required> Ich verlange ausdrücklich den sofortigen Beginn dieser einzelnen Platzierung, auch vor Ablauf einer Widerrufsfrist. Gesetzliche Rechte bleiben im jeweils anwendbaren Umfang bestehen.</label><button class="button" type="submit"><?=h(business_label('Bezahlte Platzierung jetzt starten'))?></button></form><?php endif;?>
<?php if($booking['status']==='paid_unfulfilled'):?><p class="notice">Bezahlt, aber nicht automatisch erfüllt. Bitte kontaktiere den Betreiber unter Angabe der Referenz; die Prüfung darf keine doppelte Platzvergabe erzeugen.</p><?php endif;?></article><?php endforeach;?>
<nav class="pagination" aria-label="Buchungsseiten"><?php if($page>1):?><a href="?page=<?=$page-1?>">Zurück</a><?php endif;?><span>Seite <?=$page?> von <?=max(1,(int)ceil($total/25))?></span><?php if($page*25<$total):?><a href="?page=<?=$page+1?>">Weiter</a><?php endif;?></nav><?php endif;?></section></main>
<?php include __DIR__.'/../includes/footer.php';?>
