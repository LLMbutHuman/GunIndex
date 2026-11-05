<?php
declare(strict_types=1);
/** Sequential synthetic MySQL/HTTP regressions; children exercise real concurrent connections. */
if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY === 'Windows' || getenv('DB_DATABASE') !== 'gunindex_rebuild_test') {
    if (PHP_SAPI !== 'cli') http_response_code(404);
    exit(2);
}
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/relisting.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'gunindex_rebuild_test') exit(2);

function rli_check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function rli_scalar(PDO $pdo, string $sql, array $parameters = []): mixed {
    $q = $pdo->prepare($sql); $q->execute($parameters); return $q->fetchColumn();
}
function rli_rows(PDO $pdo, string $sql, array $parameters = []): array {
    $q = $pdo->prepare($sql); $q->execute($parameters); return $q->fetchAll();
}
function rli_row(PDO $pdo, string $sql, array $parameters = []): array {
    $rows = rli_rows($pdo,$sql,$parameters); rli_check(count($rows) === 1,'Expected one fixture row.'); return $rows[0];
}
function rli_reject(callable $operation, string|array $classes): void {
    try { $operation(); }
    catch (Throwable $error) { foreach ((array)$classes as $class) if ($error instanceof $class) return; throw $error; }
    throw new RuntimeException('Operation did not reject an invalid request.');
}
function rli_setting(PDO $pdo, string $key, string $value): void {
    rli_check($key !== 'maintenance_enabled','Use the transactional maintenance API.');
    $pdo->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,$value]);
    site_settings($pdo,true);
}
function rli_user(PDO $pdo, bool $admin = false): array {
    $name = 'relisting_'.bin2hex(random_bytes(8)); $password = 'Synthetic relisting '.bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,is_admin,country_code,preferred_locale) VALUES (?,?,?,'Synthetic relisting fixture','10115','Synthetic town',?,'DE','de')")
        ->execute([$name,$name.'@example.invalid',auth_hash($password),(int)$admin]);
    $id = (int)$pdo->lastInsertId();
    finance_save_profile($pdo,$id,['legal_name'=>'Synthetic relisting seller','address_line1'=>'Synthetic Street 1','address_line2'=>'Isolated fixture only','postal_code'=>'10115','city'=>'Synthetic town','country_code'=>'DE','vat_id'=>'']);
    return ['id'=>$id,'name'=>$name,'password'=>$password];
}
function rli_input(int $category, array $changes = []): array {
    return array_replace(['title'=>'Synthetic folding table '.bin2hex(random_bytes(5)),
        'description'=>'Ordinary synthetic folding table for isolated relisting regression tests.',
        'category_id'=>(string)$category,'country_code'=>'DE','type'=>'auction','price'=>'10.00','buyout_price'=>'100.00',
        'status'=>'active','zip'=>'10115','city'=>'Synthetic town','condition_label'=>'used','pickup'=>'1','shipping'=>'1','duration_days'=>'7','accept_rules'=>'1'],$changes);
}
function rli_accept(array $quote): array {
    $input = ['auction_fee_quote'=>$quote['hash'],'auction_fee_action'=>'publish','accept_auction_fees'=>'1',
        'legal_confirm'=>'1','legal_language'=>$quote['payload']['locale'],'legal_documents'=>[]];
    foreach ($quote['documents'] as $document) $input['legal_documents'][(int)$document['id']] = $document['content_hash'];
    return $input;
}
function rli_quote(PDO $pdo, int $seller, int $draft): ?array {
    return relisting_discount_quote($pdo,$seller,$draft,auction_billing_quote($pdo,$seller),false);
}
function rli_publish(PDO $pdo, int $seller, int $category, int $draft, ?array $quote = null): int {
    $quote ??= rli_quote($pdo,$seller,$draft);
    return market_save_listing($pdo,$seller,array_replace(rli_input($category),$quote === null ? [] : rli_accept($quote)),[],$draft);
}
function rli_end_unsold(PDO $pdo, int $listing): void {
    // Alter only this synthetic fixture, sort it first, and close exactly one row.
    $pdo->prepare("UPDATE market_listings SET ends_at='1000-01-01 00:00:00' WHERE id=?")->execute([$listing]);
    rli_check((int)rli_scalar($pdo,"SELECT id FROM market_listings WHERE type='auction' AND status='active' AND ends_at<=UTC_TIMESTAMP() ORDER BY ends_at,id LIMIT 1")===$listing,'Another fixture would be closed first.');
    rli_check(market_close_due($pdo,1)===1,'Synthetic close worker did not close one auction.');
    $row = rli_row($pdo,'SELECT status,highest_bidder_id,current_bid_cents FROM market_listings WHERE id=?',[$listing]);
    rli_check($row['status'] === 'ended' && $row['highest_bidder_id'] === null && $row['current_bid_cents'] === null,'Fixture did not end without a sale.');
    rli_check((int)rli_scalar($pdo,"SELECT COUNT(*) FROM market_bids WHERE listing_id=? AND event_type='close_no_bid'",[$listing]) === 1,'No actual unsold closing event.');
}
function rli_source(PDO $pdo, int $seller, int $category): int {
    $quote = auction_billing_quote($pdo,$seller);
    $id = market_save_listing($pdo,$seller,array_replace(rli_input($category),$quote === null ? [] : rli_accept($quote)));
    rli_end_unsold($pdo,$id); return $id;
}
function rli_listing_snapshot(PDO $pdo, int $listing): array {
    $out = [rli_row($pdo,'SELECT * FROM market_listings WHERE id=?',[$listing])];
    foreach (['market_bids','market_proxy_bids','market_images','market_auction_fee_contracts','market_auction_fee_acceptances','market_auction_fee_charges'] as $table) {
        $out[$table] = rli_rows($pdo,'SELECT * FROM '.$table.' WHERE listing_id=?',[$listing]);
    }
    return $out;
}
function rli_financial_counts(PDO $pdo, int $seller): array {
    $counts = [];
    foreach (['market_auction_fee_contracts'=>'seller_id','market_invoices'=>'user_id','market_manual_orders'=>'user_id',
        'market_invoice_snapshots'=>'user_id','market_contract_receipts'=>'user_id','market_legal_acceptances'=>'user_id','market_notifications'=>'user_id'] as $table=>$column) {
        $counts[$table] = (int)rli_scalar($pdo,'SELECT COUNT(*) FROM '.$table.' WHERE '.$column.'=?',[$seller]);
    }
    return $counts;
}
function rli_images(PDO $pdo, int $listing, int $count): array {
    $directory = market_image_directory(); $rows = [];
    for ($position = 0; $position < $count; ++$position) {
        $key = bin2hex(random_bytes(24)); $image = imagecreatetruecolor(5,5);
        imagefill($image,0,0,imagecolorallocate($image,40+$position*50,100,150));
        try { foreach (['.webp','-thumb.webp'] as $suffix) market_write_webp($image,$directory.'/'.$key.$suffix); }
        finally { imagedestroy($image); }
        $pdo->prepare('INSERT INTO market_images(listing_id,storage_key,position,width,height) VALUES (?,?,?,5,5)')->execute([$listing,$key,$position]);
        $rows[] = ['id'=>(int)$pdo->lastInsertId(),'storage_key'=>$key,'position'=>$position];
    }
    return $rows;
}
function rli_browser(): CurlHandle {
    global $rliBrowsers;
    $browser = curl_init(); curl_setopt_array($browser,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>5]);
    $rliBrowsers[] = $browser; return $browser;
}
function rli_http(CurlHandle $browser, string $path, string $method = 'GET', ?array $fields = null): array {
    rli_check(str_starts_with($path,'/') && !str_starts_with($path,'//') && !preg_match('/[\r\n]/',$path),'Only fixed loopback request paths allowed.');
    $headers = [];
    curl_setopt_array($browser,[CURLOPT_URL=>'http://127.0.0.1:8081'.$path,CURLOPT_NOBODY=>false,CURLOPT_HTTPGET=>true,
        CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>[],CURLOPT_HEADERFUNCTION=>static function(CurlHandle $curl,string $line) use (&$headers): int {
            if (str_contains($line,':')) { [$name,$value] = explode(':',$line,2); $headers[strtolower(trim($name))] = trim($value); }
            return strlen($line);
        }]);
    if ($fields !== null) {
        $multipart = false; foreach ($fields as $value) if ($value instanceof CURLFile) $multipart = true;
        if ($multipart) {
            $flat = [];
            $flatten = static function(array $values,string $prefix = '') use (&$flatten,&$flat): void {
                foreach ($values as $name=>$value) { $key = $prefix === '' ? (string)$name : $prefix.'['.$name.']'; if (is_array($value)) $flatten($value,$key); else $flat[$key] = $value; }
            };
            $flatten($fields); curl_setopt($browser,CURLOPT_POSTFIELDS,$flat);
        } else curl_setopt($browser,CURLOPT_POSTFIELDS,http_build_query($fields));
    }
    if ($method === 'HEAD') curl_setopt($browser,CURLOPT_NOBODY,true);
    $body = curl_exec($browser); rli_check(is_string($body),'Loopback HTTP request failed.');
    return ['status'=>(int)curl_getinfo($browser,CURLINFO_RESPONSE_CODE),'body'=>$body,'headers'=>$headers];
}
function rli_csrf(array $response): string {
    rli_check(preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/',$response['body'],$match) === 1,'CSRF token missing.'); return $match[1];
}
function rli_login(CurlHandle $browser, array $user): void {
    $form = rli_http($browser,'/auth/login.php'); rli_check($form['status'] === 200,'Login unavailable.');
    $result = rli_http($browser,'/auth/login.php','POST',['csrf_token'=>rli_csrf($form),'login'=>$user['name'],'password'=>$user['password']]);
    rli_check($result['status'] === 303,'Synthetic login failed.');
}
function rli_parallel(array $requests): array {
    $start = (int)(microtime(true)*1000)+1200; $children = []; $results = [];
    try {
        foreach ($requests as $request) {
            $process = proc_open([PHP_BINARY,__FILE__,'--race'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            rli_check(is_resource($process),'Concurrent PHP process unavailable.');
            $children[] = [$process,$pipes];
            fwrite($pipes[0],json_encode($request+['start'=>$start],JSON_THROW_ON_ERROR)); fclose($pipes[0]);
        }
        foreach ($children as $index=>[$process,$pipes]) {
            $body = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process); unset($children[$index]);
            rli_check($exit === 0 && $errors === '','Concurrent synthetic operation failed.');
            $results[] = json_decode($body,true,12,JSON_THROW_ON_ERROR);
        }
    } finally {
        foreach ($children as [$process,$pipes]) { foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe); if (is_resource($process)) { proc_terminate($process); proc_close($process); } }
    }
    return $results;
}

if (($argv[1] ?? '') === '--race') {
    try {
        $request = json_decode(stream_get_contents(STDIN,16001),true,12,JSON_THROW_ON_ERROR);
        rli_check(is_array($request) && in_array($request['action'] ?? null,['draft','publish'],true)
            && is_int($request['seller'] ?? null) && is_int($request['listing'] ?? null) && is_int($request['start'] ?? null),'Invalid concurrent request.');
        rli_check((int)rli_scalar($pdo,"SELECT COUNT(*) FROM market_listings l JOIN users u ON u.id=l.seller_id WHERE l.id=? AND u.id=? AND u.full_name='Synthetic relisting fixture' AND u.email LIKE '%@example.invalid'",[$request['listing'],$request['seller']]) === 1,'Child requires an owned synthetic fixture.');
        $_SESSION['locale'] = 'de'; $pdo->exec('SET SESSION innodb_lock_wait_timeout=5');
        $delay = $request['start']-(int)(microtime(true)*1000); rli_check($delay >= -5000 && $delay <= 10000,'Concurrent start outside allowed range.');
        if ($delay > 0) usleep($delay*1000);
        if ($request['action'] === 'draft') $id = relisting_create_draft($pdo,$request['seller'],$request['listing']);
        else { rli_check(is_array($request['input'] ?? null),'Publication input absent.'); $id = market_save_listing($pdo,$request['seller'],$request['input'],[],$request['listing']); }
        fwrite(STDOUT,json_encode(['id'=>$id],JSON_THROW_ON_ERROR)); exit(0);
    } catch (Throwable $error) { fwrite(STDERR,'Concurrent relisting failed: '.get_class($error).' at '.$error->getLine().PHP_EOL); exit(1); }
}

$configured = [
    'auction_fees_enabled'=>'1','auction_fees_approved'=>'1','fee_auction_listing_cents'=>'50',
    'manual_paypal_enabled'=>'1','manual_paypal_approved'=>'1','manual_paypal_recipient'=>'relisting-merchant@example.invalid',
    'manual_tax_reviewed'=>'1','manual_tax_bps'=>'1900','finance_enabled'=>'1',
    'operator_legal_name'=>'Synthetic relisting fixture operator','operator_address_line1'=>'Synthetic Street 9',
    'operator_address_line2'=>'No actual operator identity','operator_postcode'=>'10115','operator_city'=>'Synthetic town',
    'operator_country'=>'DE','operator_email'=>'relisting-operator@example.invalid','operator_vat_id'=>'SYNTHETIC-NOT-VALID',
    'operator_tax_number'=>'','operator_registration'=>'Synthetic fixture only','invoice_prefix'=>'RLTEST',
    'legal_publication_enabled'=>'1','legal_checkout_documents'=>'',
];
$saved = []; foreach ($configured as $key=>$_) { $rows = rli_rows($pdo,'SELECT setting_value,updated_at FROM system_settings WHERE setting_key=?',[$key]); $saved[$key] = $rows[0] ?? null; }
$savedTax = rli_rows($pdo,"SELECT * FROM market_tax_rules WHERE country_code='DE'")[0] ?? null;
$savedCountry = rli_row($pdo,"SELECT is_active,updated_at FROM market_countries WHERE code='DE'");
$pdo->beginTransaction();
try { $savedMaintenance = maintenance_state($pdo); rli_check((int)$savedMaintenance['active'] === 0,'Suite requires an inactive maintenance state.'); }
finally { $pdo->rollBack(); }
$savedFlag = rli_rows($pdo,"SELECT setting_value,updated_at FROM system_settings WHERE setting_key='maintenance_enabled'")[0] ?? null;
$originalLocale = $_SESSION['locale'] ?? null; $hadLocale = array_key_exists('locale',$_SESSION ?? []);
$rliBrowsers = []; $temporaryFiles = []; $maintenanceWindow = null; $maintenanceDeadlines = []; $admin = null;
$failure = null; $name = 'fixture_setup'; $passed = 0;
try {
    $_SESSION['locale'] = 'de'; foreach ($configured as $key=>$value) rli_setting($pdo,$key,$value);
    $pdo->exec("UPDATE market_countries SET is_active=1 WHERE code='DE'");
    $admin = rli_user($pdo,true);
    finance_save_tax($pdo,$admin['id'],'DE',1900,'Synthetic relisting tax fixture; no actual tax approval.');
    $code = 'relisting-contract-'.bin2hex(random_bytes(8));
    $document = legal_create($pdo,$admin['id'],$code,'1.0','de','Synthetic relisting terms',str_repeat('Synthetic relisting fee terms for isolated tests. No legal or provider approval. ',6));
    legal_approve($pdo,$admin['id'],$document,'Synthetic reviewer only; no actual approval',gmdate('Y-m-d'),array_fill(0,5,'1'));
    rli_setting($pdo,'legal_checkout_documents',$code);
    $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')->execute(['Synthetic folding tables','relisting-'.bin2hex(random_bytes(8))]); $category = (int)$pdo->lastInsertId();
    $tests = [
        'draft_creation_is_idempotent_and_preserves_the_finished_parent'=>static function() use ($pdo,$category): void {
            $seller=rli_user($pdo); $parent=rli_source($pdo,$seller['id'],$category);
            $before=rli_listing_snapshot($pdo,$parent); $finances=rli_financial_counts($pdo,$seller['id']);
            $child=relisting_create_draft($pdo,$seller['id'],$parent);
            rli_check($child>$parent && relisting_create_draft($pdo,$seller['id'],$parent)===$child,'Repeated creation did not return one new child.');
            $copy=rli_row($pdo,'SELECT * FROM market_listings WHERE id=?',[$child]);
            foreach (['seller_id','category_id','title','description','type','price_cents','buyout_cents','zip','city','country_code','latitude','longitude','pickup','shipping','condition_label'] as $field)
                rli_check($copy[$field]===$before[0][$field],'Copy lost a product field: '.$field);
            rli_check($copy['status']==='draft' && $copy['ends_at']===null && $copy['current_bid_cents']===null
                && $copy['highest_bidder_id']===null && $copy['bumped_at']===null && (int)$copy['auction_fee_eligible']===1,'Draft inherited transactional state.');
            foreach (['market_bids','market_proxy_bids','market_ratings','market_auction_fee_contracts','market_auction_fee_charges'] as $table)
                rli_check((int)rli_scalar($pdo,'SELECT COUNT(*) FROM '.$table.' WHERE listing_id=?',[$child])===0,'Draft inherited history or fees.');
            $context=relisting_context($pdo,$seller['id'],$child);
            rli_check($context['root_listing_id']===$parent && $context['parent_listing_id']===$parent && $context['child_listing_id']===$child
                && $context['published_at']===null && $context['free_available'] && $context['consumed_by']===null,'Draft consumed the free claim.');
            rli_check((int)rli_scalar($pdo,'SELECT COUNT(*) FROM market_relistings WHERE parent_listing_id=?',[$parent])===1,'Duplicate lineage row.');
            rli_check($before===rli_listing_snapshot($pdo,$parent) && $finances===rli_financial_counts($pdo,$seller['id']),'Draft changed source or financial records.');
        },
        'wrong_owners_and_non_unsold_sources_cannot_create_a_successor'=>static function() use ($pdo,$category): void {
            $seller=rli_user($pdo); $other=rli_user($pdo); $parent=rli_source($pdo,$seller['id'],$category);
            $before=rli_listing_snapshot($pdo,$parent);
            rli_reject(fn()=>relisting_create_draft($pdo,$other['id'],$parent),DomainException::class);
            foreach (['status'=>['active','paused','draft','sold','archived','review'],'type'=>['fixed'],
                'highest_bidder_id'=>[$other['id']],'current_bid_cents'=>[1000],'ends_at'=>[gmdate('Y-m-d H:i:s',time()+3600)]] as $field=>$values) {
                foreach ($values as $value) {
                    $pdo->prepare('UPDATE market_listings SET '.$field.'=? WHERE id=?')->execute([$value,$parent]);
                    try { rli_reject(fn()=>relisting_create_draft($pdo,$seller['id'],$parent),DomainException::class); }
                    finally { $pdo->prepare('UPDATE market_listings SET '.$field.'=?,updated_at=? WHERE id=?')->execute([$before[0][$field],$before[0]['updated_at'],$parent]); }
                }
            }
            $pdo->prepare('INSERT INTO market_proxy_bids(listing_id,user_id,max_cents) VALUES (?,?,1000)')->execute([$parent,$other['id']]); $proxy=(int)$pdo->lastInsertId();
            try { rli_reject(fn()=>relisting_create_draft($pdo,$seller['id'],$parent),DomainException::class); }
            finally { $pdo->prepare('DELETE FROM market_proxy_bids WHERE id=?')->execute([$proxy]); }
            $pdo->prepare("INSERT INTO market_bids(listing_id,user_id,amount_cents,event_type) VALUES (?,?,1000,'buyout')")->execute([$parent,$other['id']]); $event=(int)$pdo->lastInsertId();
            try { rli_reject(fn()=>relisting_create_draft($pdo,$seller['id'],$parent),DomainException::class); }
            finally { $pdo->prepare('DELETE FROM market_bids WHERE id=?')->execute([$event]); }
            rli_check((int)rli_scalar($pdo,'SELECT COUNT(*) FROM market_relistings WHERE parent_listing_id=?',[$parent])===0,'Rejected source acquired a successor.');
            rli_check($before===rli_listing_snapshot($pdo,$parent),'Rejected source was mutated.');
        },
        'images_are_independent_private_files_and_owner_only_draft_previews'=>static function() use ($pdo,$category): void {
            $seller=rli_user($pdo); $parent=rli_source($pdo,$seller['id'],$category); $original=rli_images($pdo,$parent,2);
            $before=rli_listing_snapshot($pdo,$parent); $child=relisting_create_draft($pdo,$seller['id'],$parent);
            $copies=rli_rows($pdo,'SELECT * FROM market_images WHERE listing_id=? ORDER BY position',[$child]);
            rli_check(count($copies)===2 && array_map('intval',array_column($copies,'position'))===[0,1],'Image count or order was not copied.');
            $directory=market_image_directory(); $digests=[];
            foreach ($copies as $position=>$copy) {
                rli_check((int)$copy['id']!==$original[$position]['id'] && $copy['storage_key']!==$original[$position]['storage_key'],'Image identity is shared.');
                foreach (['.webp','-thumb.webp'] as $suffix) {
                    $sourcePath=$directory.'/'.$original[$position]['storage_key'].$suffix; $copyPath=$directory.'/'.$copy['storage_key'].$suffix;
                    rli_check(is_file($copyPath) && !is_link($copyPath) && fileinode($sourcePath)!==fileinode($copyPath),'Copied image is missing or shares an inode.');
                    rli_check((fileperms($copyPath)&0077)===0,'Copied image permissions are public.');
                    $digests[$sourcePath]=hash_file('sha256',$sourcePath);
                    $decoded=imagecreatefromwebp($copyPath); $sourceImage=imagecreatefromwebp($sourcePath);
                    try {
                        rli_check(imagesx($decoded)===imagesx($sourceImage) && imagesy($decoded)===imagesy($sourceImage),'Copied image dimensions changed.');
                        $a=imagecolorat($sourceImage,1,1); $b=imagecolorat($decoded,1,1);
                        foreach ([0,8,16] as $shift) rli_check(abs((($a>>$shift)&255)-(($b>>$shift)&255))<=8,'Copied image pixels do not match the product.');
                    } finally { imagedestroy($decoded); imagedestroy($sourceImage); }
                }
            }
            $positions=[(string)$copies[0]['id']=>'2',(string)$copies[1]['id']=>'1'];
            market_save_listing($pdo,$seller['id'],rli_input($category,['status'=>'draft','image_positions'=>$positions,'image_revision'=>image_order_revision($copies)]),[],$child);
            rli_check($before===rli_listing_snapshot($pdo,$parent),'Child image reorder altered source.');
            foreach ($digests as $path=>$digest) rli_check(hash_file('sha256',$path)===$digest,'Source image changed.');
            $browser=rli_browser(); rli_login($browser,$seller);
            $preview=rli_http($browser,'/user/listing-image.php?id='.$child.'&position=0');
            rli_check($preview['status']===200 && str_starts_with($preview['headers']['content-type']??'','image/webp'),'Owned private preview unavailable.');
            $outsider=rli_user($pdo); $foreign=rli_browser(); rli_login($foreign,$outsider);
            rli_check(rli_http($foreign,'/user/listing-image.php?id='.$child.'&position=0')['status']===404,'Foreign user read copied draft image.');
            rli_check(rli_http(rli_browser(),'/marketplace/image.php?id='.$child.'&position=0')['status']===404,'Draft image became public.');
        },
        'a_missing_later_source_image_rolls_back_rows_and_already_created_copies'=>static function() use ($pdo,$category): void {
            $seller=rli_user($pdo); $parent=rli_source($pdo,$seller['id'],$category); $images=rli_images($pdo,$parent,2);
            $directory=market_image_directory(); $path=$directory.'/'.$images[1]['storage_key'].'.webp';
            $bytes=file_get_contents($path); rli_check(is_string($bytes),'Synthetic image unreadable.'); unlink($path);
            $before=glob($directory.'/*.webp')?:[]; sort($before); $listingCount=(int)rli_scalar($pdo,'SELECT COUNT(*) FROM market_listings WHERE seller_id=?',[$seller['id']]);
            try {
                rli_reject(fn()=>relisting_create_draft($pdo,$seller['id'],$parent),DomainException::class);
                $after=glob($directory.'/*.webp')?:[]; sort($after);
                rli_check($after===$before,'Failed image copy left files or deleted existing images.');
                rli_check((int)rli_scalar($pdo,'SELECT COUNT(*) FROM market_listings WHERE seller_id=?',[$seller['id']])===$listingCount
                    && (int)rli_scalar($pdo,'SELECT COUNT(*) FROM market_relisting_chains WHERE root_listing_id=?',[$parent])===0
                    && (int)rli_scalar($pdo,'SELECT COUNT(*) FROM market_relistings WHERE parent_listing_id=?',[$parent])===0,'Image failure left partial lineage rows.');
            } finally {
                $file=fopen($path,'x+b'); rli_check(is_resource($file),'Cannot restore owned synthetic image.');
                try { rli_check(fwrite($file,$bytes)===strlen($bytes),'Synthetic image restoration incomplete.'); } finally { fclose($file); chmod($path,0600); }
            }
            rli_check(! $pdo->inTransaction(),'Copy failure leaked a transaction.');
        },
        'one_free_publication_requires_current_consent_and_preserves_success_fees'=>static function() use ($pdo,$category,$document): void {
            $seller=rli_user($pdo); $parent=rli_source($pdo,$seller['id'],$category); $before=rli_listing_snapshot($pdo,$parent);
            $child=relisting_create_draft($pdo,$seller['id'],$parent); $quote=rli_quote($pdo,$seller['id'],$child);
            rli_check(is_array($quote) && $quote['payload']['tariff']['listing_cents']===0
                && $quote['payload']['relisting']['original_listing_cents']===50 && $quote['payload']['relisting']['discount_cents']===50
                && $quote['payload']['relisting']['free_applied']===true,'First quote does not contain the bound discount.');
            $finances=rli_financial_counts($pdo,$seller['id']);
            foreach ([['accept_auction_fees'=>'0'],['legal_confirm'=>'0'],['legal_documents'=>[]],['auction_fee_quote'=>str_repeat('0',64)]] as $invalid) {
                $input=array_replace(rli_input($category),rli_accept($quote),$invalid);
                rli_reject(fn()=>market_save_listing($pdo,$seller['id'],$input,[],$child),[InvalidArgumentException::class,DomainException::class]);
                rli_check($finances===rli_financial_counts($pdo,$seller['id']) && relisting_context($pdo,$seller['id'],$child)['free_available'],'Invalid consent consumed rights or booked a fee.');
            }
            rli_publish($pdo,$seller['id'],$category,$child,$quote);
            $contract=auction_billing_contract($pdo,$child); $context=relisting_context($pdo,$seller['id'],$child);
            rli_check($contract===$quote['payload'] && !$context['free_available'] && $context['consumed_by']===$child && $context['published_at']!==null,'Publication did not freeze and claim the displayed contract.');
            $fee=rli_row($pdo,"SELECT * FROM market_auction_fee_charges WHERE listing_id=? AND kind='listing'",[$child]);
            rli_check((int)$fee['gross_cents']===0 && $fee['invoice_id']===null,'Free listing generated a payable invoice.');
            rli_check((int)rli_scalar($pdo,'SELECT COUNT(*) FROM market_auction_fee_acceptances WHERE listing_id=? AND document_id=?',[$child,$document])===1,'Free auction lacks exact legal acceptance.');
            rli_check(auction_billing_amount($contract,'success',10000)===300,'Free listing also waived the success commission.');
            $published=rli_listing_snapshot($pdo,$child); $finances=rli_financial_counts($pdo,$seller['id']);
            // Repeated creation and an ordinary active edit must not reopen a claim or create another fee.
            rli_check(relisting_create_draft($pdo,$seller['id'],$parent)===$child,'Published child caused a second draft.');
            market_save_listing($pdo,$seller['id'],rli_input($category,['duration_days'=>'14']),[],$child);
            rli_check(auction_billing_contract($pdo,$child)===$contract && relisting_context($pdo,$seller['id'],$child)===$context
                && $finances===rli_financial_counts($pdo,$seller['id']),'Active edit changed claim, tariff or receivables.');
            rli_check(rli_scalar($pdo,'SELECT ends_at FROM market_listings WHERE id=?',[$child])===$published[0]['ends_at'],'Active edit restarted the auction.');
            rli_end_unsold($pdo,$child); $grandchild=relisting_create_draft($pdo,$seller['id'],$child); $paid=rli_quote($pdo,$seller['id'],$grandchild);
            rli_check($paid['payload']['tariff']['listing_cents']===50 && !$paid['payload']['relisting']['free_applied']
                && $paid['payload']['relisting']['root_listing_id']===$parent,'Descendant received a second free relisting.');
            rli_publish($pdo,$seller['id'],$category,$grandchild,$paid);
            $charge=rli_row($pdo,"SELECT * FROM market_auction_fee_charges WHERE listing_id=? AND kind='listing'",[$grandchild]);
            rli_check((int)$charge['gross_cents']===50 && (int)$charge['invoice_id']>0,'Repeat listing fee was not actually assessed.');
            $invoice=rli_row($pdo,'SELECT * FROM market_invoices WHERE id=?',[$charge['invoice_id']]);
            rli_check($invoice['status']==='issued' && (int)$invoice['gross_cents']===50,'Paid repeat lacks the correct receivable.');
            rli_check((int)rli_scalar($pdo,'SELECT COUNT(*) FROM market_contract_receipts WHERE invoice_id=? AND document_id=?',[$invoice['id'],$document])===1,'Repeat invoice lacks a bound legal receipt.');
            rli_check((int)rli_scalar($pdo,'SELECT free_listing_id FROM market_relisting_chains WHERE root_listing_id=?',[$parent])===$child
                && $before===rli_listing_snapshot($pdo,$parent),'Repeat rewrote original run or free claim.');
        },
        'a_quote_from_another_draft_and_fixed_price_conversion_are_rejected'=>static function() use ($pdo,$category): void {
            $seller=rli_user($pdo); $a=rli_source($pdo,$seller['id'],$category); $b=rli_source($pdo,$seller['id'],$category);
            $first=relisting_create_draft($pdo,$seller['id'],$a); $second=relisting_create_draft($pdo,$seller['id'],$b);
            $quote=rli_quote($pdo,$seller['id'],$first); $before=rli_financial_counts($pdo,$seller['id']);
            rli_reject(fn()=>rli_publish($pdo,$seller['id'],$category,$second,$quote),[InvalidArgumentException::class,DomainException::class]);
            rli_reject(fn()=>market_save_listing($pdo,$seller['id'],rli_input($category,['type'=>'fixed','status'=>'draft']),[],$first),DomainException::class);
            rli_check($before===rli_financial_counts($pdo,$seller['id']) && relisting_context($pdo,$seller['id'],$first)['free_available']
                && relisting_context($pdo,$seller['id'],$second)['free_available'],'Rejected quote/conversion changed a financial claim.');
        },
        'fee_disabled_publication_and_later_active_edits_do_not_consume_the_free_claim'=>static function() use ($pdo,$category): void {
            $seller=rli_user($pdo); $parent=rli_source($pdo,$seller['id'],$category); $child=relisting_create_draft($pdo,$seller['id'],$parent);
            rli_setting($pdo,'auction_fees_enabled','0');
            try {
                rli_check(rli_quote($pdo,$seller['id'],$child)===null,'Disabled billing produced a paid quote.');
                rli_publish($pdo,$seller['id'],$category,$child);
                $context=relisting_context($pdo,$seller['id'],$child);
                rli_check($context['published_at']!==null && $context['free_available'] && $context['consumed_by']===null
                    && auction_billing_contract($pdo,$child)===null,'Disabled billing spent the future discount.');
                market_save_listing($pdo,$seller['id'],rli_input($category),[],$child);
                rli_reject(fn()=>market_save_listing($pdo,$seller['id'],rli_input($category,['status'=>'draft']),[],$child),DomainException::class);
                market_transition($pdo,$child,$seller['id'],'pause');
                $paused=rli_row($pdo,'SELECT status,ends_at,updated_at FROM market_listings WHERE id=?',[$child]);
                market_save_listing($pdo,$seller['id'],rli_input($category,['duration_days'=>'14']),[],$child);
                rli_check(rli_scalar($pdo,'SELECT ends_at FROM market_listings WHERE id=?',[$child])===$paused['ends_at'],'Paused fee-disabled publication acquired a fresh duration.');
                market_transition($pdo,$child,$seller['id'],'pause');
                $pdo->prepare("UPDATE market_listings SET ends_at='1000-01-01 00:00:00' WHERE id=?")->execute([$child]);
                try { rli_reject(fn()=>market_save_listing($pdo,$seller['id'],rli_input($category),[],$child),DomainException::class); }
                finally { $pdo->prepare("UPDATE market_listings SET status='active',ends_at=?,updated_at=? WHERE id=?")->execute([$paused['ends_at'],$paused['updated_at'],$child]); }
            } finally { rli_setting($pdo,'auction_fees_enabled','1'); }
            market_save_listing($pdo,$seller['id'],rli_input($category),[],$child);
            rli_check(auction_billing_contract($pdo,$child)===null && relisting_context($pdo,$seller['id'],$child)['free_available'],'Enabling billing retroactively charged a published free auction.');
            rli_end_unsold($pdo,$child); $next=relisting_create_draft($pdo,$seller['id'],$child); $quote=rli_quote($pdo,$seller['id'],$next);
            rli_check($quote['payload']['tariff']['listing_cents']===0 && $quote['payload']['relisting']['original_listing_cents']===50,'Preserved discount unavailable on the next charged run.');
            rli_publish($pdo,$seller['id'],$category,$next,$quote);
            rli_check(relisting_context($pdo,$seller['id'],$next)['consumed_by']===$next,'Positive discount was not consumed on publication.');
        },
        'an_already_zero_tariff_preserves_the_once_only_positive_discount'=>static function() use ($pdo,$category): void {
            $seller=rli_user($pdo); $parent=rli_source($pdo,$seller['id'],$category); $child=relisting_create_draft($pdo,$seller['id'],$parent);
            rli_setting($pdo,'fee_auction_listing_cents','0');
            try {
                $quote=rli_quote($pdo,$seller['id'],$child);
                rli_check($quote['payload']['relisting']['original_listing_cents']===0 && $quote['payload']['relisting']['discount_cents']===0
                    && !$quote['payload']['relisting']['free_applied'],'Zero base price was reported as a consumed benefit.');
                rli_publish($pdo,$seller['id'],$category,$child,$quote);
                rli_check(relisting_context($pdo,$seller['id'],$child)['free_available'],'Zero tariff consumed the free claim.');
            } finally { rli_setting($pdo,'fee_auction_listing_cents','50'); }
            rli_end_unsold($pdo,$child); $next=relisting_create_draft($pdo,$seller['id'],$child); $quote=rli_quote($pdo,$seller['id'],$next);
            rli_check($quote['payload']['relisting']['free_applied'] && $quote['payload']['tariff']['listing_cents']===0,'Later positive listing fee lost its discount.');
            rli_publish($pdo,$seller['id'],$category,$next,$quote);
            rli_check(relisting_context($pdo,$seller['id'],$next)['consumed_by']===$next,'Positive discount was never committed.');
        },
        'http_confirmation_ownership_csrf_and_redirects_use_the_real_route'=>static function() use ($pdo,$category): void {
            $seller=rli_user($pdo); $parent=rli_source($pdo,$seller['id'],$category); $before=rli_listing_snapshot($pdo,$parent);
            $anonymous=rli_browser(); rli_check(rli_http($anonymous,'/user/relist.php?id='.$parent)['status']===303,'Anonymous relisting did not require login.');
            $browser=rli_browser(); rli_login($browser,$seller); $form=rli_http($browser,'/user/relist.php?id='.$parent);
            rli_check($form['status']===200 && str_contains($form['body'],'confirm_relist'),'Confirmation form missing.');
            rli_check((int)rli_scalar($pdo,'SELECT COUNT(*) FROM market_relistings WHERE parent_listing_id=?',[$parent])===0,'GET created a draft.');
            $fields=['listing_id'=>(string)$parent,'confirm_relist'=>'1'];
            rli_check(rli_http($browser,'/user/relist.php','POST',$fields)['status']===403,'Missing CSRF was accepted.');
            rli_check(rli_http($browser,'/user/relist.php','POST',['listing_id'=>(string)$parent,'csrf_token'=>rli_csrf($form)])['status']===422,'Missing confirmation was accepted.');
            rli_check(rli_http($browser,'/user/relist.php?id='.$parent,'PUT')['status']===405,'Unsupported method mutated relisting.');
            $other=rli_user($pdo); $foreign=rli_browser(); rli_login($foreign,$other); $foreignForm=rli_http($foreign,'/user/dashboard.php');
            rli_check(rli_http($foreign,'/user/relist.php?id='.$parent)['status']===404,'Foreign source confirmation leaked data.');
            rli_check(rli_http($foreign,'/user/relist.php','POST',$fields+['csrf_token'=>rli_csrf($foreignForm)])['status']===404,'Foreign source relisted.');
            $response=rli_http($browser,'/user/relist.php','POST',$fields+['csrf_token'=>rli_csrf($form)]);
            rli_check($response['status']===303 && preg_match('~\A/user/edit-listing\.php\?id=([1-9][0-9]*)&relisted=1\z~',$response['headers']['location']??'',$match)===1,'Relisting did not redirect to its new draft.');
            $child=(int)$match[1];
            rli_check($child!==$parent && (int)rli_scalar($pdo,'SELECT child_listing_id FROM market_relistings WHERE parent_listing_id=?',[$parent])===$child,'HTTP route returned the wrong child.');
            $repeat=rli_http($browser,'/user/relist.php','POST',$fields+['csrf_token'=>rli_csrf($form)]);
            rli_check($repeat['status']===303 && $repeat['headers']['location']===$response['headers']['location'],'Repeated POST duplicated the draft.');
            $existing=rli_http($browser,'/user/relist.php?id='.$parent);
            rli_check($existing['status']===200 && str_contains($existing['body'],'edit-listing.php?id='.$child),'Existing draft link missing.');
            $edit=rli_http($browser,'/user/edit-listing.php?id='.$child); $quote=rli_quote($pdo,$seller['id'],$child);
            rli_check($edit['status']===200 && str_contains($edit['body'],$quote['hash']),'UI quote does not match the child discount.');
            $publish=rli_http($browser,'/user/edit-listing.php?id='.$child,'POST',array_replace(rli_input($category),rli_accept($quote),['csrf_token'=>rli_csrf($edit)]));
            rli_check($publish['status']===303 && relisting_context($pdo,$seller['id'],$child)['consumed_by']===$child,'HTTP publication did not claim its contract.');
            rli_check($before===rli_listing_snapshot($pdo,$parent),'HTTP flow changed the source auction.');
        },
        'late_http_image_failure_rolls_back_contract_fee_and_free_claim'=>static function() use ($pdo,$category,&$temporaryFiles): void {
            $seller=rli_user($pdo); $parent=rli_source($pdo,$seller['id'],$category); rli_images($pdo,$parent,2);
            $child=relisting_create_draft($pdo,$seller['id'],$parent); $images=rli_rows($pdo,'SELECT id,position FROM market_images WHERE listing_id=? ORDER BY position',[$child]);
            // A synthetic noncontiguous legacy position collides with the appended image
            // AFTER publication/fee hooks, forcing the real transaction to roll back.
            $pdo->prepare('UPDATE market_images SET position=2 WHERE id=?')->execute([$images[1]['id']]);
            $file=tempnam(sys_get_temp_dir(),'gunindex-relist-'); rli_check(is_string($file),'Temporary fixture unavailable.'); $temporaryFiles[]=$file;
            $image=imagecreatetruecolor(4,4); try { rli_check(imagepng($image,$file),'PNG fixture unavailable.'); } finally { imagedestroy($image); }
            $browser=rli_browser(); rli_login($browser,$seller); $form=rli_http($browser,'/user/edit-listing.php?id='.$child); $quote=rli_quote($pdo,$seller['id'],$child);
            $financial=rli_financial_counts($pdo,$seller['id']); $snapshot=rli_listing_snapshot($pdo,$child);
            $paths=glob(market_image_directory().'/*.webp')?:[]; sort($paths);
            try {
                $fields=array_replace(rli_input($category),rli_accept($quote),['csrf_token'=>rli_csrf($form),'images[0]'=>new CURLFile($file,'image/png','synthetic.png')]);
                $result=rli_http($browser,'/user/edit-listing.php?id='.$child,'POST',$fields);
                rli_check($result['status']===500,'The intended late duplicate-position failure was not reached.');
                rli_check($financial===rli_financial_counts($pdo,$seller['id']) && $snapshot===rli_listing_snapshot($pdo,$child),'Late failure committed a contract, fee or image.');
                $context=relisting_context($pdo,$seller['id'],$child);
                rli_check($context['free_available'] && $context['published_at']===null,'Late failure consumed the once-only claim.');
                $after=glob(market_image_directory().'/*.webp')?:[]; sort($after); rli_check($paths===$after,'Late failure left new private image files.');
            } finally { $pdo->prepare('UPDATE market_images SET position=1 WHERE id=?')->execute([$images[1]['id']]); }
            rli_publish($pdo,$seller['id'],$category,$child,$quote);
            rli_check(relisting_context($pdo,$seller['id'],$child)['consumed_by']===$child,'A rolled-back publication could not be retried.');
        },
        'parallel_create_requests_return_one_child_and_one_independent_image_set'=>static function() use ($pdo,$category): void {
            $seller=rli_user($pdo); $parent=rli_source($pdo,$seller['id'],$category); rli_images($pdo,$parent,2); $before=rli_listing_snapshot($pdo,$parent);
            $request=['action'=>'draft','seller'=>$seller['id'],'listing'=>$parent];
            $results=rli_parallel([$request,$request]); $child=$results[0]['id'];
            rli_check(is_int($child) && $child>$parent && $results[1]['id']===$child,'Concurrent draft requests created different children.');
            rli_check((int)rli_scalar($pdo,'SELECT COUNT(*) FROM market_relistings WHERE parent_listing_id=?',[$parent])===1
                && (int)rli_scalar($pdo,'SELECT COUNT(*) FROM market_images WHERE listing_id=?',[$child])===2,'Concurrent copy duplicated records.');
            rli_check((int)rli_scalar($pdo,'SELECT COUNT(*) FROM market_listings WHERE seller_id=?',[$seller['id']])===2 && $before===rli_listing_snapshot($pdo,$parent),'Concurrent request left an orphan child or changed the source.');
        },
        'parallel_publications_commit_one_claim_contract_and_zero_fee_record'=>static function() use ($pdo,$category): void {
            $seller=rli_user($pdo); $parent=rli_source($pdo,$seller['id'],$category); $child=relisting_create_draft($pdo,$seller['id'],$parent); $quote=rli_quote($pdo,$seller['id'],$child);
            $request=['action'=>'publish','seller'=>$seller['id'],'listing'=>$child,'input'=>array_replace(rli_input($category),rli_accept($quote))];
            $results=rli_parallel([$request,$request]); rli_check(array_column($results,'id')===[$child,$child],'Publication retry did not serialize.');
            rli_check((int)rli_scalar($pdo,'SELECT auction_fee_eligible FROM market_listings WHERE id=?',[$child])===1,'Concurrent publication forgot the accepted tariff.');
            rli_check((int)rli_scalar($pdo,'SELECT COUNT(*) FROM market_auction_fee_contracts WHERE listing_id=?',[$child])===1
                && (int)rli_scalar($pdo,"SELECT COUNT(*) FROM market_auction_fee_charges WHERE listing_id=? AND kind='listing' AND gross_cents=0 AND invoice_id IS NULL",[$child])===1,'Parallel publication duplicated its accepted fee.');
            rli_check((int)rli_scalar($pdo,'SELECT free_listing_id FROM market_relisting_chains WHERE root_listing_id=?',[$parent])===$child
                && (int)rli_scalar($pdo,"SELECT COUNT(*) FROM admin_logs WHERE action_type='auction.relist_published' AND target_id=?",[$child])===1,'Parallel publication duplicated or lost its claim audit.');
        },
        'a_preexisting_repeatable_read_snapshot_does_not_hide_a_committed_contract'=>static function() use ($pdo,$category): void {
            $seller=rli_user($pdo); $parent=rli_source($pdo,$seller['id'],$category); $child=relisting_create_draft($pdo,$seller['id'],$parent);
            $quote=rli_quote($pdo,$seller['id'],$child); $input=array_replace(rli_input($category),rli_accept($quote));
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->beginTransaction();
            try{
                rli_check(auction_billing_contract($pdo,$child)===null,'Fixture must start with an uncontracted draft.');
                $results=rli_parallel([['action'=>'publish','seller'=>$seller['id'],'listing'=>$child,'input'=>$input]]);
                rli_check($results[0]['id']===$child,'Concurrent fixture did not publish.');
                rli_check(auction_billing_contract($pdo,$child)===null,'Fixture did not retain the older read snapshot.');
                $context=relisting_context($pdo,$seller['id'],$child,true);$listing=market_lock_listing($pdo,$child);
                $prepared=auction_billing_prepare($pdo,$seller['id'],['type'=>'auction','status'=>'active'],$input,$listing);
                rli_check($context['published_at']!==null && ($prepared['existing']??false)===true && $prepared['payload']===$quote['payload'],
                    'Current publication reused a stale missing-contract snapshot.');
            }finally{if($pdo->inTransaction())$pdo->rollBack();}
        },
        'maintenance_blocks_direct_creation_and_http_creation_and_publication'=>static function() use ($pdo,$category,$admin,&$maintenanceWindow,&$maintenanceDeadlines): void {
            $seller=rli_user($pdo); $parent=rli_source($pdo,$seller['id'],$category); $other=rli_source($pdo,$seller['id'],$category);
            $child=relisting_create_draft($pdo,$seller['id'],$parent); $quote=rli_quote($pdo,$seller['id'],$child);
            $browser=rli_browser(); rli_login($browser,$seller); $form=rli_http($browser,'/user/relist.php?id='.$other); $edit=rli_http($browser,'/user/edit-listing.php?id='.$child);
            $before=rli_financial_counts($pdo,$seller['id']); $snapshot=rli_listing_snapshot($pdo,$child);
            $pdo->beginTransaction();
            try {
                maintenance_state($pdo,true);
                foreach ($pdo->query("SELECT id,ends_at,updated_at FROM market_listings WHERE type='auction' AND status IN ('active','paused') AND ends_at>UTC_TIMESTAMP() FOR SHARE")->fetchAll() as $row) $maintenanceDeadlines[(int)$row['id']]=$row;
                maintenance_transition($pdo,$admin['id'],'0','1');
                $window=(int)rli_scalar($pdo,'SELECT window_id FROM market_maintenance_state WHERE id=1');
                $pdo->commit(); $maintenanceWindow=$window;
            } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
            rli_reject(fn()=>relisting_create_draft($pdo,$seller['id'],$other),MaintenancePaused::class);
            rli_reject(fn()=>rli_publish($pdo,$seller['id'],$category,$child,$quote),MaintenancePaused::class);
            foreach ([rli_http($browser,'/user/relist.php','POST',['csrf_token'=>rli_csrf($form),'listing_id'=>(string)$other,'confirm_relist'=>'1']),
                rli_http($browser,'/user/edit-listing.php?id='.$child,'POST',array_replace(rli_input($category),rli_accept($quote),['csrf_token'=>rli_csrf($edit)]))] as $response)
                rli_check($response['status']===503 && ($response['headers']['retry-after']??'')==='300','HTTP relisting bypassed maintenance.');
            rli_check($before===rli_financial_counts($pdo,$seller['id']) && $snapshot===rli_listing_snapshot($pdo,$child)
                && (int)rli_scalar($pdo,'SELECT COUNT(*) FROM market_relistings WHERE parent_listing_id=?',[$other])===0,'Paused request left a draft, claim or receivable.');
        },
    ];
    foreach ($tests as $name=>$run) { $run(); ++$passed; fwrite(STDOUT,'PASS '.$name.PHP_EOL); }
    rli_check($passed > 0,'Relisting regression cases must be present.');
    fwrite(STDOUT,'Relisting integration checks passed: '.$passed.PHP_EOL);
} catch (Throwable $error) { $failure = $error; }
finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($maintenanceWindow !== null && $admin !== null) {
        $pdo->beginTransaction();
        try {
            $state = maintenance_state($pdo,true);
            rli_check((int)$state['window_id'] === $maintenanceWindow,'Another maintenance window replaced the test fixture.');
            maintenance_transition($pdo,$admin['id'],(string)$state['active'],'0');
            foreach ($maintenanceDeadlines as $id=>$row) $pdo->prepare('UPDATE market_listings SET ends_at=?,updated_at=? WHERE id=?')->execute([$row['ends_at'],$row['updated_at'],$id]);
            $pdo->prepare('UPDATE market_maintenance_state SET active=?,window_id=? WHERE id=1')->execute([$savedMaintenance['active'],$savedMaintenance['window_id']]);
            if ($savedFlag === null) $pdo->exec("DELETE FROM system_settings WHERE setting_key='maintenance_enabled'");
            else $pdo->prepare("UPDATE system_settings SET setting_value=?,updated_at=? WHERE setting_key='maintenance_enabled'")->execute([$savedFlag['setting_value'],$savedFlag['updated_at']]);
            $pdo->commit();
        } catch (Throwable $restoreError) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $restoreError; }
    }
    foreach ($saved as $key=>$row) {
        if ($row === null) $pdo->prepare('DELETE FROM system_settings WHERE setting_key=?')->execute([$key]);
        else $pdo->prepare('INSERT INTO system_settings(setting_key,setting_value,updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=VALUES(updated_at)')->execute([$key,$row['setting_value'],$row['updated_at']]);
    }
    if ($savedTax === null) $pdo->exec("DELETE FROM market_tax_rules WHERE country_code='DE'");
    else $pdo->prepare('INSERT INTO market_tax_rules(country_code,basis_points,explanation,reviewed_by,reviewed_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE basis_points=VALUES(basis_points),explanation=VALUES(explanation),reviewed_by=VALUES(reviewed_by),reviewed_at=VALUES(reviewed_at)')->execute([$savedTax['country_code'],$savedTax['basis_points'],$savedTax['explanation'],$savedTax['reviewed_by'],$savedTax['reviewed_at']]);
    $pdo->prepare("UPDATE market_countries SET is_active=?,updated_at=? WHERE code='DE'")->execute([$savedCountry['is_active'],$savedCountry['updated_at']]);
    site_settings($pdo,true);
    foreach ($rliBrowsers as $browser) curl_close($browser);
    foreach ($temporaryFiles as $file) if (is_file($file) && !is_link($file)) unlink($file);
    if ($hadLocale) $_SESSION['locale'] = $originalLocale; else unset($_SESSION['locale']);
}
if ($failure !== null) { fwrite(STDERR,'FAIL '.$name.' ('.get_class($failure).' at '.basename($failure->getFile()).':'.$failure->getLine().')'.PHP_EOL); exit(1); }
