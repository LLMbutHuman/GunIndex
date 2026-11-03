<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);$pageTitle='Bumps & Zahlungsaufträge';$error='';$success='';
if(!isset($_SESSION['billing_order_key']))$_SESSION['billing_order_key']=bin2hex(random_bytes(32));
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 require_csrf();
 try{
  $action=post_string('action');
  if($action==='order'){
   if(post_string('accept_terms')!=='1')throw new InvalidArgumentException('Bitte die Leistungsbedingungen ausdrücklich bestätigen.');
   $key=post_string('order_key',64);if(!hash_equals($_SESSION['billing_order_key'],$key))throw new PaymentConflict('Bitte lade das Bestellformular neu.');
   $id=manual_order($pdo,(int)$user['id'],(int)post_string('listing_id'),post_string('service'),$key,null,$_POST);
   $_SESSION['billing_order_key']=bin2hex(random_bytes(32));redirect('/user/billing.php?order='.$id);
  }elseif($action==='claim'){
   $pdo->prepare("UPDATE market_manual_orders o JOIN market_invoices i ON i.id=o.invoice_id SET o.customer_claimed_at=COALESCE(o.customer_claimed_at,UTC_TIMESTAMP()),i.status=IF(i.status='issued','pending',i.status) WHERE o.invoice_id=? AND o.user_id=? AND i.status IN ('issued','pending')")->execute([(int)post_string('invoice_id'),$user['id']]);
   $success='Dein Hinweis wurde gespeichert. Die Zahlung bleibt bis zur tatsächlichen Prüfung unbestätigt.';
  }elseif($action==='bump'){manual_bump($pdo,(int)$user['id'],(int)post_string('listing_id'),post_string('immediate_start')==='1');$success='Dein Inserat wurde nach oben verschoben.';}
  else throw new InvalidArgumentException('Ungültige Aktion.');
 }catch(InvalidArgumentException|DomainException|PaymentConflict|PaymentUnavailable|MarketConflictException|MarketNotFoundException $e){http_response_code(422);$error=$e->getMessage();}
}
$q=$pdo->prepare("SELECT l.* FROM market_listings l WHERE seller_id=? AND status='active' ORDER BY id DESC LIMIT 100");$q->execute([$user['id']]);$listings=array_values(array_filter($q->fetchAll(),fn($l)=>catalog_category_allowed($pdo,(int)$l['category_id'])&&($l['type']!=='auction'||!market_expired($l))));
$q=$pdo->prepare('SELECT COALESCE(SUM(c.remaining),0) FROM market_bump_credits c JOIN market_invoices i ON i.id=c.invoice_id WHERE c.user_id=? AND i.status=\'paid\'');$q->execute([$user['id']]);$credits=(int)$q->fetchColumn();
$q=$pdo->prepare('SELECT i.*,o.reference,o.recipient,o.quantity,o.terms_text,o.net_cents,o.tax_cents,o.customer_claimed_at FROM market_invoices i JOIN market_manual_orders o ON o.invoice_id=i.id WHERE i.user_id=? ORDER BY i.id DESC LIMIT 100');$q->execute([$user['id']]);$orders=$q->fetchAll();
$selected=null;if(isset($_GET['order'])){$raw=$_GET['order'];$id=is_string($raw)?filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;if(!$id)abort_request(404,'Zahlungsauftrag nicht gefunden.');$q=$pdo->prepare('SELECT i.*,o.reference,o.recipient,o.quantity,o.terms_text,o.net_cents,o.tax_cents,o.customer_claimed_at FROM market_invoices i JOIN market_manual_orders o ON o.invoice_id=i.id WHERE i.id=? AND i.user_id=?');$q->execute([$id,$user['id']]);$selected=$q->fetch();if(!$selected)abort_request(404,'Zahlungsauftrag nicht gefunden.');}
$selectedPromotion=null;if($selected&&in_array($selected['service_code'],['category_top','home_top','banner_monthly'],true)){$q=$pdo->prepare('SELECT * FROM market_premium_bookings WHERE invoice_id=? AND user_id=?');$q->execute([$selected['id'],$user['id']]);$selectedPromotion=$q->fetch()?:null;}
$selectedPayable=$selected&&in_array($selected['status'],['issued','pending','manual_rejected'],true)&&manual_payment_reason($pdo)===null;
$selectedAuction=$selected&&auction_billing_service($selected['service_code']);
if($selectedAuction&&!finance_enabled($pdo))$selectedPayable=false;
if($selectedPromotion&&($selectedPromotion['status']!=='reserved'||strtotime($selectedPromotion['reserved_until'].' UTC')<=time()||business_reason($pdo)!==null))$selectedPayable=false;

$reason=manual_payment_reason($pdo);
try{$contractDocuments=consent_documents($pdo,'checkout',i18n_locale());}catch(InvalidArgumentException|DomainException $e){$reason=$e->getMessage();}
include __DIR__.'/../includes/header.php';
?>
<main class="container"><div class="page-heading"><?php if($selectedAuction):?><h1><?=h(t('auction_billing.heading'))?></h1><a href="/user/invoices.php"><?=h(t('auction_billing.open'))?></a><?php else:?><p class="eyebrow">Mehr Sichtbarkeit</p><h1>Bumps. Klar und kontrolliert.</h1><a class="button secondary" href="/user/promotions.php">Premiumplätze &amp; Händlerwerbung</a><p>Bezahltes Guthaben: <strong><?= $credits ?></strong> · ein Bump je Inserat alle 72 Stunden.</p><?php endif;?></div>
<?php if($error):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?><?php if($success):?><p class="notice success" role="status"><?=h($success)?></p><?php endif;?>
<?php if(!$selectedAuction):?><section class="panel"><h2>Bump-Guthaben bestellen</h2>
<?php if($reason):?><p class="notice"><?=h($reason)?> Es wird kein Zahlungsauftrag ausgelöst.</p><?php elseif(!$listings):?><p>Erstelle zuerst ein aktives Angebot.</p><?php else:?>
<form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="order"><input type="hidden" name="order_key" value="<?=h($_SESSION['billing_order_key'])?>">
<?php consent_form($contractDocuments,i18n_locale());?>
<label class="field">Dein Inserat<select name="listing_id"><?php foreach($listings as $l):?><option value="<?=(int)$l['id']?>"><?=h($l['title'])?></option><?php endforeach;?></select></label>
<label class="field">Leistung<select name="service"><?php foreach(['bump_single','bump_pack'] as $service):$quote=fees_quote($service,null,$pdo);?><option value="<?=h($service)?>"><?=h($quote['label'].' · '.money_eur($quote['gross_cents']))?></option><?php endforeach;?></select></label>
<p>Der Gesamtbetrag einschließlich der konfigurierten Umsatzsteuer steht bei der Leistung. Der Artikelkaufpreis wird hier nicht eingezogen. Keine automatische Aktivierung, kein zugesicherter Verkauf. Nach manueller Zahlungsprüfung aktivierst du einen Bump selbst. Gesetzliche Verbraucherrechte bleiben bestehen. <a href="/marketplace/policy.php">Bedingungen lesen und speichern</a>.</p>
<label><input type="checkbox" name="accept_terms" value="1" required> Ich bestelle die ausgewählte Option zu ihrem angezeigten Gesamtpreis und bestätige die Leistungsbedingungen: <?=h(manual_terms(1). ' Bei der Dreieroption umfasst das Guthaben drei Aktivierungen.')?></label><button class="button" type="submit">Zahlungspflichtig bestellen</button></form>
<?php endif;?></section><?php endif;?>
<?php if($selected)consent_order_links($pdo,(int)$user['id'],(int)$selected['id']);?>
<?php if($selected):?><section class="panel"><h2>Dein Zahlungsauftrag <?=h($selected['reference'])?></h2><p>Gesamt: <strong><?=h(money_eur((int)$selected['gross_cents']))?></strong> · Netto <?=h(money_eur((int)$selected['net_cents']))?> · Umsatzsteuer <?=h(money_eur((int)$selected['tax_cents']))?></p><p>Verwendungszweck: <strong><?=h($selected['reference'])?></strong><br>Empfänger: <?=h($selected['recipient'])?><br>Status: <?=h(payments_status_label($selected['status']))?></p>
<?php if($selectedPayable):?>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank" rel="noopener noreferrer"><input type="hidden" name="cmd" value="_xclick"><input type="hidden" name="business" value="<?=h($selected['recipient'])?>"><input type="hidden" name="amount" value="<?=h(payments_decimal((int)$selected['gross_cents']))?>"><input type="hidden" name="currency_code" value="EUR"><input type="hidden" name="item_name" value="<?=h(site_setting($pdo,'brand_name').' · '.$selected['service_code'].' · '.$selected['reference'])?>"><input type="hidden" name="invoice" value="<?=h($selected['reference'])?>"><input type="hidden" name="custom" value="<?=h($selected['reference'])?>"><input type="hidden" name="no_shipping" value="1"><input type="hidden" name="charset" value="utf-8"><button class="button" type="submit">PayPal-Zahlungsauftrag öffnen · <?=h(money_eur((int)$selected['gross_cents']))?></button></form>
<p>PayPal öffnet separat. Nutze die geschäftliche Zahlung für diese Leistung. Ob dein Empfängerkonto diesen Zahlungsbutton unterstützt, richtet sich nach PayPal. Eine Rückkehr zur Website bestätigt keinen Zahlungseingang.</p>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="claim"><input type="hidden" name="invoice_id" value="<?=(int)$selected['id']?>"><button class="button secondary" type="submit">Ich habe bezahlt · Prüfung anfordern</button></form>
<?php endif;?><?php if($selectedPromotion):?><p>Platzierung: <?=h(business_state_label($selectedPromotion['status']))?> · reserviert bis <?=h($selectedPromotion['reserved_until'])?> UTC. <a href="/user/promotions.php">Buchung und Leistungsbeginn verwalten</a></p><?php endif;?><details><summary>Gespeicherte Leistungsbedingungen</summary><p><?=h($selected['terms_text'])?></p></details></section><?php endif;?>
<?php if(!$selectedAuction):?><section class="panel"><h2>Bump aktivieren</h2><?php if($credits>0&&$listings):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="bump"><label class="field">Inserat<select name="listing_id"><?php foreach($listings as $l):?><option value="<?=(int)$l['id']?>"><?=h($l['title'])?><?= $l['bumped_at']?' · letzter Bump '.h($l['bumped_at']).' UTC':''?></option><?php endforeach;?></select></label><p>Eine Aktivierung verbraucht ein bezahltes Guthaben. Erstellungsdatum, Gebote und Auktionsende bleiben erhalten.</p><label><input type="checkbox" name="immediate_start" value="1" required> Ich verlange ausdrücklich, dass dieser einzelne Bump sofort ausgeführt wird, auch vor Ablauf einer Widerrufsfrist. Gesetzliche Rechte für noch nicht vollständig erbrachte Leistungen bleiben bestehen; dies ist kein pauschaler Widerrufsverzicht.</label><button class="button" type="submit">Einen Bump sofort aktivieren</button></form><?php else:?><p>Hier kannst du bestätigtes Guthaben auf ein aktives Inserat anwenden.</p><?php endif;?></section>
<?php endif;?><section class="panel"><h2>Deine Zahlungsaufträge</h2><?php if(!$orders):?><p>Noch keine Aufträge.</p><?php else:?><div class="table-wrap"><table><thead><tr><th>Referenz</th><th>Betrag</th><th>Status</th></tr></thead><tbody><?php foreach($orders as $o):?><tr><td><a href="?order=<?=(int)$o['id']?>"><?=h($o['reference'])?></a></td><td><?=h(money_eur((int)$o['gross_cents']))?></td><td><?=h(payments_status_label($o['status']))?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section></main>
<?php include __DIR__.'/../includes/footer.php';?>
