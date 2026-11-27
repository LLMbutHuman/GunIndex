<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/business.php';
$user=require_login($pdo);$pageTitle=t('promotion.title');$error='';$success='';
$_SESSION['promotion_order_key']??=bin2hex(random_bytes(32));
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    require_csrf();
    try{
        $action=post_string('action');
        if($action==='order'){
            if(post_string('accept_terms')!=='1')throw new InvalidArgumentException(t('billing_customer.terms_required'));
            $key=post_string('order_key',64);if(!hash_equals($_SESSION['promotion_order_key'],$key))throw new DomainException(t('billing_customer.reload'));
            $service=post_string('service',24);business_scope($service);
            $listing=post_string('listing_id',20);$campaign=post_string('campaign_id',20);
            if(!ctype_digit($listing)||($campaign!==''&&!ctype_digit($campaign)))throw new InvalidArgumentException(t('promotion.invalid_selection'));
            $id=manual_order($pdo,(int)$user['id'],(int)$listing,$service,$key,$campaign!==''?(int)$campaign:null,$_POST);
            $_SESSION['promotion_order_key']=bin2hex(random_bytes(32));redirect('/user/billing.php?order='.$id);
        }elseif($action==='start'){
            business_start_booking($pdo,(int)$user['id'],(int)post_string('booking_id',20),post_string('immediate_start')==='1',$_POST);$success=t('promotion.started');
        }else throw new InvalidArgumentException(t('finance_customer.invalid_action'));
    }catch(InvalidArgumentException|DomainException|PaymentConflict|PaymentUnavailable|MarketAccessException|MarketConflictException $exception){http_response_code(422);$error=$exception->getMessage();}
    catch(Throwable){http_response_code(503);$error=t('promotion.retry');}
}
$country=business_country($user['country_code']??'DE');
$q=$pdo->prepare("SELECT l.id,l.title,l.country_code,c.name AS category_name FROM market_listings l JOIN market_categories c ON c.id=l.category_id WHERE l.seller_id=? AND l.status='active' ORDER BY l.id DESC LIMIT 100");$q->execute([$user['id']]);$listings=$q->fetchAll();
$q=$pdo->prepare("SELECT id,title,country_code,category_id FROM market_campaigns WHERE user_id=? AND status='approved' AND placement='legacy' ORDER BY id DESC LIMIT 100");$q->execute([$user['id']]);$campaigns=$q->fetchAll();
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]])?:1;
$q=$pdo->prepare('SELECT COUNT(*) FROM market_premium_bookings WHERE user_id=?');$q->execute([$user['id']]);$total=(int)$q->fetchColumn();$page=min($page,max(1,(int)ceil($total/25)));
$q=$pdo->prepare('SELECT b.*,i.invoice_number,i.gross_cents,l.title FROM market_premium_bookings b JOIN market_invoices i ON i.id=b.invoice_id JOIN market_listings l ON l.id=b.listing_id WHERE b.user_id=? ORDER BY b.id DESC LIMIT 25 OFFSET ?');$q->bindValue(1,(int)$user['id'],PDO::PARAM_INT);$q->bindValue(2,($page-1)*25,PDO::PARAM_INT);$q->execute();$bookings=$q->fetchAll();
$reason=business_reason($pdo);
try{$contractDocuments=consent_documents($pdo,'checkout',i18n_locale());}catch(InvalidArgumentException|DomainException $e){$reason=$e->getMessage();}
include __DIR__.'/../includes/header.php';
?>
<main class="container"><div class="page-heading"><h1><?=h($pageTitle)?></h1><p><?=h(t('promotion.intro'))?></p><a class="button secondary" href="/user/dealer.php"><?=h(t('promotion.dealer_link'))?></a></div>
<?php if($error!==''):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?><?php if($success!==''):?><p class="notice success" role="status"><?=h($success)?></p><?php endif;?>
<section class="panel"><h2><?=h(t('promotion.reserve'))?></h2><p><?=h(t('promotion.capacity'))?></p>
<?php if($reason!==null):?><p class="notice"><?=h($reason)?></p><?php elseif(!$listings):?><p><?=h(t('promotion.listing_required'))?></p><?php else:?>
<form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="order"><input type="hidden" name="order_key" value="<?=h($_SESSION['promotion_order_key'])?>">
<?php consent_form($contractDocuments,i18n_locale());?>
<label class="field"><?=h(t('billing_customer.listing'))?><select name="listing_id" required><?php foreach($listings as $listing):?><option value="<?=(int)$listing['id']?>"><?=h($listing['title'].' · '.$listing['country_code'].' · '.$listing['category_name'])?></option><?php endforeach;?></select></label>
<label class="field"><?=h(t('promotion.approved_banner'))?> <small><?=h(t('promotion.banner_help'))?></small><select name="campaign_id"><option value=""><?=h(t('promotion.no_banner'))?></option><?php foreach($campaigns as $campaign):?><option value="<?=(int)$campaign['id']?>"><?=h($campaign['title'].' · '.$campaign['country_code'])?></option><?php endforeach;?></select></label>
<p><a href="/marketplace/policy.php"><?=h(t('billing_customer.read_terms'))?></a></p>
<?php manual_checkout_fields($pdo,['category_top','home_top','banner_monthly']);?>
<button class="button" type="submit"><?=h(business_label('Zahlungspflichtig reservieren'))?></button></form><?php endif;?></section>
<section class="panel"><h2><?=h(t('promotion.bookings'))?></h2><?php if(!$bookings):?><p><?=h(t('promotion.empty'))?></p><?php else:?>
<?php foreach($bookings as $booking):?><article class="panel"><p class="eyebrow"><?=h($booking['country_code'].' · '.payments_service_label($booking['scope']))?></p><h3><?=h($booking['title'])?></h3><p><?=h(business_state_label($booking['status']))?> · <?=h(money_eur((int)$booking['gross_cents'],i18n_locale()))?></p>
<?php if($booking['status']==='awaiting_start'):?><p><?=h(t('promotion.start_deadline',['time'=>$booking['reserved_until']]))?></p><?php elseif($booking['status']==='reserved'):?><p><?=h(t('billing_customer.reserved_until'))?> <?=h($booking['reserved_until'])?> UTC</p><?php elseif($booking['ends_at']):?><p><?=h(t('promotion.end_time',['time'=>$booking['ends_at']]))?></p><?php endif;?>
<a href="/user/billing.php?order=<?=(int)$booking['invoice_id']?>"><?=h(t('billing_customer.order_title',['reference'=>$booking['invoice_number']]))?></a>
<?php if($booking['status']==='awaiting_start'):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="start"><input type="hidden" name="booking_id" value="<?=(int)$booking['id']?>"><?php consent_start_fields('promotion');?><button class="button" type="submit"><?=h(business_label('Bezahlte Platzierung jetzt starten'))?></button></form><?php endif;?>
<?php if($booking['status']==='paid_unfulfilled'):?><p class="notice"><?=h(t('promotion.unfulfilled'))?></p><?php endif;?></article><?php endforeach;?>
<nav class="pagination" aria-label="<?=h(t('promotion.booking_pages'))?>"><?php if($page>1):?><a href="?page=<?=$page-1?>"><?=h(t('Zurück'))?></a><?php endif;?><span><?=h(t('promotion.page',['page'=>$page,'total'=>max(1,(int)ceil($total/25))]))?></span><?php if($page*25<$total):?><a href="?page=<?=$page+1?>"><?=h(t('Weiter'))?></a><?php endif;?></nav><?php endif;?></section></main>
<?php include __DIR__.'/../includes/footer.php';?>
