<?php
declare(strict_types=1);
/** Sequential synthetic MySQL/HTTP suite. Never execute against a real data set. */
if (PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('DB_DATABASE')!=='gunindex_rebuild_test') {
    if (PHP_SAPI!=='cli') http_response_code(404); exit(2);
}
require_once __DIR__.'/../config/bootstrap.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='gunindex_rebuild_test') exit(2);

function lci_check(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
function lci_scalar(PDO $pdo,string $sql,array $parameters=[]): mixed {
    $q=$pdo->prepare($sql);$q->execute($parameters);return $q->fetchColumn();
}
function lci_row(PDO $pdo,string $sql,array $parameters=[]): array {
    $q=$pdo->prepare($sql);$q->execute($parameters);$row=$q->fetch();lci_check(is_array($row),'Expected a fixture row.');return $row;
}
function lci_reject(callable $run,string|array $classes=DomainException::class): void {
    try {$run();} catch (Throwable $error) {foreach ((array)$classes as $class) if ($error instanceof $class) return;throw $error;}
    throw new RuntimeException('Invalid lifecycle operation succeeded.');
}
function lci_user(PDO $pdo): array {
    $name='lifecycle_'.bin2hex(random_bytes(8));$password='Synthetic lifecycle '.bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code,preferred_locale) VALUES (?,?,?,'Synthetic lifecycle fixture','10115','Synthetic town','DE','de')")
        ->execute([$name,$name.'@example.invalid',auth_hash($password)]);
    return ['id'=>(int)$pdo->lastInsertId(),'name'=>$name,'password'=>$password];
}
function lci_listing(PDO $pdo,int $seller,int $category,array $changes=[]): int {
    $values=array_replace(['seller_id'=>$seller,'category_id'=>$category,'title'=>'Synthetic folding table '.bin2hex(random_bytes(6)),
        'description'=>'Ordinary table fixture. No real purchase, inventory or payment.','type'=>'fixed','price_cents'=>1450,
        'status'=>'active','ends_at'=>gmdate('Y-m-d H:i:s',time()+86400),'zip'=>'10115','city'=>'Synthetic town','country_code'=>'DE',
        'pickup'=>1,'shipping'=>1,'shipping_cents'=>450,'condition_label'=>'used','auction_fee_eligible'=>0],$changes);
    $pdo->prepare('INSERT INTO market_listings('.implode(',',array_keys($values)).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')')->execute(array_values($values));
    return (int)$pdo->lastInsertId();
}
function lci_trade(PDO $pdo,int $listing,int $seller,int $buyer,string $status): int {
    // Synthetic state fixture only; complete trade/consent transitions have a separate suite.
    $pdo->prepare("INSERT INTO market_trades(reference,listing_id,seller_id,buyer_id,delivery,price_cents,shipping_cents,total_cents,status,snapshot_cipher,request_actor,request_key,request_hash) VALUES (?,?,?,?,'pickup',1450,0,1450,?,?,?,?,?)")
        ->execute(['GT-'.strtoupper(bin2hex(random_bytes(10))),$listing,$seller,$buyer,$status,
            crypto_encrypt('trade_snapshot',trade_json(['fixture'=>'Synthetic lifecycle fixture'])),$buyer,bin2hex(random_bytes(16)),bin2hex(random_bytes(32))]);
    return (int)$pdo->lastInsertId();
}
function lci_money(PDO $pdo,int $seller): array {
    $out=[];
    foreach (['market_invoices'=>'user_id','market_manual_orders'=>'user_id','market_auction_fee_contracts'=>'seller_id'] as $table=>$column)
        $out[$table]=(int)lci_scalar($pdo,'SELECT COUNT(*) FROM '.$table.' WHERE '.$column.'=?',[$seller]);
    $out['market_auction_fee_charges']=(int)lci_scalar($pdo,'SELECT COUNT(*) FROM market_auction_fee_charges c JOIN market_auction_fee_contracts ac ON ac.listing_id=c.listing_id WHERE ac.seller_id=?',[$seller]);
    return $out;
}
function lci_image(PDO $pdo,int $listing): string {
    $key=bin2hex(random_bytes(24));$image=imagecreatetruecolor(8,6);imagefill($image,0,0,imagecolorallocate($image,80,120,160));
    try {foreach (['.webp','-thumb.webp'] as $suffix)market_write_webp($image,market_image_directory().'/'.$key.$suffix);}
    finally {imagedestroy($image);}
    $pdo->prepare('INSERT INTO market_images(listing_id,storage_key,position,width,height) VALUES (?,?,0,8,6)')->execute([$listing,$key]);return $key;
}
function lci_browser(array $user): CurlHandle {
    global $lciBrowsers;
    $browser=curl_init();curl_setopt_array($browser,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>5]);
    $lciBrowsers[]=$browser;$form=lci_http($browser,'/auth/login.php');
    $response=lci_http($browser,'/auth/login.php','POST',['csrf_token'=>lci_csrf($form),'login'=>$user['name'],'password'=>$user['password']]);
    lci_check($response['status']===303,'Synthetic login failed.');return $browser;
}
function lci_http(CurlHandle $browser,string $path,string $method='GET',?array $fields=null): array {
    lci_check(str_starts_with($path,'/')&&!str_starts_with($path,'//')&&!preg_match('/[\r\n]/',$path),'Loopback path required.');
    $headers=[];
    curl_setopt_array($browser,[CURLOPT_URL=>'http://127.0.0.1:8081'.$path,CURLOPT_NOBODY=>false,CURLOPT_HTTPGET=>true,CURLOPT_CUSTOMREQUEST=>$method,
        CURLOPT_HTTPHEADER=>[],CURLOPT_HEADERFUNCTION=>static function(CurlHandle $curl,string $line)use(&$headers):int {
            if(str_contains($line,':')){[$name,$value]=explode(':',$line,2);$headers[strtolower(trim($name))]=trim($value);}return strlen($line);
        }]);
    if($fields!==null)curl_setopt($browser,CURLOPT_POSTFIELDS,http_build_query($fields));
    if($method==='HEAD')curl_setopt($browser,CURLOPT_NOBODY,true);
    $body=curl_exec($browser);lci_check(is_string($body),'Loopback request failed.');
    return ['status'=>(int)curl_getinfo($browser,CURLINFO_RESPONSE_CODE),'body'=>$body,'headers'=>$headers];
}
function lci_csrf(array $response): string {
    lci_check(preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/',$response['body'],$match)===1,'CSRF token absent.');return $match[1];
}
function lci_parallel(array $requests): array {
    $children=[];$results=[];$start=(int)(microtime(true)*1000)+1000;
    try {
        foreach ($requests as $request) {
            $process=proc_open([PHP_BINARY,__FILE__,'--race'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            lci_check(is_resource($process),'Concurrent process unavailable.');$children[]=[$process,$pipes];
            fwrite($pipes[0],json_encode($request+['start'=>$start],JSON_THROW_ON_ERROR));fclose($pipes[0]);
        }
        foreach ($children as $index=>[$process,$pipes]) {
            $output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);unset($children[$index]);
            lci_check($exit===0&&$errors==='','Concurrent lifecycle child failed.');$results[]=json_decode($output,true,12,JSON_THROW_ON_ERROR);
        }
    }finally{foreach($children as [$process,$pipes]){foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);if(is_resource($process)){proc_terminate($process);proc_close($process);}}}
    return $results;
}
if(($argv[1]??'')==='--race'){
    try{
        $request=json_decode(stream_get_contents(STDIN,8001),true,12,JSON_THROW_ON_ERROR);
        lci_check(is_array($request)&&in_array($request['action']??null,['copy','extend'],true)&&is_int($request['seller']??null)&&is_int($request['listing']??null)&&is_int($request['start']??null),'Bad concurrent fixture request.');
        lci_check((int)lci_scalar($pdo,"SELECT COUNT(*) FROM market_listings l JOIN users u ON u.id=l.seller_id WHERE l.id=? AND u.id=? AND u.full_name='Synthetic lifecycle fixture' AND u.email LIKE '%@example.invalid'",[$request['listing'],$request['seller']])===1,'Concurrent request must own an isolated fixture.');
        $delay=$request['start']-(int)(microtime(true)*1000);lci_check($delay>=-5000&&$delay<=10000,'Concurrent start outside bounds.');if($delay>0)usleep($delay*1000);
        try{$result=$request['action']==='copy'?relisting_create_draft($pdo,$request['seller'],$request['listing']):relisting_extend($pdo,$request['seller'],$request['listing'],$request['days'],$request['hash']);$response=['ok'=>true,'result'=>$result];}
        catch(DomainException){$response=['ok'=>false];}
        fwrite(STDOUT,json_encode($response,JSON_THROW_ON_ERROR));exit(0);
    }catch(Throwable $error){fwrite(STDERR,'Lifecycle child failed: '.get_class($error).' at '.$error->getLine().PHP_EOL);exit(1);}
}

$lciBrowsers=[];$passed=0;$name='fixture_setup';$failure=null;
try{
    lci_check(geo_country_enabled($pdo,'DE'),'Active DE fixture country required.');
    $pdo->beginTransaction();try{lci_check((int)maintenance_state($pdo)['active']===0,'Inactive maintenance required.');}finally{$pdo->rollBack();}
    $seller=lci_user($pdo);$other=lci_user($pdo);
    $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')->execute(['Synthetic lifecycle tables','lifecycle-'.bin2hex(random_bytes(8))]);$category=(int)$pdo->lastInsertId();
    $tests=[
        'general_copies_preserve_source_shipping_and_independent_history'=>static function()use($pdo,$seller,$category):void{
            foreach(['fixed','negotiated'] as $type){
                $parent=lci_listing($pdo,$seller['id'],$category,['type'=>$type,'status'=>'ended','ends_at'=>gmdate('Y-m-d H:i:s',time()-60)]);
                $before=lci_row($pdo,'SELECT * FROM market_listings WHERE id=?',[$parent]);$money=lci_money($pdo,$seller['id']);
                $child=relisting_create_draft($pdo,$seller['id'],$parent);$copy=lci_row($pdo,'SELECT * FROM market_listings WHERE id=?',[$child]);
                lci_check($child>$parent&&relisting_create_draft($pdo,$seller['id'],$parent)===$child,'Copy retry did not reuse one new ID.');
                foreach(['seller_id','category_id','type','title','description','price_cents','shipping_cents','pickup','shipping','country_code','zip','city','condition_label'] as $field)
                    lci_check($before[$field]===$copy[$field],'Copy lost '.$field);
                lci_check($copy['status']==='draft'&&$copy['ends_at']===null&&$copy['highest_bidder_id']===null&&$copy['current_bid_cents']===null
                    &&(int)$copy['auction_fee_eligible']===1&&(int)$copy['trade_checkout_enabled']===0,'Copy inherited transaction state or bypasses fees if converted to an auction.');
                lci_check(relisting_context($pdo,$seller['id'],$child)===null,'General copy acquired auction entitlement.');
                foreach(['market_bids','market_proxy_bids','market_ratings','market_trades'] as $table)lci_check((int)lci_scalar($pdo,'SELECT COUNT(*) FROM '.$table.' WHERE listing_id=?',[$child])===0,'Copy inherited historical rows.');
                lci_check(lci_row($pdo,'SELECT * FROM market_listings WHERE id=?',[$parent])===$before&&lci_money($pdo,$seller['id'])===$money,'Copy mutated original/finances.');
            }
        },
        'sold_inventory_requires_explicit_confirmation_and_no_non_cancelled_trade'=>static function()use($pdo,$seller,$other,$category):void{
            $parent=lci_listing($pdo,$seller['id'],$category,['status'=>'sold']);
            lci_reject(fn()=>relisting_create_draft($pdo,$seller['id'],$parent));
            $child=relisting_create_draft($pdo,$seller['id'],$parent,true);
            lci_check((int)lci_scalar($pdo,'SELECT inventory_confirmed FROM market_listing_copies WHERE child_listing_id=?',[$child])===1,'Inventory confirmation not recorded.');
            foreach(['agreed','dispatched','completed','cancel_requested','disputed','cancelled'] as $status){
                $id=lci_listing($pdo,$seller['id'],$category,['status'=>'sold']);lci_trade($pdo,$id,$seller['id'],$other['id'],$status);
                if($status==='cancelled')lci_check(relisting_create_draft($pdo,$seller['id'],$id,true)>$id,'Cancelled source cannot be copied.');
                else lci_reject(fn()=>relisting_create_draft($pdo,$seller['id'],$id,true));
                lci_check(lci_scalar($pdo,'SELECT status FROM market_listings WHERE id=?',[$id])==='sold','Historical sale revived.');
            }
        },
        'unauthorized_unfinished_or_auction_history_sources_do_not_copy'=>static function()use($pdo,$seller,$other,$category):void{
            $parent=lci_listing($pdo,$seller['id'],$category,['status'=>'archived']);lci_reject(fn()=>relisting_create_draft($pdo,$other['id'],$parent));
            foreach(['draft','active','paused','review'] as $status){$id=lci_listing($pdo,$seller['id'],$category,['status'=>$status]);lci_reject(fn()=>relisting_create_draft($pdo,$seller['id'],$id));}
            $id=lci_listing($pdo,$seller['id'],$category,['status'=>'ended','ends_at'=>gmdate('Y-m-d H:i:s',time()-30)]);
            $pdo->prepare("INSERT INTO market_bids(listing_id,event_type,idempotency_key) VALUES (?,'close_no_bid',?)")->execute([$id,'close-'.$id]);
            lci_reject(fn()=>relisting_create_draft($pdo,$seller['id'],$id));
            lci_check((int)lci_scalar($pdo,'SELECT COUNT(*) FROM market_listing_copies WHERE parent_listing_id=?',[$id])===0,'Rejected source acquired a copy.');
        },
        'images_are_reencoded_and_corrupt_metadata_rolls_back'=>static function()use($pdo,$seller,$category):void{
            $parent=lci_listing($pdo,$seller['id'],$category,['status'=>'archived']);$key=lci_image($pdo,$parent);
            $path=market_image_directory().'/'.$key.'.webp';$before=hash_file('sha256',$path);$child=relisting_create_draft($pdo,$seller['id'],$parent);
            $copyKey=lci_scalar($pdo,'SELECT storage_key FROM market_images WHERE listing_id=?',[$child]);
            lci_check(is_string($copyKey)&&$copyKey!==$key&&hash_file('sha256',$path)===$before,'Images share keys or original changed.');
            foreach(['.webp','-thumb.webp'] as $suffix)lci_check(is_file(market_image_directory().'/'.$copyKey.$suffix),'Copied image missing.');
            $broken=lci_listing($pdo,$seller['id'],$category,['status'=>'archived']);$brokenKey=lci_image($pdo,$broken);
            $pdo->prepare('UPDATE market_images SET width=9 WHERE listing_id=?')->execute([$broken]);$count=(int)lci_scalar($pdo,'SELECT COUNT(*) FROM market_listings WHERE seller_id=?',[$seller['id']]);
            lci_reject(fn()=>relisting_create_draft($pdo,$seller['id'],$broken));
            lci_check((int)lci_scalar($pdo,'SELECT COUNT(*) FROM market_listings WHERE seller_id=?',[$seller['id']])===$count&&is_file(market_image_directory().'/'.$brokenKey.'.webp'),'Failed copy lost original or left a draft.');
        },
        'one_extension_changes_only_the_deadline_and_records_an_idempotent_free_event'=>static function()use($pdo,$seller,$category):void{
            foreach(['fixed','negotiated','auction'] as $type){
                $id=lci_listing($pdo,$seller['id'],$category,['type'=>$type]);$context=relisting_extension_context($pdo,$seller['id'],$id);$money=lci_money($pdo,$seller['id']);
                $deadline=relisting_extend($pdo,$seller['id'],$id,7,$context['hash']);
                lci_check($deadline===gmdate('Y-m-d H:i:s',strtotime($context['listing']['ends_at'].' UTC')+7*86400),'Extension used wrong origin.');
                lci_check(relisting_extend($pdo,$seller['id'],$id,7,$context['hash'])===$deadline,'Exact replay not idempotent.');
                $event=lci_row($pdo,'SELECT * FROM market_listing_extensions WHERE listing_id=?',[$id]);
                lci_check($event['original_ends_at']===$context['listing']['ends_at']&&$event['extended_ends_at']===$deadline&&(int)$event['fee_cents']===0,'Event lost old/new deadline or introduced a fee.');
                lci_check(lci_money($pdo,$seller['id'])===$money&&(int)lci_scalar($pdo,"SELECT COUNT(*) FROM admin_logs WHERE target_id=? AND action_type='listing.extended'",[$id])===1,'Replay duplicated financial/audit effects.');
                $after=lci_row($pdo,'SELECT * FROM market_listings WHERE id=?',[$id]);foreach($context['listing'] as $field=>$value)if(!in_array($field,['ends_at','updated_at'],true))lci_check($after[$field]===$value,'Extension changed '.$field);
                lci_reject(fn()=>relisting_extend($pdo,$seller['id'],$id,7,relisting_extension_hash($after)));
            }
        },
        'extension_rejects_stale_owner_state_bids_and_unlimited_deadlines'=>static function()use($pdo,$seller,$other,$category):void{
            $id=lci_listing($pdo,$seller['id'],$category);$context=relisting_extension_context($pdo,$seller['id'],$id);
            lci_reject(fn()=>relisting_extend($pdo,$other['id'],$id,7,$context['hash']));
            $pdo->prepare('UPDATE market_listings SET price_cents=price_cents+1 WHERE id=?')->execute([$id]);lci_reject(fn()=>relisting_extend($pdo,$seller['id'],$id,7,$context['hash']));
            foreach([['ends_at'=>null],['ends_at'=>gmdate('Y-m-d H:i:s',time()-10)],['status'=>'paused'],['status'=>'ended'],['status'=>'sold']] as $changes){
                $row=lci_listing($pdo,$seller['id'],$category,$changes);$hash=relisting_extension_hash(lci_row($pdo,'SELECT * FROM market_listings WHERE id=?',[$row]));lci_reject(fn()=>relisting_extend($pdo,$seller['id'],$row,7,$hash));
            }
            foreach(['proxy','event'] as $kind){
                $row=lci_listing($pdo,$seller['id'],$category,['type'=>'auction']);$hash=relisting_extension_hash(lci_row($pdo,'SELECT * FROM market_listings WHERE id=?',[$row]));
                if($kind==='proxy')$pdo->prepare('INSERT INTO market_proxy_bids(listing_id,user_id,max_cents) VALUES (?,?,2000)')->execute([$row,$other['id']]);
                else $pdo->prepare("INSERT INTO market_bids(listing_id,event_type) VALUES (?,'bid')")->execute([$row]);
                lci_reject(fn()=>relisting_extend($pdo,$seller['id'],$row,7,$hash));
            }
        },
        'extensions_enforce_current_trade_account_and_category_guards'=>static function()use($pdo,$seller,$other,$category):void{
            foreach(['agreed','completed'] as $status){$id=lci_listing($pdo,$seller['id'],$category);$context=relisting_extension_context($pdo,$seller['id'],$id);lci_trade($pdo,$id,$seller['id'],$other['id'],$status);lci_reject(fn()=>relisting_extend($pdo,$seller['id'],$id,7,$context['hash']));}
            $id=lci_listing($pdo,$seller['id'],$category);$context=relisting_extension_context($pdo,$seller['id'],$id);
            $pdo->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$seller['id']]);
            try{lci_reject(fn()=>relisting_extend($pdo,$seller['id'],$id,7,$context['hash']),MarketAccessException::class);}finally{$pdo->prepare('UPDATE users SET is_active=1 WHERE id=?')->execute([$seller['id']]);}
            $pdo->prepare('UPDATE market_categories SET is_active=0 WHERE id=?')->execute([$category]);
            try{lci_reject(fn()=>relisting_extend($pdo,$seller['id'],$id,7,$context['hash']));}finally{$pdo->prepare('UPDATE market_categories SET is_active=1 WHERE id=?')->execute([$category]);}
            lci_check((int)lci_scalar($pdo,'SELECT COUNT(*) FROM market_listing_extensions WHERE listing_id=?',[$id])===0,'Rejected guard left an event.');
        },
        'concurrent_general_copy_creators_get_one_child'=>static function()use($pdo,$seller,$category):void{
            $parent=lci_listing($pdo,$seller['id'],$category,['status'=>'archived']);$request=['action'=>'copy','seller'=>$seller['id'],'listing'=>$parent];
            $results=lci_parallel([$request,$request]);lci_check($results[0]['ok']&&$results[1]['ok']&&$results[0]['result']===$results[1]['result'],'Concurrent copies diverged.');
            lci_check((int)lci_scalar($pdo,'SELECT COUNT(*) FROM market_listing_copies WHERE parent_listing_id=?',[$parent])===1,'Duplicate copy links.');
        },
        'concurrent_extensions_replay_or_reject_without_double_duration'=>static function()use($pdo,$seller,$category):void{
            foreach([false,true] as $different){
                $id=lci_listing($pdo,$seller['id'],$category);$context=relisting_extension_context($pdo,$seller['id'],$id);$request=['action'=>'extend','seller'=>$seller['id'],'listing'=>$id,'days'=>7,'hash'=>$context['hash']];
                $results=lci_parallel([$request,array_replace($request,['days'=>$different?14:7])]);$successes=array_values(array_filter($results,fn($row)=>$row['ok']));
                lci_check(count($successes)===($different?1:2),'Wrong number of concurrent extensions accepted.');
                $event=lci_row($pdo,'SELECT * FROM market_listing_extensions WHERE listing_id=?',[$id]);
                lci_check($event['original_ends_at']===$context['listing']['ends_at']&&$event['extended_ends_at']===$successes[0]['result'],'Concurrent extension compounded duration.');
                if(!$different)lci_check($results[0]['result']===$results[1]['result'],'Exact concurrent replay diverged.');
            }
        },
        'relist_http_requires_owner_csrf_and_inventory_confirmation'=>static function()use($pdo,$seller,$other,$category):void{
            $browser=lci_browser($seller);$parent=lci_listing($pdo,$seller['id'],$category,['status'=>'sold','type'=>'negotiated']);$form=lci_http($browser,'/user/relist.php?id='.$parent);
            lci_check($form['status']===200&&str_contains($form['body'],'name="confirm_inventory"'),'Inventory form absent.');
            lci_check((int)lci_scalar($pdo,'SELECT COUNT(*) FROM market_listing_copies WHERE parent_listing_id=?',[$parent])===0,'GET created a draft.');
            $input=['listing_id'=>(string)$parent,'confirm_relist'=>'1','confirm_inventory'=>'1'];
            lci_check(lci_http($browser,'/user/relist.php','POST',$input)['status']===403,'Missing CSRF accepted.');$input['csrf_token']=lci_csrf($form);
            lci_check(lci_http($browser,'/user/relist.php','POST',array_replace($input,['confirm_inventory'=>'0']))['status']===422,'Missing inventory confirmation accepted.');
            $created=lci_http($browser,'/user/relist.php','POST',$input);lci_check($created['status']===303&&str_starts_with($created['headers']['location']??'','/user/edit-listing.php?id='),'General copy did not redirect to editor.');
            $foreign=lci_listing($pdo,$other['id'],$category,['status'=>'archived']);lci_check(lci_http($browser,'/user/relist.php?id='.$foreign)['status']===404,'Foreign source exposed.');
            lci_check(lci_http($browser,'/user/relist.php?id='.$parent,'DELETE')['status']===405,'Unsupported method accepted.');
        },
        'extension_http_checks_csrf_hash_and_records_one_confirmed_deadline'=>static function()use($pdo,$seller,$other,$category):void{
            $browser=lci_browser($seller);$id=lci_listing($pdo,$seller['id'],$category);$context=relisting_extension_context($pdo,$seller['id'],$id);$form=lci_http($browser,'/user/extend-listing.php?id='.$id);
            lci_check($form['status']===200&&str_contains($form['body'],'name="snapshot_hash"'),'Extension form absent.');
            $input=['listing_id'=>(string)$id,'days'=>'7','snapshot_hash'=>$context['hash'],'confirm_extend'=>'1'];
            lci_check(lci_http($browser,'/user/extend-listing.php','POST',$input)['status']===403,'Extension bypassed CSRF.');$input['csrf_token']=lci_csrf($form);
            lci_check(lci_http($browser,'/user/extend-listing.php','POST',array_replace($input,['snapshot_hash'=>str_repeat('0',64)]))['status']===422,'Stale hash accepted.');
            lci_check(lci_http($browser,'/user/extend-listing.php','POST',array_replace($input,['confirm_extend'=>'0']))['status']===422,'Missing confirmation accepted.');
            foreach([1,2] as $_)lci_check(lci_http($browser,'/user/extend-listing.php','POST',$input)['status']===303,'Extension/replay failed.');
            $done=lci_http($browser,'/user/extend-listing.php?id='.$id);lci_check($done['status']===200&&!str_contains($done['body'],'name="confirm_extend"'),'Used extension offered again.');
            $foreign=lci_listing($pdo,$other['id'],$category);lci_check(lci_http($browser,'/user/extend-listing.php?id='.$foreign)['status']===404,'Foreign extension exposed.');
            lci_check(lci_http($browser,'/user/extend-listing.php?id='.$id,'PUT')['status']===405,'Unsupported extension method accepted.');
        },
        'database_constraints_reject_cycles_unconfirmed_inventory_and_second_events'=>static function()use($pdo,$seller,$category):void{
            $parent=lci_listing($pdo,$seller['id'],$category,['status'=>'archived']);$child=lci_listing($pdo,$seller['id'],$category,['status'=>'draft']);
            lci_reject(fn()=>$pdo->prepare("INSERT INTO market_listing_copies(parent_listing_id,child_listing_id,seller_id,source_type,source_status) VALUES (?,?,?,'fixed','archived')")->execute([$parent,$parent,$seller['id']]),PDOException::class);
            lci_reject(fn()=>$pdo->prepare("INSERT INTO market_listing_copies(parent_listing_id,child_listing_id,seller_id,source_type,source_status) VALUES (?,?,?,'fixed','sold')")->execute([$parent,$child,$seller['id']]),PDOException::class);
            $id=lci_listing($pdo,$seller['id'],$category);$context=relisting_extension_context($pdo,$seller['id'],$id);relisting_extend($pdo,$seller['id'],$id,7,$context['hash']);
            lci_reject(fn()=>$pdo->prepare('INSERT INTO market_listing_extensions SELECT * FROM market_listing_extensions WHERE listing_id=?')->execute([$id]),PDOException::class);
        },
    ];
    foreach($tests as $name=>$run){$run();++$passed;fwrite(STDOUT,'PASS '.$name.PHP_EOL);}
}catch(Throwable $error){$failure=$error;}
finally{if($pdo->inTransaction())$pdo->rollBack();foreach($lciBrowsers as $browser)curl_close($browser);}
if($failure!==null){fwrite(STDERR,'FAIL '.$name.' ('.get_class($failure).' at '.basename($failure->getFile()).':'.$failure->getLine().'): '.$failure->getMessage().PHP_EOL);exit(1);}
fwrite(STDOUT,'Listing lifecycle integration checks passed: '.$passed.PHP_EOL);
