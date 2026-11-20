<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('DB_DATABASE')!=='gunindex_rebuild_test'){if(PHP_SAPI!=='cli')http_response_code(404);exit(2);}
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/business.php';

// Separate processes prove capacity protection without relying on PHP session serialization.
if(($argv[1]??'')==='--race'){
    try{
        $user=(int)($argv[2]??0);$listing=(int)($argv[3]??0);$key=$argv[4]??'';$start=(int)($argv[5]??0);
        $q=$pdo->prepare("SELECT id FROM users WHERE id=? AND username REGEXP '^business_test_[a-f0-9]{16}$' AND full_name='Synthetic business fixture'");$q->execute([$user]);if(!$q->fetchColumn()||!preg_match('/\A[a-f0-9]{64}\z/',$key)||$start<(int)(microtime(true)*1000)-5000||$start>(int)(microtime(true)*1000)+5000)exit(2);
        while((int)(microtime(true)*1000)<$start)usleep(2000);
        for($attempt=0;$attempt<3;$attempt++){
            try{$invoice=manual_order($pdo,$user,$listing,'home_top',$key);echo json_encode(['ok'=>true,'invoice'=>$invoice]);exit;}
            catch(PDOException $error){if($error->getCode()!=='40001'||$attempt===2)throw$error;usleep(20000);}
        }
    }catch(DomainException|PaymentConflict $error){echo json_encode(['ok'=>false,'kind'=>'capacity']);exit;}
    catch(Throwable){fwrite(STDERR,"Synthetic race operation failed.\n");exit(2);}
}

function biz_assert(bool $condition,string $label='Assertion failed'):void{if(!$condition)throw new RuntimeException($label);}
function biz_reject(callable $operation):void{try{$operation();}catch(InvalidArgumentException|DomainException|PaymentConflict|PaymentUnavailable|MarketAccessException|MarketConflictException){return;}throw new RuntimeException('Expected rejection.');}
function biz_user(PDO $pdo,bool $admin=false,string $country='DE'):int{
    $name='business_test_'.bin2hex(random_bytes(8));$q=$pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,is_admin,country_code) VALUES (?,?,?,'Synthetic business fixture',?,'Testort',?,?)");$q->execute([$name,$name.'@example.invalid',auth_hash('Synthetic '.bin2hex(random_bytes(16))),$country==='AT'?'1010':'10115',(int)$admin,$country]);return(int)$pdo->lastInsertId();
}
function biz_category(PDO $pdo):int{$q=$pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)');$q->execute(['Business test '.bin2hex(random_bytes(4)),'business-test-'.bin2hex(random_bytes(8))]);return(int)$pdo->lastInsertId();}
function biz_listing(PDO $pdo,int $user,int $category,string $country='DE'):int{return market_save_listing($pdo,$user,['title'=>'Business fixture '.bin2hex(random_bytes(8)),'description'=>'Ordinary synthetic outdoor equipment for isolated business regression tests.','category_id'=>(string)$category,'type'=>'fixed','price'=>'45.00','status'=>'active','zip'=>$country==='AT'?'1010':'10115','city'=>'Testort','country_code'=>$country,'condition_label'=>'used','pickup'=>'1','shipping'=>'1','duration_days'=>'7','accept_rules'=>'1']);}
function biz_order(PDO $pdo,int $user,int $category,string $scope='category_top',string $country='DE',?int $campaign=null):array{$listing=biz_listing($pdo,$user,$category,$country);$invoice=manual_order($pdo,$user,$listing,$scope,bin2hex(random_bytes(32)),$campaign);$q=$pdo->prepare('SELECT b.*,o.reference,i.gross_cents FROM market_premium_bookings b JOIN market_manual_orders o ON o.invoice_id=b.invoice_id JOIN market_invoices i ON i.id=b.invoice_id WHERE b.invoice_id=?');$q->execute([$invoice]);return$q->fetch();}
function biz_pay(PDO $pdo,int $admin,array $booking,?string $transaction=null):string{$transaction??='BIZTEST'.strtoupper(bin2hex(random_bytes(8)));manual_review($pdo,$admin,(int)$booking['invoice_id'],$transaction,$booking['reference'],(int)$booking['gross_cents'],'approve','Synthetic direct account verification');return$transaction;}
function biz_release(PDO $pdo):void{$pdo->exec("UPDATE market_premium_bookings b JOIN users u ON u.id=b.user_id SET b.status='cancelled' WHERE u.username REGEXP '^business_test_[a-f0-9]{16}$' AND u.full_name='Synthetic business fixture' AND b.status IN ('reserved','awaiting_start','active')");}

$configured=['manual_paypal_enabled'=>'1','manual_paypal_approved'=>'1','manual_paypal_recipient'=>'merchant@example.invalid','manual_tax_reviewed'=>'1','manual_tax_bps'=>'1900','business_promotions_enabled'=>'1','dealer_profiles_enabled'=>'1','business_reservation_minutes'=>'1440','finance_enabled'=>'0'];
$saved=[];$q=$pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key=?');foreach($configured as $key=>$value){$q->execute([$key]);$saved[$key]=$q->fetchColumn();}
$countryStates=$pdo->query("SELECT code,is_active FROM market_countries WHERE code IN ('DE','AT')")->fetchAll(PDO::FETCH_KEY_PAIR);
$write=$pdo->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');foreach($configured as $key=>$value)$write->execute([$key,$value]);site_settings($pdo,true);$pdo->exec("UPDATE market_countries SET is_active=1 WHERE code IN ('DE','AT')");
$admin=biz_user($pdo,true);$passed=0;
$tests=[
 'calendar_month_clamps_last_day'=>static function():void{biz_assert(business_end_time('2024-01-31 15:20:00','banner_monthly')==='2024-02-29 15:20:00');biz_assert(business_end_time('2025-01-31 15:20:00','banner_monthly')==='2025-02-28 15:20:00');biz_assert(business_end_time('2026-12-31 15:20:00','banner_monthly')==='2027-01-31 15:20:00');biz_assert(business_end_time('2026-03-01 12:00:00','home_top')==='2026-03-08 12:00:00');},
 'redirect_validation_and_csv_formulas'=>static function():void{biz_assert(business_validate_url('https://shop.example.org/outdoor?item=1')==='https://shop.example.org/outdoor?item=1');foreach(['javascript:alert(1)','https://user:pass@example.org','http://127.0.0.1/a','http://localhost/a','https://shop.example.org:444/a',"https://shop.example.org/\r\nHeader:x",'https://shop.example.invalid'] as $url)biz_reject(static fn()=>business_validate_url($url));biz_assert(business_csv_cell('=HYPERLINK("bad")')[0]==="'");biz_assert(business_csv_cell('ordinary')==='ordinary');},
 'category_capacity_three_and_no_fourth_invoice'=>static function()use($pdo):void{$category=biz_category($pdo);for($i=0;$i<3;$i++)biz_order($pdo,biz_user($pdo),$category);$user=biz_user($pdo);$listing=biz_listing($pdo,$user,$category);biz_reject(static fn()=>manual_order($pdo,$user,$listing,'category_top',bin2hex(random_bytes(32))));$q=$pdo->prepare('SELECT COUNT(*) FROM market_invoices WHERE user_id=?');$q->execute([$user]);biz_assert((int)$q->fetchColumn()===0);biz_assert(business_availability($pdo,'DE','category_top',$category)['available']===0);},
 'home_capacity_is_separate_per_country'=>static function()use($pdo):void{$category=biz_category($pdo);for($i=0;$i<2;$i++)biz_order($pdo,biz_user($pdo),$category,'home_top');biz_assert(business_availability($pdo,'DE','home_top',0)['available']===0);biz_assert(business_availability($pdo,'AT','home_top',0)['available']===2);biz_order($pdo,biz_user($pdo,false,'AT'),$category,'home_top','AT');biz_assert(business_availability($pdo,'AT','home_top',0)['available']===1);},
 'paid_placement_starts_explicitly_and_never_grants_bump_credits'=>static function()use($pdo,$admin):void{$category=biz_category($pdo);$user=biz_user($pdo);$booking=biz_order($pdo,$user,$category);$transaction=biz_pay($pdo,$admin,$booking);biz_pay($pdo,$admin,$booking,$transaction);$q=$pdo->prepare('SELECT COUNT(*) FROM market_bump_credits WHERE invoice_id=?');$q->execute([$booking['invoice_id']]);biz_assert((int)$q->fetchColumn()===0);biz_reject(static fn()=>business_start_booking($pdo,$user,(int)$booking['id'],false));business_start_booking($pdo,$user,(int)$booking['id'],true);biz_assert(count(business_sponsored($pdo,'DE',$category,'category_top'))===1);biz_assert(count(business_sponsored($pdo,'AT',$category,'category_top'))===0);biz_reject(static fn()=>business_start_booking($pdo,biz_user($pdo),(int)$booking['id'],true));},
 'late_payment_is_review_not_overbooking'=>static function()use($pdo,$admin):void{$category=biz_category($pdo);$booking=biz_order($pdo,biz_user($pdo),$category,'home_top');$pdo->prepare('UPDATE market_premium_bookings SET reserved_until=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id=?')->execute([$booking['id']]);business_worker_once($pdo);biz_pay($pdo,$admin,$booking);$q=$pdo->prepare('SELECT status FROM market_premium_bookings WHERE id=?');$q->execute([$booking['id']]);biz_assert($q->fetchColumn()==='paid_unfulfilled');biz_assert(business_availability($pdo,'DE','home_top',0)['available']===2);},
 'active_expiry_and_refund_remove_public_entitlement'=>static function()use($pdo,$admin):void{$category=biz_category($pdo);$user=biz_user($pdo);$booking=biz_order($pdo,$user,$category);biz_pay($pdo,$admin,$booking);business_start_booking($pdo,$user,(int)$booking['id'],true);$pdo->prepare('UPDATE market_premium_bookings SET ends_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id=?')->execute([$booking['id']]);business_worker_once($pdo);biz_assert(business_sponsored($pdo,'DE',$category,'category_top')===[]);$another=biz_order($pdo,$user,$category);biz_pay($pdo,$admin,$another);business_start_booking($pdo,$user,(int)$another['id'],true);$pdo->beginTransaction();business_refund_invoice($pdo,(int)$another['invoice_id']);$pdo->commit();biz_assert(business_sponsored($pdo,'DE',$category,'category_top')===[]);},
 'long_maintenance_preserves_only_paid_placement_time_and_resumes_once'=>static function()use($pdo,$admin):void{
    $category=biz_category($pdo);
    $bookings=[];
    $invoices=[];
    foreach(['active','awaiting','settled_during_pause','unpaid','expired','cancelled'] as $kind){
        $booking=biz_order($pdo,biz_user($pdo),$kind==='active'?$category:biz_category($pdo));
        $bookings[$kind]=(int)$booking['id'];
        $invoices[$kind]=(int)$booking['invoice_id'];
        if($kind==='settled_during_pause')$pending=$booking;
        elseif($kind!=='unpaid')biz_pay($pdo,$admin,$booking);
        if(in_array($kind,['active','expired','cancelled'],true))business_start_booking($pdo,(int)$booking['user_id'],(int)$booking['id'],true);
    }
    biz_assert(count(business_sponsored($pdo,'DE',$category,'category_top'))===1,'The paid placement was not initially public.');
    $read=$pdo->prepare('SELECT status,starts_at,ends_at,reserved_until FROM market_premium_bookings WHERE id=?');
    $get=static function(string $kind)use($read,$bookings):array{$read->execute([$bookings[$kind]]);return $read->fetch();};
    $transition=static function(string $old,string $new)use($pdo,$admin):void{
        $pdo->beginTransaction();
        try{maintenance_transition($pdo,$admin,$old,$new);$pdo->commit();site_settings($pdo,true);}
        catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
    };
    $transition('0','1');
    try{
        $window=(int)$pdo->query('SELECT window_id FROM market_maintenance_state WHERE id=1')->fetchColumn();
        $pdo->prepare('UPDATE market_maintenance_windows SET started_at=UTC_TIMESTAMP(6)-INTERVAL 9 DAY WHERE id=?')->execute([$window]);
        $shift=$pdo->prepare('UPDATE market_premium_bookings SET starts_at=UTC_TIMESTAMP()-INTERVAL 10 DAY,ends_at=UTC_TIMESTAMP()-INTERVAL ? HOUR WHERE id=?');
        foreach(['active'=>215,'expired'=>217,'cancelled'=>215] as $kind=>$hours)$shift->execute([$hours,$bookings[$kind]]);
        $pdo->prepare('UPDATE market_premium_bookings SET reserved_until=UTC_TIMESTAMP()-INTERVAL 214 HOUR WHERE id IN (?,?)')->execute([$bookings['awaiting'],$bookings['unpaid']]);
        biz_pay($pdo,$admin,$pending);
        biz_assert($get('settled_during_pause')['status']==='awaiting_start','Verified settlement was blocked by maintenance.');
        $pdo->prepare('UPDATE market_premium_bookings SET reserved_until=UTC_TIMESTAMP()+INTERVAL 1 DAY WHERE id=?')->execute([$bookings['settled_during_pause']]);
        $pdo->beginTransaction();business_refund_invoice($pdo,$invoices['cancelled']);$pdo->commit();

        $before=[];foreach(array_keys($bookings) as $kind)$before[$kind]=$get($kind);
        biz_assert(business_worker_once($pdo)===['changed'=>0,'payment_reviews'=>0],'Direct expiry advanced while the marketplace was paused.');
        foreach($before as $kind=>$row)biz_assert($get($kind)===$row,'Worker changed paused booking '.$kind);
        $transition('1','0');

        $period=$pdo->prepare('SELECT ended_at,duration_seconds FROM market_maintenance_windows WHERE id=?');$period->execute([$window]);$period=$period->fetch();
        $seconds=(int)$period['duration_seconds'];
        biz_assert($seconds>=9*86400&&$seconds<=9*86400+30,'Long pause duration was not recorded.');
        $add=static fn(string $time,int $shift):string=>gmdate('Y-m-d H:i:s',strtotime($time.' UTC')+$shift);
        $active=$get('active');
        biz_assert($active['status']==='active'&&$active['starts_at']===$before['active']['starts_at']&&$active['ends_at']===$add($before['active']['ends_at'],$seconds),'Remaining paid advertising time was not restored.');
        biz_assert($get('awaiting')['reserved_until']===$add($before['awaiting']['reserved_until'],$seconds),'Remaining paid start deadline was not restored.');
        biz_assert($get('settled_during_pause')['reserved_until']===$add(substr($period['ended_at'],0,19),7*86400),'Settlement during maintenance did not receive the standard start deadline after reopening.');
        foreach(['expired','cancelled','unpaid'] as $kind)biz_assert($get($kind)===$before[$kind],'Ineligible booking was extended: '.$kind);
        $transition('0','0');
        biz_assert($get('active')===$active,'Retry extended the active placement again.');
        $audit=$pdo->prepare("SELECT details FROM admin_logs WHERE action_type='maintenance.ended' AND target_id=?");$audit->execute([$window]);$events=$audit->fetchAll(PDO::FETCH_COLUMN);
        biz_assert(count($events)===1,'Reopening was not audited exactly once.');
        $details=json_decode($events[0],true,32,JSON_THROW_ON_ERROR);
        biz_assert($details['duration_seconds']===$seconds&&$details['promotions_extended']>=1&&$details['paid_start_deadlines_extended']>=2,'The restoration audit omitted paid placements.');
        biz_assert(count(business_sponsored($pdo,'DE',$category,'category_top'))===1,'The paid placement did not become public again.');
        business_worker_once($pdo);
        biz_assert($get('unpaid')['status']==='expired'&&$get('expired')['status']==='expired'&&$get('active')===$active,'Expiry after reopening handled restored and elapsed bookings incorrectly.');
    }finally{
        if($pdo->inTransaction())$pdo->rollBack();
        $current=(string)$pdo->query('SELECT active FROM market_maintenance_state WHERE id=1')->fetchColumn();
        $transition($current,'0');
    }
 },
 'banner_moderation_country_and_metric_deduplication'=>static function()use($pdo,$admin):void{$category=biz_category($pdo);$user=biz_user($pdo);business_save_dealer($pdo,$user,['company_name'=>'Synthetic Business','address_text'=>'Teststraße 1, 10115 Testort','country_code'=>'DE','contact_email'=>'synthetic@example.invalid','website'=>'https://shop.example.org','legal_notice'=>'Synthetic company information for isolated tests only.']);$profile=business_dealer_profile($pdo,$user,false);biz_assert(business_dealer_profile($pdo,$user)===null);business_review_dealer($pdo,$admin,$user,'published','Synthetic content review',business_profile_revision($profile));$q=$pdo->prepare("INSERT INTO market_campaigns(user_id,country_code,category_id,title,target_url,storage_key,status) VALUES (?,'DE',?,'Synthetic outdoor banner','https://shop.example.org',?,'pending')");$q->execute([$user,$category,bin2hex(random_bytes(24))]);$campaign=(int)$pdo->lastInsertId();biz_assert(business_public_campaign($pdo,$campaign,'DE')===null);business_review_campaign($pdo,$admin,$campaign,'approved','Synthetic creative reviewed');$booking=biz_order($pdo,$user,$category,'banner_monthly','DE',$campaign);biz_pay($pdo,$admin,$booking);business_start_booking($pdo,$user,(int)$booking['id'],true);biz_assert(count(business_banners($pdo,'DE',$category))===1);biz_assert(business_banners($pdo,'AT',$category)===[]);business_record_metric($pdo,$campaign,'impression');business_record_metric($pdo,$campaign,'impression');business_record_metric($pdo,$campaign,'click');business_record_metric($pdo,$campaign,'click');$q=$pdo->prepare('SELECT impressions,clicks FROM market_ad_daily_stats WHERE campaign_id=?');$q->execute([$campaign]);$counts=$q->fetch();biz_assert((int)$counts['impressions']===1&&(int)$counts['clicks']===1);business_review_campaign($pdo,$admin,$campaign,'disabled','Synthetic temporary disable');biz_assert(business_banners($pdo,'DE',$category)===[]);},
 'bulk_changes_are_owner_checked_and_atomic'=>static function()use($pdo):void{$category=biz_category($pdo);$user=biz_user($pdo);$own=biz_listing($pdo,$user,$category);$other=biz_listing($pdo,biz_user($pdo),$category);biz_reject(static fn()=>business_bulk_transition($pdo,$user,[$own,$other],'pause'));$q=$pdo->prepare('SELECT status FROM market_listings WHERE id=?');$q->execute([$own]);biz_assert($q->fetchColumn()==='active');$another=biz_listing($pdo,$user,$category);biz_assert(business_bulk_transition($pdo,$user,[$own,$another],'pause')===2);biz_assert(business_bulk_transition($pdo,$user,[$own,$another],'archive')===2);biz_reject(static fn()=>business_bulk_transition($pdo,$user,[['invalid']],'pause'));},
 'concurrent_requests_for_last_home_slot_have_one_winner'=>static function()use($pdo):void{$category=biz_category($pdo);biz_order($pdo,biz_user($pdo),$category,'home_top');$actors=[];for($i=0;$i<2;$i++){$actor=biz_user($pdo);$actors[]=[$actor,biz_listing($pdo,$actor,$category)];}$children=[];$start=(int)(microtime(true)*1000)+1800;foreach($actors as [$user,$listing]){$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--race',(string)$user,(string)$listing,bin2hex(random_bytes(32)),(string)$start],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('Race process unavailable');fclose($pipes[0]);$children[]=[$process,$pipes];}$successes=0;foreach($children as [$process,$pipes]){$body=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);biz_assert($code===0,'Race subprocess failed');$result=json_decode($body,true,8,JSON_THROW_ON_ERROR);if($result['ok'])$successes++;else biz_assert($result['kind']==='capacity');}biz_assert($successes===1,'Exactly one concurrent order must reserve the last slot');biz_assert(business_availability($pdo,'DE','home_top',0)['occupied']===2);},
];
$failed=false;
try{foreach($tests as $name=>$test){biz_release($pdo);$test();$passed++;fwrite(STDOUT,'PASS '.$name.PHP_EOL);}fwrite(STDOUT,'Business checks passed: '.$passed.PHP_EOL);}
catch(Throwable $error){fwrite(STDERR,'FAIL business check '.($name??'initialization').' ('.get_class($error).')'.PHP_EOL);$failed=true;}
finally{if($pdo->inTransaction())$pdo->rollBack();biz_release($pdo);$restore=$pdo->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');foreach($saved as $key=>$value){if($value===false)$pdo->prepare('DELETE FROM system_settings WHERE setting_key=?')->execute([$key]);else $restore->execute([$key,$value]);}site_settings($pdo,true);foreach($countryStates as $code=>$active)$pdo->prepare('UPDATE market_countries SET is_active=? WHERE code=?')->execute([$active,$code]);}

if($failed)exit(1);
