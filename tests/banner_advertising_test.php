<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit(2); }
require_once __DIR__.'/../config/functions.php';
require_once __DIR__.'/../config/business.php';

function banner_check(bool $valid, string $message): void
{
    if (!$valid) throw new RuntimeException($message);
}

function banner_rejected(callable $operation): void
{
    try { $operation(); }
    catch (InvalidArgumentException|DomainException|PaymentConflict|PaymentUnavailable) { return; }
    throw new RuntimeException('Expected banner rejection.');
}

$gif = 'GIF89a'."\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff";
$frame = "\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00";
$comment = "\x21\xfe\x04text\x00";
$loop = "\x21\xff\x0bNETSCAPE2.0\x03\x01\x00\x00\x00";
$gce = "\x21\xf9\x04\x00\x01\x00\x00\x00";
$clean = banner_safe_gif($gif.$comment.$loop.$gce.$frame.$gce.$frame."\x3b");
$cleanGce = "\x21\xf9\x04\x00\x0c\x00\x00\x00";
banner_check($clean===$gif.$cleanGce.$frame.$cleanGce.$frame."\x3b",'GIF animation or metadata processing differs.');
foreach ([$gif.$frame,$gif.$frame."\x3b".'extra',$gif.str_repeat($frame,31)."\x3b",$gif."\x21\x01\x01\x00\x00".$frame."\x3b"] as $invalid) {
    banner_rejected(static fn()=>banner_safe_gif($invalid));
}
banner_check(banner_ads_url('https://shop.example.org/offer?q=demo')==='https://shop.example.org/offer?q=demo','HTTPS URL changed.');
banner_check(banner_ads_url('/marketplace/profile.php?user_id=17')==='/marketplace/profile.php?user_id=17','Internal dealer profile rejected.');
foreach (['//other.example.org','/marketplace/banner-click.php?id=1','/marketplace/search.php?return=https://other.example.org',
    '/%2fother.example.org','javascript:alert(1)'] as $invalid) banner_rejected(static fn()=>banner_ads_url($invalid));
banner_check(!banner_ads_capacity_available([['1','4'],['2','5'],['3','6']],'3','4'),'Overlapping fourth banner admitted.');
banner_check(banner_ads_capacity_available([['1','3'],['1','3'],['1','3']],'3','4'),'Ending slots blocked a consecutive interval.');
banner_check(banner_ads_capacity_available([['1','2'],['2','3'],['3','4']],'1','4'),'Nonoverlapping intervals were counted as concurrent.');
foreach (['http://shop.example.org/', 'https://user@shop.example.org/', 'https://127.0.0.1/', "https://shop.example.org/\r\n"] as $invalid) {
    // Surrounding whitespace is normalized by the established URL helper. A control inside the URL is rejected.
    if (str_contains($invalid,"\r")) $invalid .= 'x';
    banner_rejected(static fn()=>banner_ads_url($invalid));
}
banner_check(banner_ads_selection('country','14')===['country',14],'Valid offer selection failed.');
banner_rejected(static fn()=>banner_ads_selection('country','8'));
banner_rejected(static fn()=>banner_ads_time('2026-02-30T11:00'));
$source = array_filter(i18n_dictionary('de'),static fn(string $key):bool=>str_starts_with($key,'banner_ads.'), ARRAY_FILTER_USE_KEY);
banner_check(count($source)>=50,'German advertising catalog incomplete.');
foreach (array_keys(i18n_locales()) as $language) {
    $translated = array_intersect_key(i18n_dictionary($language),$source);
    banner_check(count($translated)===count($source),'Missing advertising texts in '.$language);
    foreach ($source as $key=>$original) {
        preg_match_all('/\{[a-z]+\}/',$original,$expected);
        preg_match_all('/\{[a-z]+\}/',$translated[$key],$actual);
        sort($expected[0]); sort($actual[0]);
        banner_check($expected[0]===$actual[0],'Changed parameters in '.$language.' / '.$key);
    }
}
ob_start();
banner_ads_creative(['creative_format'=>'text','overlay_style'=>'light','overlay_text'=>'<b>Example</b>','title'=>'Preview'],'','');
$html = ob_get_clean();
banner_check(str_contains($html,'&lt;b&gt;Example&lt;/b&gt;')&&!str_contains($html,'<b>'),'Overlay markup is not escaped.');
echo "Banner validation, localization, escaped text and animated GIF checks passed.\n";

if (($argv[1]??'')!=='--database') exit;
if (getenv('DB_DATABASE')!=='gunindex_rebuild_test') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/dealer_admin.php';

function banner_fixture_user(PDO $pdo, bool $admin = false): int
{
    $name = 'banner_test_'.bin2hex(random_bytes(7));
    $q = $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,is_admin,country_code) VALUES (?,?,?,'Synthetic banner fixture','10115','Test City',?,'DE')");
    $q->execute([$name,$name.'@example.invalid',auth_hash('Banner test '.bin2hex(random_bytes(12))),(int)$admin]);
    return (int)$pdo->lastInsertId();
}

function banner_fixture_campaign(PDO $pdo, int $id): array
{
    $q = $pdo->prepare('SELECT * FROM market_campaigns WHERE id=?');
    $q->execute([$id]);
    return $q->fetch();
}

function banner_fixture_booking(PDO $pdo, int $invoice): array
{
    $q = $pdo->prepare('SELECT b.*,i.gross_cents,i.status AS invoice_status,o.reference FROM market_premium_bookings b JOIN market_invoices i ON i.id=b.invoice_id JOIN market_manual_orders o ON o.invoice_id=i.id WHERE b.invoice_id=?');
    $q->execute([$invoice]);
    return $q->fetch();
}

$settings = ['business_promotions_enabled'=>'1','dealer_profiles_enabled'=>'1','manual_paypal_enabled'=>'1','manual_paypal_approved'=>'1',
    'manual_paypal_recipient'=>'banner-test@example.invalid','manual_tax_reviewed'=>'1','manual_tax_bps'=>'0','finance_enabled'=>'0','legal_checkout_documents'=>''];
$previous = [];
$read = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key=?');
$write = $pdo->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
foreach ($settings as $key=>$value) { $read->execute([$key]); $previous[$key]=$read->fetchColumn(); $write->execute([$key,$value]); }
site_settings($pdo,true);
$actors = [];
$operatorBanners = [];
$country = $pdo->query("SELECT is_active FROM market_countries WHERE code='DE'")->fetchColumn();
$rate = $pdo->query("SELECT * FROM market_banner_rates WHERE placement='country' AND term_days=7")->fetch();
try {
    $pdo->exec("UPDATE market_countries SET is_active=1 WHERE code='DE'");
    $admin = banner_fixture_user($pdo,true); $actors[]=$admin;
    $dealer = banner_fixture_user($pdo); $actors[]=$dealer;
    $outsider = banner_fixture_user($pdo); $actors[]=$outsider;
    $adminInput = ['placement'=>'country','country_code'=>'DE','title'=>'Synthetic platform banner','overlay_text'=>'Synthetic text',
        'overlay_style'=>'light','target_url'=>'https://shop.example.org/demo','priority'=>'25',
        'scheduled_from'=>gmdate('Y-m-d\TH:i',time()-3600),'scheduled_until'=>gmdate('Y-m-d\TH:i',time()+86400)];
    $operatorId = banner_ads_operator_save($pdo,$admin,null,$adminInput,[]); $operatorBanners[]=$operatorId;
    banner_check(!business_dealer_profile($pdo,$admin,false),'Operator unexpectedly has a dealer profile.');
    banner_check(banner_ads_public_campaign($pdo,$operatorId,'DE')===null,'Pending operator banner surfaced.');
    $review = static function(int $id,string $decision) use ($pdo,$admin): void {
        banner_ads_review($pdo,$admin,$id,$decision,'Synthetic explicit ad review',banner_ads_revision(banner_fixture_campaign($pdo,$id)));
    };
    banner_rejected(static fn()=>banner_ads_review($pdo,$outsider,$operatorId,'approved','Synthetic review',str_repeat('0',64)));
    $review($operatorId,'approved');
    banner_check(banner_ads_public_campaign($pdo,$operatorId,'DE')!==null,'Approved operator banner did not surface.');
    banner_check(!banner_ads_public_campaign($pdo,$operatorId,'AT'),'Ad leaked into another country.');
    $review($operatorId,'disabled');
    banner_check(!banner_ads_public_campaign($pdo,$operatorId,'DE'),'Paused banner still visible.');

    business_save_dealer($pdo,$dealer,['company_name'=>'Synthetic Banner Seller','address_text'=>'A Test Address 123','country_code'=>'DE',
        'contact_email'=>'seller@example.invalid','legal_notice'=>'A synthetic legal notice for testing.','website'=>'https://shop.example.org']);
    $profile=business_dealer_profile($pdo,$dealer,false);
    business_review_dealer($pdo,$admin,$dealer,'published','Synthetic dealer profile review',business_profile_revision($profile));
    banner_rejected(static fn()=>dealer_admin_revoke($pdo,$outsider,$dealer,'member',$admin,1,'Synthetic denied permission'));
    banner_ads_save_rate($pdo,$admin,'country',7,1234);
    $quote=banner_ads_quote($pdo,'country',7);
    $input=['placement'=>'country','term_days'=>'7','country_code'=>'DE','title'=>'Synthetic paid banner','overlay_text'=>'Synthetic copy',
        'overlay_style'=>'dark','target_url'=>'https://shop.example.org/privacy','request_key'=>bin2hex(random_bytes(32)),
        'accept_terms'=>'1','quote'=>['country_7'=>$quote['approval_hash']]];
    banner_rejected(static fn()=>banner_ads_submit($pdo,$dealer,array_replace($input,['quote'=>['country_7'=>str_repeat('0',64)]]),[]));
    [$campaignId,$invoiceId]=banner_ads_submit($pdo,$dealer,$input,[]);
    banner_check(banner_ads_submit($pdo,$dealer,$input,[])===[$campaignId,$invoiceId],'Submission was not idempotent.');
    $booking=banner_fixture_booking($pdo,$invoiceId);
    banner_check($booking['status']==='reserved' && $booking['listing_id']===null && (int)$booking['gross_cents']===1234,'Malformed paid banner reservation.');
    banner_check(!banner_ads_public_campaign($pdo,$campaignId,'DE'),'Unpaid creative surfaced.');
    manual_review($pdo,$admin,$invoiceId,'GIADTEST'.strtoupper(bin2hex(random_bytes(6))),$booking['reference'],1234,'approve','Synthetic verified payment');
    $booking=banner_fixture_booking($pdo,$invoiceId);
    banner_check($booking['status']==='awaiting_review' && $booking['invoice_status']==='paid','Payment and campaign review were not separated.');
    banner_check(!banner_ads_public_campaign($pdo,$campaignId,'DE'),'Paid campaign surfaced before review.');
    $review($campaignId,'approved');
    $booking=banner_fixture_booking($pdo,$invoiceId);
    banner_check($booking['status']==='awaiting_start','Approval did not release the explicit start step.');
    $notice=consent_start_notice('promotion');
    $start=['start_language'=>$notice['consent_language'],'start_hash'=>$notice['consent_hash']];
    banner_rejected(static fn()=>business_start_booking($pdo,$outsider,(int)$booking['id'],true,$start));
    business_start_booking($pdo,$dealer,(int)$booking['id'],true,$start);
    $booking=banner_fixture_booking($pdo,$invoiceId);
    banner_check(strtotime($booking['ends_at'].' UTC')-strtotime($booking['starts_at'].' UTC')===604800,'The price did not preserve the booked seven-day term.');
    banner_check(banner_ads_public_campaign($pdo,$campaignId,'DE')!==null,'Paid and reviewed campaign did not activate.');

    $makeOperator = static function (string $market, string $title, int $priority) use ($pdo,$admin,$adminInput,&$operatorBanners): int {
        $id = banner_ads_operator_save($pdo,$admin,null,array_replace($adminInput,['country_code'=>$market,'title'=>$title,'priority'=>(string)$priority]),[]);
        $operatorBanners[] = $id;
        return $id;
    };
    $de1=$makeOperator('DE','Synthetic DE priority one',20); $review($de1,'approved');
    $de2=$makeOperator('DE','Synthetic DE priority two',10); $review($de2,'approved');
    $blocked=$makeOperator('DE','Synthetic DE blocked',0);
    banner_rejected(static fn()=>$review($blocked,'approved'));
    banner_rejected(static fn()=>banner_ads_submit($pdo,$dealer,array_replace($input,['request_key'=>bin2hex(random_bytes(32))]),[]));
    banner_check(array_map('intval',array_column(banner_ads_public($pdo,'DE'),'id'))===[$campaignId,$de1,$de2],'Country capacity or paid-first ordering is wrong.');

    // Exercise the migration snapshot against a pre-existing paid overlap without altering its invoice.
    $pdo->prepare("UPDATE market_campaigns SET status='approved',placement='search' WHERE id=?")->execute([$blocked]);
    $pdo->prepare("UPDATE market_campaigns SET placement='home' WHERE id IN (?,?)")->execute([$de1,$de2]);
    $pdo->prepare("UPDATE market_campaigns SET placement='home' WHERE id=?")->execute([$campaignId]);
    $pdo->prepare("UPDATE market_premium_bookings SET scope='banner_home' WHERE id=?")->execute([$booking['id']]);
    $migration = file_get_contents(__DIR__.'/../sql/migrations/044_country_banner_inventory.sql');
    $snapshot = strstr($migration,'INSERT INTO market_banner_capacity_cases (booking_id)');
    banner_check(is_string($snapshot),'Migration case capture missing.');
    $pdo->exec($snapshot);
    banner_check(in_array((int)$booking['id'],array_map('intval',array_column(banner_ads_capacity_cases($pdo,'DE'),'booking_id')),true),'A paid overflow was not surfaced for manual review.');
    banner_check(!banner_ads_public_campaign($pdo,$blocked,'DE'),'An overflow banner was accessible by its direct endpoint selection.');
    $reason='Synthetic dealer agreed to a replacement term.';
    $future=gmdate('Y-m-d\TH:i',time()+10*86400);
    banner_rejected(static fn()=>banner_ads_resolve_capacity($pdo,$outsider,(int)$booking['id'],$reason,$future,true));
    banner_ads_resolve_capacity($pdo,$admin,(int)$booking['id'],$reason,$future,true);
    $replacement=banner_fixture_booking($pdo,$invoiceId);
    banner_check($replacement['invoice_id']===$booking['invoice_id'] && $replacement['invoice_status']==='paid'
        && $replacement['reference']===$booking['reference'] && $replacement['scope']==='banner_home'
        && $replacement['immediate_start_at']===$booking['immediate_start_at'] && $replacement['starts_at']>$booking['ends_at'],
        'The replacement did not preserve the original invoice, booking scope and consent.');
    banner_rejected(static fn()=>banner_ads_resolve_capacity($pdo,$admin,(int)$booking['id'],$reason,$future,true));
    $review($blocked,'disabled');
    $pdo->prepare('UPDATE market_premium_bookings SET ends_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id=?')->execute([$booking['id']]);
    banner_check(!banner_ads_public_campaign($pdo,$campaignId,'DE'),'An expired banner served without waiting for the worker.');
    business_worker_once($pdo);
    banner_check(banner_fixture_booking($pdo,$invoiceId)['status']==='expired','Worker did not record banner expiry.');
    echo "Operator independence, campaign review, idempotent quote, verified payment, start authorization and expiration checks passed.\n";
} finally {
    $pdo->prepare('UPDATE market_countries SET is_active=? WHERE code=\'DE\'')->execute([$country]);
    if ($rate===false) $pdo->exec("DELETE FROM market_banner_rates WHERE placement='country' AND term_days=7");
    else $pdo->prepare("UPDATE market_banner_rates SET gross_cents=?,updated_by=?,updated_at=? WHERE placement='country' AND term_days=7")
        ->execute([$rate['gross_cents'],$rate['updated_by'],$rate['updated_at']]);
    foreach ($actors as $actor) {
        $pdo->prepare("UPDATE market_premium_bookings SET status='cancelled' WHERE user_id=? AND scope IN ('banner_country','banner_home','banner_search')")->execute([$actor]);
        $pdo->prepare("UPDATE market_campaigns SET status='disabled' WHERE user_id=?")->execute([$actor]);
    }
    $delete=$pdo->prepare('DELETE FROM system_settings WHERE setting_key=?');
    foreach ($previous as $key=>$value) { if ($value===false) $delete->execute([$key]); else $write->execute([$key,$value]); }
    site_settings($pdo,true);
}
