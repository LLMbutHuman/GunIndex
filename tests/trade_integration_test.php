<?php
declare(strict_types=1);
/** Sequential isolated MySQL regressions. Child processes only race one synthetic listing. */
if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY === 'Windows' || getenv('DB_DATABASE') !== 'gunindex_rebuild_test') {
    if (PHP_SAPI !== 'cli') http_response_code(404);
    exit(2);
}
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/trade.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'gunindex_rebuild_test') exit(2);

function tri_check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function tri_rows(PDO $pdo, string $sql, array $parameters=[]): array {
    $query=$pdo->prepare($sql); $query->execute($parameters); return $query->fetchAll();
}
function tri_scalar(PDO $pdo, string $sql, array $parameters=[]): mixed {
    $query=$pdo->prepare($sql); $query->execute($parameters); return $query->fetchColumn();
}
function tri_reject(callable $run): void {
    try { $run(); } catch (DomainException|InvalidArgumentException|OutOfBoundsException $error) { return; }
    throw new RuntimeException('Invalid trade operation was accepted.');
}
function tri_user(PDO $pdo, bool $admin=false): int {
    $name='trade_'.bin2hex(random_bytes(10));
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,is_admin,country_code,preferred_locale) VALUES (?,?,?,'Synthetic trade integration fixture','10115','Synthetic town',?,'DE','de')")
        ->execute([$name,$name.'@example.invalid',auth_hash('Synthetic trade '.bin2hex(random_bytes(16))),(int)$admin]);
    return (int)$pdo->lastInsertId();
}
function tri_listing(PDO $pdo, int $seller, int $category, string $type='fixed', ?string $ends=null): int {
    $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,shipping_cents,status,zip,city,country_code,pickup,shipping,ends_at) VALUES (?,?,?,'Ordinary synthetic desk for isolated trade integration checks.',?,4500,300,'active','10115','Synthetic town','DE',1,1,?)")
        ->execute([$seller,$category,'Synthetic trade desk '.bin2hex(random_bytes(8)),$type,$ends]);
    return (int)$pdo->lastInsertId();
}
function tri_legal(array $context): array {
    return ['legal_confirm'=>'1','legal_language'=>$context['locale'],
        'legal_documents'=>array_column($context['documents'],'content_hash','id')];
}
function tri_buy_input(PDO $pdo, int $buyer, int $listing, string $delivery='shipping', ?int $offer=null): array {
    $quote=trade_quote($pdo,$buyer,$listing,$delivery,$offer);
    return ['delivery'=>$delivery,'quote_hash'=>$quote['hash'],'request_key'=>bin2hex(random_bytes(16)),
        'confirm_purchase'=>'1']+tri_legal($quote);
}
function tri_offer_input(PDO $pdo, int $actor, int $listing, string $amount='39.95', ?int $parent=null): array {
    $context=trade_offer_context($pdo,$actor,$listing);
    $input=['amount'=>$amount,'delivery'=>'shipping','hours'=>'24','request_key'=>bin2hex(random_bytes(16)),
        'listing_hash'=>$context['hash'],'confirm_offer'=>'1']+tri_legal($context);
    if ($parent!==null) $input['parent_id']=(string)$parent;
    return $input;
}
function tri_transition(PDO $pdo, int $actor, int $id, string $action, string $details='Synthetic external arrangement only.'): array {
    $trade=trade_get($pdo,$actor,$id);
    $input=['version'=>(string)$trade['version'],'request_key'=>bin2hex(random_bytes(16)),
        'confirm_action'=>'1','details'=>$details];
    trade_transition($pdo,$actor,$id,$action,$input); return $input;
}
function tri_setting(PDO $pdo, string $key, string $value): void {
    $pdo->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,$value]);
    site_settings($pdo,true);
}
function tri_parallel(array $requests): array {
    $children=[]; $results=[]; $start=(int)(microtime(true)*1000)+1200;
    try {
        foreach ($requests as $request) {
            $process=proc_open([PHP_BINARY,__FILE__,'--race'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            tri_check(is_resource($process),'Concurrent process unavailable.'); $children[]=[$process,$pipes];
            fwrite($pipes[0],json_encode($request+['start'=>$start],JSON_THROW_ON_ERROR)); fclose($pipes[0]);
        }
        foreach ($children as $index=>[$process,$pipes]) {
            $body=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($process); unset($children[$index]);
            tri_check($exit===0 && $errors==='','Concurrent child failed.');
            $results[]=json_decode($body,true,12,JSON_THROW_ON_ERROR);
        }
    } finally {
        foreach ($children as [$process,$pipes]) {
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        }
    }
    return $results;
}

if (($argv[1]??'')==='--race') {
    try {
        $request=json_decode(stream_get_contents(STDIN,16001),true,12,JSON_THROW_ON_ERROR);
        tri_check(is_array($request) && is_int($request['buyer']??null) && is_int($request['listing']??null)
            && is_int($request['start']??null) && is_array($request['input']??null),'Invalid race input.');
        tri_check((int)tri_scalar($pdo,"SELECT COUNT(*) FROM users WHERE id=? AND full_name='Synthetic trade integration fixture' AND email LIKE '%@example.invalid'",[$request['buyer']])===1,'Child requires synthetic buyer.');
        tri_check((int)tri_scalar($pdo,"SELECT COUNT(*) FROM market_listings l JOIN users u ON u.id=l.seller_id WHERE l.id=? AND u.full_name='Synthetic trade integration fixture' AND u.email LIKE '%@example.invalid'",[$request['listing']])===1,'Child requires synthetic item.');
        $_SESSION['locale']='de'; $pdo->exec('SET SESSION innodb_lock_wait_timeout=5');
        $delay=$request['start']-(int)(microtime(true)*1000); tri_check($delay>=-5000 && $delay<=10000,'Race outside allowed time window.');
        if ($delay>0) usleep($delay*1000);
        try { $id=trade_purchase($pdo,$request['buyer'],$request['listing'],$request['input']); $out=['id'=>$id,'rejected'=>false]; }
        catch (DomainException|InvalidArgumentException $error) { $out=['id'=>null,'rejected'=>true]; }
        echo json_encode($out,JSON_THROW_ON_ERROR); exit(0);
    } catch (Throwable $error) {
        fwrite(STDERR,'Synthetic trade race failed: '.get_class($error).' at '.basename($error->getFile()).':'.$error->getLine().PHP_EOL); exit(1);
    }
}

$saved=[];
foreach (['legal_checkout_documents','legal_publication_enabled'] as $key)
    $saved[$key]=tri_rows($pdo,'SELECT setting_value,updated_at FROM system_settings WHERE setting_key=?',[$key])[0]??null;
$hadLocale=array_key_exists('locale',$_SESSION??[]); $originalLocale=$_SESSION['locale']??null;
$failure=null; $name='fixture_setup'; $passed=0;
try {
    $_SESSION['locale']='de'; tri_check(geo_country_enabled($pdo,'DE'),'DE fixture country inactive.');
    tri_setting($pdo,'legal_checkout_documents','');
    $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')->execute(['Synthetic trade desks','trade-'.bin2hex(random_bytes(16))]);
    $category=(int)$pdo->lastInsertId(); $seller=tri_user($pdo); $buyer=tri_user($pdo); $outsider=tri_user($pdo);
    $tests=[
        'purchase_has_exact_totals_one_item_and_immutable_encrypted_snapshot'=>static function() use($pdo,$seller,$buyer,$category):void {
            $listing=tri_listing($pdo,$seller,$category); $input=tri_buy_input($pdo,$buyer,$listing);
            $id=trade_purchase($pdo,$buyer,$listing,$input); $trade=trade_get($pdo,$buyer,$id);
            tri_check($trade['status']==='agreed' && (int)$trade['price_cents']===4500 && (int)$trade['shipping_cents']===300 && (int)$trade['total_cents']===4800,'Purchase totals or state incorrect.');
            tri_check((int)$trade['seller_id']===$seller && (int)$trade['buyer_id']===$buyer,'Wrong trade parties.');
            tri_check(tri_scalar($pdo,'SELECT status FROM market_listings WHERE id=?',[$listing])==='sold','Purchased listing stayed available.');
            $snapshot=$trade['snapshot']; tri_check(is_array($snapshot) && isset($snapshot['title']),'Snapshot unavailable.');
            $cipher=tri_scalar($pdo,'SELECT snapshot_cipher FROM market_trades WHERE id=?',[$id]);
            tri_check(is_string($cipher) && !str_contains($cipher,$snapshot['title']),'Snapshot stored in plaintext.');
            $pdo->prepare("UPDATE market_listings SET title='Changed synthetic title',price_cents=9900,shipping_cents=700 WHERE id=?")->execute([$listing]);
            tri_check(trade_get($pdo,$seller,$id)['snapshot']===$snapshot,'Historical purchase snapshot changed.');
            tri_check(!trade_listing_available($pdo,$listing),'Sold inventory is available.');
            tri_check((int)tri_scalar($pdo,'SELECT COUNT(*) FROM market_invoices WHERE user_id IN (?,?)',[$seller,$buyer])===0,'Goods trade unexpectedly created a platform invoice.');
        },
        'purchase_retries_return_one_trade_and_reused_keys_cannot_change_context'=>static function() use($pdo,$seller,$buyer,$category):void {
            $listing=tri_listing($pdo,$seller,$category); $input=tri_buy_input($pdo,$buyer,$listing);
            $id=trade_purchase($pdo,$buyer,$listing,$input);
            tri_check(trade_purchase($pdo,$buyer,$listing,$input)===$id,'Purchase retry created a second result.');
            tri_check((int)tri_scalar($pdo,'SELECT COUNT(*) FROM market_trades WHERE listing_id=?',[$listing])===1,'Duplicate transaction.');
            $other=tri_listing($pdo,$seller,$category); $second=tri_buy_input($pdo,$buyer,$other); $second['request_key']=$input['request_key'];
            tri_reject(fn()=>trade_purchase($pdo,$buyer,$other,$second));
        },
        'two_buyers_racing_can_only_purchase_the_single_item_once'=>static function() use($pdo,$seller,$buyer,$outsider,$category):void {
            $listing=tri_listing($pdo,$seller,$category);
            $results=tri_parallel([
                ['buyer'=>$buyer,'listing'=>$listing,'input'=>tri_buy_input($pdo,$buyer,$listing)],
                ['buyer'=>$outsider,'listing'=>$listing,'input'=>tri_buy_input($pdo,$outsider,$listing)],
            ]);
            tri_check(count(array_filter($results,static fn(array $result):bool=>!$result['rejected']))===1,'Race did not produce exactly one purchase.');
            tri_check((int)tri_scalar($pdo,'SELECT COUNT(*) FROM market_trades WHERE listing_id=?',[$listing])===1,'Concurrent double sale.');
        },
        'simultaneous_retry_is_idempotent'=>static function() use($pdo,$seller,$buyer,$category):void {
            $listing=tri_listing($pdo,$seller,$category); $request=['buyer'=>$buyer,'listing'=>$listing,'input'=>tri_buy_input($pdo,$buyer,$listing)];
            $results=tri_parallel([$request,$request]);
            tri_check(!$results[0]['rejected'] && !$results[1]['rejected'] && $results[0]['id']===$results[1]['id'],'Concurrent retry lost idempotence.');
        },
        'own_purchase_forged_quote_stale_price_and_missing_confirmation_fail'=>static function() use($pdo,$seller,$buyer,$category):void {
            $listing=tri_listing($pdo,$seller,$category); tri_reject(fn()=>trade_quote($pdo,$seller,$listing,'pickup'));
            $input=tri_buy_input($pdo,$buyer,$listing); $forged=$input; $forged['quote_hash']=str_repeat('0',64);
            tri_reject(fn()=>trade_purchase($pdo,$buyer,$listing,$forged));
            $missing=$input; unset($missing['confirm_purchase']); tri_reject(fn()=>trade_purchase($pdo,$buyer,$listing,$missing));
            $pdo->prepare('UPDATE market_listings SET price_cents=4501 WHERE id=?')->execute([$listing]);
            tri_reject(fn()=>trade_purchase($pdo,$buyer,$listing,$input));
            tri_check((int)tri_scalar($pdo,'SELECT COUNT(*) FROM market_trades WHERE listing_id=?',[$listing])===0,'Rejected purchase left a transaction.');
        },
        'delivery_unavailable_expired_and_suspended_items_cannot_be_purchased'=>static function() use($pdo,$seller,$buyer,$category):void {
            $listing=tri_listing($pdo,$seller,$category); $pdo->prepare('UPDATE market_listings SET shipping=0 WHERE id=?')->execute([$listing]);
            tri_reject(fn()=>trade_quote($pdo,$buyer,$listing,'shipping')); tri_reject(fn()=>trade_quote($pdo,$buyer,$listing,'forged'));
            $expired=tri_listing($pdo,$seller,$category,'fixed','2000-01-01 00:00:00'); tri_reject(fn()=>trade_quote($pdo,$buyer,$expired,'pickup'));
            $input=tri_buy_input($pdo,$buyer,$listing,'pickup'); $pdo->prepare("UPDATE market_listings SET status='paused' WHERE id=?")->execute([$listing]);
            tri_reject(fn()=>trade_purchase($pdo,$buyer,$listing,$input));
        },
        'negotiation_counter_and_accept_preserve_exact_agreed_price'=>static function() use($pdo,$seller,$buyer,$outsider,$category):void {
            $listing=tri_listing($pdo,$seller,$category,'negotiated');
            tri_reject(fn()=>trade_quote($pdo,$buyer,$listing,'shipping'));
            $input=tri_offer_input($pdo,$buyer,$listing); $offer=trade_offer_create($pdo,$buyer,$listing,$input);
            tri_check(trade_offer_create($pdo,$buyer,$listing,$input)===$offer,'Offer retry duplicated.');
            tri_reject(fn()=>trade_offer_create($pdo,$outsider,$listing,tri_offer_input($pdo,$outsider,$listing,'38.00',$offer)));
            tri_reject(fn()=>trade_quote($pdo,$buyer,$listing,'shipping',$offer));
            $counterInput=tri_offer_input($pdo,$seller,$listing,'42.00',$offer);
            $counter=trade_offer_create($pdo,$seller,$listing,$counterInput);
            tri_check(tri_scalar($pdo,'SELECT status FROM market_trade_offers WHERE id=?',[$offer])==='countered','Original offer remained open.');
            tri_reject(fn()=>trade_quote($pdo,$seller,$listing,'shipping',$counter));
            $id=trade_purchase($pdo,$buyer,$listing,tri_buy_input($pdo,$buyer,$listing,'shipping',$counter),$counter);
            $trade=trade_get($pdo,$buyer,$id);
            tri_check((int)$trade['price_cents']===4200 && (int)$trade['shipping_cents']===300 && (int)$trade['total_cents']===4500 && (int)$trade['offer_id']===$counter,'Counter acceptance ignored agreed amounts.');
            tri_check(tri_scalar($pdo,'SELECT status FROM market_trade_offers WHERE id=?',[$counter])==='accepted','Accepted counter not recorded.');
        },
        'offer_privacy_reject_withdraw_expiry_and_stale_content'=>static function() use($pdo,$seller,$buyer,$outsider,$category):void {
            $listing=tri_listing($pdo,$seller,$category,'negotiated');
            $offer=trade_offer_create($pdo,$buyer,$listing,tri_offer_input($pdo,$buyer,$listing));
            tri_check(trade_offer_context($pdo,$outsider,$listing)['offers']===[],'Other buyer saw private negotiations.');
            tri_reject(fn()=>trade_offer_action($pdo,$outsider,$offer,'reject'));
            tri_reject(fn()=>trade_offer_action($pdo,$buyer,$offer,'reject'));
            trade_offer_action($pdo,$seller,$offer,'reject'); trade_offer_action($pdo,$seller,$offer,'reject');
            tri_check(tri_scalar($pdo,'SELECT status FROM market_trade_offers WHERE id=?',[$offer])==='rejected','Rejection not recorded.');
            $withdraw=trade_offer_create($pdo,$buyer,$listing,tri_offer_input($pdo,$buyer,$listing));
            tri_reject(fn()=>trade_offer_action($pdo,$seller,$withdraw,'withdraw')); trade_offer_action($pdo,$buyer,$withdraw,'withdraw');
            trade_offer_action($pdo,$buyer,$withdraw,'withdraw');
            $expiry=trade_offer_create($pdo,$buyer,$listing,tri_offer_input($pdo,$buyer,$listing));
            $pdo->prepare("UPDATE market_trade_offers SET expires_at='2000-01-01 00:00:00' WHERE id=?")->execute([$expiry]);
            tri_reject(fn()=>trade_quote($pdo,$seller,$listing,'shipping',$expiry));
            trade_expire($pdo,100);
            tri_check(tri_scalar($pdo,'SELECT status FROM market_trade_offers WHERE id=?',[$expiry])==='expired','Expiry worker did not close offer.');
            $stale=trade_offer_create($pdo,$buyer,$listing,tri_offer_input($pdo,$buyer,$listing));
            $pdo->prepare("UPDATE market_listings SET description='Changed synthetic desk description.' WHERE id=?")->execute([$listing]);
            tri_reject(fn()=>trade_quote($pdo,$seller,$listing,'shipping',$stale));
        },
        'dispatch_receipt_settlement_roles_version_and_event_idempotence'=>static function() use($pdo,$seller,$buyer,$outsider,$category):void {
            $listing=tri_listing($pdo,$seller,$category); $id=trade_purchase($pdo,$buyer,$listing,tri_buy_input($pdo,$buyer,$listing));
            tri_reject(fn()=>trade_get($pdo,$outsider,$id)); tri_reject(fn()=>tri_transition($pdo,$buyer,$id,'dispatch'));
            tri_reject(fn()=>tri_transition($pdo,$seller,$id,'receive'));
            $input=tri_transition($pdo,$seller,$id,'dispatch','Synthetic shipping reference, no real carrier.');
            trade_transition($pdo,$seller,$id,'dispatch',$input);
            tri_check((int)tri_scalar($pdo,"SELECT COUNT(*) FROM market_trade_events WHERE trade_id=? AND action='dispatch'",[$id])===1,'Retry duplicated dispatch event.');
            $stale=$input; $stale['request_key']=bin2hex(random_bytes(16)); tri_reject(fn()=>trade_transition($pdo,$seller,$id,'settle',$stale));
            tri_transition($pdo,$buyer,$id,'receive');
            tri_check(trade_get($pdo,$buyer,$id)['status']!=='completed','Receipt alone certified external settlement.');
            tri_reject(fn()=>trade_rate($pdo,$buyer,$id,5,'Premature synthetic review.'));
            tri_transition($pdo,$seller,$id,'settle'); tri_check(trade_get($pdo,$buyer,$id)['status']==='completed','Both confirmations did not complete the trade.');
            tri_reject(fn()=>tri_transition($pdo,$seller,$id,'dispatch'));
        },
        'reviews_are_bound_to_completed_trade_and_actual_parties'=>static function() use($pdo,$seller,$buyer,$outsider,$category):void {
            $listing=tri_listing($pdo,$seller,$category); $id=trade_purchase($pdo,$buyer,$listing,tri_buy_input($pdo,$buyer,$listing));
            tri_transition($pdo,$seller,$id,'dispatch'); tri_transition($pdo,$seller,$id,'settle'); tri_transition($pdo,$buyer,$id,'receive');
            tri_reject(fn()=>trade_rate($pdo,$outsider,$id,5,'Unrelated synthetic reviewer.'));
            foreach ([0,6] as $stars) tri_reject(fn()=>trade_rate($pdo,$buyer,$id,$stars,'Invalid synthetic stars.'));
            tri_reject(fn()=>trade_rate($pdo,$buyer,$id,5,str_repeat('x',1001)));
            trade_rate($pdo,$buyer,$id,5,'Synthetic buyer review.'); trade_rate($pdo,$seller,$id,4,'Synthetic seller review.');
            tri_reject(fn()=>trade_rate($pdo,$buyer,$id,1,'Duplicate synthetic review.'));
            $ratings=tri_rows($pdo,'SELECT sender_id,receiver_id,trade_id,listing_id FROM market_ratings WHERE trade_id=? ORDER BY sender_id',[$id]);
            tri_check(count($ratings)===2,'Missing trade reviews.');
            foreach ($ratings as $rating) tri_check((int)$rating['listing_id']===$listing && (int)$rating['trade_id']===$id
                && (int)$rating['receiver_id']===((int)$rating['sender_id']===$buyer?$seller:$buyer),'Review bound to wrong counterparty.');
        },
        'mutual_cancellation_preserves_history_and_never_resurrects_inventory'=>static function() use($pdo,$seller,$buyer,$category):void {
            $listing=tri_listing($pdo,$seller,$category); $id=trade_purchase($pdo,$buyer,$listing,tri_buy_input($pdo,$buyer,$listing));
            tri_transition($pdo,$buyer,$id,'cancel_request'); tri_reject(fn()=>tri_transition($pdo,$buyer,$id,'cancel_accept'));
            tri_transition($pdo,$seller,$id,'cancel_decline'); tri_check(trade_get($pdo,$buyer,$id)['status']==='agreed','Declined cancellation lost prior state.');
            tri_transition($pdo,$seller,$id,'cancel_request'); tri_transition($pdo,$buyer,$id,'cancel_accept');
            tri_check(trade_get($pdo,$buyer,$id)['status']==='cancelled','Mutual cancellation did not finish.');
            tri_check(tri_scalar($pdo,'SELECT status FROM market_listings WHERE id=?',[$listing])!=='active','Cancellation silently restored inventory.');
            tri_reject(fn()=>trade_rate($pdo,$buyer,$id,5,'Cancelled synthetic review.'));
            tri_reject(fn()=>trade_quote($pdo,$buyer,$listing,'shipping'));
        },
        'dispute_freezes_fulfilment_and_cannot_be_self_resolved'=>static function() use($pdo,$seller,$buyer,$category):void {
            $listing=tri_listing($pdo,$seller,$category); $id=trade_purchase($pdo,$buyer,$listing,tri_buy_input($pdo,$buyer,$listing));
            tri_transition($pdo,$buyer,$id,'dispute','Synthetic fixture: delivery disputed.');
            tri_check(trade_get($pdo,$seller,$id)['status']==='disputed','Dispute not recorded.');
            tri_reject(fn()=>tri_transition($pdo,$seller,$id,'dispatch'));
            tri_reject(fn()=>tri_transition($pdo,$buyer,$id,'resolve_complete'));
            tri_reject(fn()=>tri_transition($pdo,$seller,$id,'resolve_cancel'));
            tri_reject(fn()=>trade_rate($pdo,$buyer,$id,5,'Disputed synthetic review.'));
        },
        'current_legal_version_is_required_and_exact_consent_is_retained'=>static function() use($pdo,$seller,$buyer,$category):void {
            $admin=tri_user($pdo,true); $code='trade-contract-'.bin2hex(random_bytes(8));
            $body=str_repeat('Synthetic trade terms, not real legal approval. ',8);
            $first=legal_create($pdo,$admin,$code,'1.0','de','Synthetic trade terms',$body);
            legal_approve($pdo,$admin,$first,'Synthetic fixture only; no legal approval',gmdate('Y-m-d'),array_fill(0,5,'1'));
            tri_setting($pdo,'legal_publication_enabled','1'); tri_setting($pdo,'legal_checkout_documents',$code);
            try {
                $listing=tri_listing($pdo,$seller,$category); $old=tri_buy_input($pdo,$buyer,$listing);
                $second=legal_create($pdo,$admin,$code,'2.0','de','Synthetic revised trade terms',$body.' Changed fixture.');
                legal_approve($pdo,$admin,$second,'Synthetic fixture only; no legal approval',gmdate('Y-m-d'),array_fill(0,5,'1'));
                tri_reject(fn()=>trade_purchase($pdo,$buyer,$listing,$old));
                $input=tri_buy_input($pdo,$buyer,$listing); $missing=$input; unset($missing['legal_confirm']);
                tri_reject(fn()=>trade_purchase($pdo,$buyer,$listing,$missing));
                $id=trade_purchase($pdo,$buyer,$listing,$input);
                tri_check((int)tri_scalar($pdo,'SELECT COUNT(*) FROM market_trade_consents WHERE trade_id=? AND user_id=? AND document_id=?',[$id,$buyer,$second])===1,'Accepted legal version receipt missing.');
                tri_check((int)tri_scalar($pdo,'SELECT COUNT(*) FROM market_trade_consents WHERE trade_id=? AND document_id=?',[$id,$first])===0,'Stale consent leaked into final trade.');
            } finally { tri_setting($pdo,'legal_checkout_documents',''); }
        },
    ];
    foreach ($tests as $name=>$run) { $run(); ++$passed; fwrite(STDOUT,'PASS '.$name.PHP_EOL); }
    tri_check($passed>0,'Trade cases missing.'); fwrite(STDOUT,'Trade integration checks passed: '.$passed.PHP_EOL);
} catch (Throwable $error) { $failure=$error; }
finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($saved as $key=>$row) {
        if ($row===null) $pdo->prepare('DELETE FROM system_settings WHERE setting_key=?')->execute([$key]);
        else $pdo->prepare('INSERT INTO system_settings(setting_key,setting_value,updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=VALUES(updated_at)')->execute([$key,$row['setting_value'],$row['updated_at']]);
    }
    site_settings($pdo,true);
    if ($hadLocale) $_SESSION['locale']=$originalLocale; else unset($_SESSION['locale']);
}
if ($failure!==null) {
    fwrite(STDERR,'FAIL '.$name.' ('.get_class($failure).' at '.basename($failure->getFile()).':'.$failure->getLine().')'.PHP_EOL); exit(1);
}
