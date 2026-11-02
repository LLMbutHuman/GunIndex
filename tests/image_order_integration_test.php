<?php
declare(strict_types=1);
/** Real MySQL/HTTP regression; only synthetic data, never a production schema. */
if (PHP_SAPI !== 'cli' || getenv('DB_DATABASE') !== 'gunindex_rebuild_test') {
    if (PHP_SAPI !== 'cli') http_response_code(404);
    exit(2);
}
require_once __DIR__ . '/../config/bootstrap.php';

function order_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function order_reject(callable $operation, string $type): void
{
    try { $operation(); } catch (Throwable $error) {
        if ($error instanceof $type) return;
        throw $error;
    }
    throw new RuntimeException('Expected rejection did not occur.');
}
function order_rows(PDO $pdo, int $listing): array
{
    $query=$pdo->prepare('SELECT id,listing_id,storage_key,position,width,height,created_at FROM market_images WHERE listing_id=? ORDER BY position');
    $query->execute([$listing]);
    return $query->fetchAll();
}
function order_positions(array $ids): array
{
    $positions=[];
    foreach($ids as $index=>$id) $positions[(string)$id]=(string)($index+1);
    return $positions;
}
function order_user(PDO $pdo): array
{
    $name='image_order_'.bin2hex(random_bytes(8));
    $password='Synthetic image ordering '.bin2hex(random_bytes(16));
    $query=$pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city) VALUES (?,?,?,'Synthetic image-order fixture','00001','Synthetic town')");
    $query->execute([$name,$name.'@example.invalid',auth_hash($password)]);
    return ['id'=>(int)$pdo->lastInsertId(),'username'=>$name,'password'=>$password];
}
function order_listing(PDO $pdo, int $seller, int $category, string $type='fixed', string $status='active', ?string $end=null): int
{
    $query=$pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code,ends_at) VALUES (?,?,'Synthetic image-order table','Ordinary synthetic folding table for image-order regression tests.',?,1000,?,'00001','Synthetic town','DE',?)");
    $query->execute([$seller,$category,$type,$status,$end]);
    return (int)$pdo->lastInsertId();
}
function order_images(PDO $pdo, int $listing, int $count, array &$checksums): void
{
    $directory=market_image_directory();
    for($position=0;$position<$count;$position++) {
        $key=bin2hex(random_bytes(24));
        $image=imagecreatetruecolor(3,3);
        imagefill($image,0,0,imagecolorallocate($image,30+$position*50,90,150));
        try {
            foreach(['.webp','-thumb.webp'] as $extension) {
                $path=$directory.'/'.$key.$extension;
                // Exclusive creation cannot replace another fixture's existing image.
                market_write_webp($image,$path);
                $checksums[$path]=hash_file('sha256',$path);
            }
        } finally { imagedestroy($image); }
        $pdo->prepare('INSERT INTO market_images(listing_id,storage_key,position,width,height) VALUES (?,?,?,3,3)')->execute([$listing,$key,$position]);
    }
}
function order_input(int $category, array $extra=[]): array
{
    return array_merge(['title'=>'Synthetic image-order table','description'=>'Ordinary synthetic folding table for image-order regression tests.',
        'category_id'=>(string)$category,'country_code'=>'DE','type'=>'fixed','price'=>'10.00','buyout_price'=>'','status'=>'active',
        'zip'=>'00001','city'=>'Synthetic town','condition_label'=>'used','pickup'=>'1','shipping'=>'0','duration_days'=>'7','accept_rules'=>'1'],$extra);
}
function order_files_unchanged(array $checksums): void
{
    foreach($checksums as $path=>$digest) order_assert(is_file($path) && hash_file('sha256',$path)===$digest,'Existing fixture image was lost or modified.');
}
function order_browser(): CurlHandle
{
    $browser=curl_init();
    curl_setopt_array($browser,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false]);
    return $browser;
}
function order_http(CurlHandle $browser, string $path, string $method='GET', ?array $fields=null): array
{
    $headers=[];
    curl_setopt_array($browser,[CURLOPT_URL=>'http://127.0.0.1:8081'.$path,CURLOPT_NOBODY=>false,CURLOPT_HTTPGET=>true,
        CURLOPT_HEADERFUNCTION=>static function($handle,string $line)use(&$headers):int {
            $parts=explode(':',$line,2);
            if(count($parts)===2)$headers[strtolower(trim($parts[0]))]=trim($parts[1]);
            return strlen($line);
        }]);
    if($method==='POST') { curl_setopt($browser,CURLOPT_POST,true);curl_setopt($browser,CURLOPT_POSTFIELDS,http_build_query($fields??[])); }
    elseif($method==='HEAD') curl_setopt($browser,CURLOPT_NOBODY,true);
    elseif($method!=='GET') throw new InvalidArgumentException('Unexpected test HTTP method.');
    $body=curl_exec($browser);
    order_assert(is_string($body),'HTTP request failed.');
    return ['status'=>(int)curl_getinfo($browser,CURLINFO_RESPONSE_CODE),'body'=>$body,'headers'=>$headers];
}
function order_csrf(string $html): string
{
    order_assert(preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/',$html,$matches)===1,'CSRF form field missing.');
    return $matches[1];
}
function order_login(CurlHandle $browser, array $user): void
{
    $response=order_http($browser,'/auth/login.php');
    order_assert($response['status']===200,'Login form unavailable.');
    $response=order_http($browser,'/auth/login.php','POST',['csrf_token'=>order_csrf($response['body']),'login'=>$user['username'],'password'=>$user['password']]);
    order_assert($response['status']===303,'Synthetic login failed.');
}

$savedCountry=$pdo->query("SELECT is_active FROM market_countries WHERE code='DE'")->fetchColumn();
order_assert($savedCountry!==false,'DE test market missing.');
$pdo->exec("UPDATE market_countries SET is_active=1 WHERE code='DE'");
$checksums=[];$browsers=[];$passed=0;$failed=false;$name='fixture_initialization';
try {
    $owner=order_user($pdo);$outsider=order_user($pdo);
    $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')->execute(['Synthetic image-order category','image-order-'.bin2hex(random_bytes(8))]);
    $category=(int)$pdo->lastInsertId();
    $listing=order_listing($pdo,$owner['id'],$category);
    $foreign=order_listing($pdo,$outsider['id'],$category);
    $draft=order_listing($pdo,$owner['id'],$category,'fixed','draft');
    $bidLocked=order_listing($pdo,$owner['id'],$category,'auction','active',gmdate('Y-m-d H:i:s',time()+86400));
    $expired=order_listing($pdo,$owner['id'],$category,'auction','active','2020-01-01 00:00:00');
    foreach([$listing=>3,$foreign=>1,$draft=>2,$bidLocked=>2,$expired=>2] as $id=>$count)order_images($pdo,$id,$count,$checksums);
    $pdo->prepare('INSERT INTO market_proxy_bids(listing_id,user_id,max_cents) VALUES (?,?,1000)')->execute([$bidLocked,$outsider['id']]);
    $initial=order_rows($pdo,$listing);
    $input=order_input($category);
    $tests=[
        'swap_preserves_unique_positions_ids_keys_and_files'=>static function()use($pdo,$owner,$listing,$initial,$input,$checksums):void {
            $expected=[(string)$initial[2]['id'],(string)$initial[0]['id'],(string)$initial[1]['id']];
            market_save_listing($pdo,$owner['id'],array_merge($input,['image_positions'=>order_positions($expected),'image_revision'=>image_order_revision($initial)]),[],$listing);
            $after=order_rows($pdo,$listing);
            order_assert(array_map(static fn($row)=>(string)$row['id'],$after)===$expected,'Image cycle did not persist.');
            order_assert(array_map(static fn($row)=>(int)$row['position'],$after)===[0,1,2],'Positions are not contiguous.');
            $keysBefore=array_column($initial,'storage_key','id');$keysAfter=array_column($after,'storage_key','id');ksort($keysBefore);ksort($keysAfter);
            order_assert($keysBefore===$keysAfter,'Reorder changed storage keys.');
            order_files_unchanged($checksums);
        },
        'stale_revision_cannot_overwrite_new_order'=>static function()use($pdo,$owner,$listing,$initial,$input):void {
            $before=order_rows($pdo,$listing);
            order_reject(static fn()=>market_save_listing($pdo,$owner['id'],array_merge($input,['image_positions'=>order_positions(array_column($initial,'id')),'image_revision'=>image_order_revision($initial)]),[],$listing),InvalidArgumentException::class);
            order_assert(order_rows($pdo,$listing)===$before,'Stale request modified image order.');
        },
        'foreign_id_cannot_replace_owned_image'=>static function()use($pdo,$owner,$listing,$foreign,$input):void {
            $before=order_rows($pdo,$listing);$foreignBefore=order_rows($pdo,$foreign);
            $ids=array_column($before,'id');$ids[2]=$foreignBefore[0]['id'];
            order_reject(static fn()=>market_save_listing($pdo,$owner['id'],array_merge($input,['image_positions'=>order_positions($ids),'image_revision'=>image_order_revision($before)]),[],$listing),InvalidArgumentException::class);
            order_assert(order_rows($pdo,$listing)===$before&&order_rows($pdo,$foreign)===$foreignBefore,'Foreign image injection changed records.');
        },
        'duplicate_positions_and_extra_ids_fail_atomically'=>static function()use($pdo,$owner,$listing,$foreign,$input):void {
            $before=order_rows($pdo,$listing);$positions=order_positions(array_column($before,'id'));$positions[(string)$before[1]['id']]='1';
            order_reject(static fn()=>market_save_listing($pdo,$owner['id'],array_merge($input,['image_positions'=>$positions,'image_revision'=>image_order_revision($before)]),[],$listing),InvalidArgumentException::class);
            $positions=order_positions(array_column($before,'id'));$positions[(string)order_rows($pdo,$foreign)[0]['id']]='4';
            order_reject(static fn()=>market_save_listing($pdo,$owner['id'],array_merge($input,['image_positions'=>$positions,'image_revision'=>image_order_revision($before)]),[],$listing),InvalidArgumentException::class);
            order_assert(order_rows($pdo,$listing)===$before,'Invalid permutation was partially saved.');
        },
        'upload_failure_rolls_back_reorder_without_removing_files'=>static function()use($pdo,$owner,$listing,$input,$checksums):void {
            $before=order_rows($pdo,$listing);
            $invalidUpload=['name'=>['too-large.png'],'tmp_name'=>[''],'error'=>[UPLOAD_ERR_INI_SIZE],'size'=>[0]];
            $change=array_merge($input,['title'=>'Synthetic changed title rollback','image_positions'=>order_positions(array_reverse(array_column($before,'id'))),'image_revision'=>image_order_revision($before)]);
            order_reject(static fn()=>market_save_listing($pdo,$owner['id'],$change,$invalidUpload,$listing),InvalidArgumentException::class);
            order_assert(order_rows($pdo,$listing)===$before,'Reorder escaped rollback after upload failure.');
            $query=$pdo->prepare('SELECT title FROM market_listings WHERE id=?');$query->execute([$listing]);
            order_assert($query->fetchColumn()===$input['title'],'Listing update escaped failed transaction.');
            order_files_unchanged($checksums);
        },
        'same_order_is_safe_and_service_rejects_outsider'=>static function()use($pdo,$owner,$outsider,$listing,$input):void {
            $before=order_rows($pdo,$listing);$fields=array_merge($input,['image_positions'=>order_positions(array_column($before,'id')),'image_revision'=>image_order_revision($before)]);
            market_save_listing($pdo,$owner['id'],$fields,[],$listing);
            order_assert(order_rows($pdo,$listing)===$before,'Same-order save changed image rows.');
            order_reject(static fn()=>market_save_listing($pdo,$outsider['id'],$fields,[],$listing),MarketAccessException::class);
            order_assert(order_rows($pdo,$listing)===$before,'Outsider altered another owner image order.');
        },
        'bid_and_expiry_locks_apply_to_image_reordering'=>static function()use($pdo,$owner,$bidLocked,$expired,$category):void {
            foreach([$bidLocked,$expired] as $id) {
                $before=order_rows($pdo,$id);
                $fields=order_input($category,['type'=>'auction','image_positions'=>order_positions(array_reverse(array_column($before,'id'))),'image_revision'=>image_order_revision($before)]);
                order_reject(static fn()=>market_save_listing($pdo,$owner['id'],$fields,[],$id),MarketConflictException::class);
                order_assert(order_rows($pdo,$id)===$before,'Locked auction image order changed.');
            }
        },
        'http_owner_draft_preview_is_private_and_other_users_are_denied'=>static function()use($pdo,$owner,$outsider,$draft,&$browsers):void {
            $anonymous=order_browser();$browsers[]=$anonymous;
            $response=order_http($anonymous,'/user/listing-image.php?id='.$draft.'&position=0');
            order_assert($response['status']===303,'Anonymous draft preview not rejected.');
            $browser=order_browser();$browsers[]=$browser;order_login($browser,$owner);
            $response=order_http($browser,'/user/listing-image.php?id='.$draft.'&position=0');
            order_assert($response['status']===200&&str_starts_with($response['headers']['content-type']??'','image/webp'),'Owner draft thumbnail unavailable.');
            order_assert(($response['headers']['cache-control']??'')==='private, no-store','Private thumbnail is publicly cacheable.');
            $key=order_rows($pdo,$draft)[0]['storage_key'];
            order_assert($response['body']===file_get_contents(market_image_directory().'/'.$key.'-thumb.webp'),'Preview served different file.');
            $head=order_http($browser,'/user/listing-image.php?id='.$draft.'&position=0','HEAD');
            order_assert($head['status']===200&&$head['body']==='','HEAD returned private image bytes.');
            $post=order_http($browser,'/user/listing-image.php?id='.$draft.'&position=0','POST');
            order_assert($post['status']===405,'Mutating method accepted by image endpoint.');
            $other=order_browser();$browsers[]=$other;order_login($other,$outsider);
            order_assert(order_http($other,'/user/listing-image.php?id='.$draft.'&position=0')['status']===404,'Outsider obtained private draft preview.');
            order_assert(order_http($anonymous,'/marketplace/image.php?id='.$draft.'&position=0')['status']===404,'Draft image exposed by public endpoint.');
        },
        'http_csrf_ownership_stale_form_and_successful_swap'=>static function()use($pdo,$owner,$outsider,$listing,$input,&$browsers):void {
            $browser=order_browser();$browsers[]=$browser;order_login($browser,$owner);
            $path='/user/edit-listing.php?id='.$listing;$page=order_http($browser,$path);
            order_assert($page['status']===200,'Owner edit form unavailable.');
            order_assert(preg_match('/name="image_revision" value="([a-f0-9]{64})"/',$page['body'],$match)===1,'Image revision not rendered.');
            $before=order_rows($pdo,$listing);
            $expected=array_reverse(array_map(static fn($row)=>(string)$row['id'],$before));
            $fields=array_merge($input,['image_positions'=>order_positions($expected),'image_revision'=>$match[1]]);
            order_assert(order_http($browser,$path,'POST',$fields)['status']===403,'CSRF-less reorder accepted.');
            order_assert(order_rows($pdo,$listing)===$before,'CSRF failure changed rows.');
            $fields['csrf_token']=order_csrf($page['body']);
            order_assert(order_http($browser,$path,'POST',$fields)['status']===303,'Valid owner reorder did not save.');
            $after=order_rows($pdo,$listing);
            order_assert(array_map(static fn($row)=>(string)$row['id'],$after)===$expected,'HTTP reorder not persisted.');
            order_assert(order_http($browser,$path,'POST',$fields)['status']===422,'Stale browser form not rejected.');
            order_assert(order_rows($pdo,$listing)===$after,'Stale browser form changed order.');
            $other=order_browser();$browsers[]=$other;order_login($other,$outsider);
            order_assert(order_http($other,$path)['status']===404,'Foreign edit form accessible.');
            $profile=order_http($other,'/user/profile.php');$fields['csrf_token']=order_csrf($profile['body']);
            order_assert(order_http($other,$path,'POST',$fields)['status']===404,'Outsider POST reached image save.');
            order_assert(order_rows($pdo,$listing)===$after,'Foreign POST changed rows.');
        },
        'http_locked_auctions_reject_edits'=>static function()use($owner,$bidLocked,$expired,&$browsers):void {
            $browser=order_browser();$browsers[]=$browser;order_login($browser,$owner);
            foreach([$bidLocked,$expired] as $id) {
                order_assert(order_http($browser,'/user/edit-listing.php?id='.$id)['status']===409,'Locked auction edit form accessible.');
                order_assert(order_http($browser,'/user/edit-listing.php?id='.$id,'POST',[])['status']===409,'Locked auction POST accepted.');
            }
        },
        'all_existing_files_remain_byte_identical'=>static function()use($checksums):void { order_files_unchanged($checksums); },
    ];
    foreach($tests as $name=>$test) { $test();$passed++;fwrite(STDOUT,'PASS '.$name.PHP_EOL); }
    fwrite(STDOUT,'Image-order integration checks passed: '.$passed.PHP_EOL);
} catch(Throwable $error) {
    fwrite(STDERR,'FAIL image-order integration '.$name.' ('.get_class($error).' at '.basename($error->getFile()).':'.$error->getLine().')'.PHP_EOL);
    $failed=true;
} finally {
    if($pdo->inTransaction())$pdo->rollBack();
    foreach($browsers as $browser)curl_close($browser);
    $pdo->prepare("UPDATE market_countries SET is_active=? WHERE code='DE'")->execute([(int)$savedCountry]);
    // Keep bounded, clearly synthetic rows and their image files together for diagnosis.
    // Never delete pre-existing images, users, listings, volumes or application data.
}
if($failed)exit(1);
