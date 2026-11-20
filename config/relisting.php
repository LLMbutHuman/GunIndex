<?php
declare(strict_types=1);

/** Only canonical DB integers are accepted by the pure boundary helpers. */
function relisting_integer(mixed $value, int $minimum=1, int $maximum=PHP_INT_MAX): int
{
    if (is_int($value)) $number=$value;
    elseif (is_string($value)&&preg_match('/\A(?:0|[1-9][0-9]{0,18})\z/',$value)) {
        $number=filter_var($value,FILTER_VALIDATE_INT);
        if ($number===false) throw new DomainException(t('relisting.unavailable'));
    } else throw new DomainException(t('relisting.unavailable'));
    if ($number<$minimum||$number>$maximum) throw new DomainException(t('relisting.unavailable'));
    return $number;
}

/** Strict UTC database timestamp, including rejection of NUL bytes and normalized dates. */
function relisting_timestamp(mixed $value): ?int
{
    if (!is_string($value)||!preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\z/',$value)) return null;
    $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('UTC'));
    return $date&&$date->format('Y-m-d H:i:s')===$value?$date->getTimestamp():null;
}

/** Expiration alone or absence of proxy rows does not prove an unsuccessful close. */
function relisting_source_eligible(array $source, array $events, bool $hasProxy, ?int $now=null): bool
{
    try { $id=relisting_integer($source['id']??null); } catch (DomainException) { return false; }
    if (($source['type']??null)!=='auction'||($source['status']??null)!=='ended'||$hasProxy
        ||!array_key_exists('highest_bidder_id',$source)||$source['highest_bidder_id']!==null
        ||!array_key_exists('current_bid_cents',$source)||$source['current_bid_cents']!==null
        ||!is_string($source['ends_at']??null)||count($events)!==1) return false;
    $end=relisting_timestamp($source['ends_at']);
    if ($end===null||$end>($now??time())) return false;
    $event=array_values($events)[0];
    return is_array($event)&&($event['event_type']??null)==='close_no_bid'
        &&($event['idempotency_key']??null)==='close-'.$id
        &&array_key_exists('user_id',$event)&&$event['user_id']===null
        &&array_key_exists('amount_cents',$event)&&$event['amount_cents']===null;
}

/** A sold copy must match the actual closing or buyout event. */
function relisting_auction_sale_eligible(array $source, array $event, bool $hasProxy, ?int $now=null): bool
{
    try {
        $id=relisting_integer($source['id']??null);
        $seller=relisting_integer($source['seller_id']??null);
        $winner=relisting_integer($source['highest_bidder_id']??null);
        $price=relisting_integer($source['current_bid_cents']??null);
        $eventBuyer=relisting_integer($event['user_id']??null);
        $eventPrice=relisting_integer($event['amount_cents']??null);
    } catch (DomainException) { return false; }
    if (($source['type']??null)!=='auction'||$winner===$seller||$winner!==$eventBuyer||$price!==$eventPrice) return false;
    if (($source['status']??null)==='sold') {
        return !$hasProxy&&($event['event_type']??null)==='buyout'&&($event['idempotency_key']??null)==='buyout-'.$id;
    }
    $end=relisting_timestamp($source['ends_at']??null);
    return ($source['status']??null)==='ended'&&$hasProxy&&$end!==null&&$end<=($now??time())
        &&($event['event_type']??null)==='close'&&($event['idempotency_key']??null)==='close-'.$id;
}

/** Closed general-goods listings can be copied, never revived in place. */
function relisting_general_eligible(array $source, array $events, bool $hasProxy, ?int $now=null): bool
{
    try { relisting_integer($source['id']??null); relisting_integer($source['seller_id']??null); } catch (DomainException) { return false; }
    if (!in_array($source['type']??null,['fixed','negotiated'],true)
        ||!in_array($source['status']??null,['archived','ended','sold'],true)||$hasProxy||$events!==[]
        ||!array_key_exists('highest_bidder_id',$source)||$source['highest_bidder_id']!==null
        ||!array_key_exists('current_bid_cents',$source)||$source['current_bid_cents']!==null) return false;
    if ($source['status']==='ended') {
        $end=relisting_timestamp($source['ends_at']??null);
        if ($end===null||$end>($now??time())) return false;
    }
    return true;
}

/** Limited choices are deliberately independent of the auction's accepted fee tariff. */
function relisting_extension_days(string $type): array
{
    return match($type) { 'auction'=>[1,3,7], 'fixed','negotiated'=>[7,14,30], default=>[] };
}

/** An existing future deadline is required; historical unlimited listings stay unlimited. */
function relisting_extension_eligible(array $listing, bool $hasBidHistory, ?int $now=null): bool
{
    try { relisting_integer($listing['id']??null); relisting_integer($listing['seller_id']??null); } catch (DomainException) { return false; }
    $end=relisting_timestamp($listing['ends_at']??null);
    return ($listing['status']??null)==='active'&&relisting_extension_days((string)($listing['type']??''))!==[]
        &&$end!==null&&$end>($now??time())&&!$hasBidHistory
        &&array_key_exists('highest_bidder_id',$listing)&&$listing['highest_bidder_id']===null
        &&array_key_exists('current_bid_cents',$listing)&&$listing['current_bid_cents']===null;
}

/** Bind confirmation to every visible/product state field, not just a second-granularity timestamp. */
function relisting_extension_hash(array $listing): string
{
    $fields=['id','seller_id','category_id','title','description','type','status','price_cents','buyout_cents','shipping_cents',
        'zip','city','country_code','latitude','longitude','pickup','shipping','condition_label','ends_at','updated_at',
        'highest_bidder_id','current_bid_cents'];
    $snapshot=[];
    foreach ($fields as $field) {
        if (!array_key_exists($field,$listing)||(!is_scalar($listing[$field])&&$listing[$field]!==null)) throw new DomainException(t('relisting.extension_conflict'));
        $snapshot[$field]=$listing[$field]===null?null:(string)$listing[$field];
    }
    return hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}

/** UTC arithmetic from the current deadline; never shorten or reset to the request time. */
function relisting_extension_deadline(array $listing, int $days, ?int $now=null): string
{
    if (!in_array($days,relisting_extension_days((string)($listing['type']??'')),true)
        ||!relisting_extension_eligible($listing,false,$now)) throw new DomainException(t('relisting.extension_unavailable'));
    $end=relisting_timestamp($listing['ends_at']);
    $deadline=gmdate('Y-m-d H:i:s',$end+$days*86400);
    if (relisting_timestamp($deadline)===null) throw new DomainException(t('relisting.extension_unavailable'));
    return $deadline;
}

/** Caller holds the listing barrier, so bids/trades cannot appear after this check. */
function relisting_has_bid_history(PDO $pdo, int $listingId, bool $lock=false): bool
{
    foreach (['market_proxy_bids','market_bids'] as $table) {
        $q=$pdo->prepare('SELECT id FROM '.$table.' WHERE listing_id=? LIMIT 1'.($lock?' FOR SHARE':''));
        $q->execute([$listingId]); if ($q->fetchColumn()!==false) return true;
    }
    return false;
}

/** Public GET eligibility helper; a locked call requires the lineage barrier first. */
function relisting_source(PDO $pdo, int $seller, int $parentId, bool $lock=false): array
{
    if ($seller<1||$parentId<1||($lock&&!$pdo->inTransaction())) throw new DomainException(t('relisting.unavailable'));
    $q=$pdo->prepare('SELECT * FROM market_listings WHERE id=? AND seller_id=?'.($lock?' FOR UPDATE':''));
    $q->execute([$parentId,$seller]); $source=$q->fetch();
    if (!$source) throw new DomainException(t('relisting.unavailable'));
    $q=$pdo->prepare('SELECT id FROM market_proxy_bids WHERE listing_id=? LIMIT 1'.($lock?' FOR SHARE':''));
    $q->execute([$parentId]); $hasProxy=$q->fetchColumn()!==false;
    if ($source['type']==='auction'&&$source['highest_bidder_id']!==null) {
        $key=($source['status']==='sold'?'buyout-':'close-').$parentId;
        $q=$pdo->prepare('SELECT event_type,idempotency_key,user_id,amount_cents FROM market_bids WHERE listing_id=? AND idempotency_key=?'.($lock?' FOR SHARE':''));
        $q->execute([$parentId,$key]); $event=$q->fetch();
        if (!$event||!relisting_auction_sale_eligible($source,$event,$hasProxy)||!trade_listing_available($pdo,$parentId)) throw new DomainException(t('relisting.unavailable'));
        return $source+['source_outcome'=>'sold'];
    }
    $q=$pdo->prepare('SELECT event_type,idempotency_key,user_id,amount_cents FROM market_bids WHERE listing_id=? ORDER BY id LIMIT 2'.($lock?' FOR SHARE':''));
    $q->execute([$parentId]);
    $events=$q->fetchAll();
    if (in_array($source['type'],['fixed','negotiated'],true)) {
        if (!relisting_general_eligible($source,$events,$hasProxy)||!trade_listing_available($pdo,$parentId)) throw new DomainException(t('relisting.general_unavailable'));
    } else {
        if (!relisting_source_eligible($source,$events,$hasProxy)) throw new DomainException(t('relisting.unavailable'));
        $source['source_outcome']='no_bid';
    }
    return $source;
}

function relisting_context_values(array $link, array $chain, int $owner): array
{
    $root=relisting_integer($link['root_listing_id']??null);
    $parent=relisting_integer($link['parent_listing_id']??null);
    $child=relisting_integer($link['child_listing_id']??null);
    $seller=relisting_integer($link['seller_id']??null);
    if ($owner!==$seller||$root>$parent||$parent>=$child
        ||relisting_integer($chain['root_listing_id']??null)!==$root
        ||relisting_integer($chain['seller_id']??null)!==$seller
        ||!array_key_exists('free_listing_id',$chain)||!array_key_exists('free_used_at',$chain)
        ||!in_array($link['source_outcome']??null,['no_bid','sold'],true)
        ||!array_key_exists('published_at',$link)) throw new DomainException(t('relisting.conflict'));
    $used=$chain['free_listing_id']===null?null:relisting_integer($chain['free_listing_id']);
    if (($used===null)!==($chain['free_used_at']===null)||($used!==null&&$used<=$root)
        ||($chain['free_used_at']!==null&&relisting_timestamp($chain['free_used_at'])===null)
        ||($link['published_at']!==null&&relisting_timestamp($link['published_at'])===null)
        ||($used!==null&&$used>=$child&&$link['published_at']===null)) throw new DomainException(t('relisting.conflict'));
    return ['root_listing_id'=>$root,'parent_listing_id'=>$parent,'child_listing_id'=>$child,'seller_id'=>$seller,
        'published_at'=>$link['published_at'],'free_available'=>$used===null,'consumed_by'=>$used,'source_outcome'=>$link['source_outcome']];
}

/**
 * With lock=true call before market_lock_listing: root -> child is the common order.
 * The initial immutable link/owner lookup acquires no child row lock.
 */
function relisting_context(PDO $pdo, int $seller, int $listingId, bool $lock=false): ?array
{
    if ($seller<1||$listingId<1) throw new DomainException(t('relisting.unavailable'));
    if ($lock&&!$pdo->inTransaction()) throw new LogicException('Relisting locks require a caller transaction.');
    $q=$pdo->prepare('SELECT r.*,l.seller_id AS listing_owner FROM market_relistings r JOIN market_listings l ON l.id=r.child_listing_id WHERE r.child_listing_id=?');
    $q->execute([$listingId]); $link=$q->fetch(); if (!$link) return null;
    if ((int)$link['seller_id']!==$seller||(int)$link['listing_owner']!==$seller) throw new DomainException(t('relisting.unavailable'));
    $q=$pdo->prepare('SELECT * FROM market_relisting_chains WHERE root_listing_id=?'.($lock?' FOR UPDATE':''));
    $q->execute([$link['root_listing_id']]); $chain=$q->fetch();
    if (!$chain) throw new DomainException(t('relisting.conflict'));
    if ($lock) {
        // Current read after waiting on the root: never reuse a stale publication snapshot.
        $q=$pdo->prepare('SELECT * FROM market_relistings WHERE child_listing_id=? FOR SHARE');
        $q->execute([$listingId]); $current=$q->fetch();
        if (!$current||(int)$current['root_listing_id']!==(int)$link['root_listing_id']) throw new DomainException(t('relisting.conflict'));
        $link=$current;
    }
    return relisting_context_values($link,$chain,$seller);
}

/** Called after the existing owner check, for draft saves as well as publications. */
function relisting_assert_target(PDO $pdo, int $seller, int $listingId, array $data): void
{
    $context=relisting_context($pdo,$seller,$listingId,true); if (!$context) return;
    if (($data['type']??null)!=='auction') throw new DomainException(t('relisting.unavailable'));
    if ($context['published_at']!==null) {
        $listing=market_lock_listing($pdo,$listingId);
        if (($data['status']??null)!=='active'||market_expired($listing)) throw new DomainException(t('relisting.conflict'));
    }
}

/** Validate the bounded manifest before reading any private path. */
function relisting_image_manifest(array $rows): array
{
    if (!array_is_list($rows)||count($rows)>5) throw new DomainException(t('relisting.image_failed'));
    $keys=[]; $out=[];
    foreach ($rows as $position=>$row) {
        if (!is_array($row)||!is_string($row['storage_key']??null)
            ||!preg_match('/\A[a-f0-9]{48}\z/',$row['storage_key'])||isset($keys[$row['storage_key']])) throw new DomainException(t('relisting.image_failed'));
        try {
            $storedPosition=relisting_integer($row['position']??null,0,4);
            $width=relisting_integer($row['width']??null,1,1600);
            $height=relisting_integer($row['height']??null,1,1600);
        } catch (DomainException) { throw new DomainException(t('relisting.image_failed')); }
        if ($storedPosition!==$position) throw new DomainException(t('relisting.image_failed'));
        $keys[$row['storage_key']]=true;
        $out[]=['storage_key'=>$row['storage_key'],'position'=>$position,'width'=>$width,'height'=>$height];
    }
    return $out;
}

/** Open only an existing regular file in the resolved private image directory. */
function relisting_read_image(string $directory, string $key): string
{
    if (!preg_match('/\A[a-f0-9]{48}\z/',$key)) throw new DomainException(t('relisting.image_failed'));
    $path=$directory.'/'.$key.'.webp'; clearstatcache(true,$path); $before=@lstat($path);
    if (!$before||($before['mode']&0170000)!==0100000||is_link($path)||realpath($path)!==$path
        ||$before['size']<1||$before['size']>8*1024*1024) throw new DomainException(t('relisting.image_failed'));
    $file=@fopen($path,'rb'); if ($file===false) throw new DomainException(t('relisting.image_failed'));
    try {
        $opened=fstat($file);
        if (!$opened||($opened['mode']&0170000)!==0100000||$opened['ino']!==$before['ino']||$opened['dev']!==$before['dev']
            ||$opened['size']!==$before['size']) throw new DomainException(t('relisting.image_failed'));
        $bytes=stream_get_contents($file,8*1024*1024+1); $after=fstat($file);
        if (!is_string($bytes)||strlen($bytes)!==$opened['size']||!$after||$after['size']!==$opened['size']
            ||$after['mtime']!==$opened['mtime']) throw new DomainException(t('relisting.image_failed'));
        return $bytes;
    } finally { fclose($file); }
}

/** Track only paths whose exclusive creation succeeded; never remove a colliding file. */
function relisting_write_image(GdImage $image, string $path, array &$created): void
{
    $file=@fopen($path,'x+b'); if ($file===false) throw new DomainException(t('relisting.image_failed'));
    $created[]=$path;
    try {
        if (!chmod($path,0600)||!imagewebp($image,$file,84)||!fflush($file)) throw new DomainException(t('relisting.image_failed'));
        $stat=fstat($file); if (!$stat||$stat['size']<1) throw new DomainException(t('relisting.image_failed'));
    } finally { fclose($file); }
}

/** Re-encode each original; regenerate its thumbnail. No shared keys/hardlinks. */
function relisting_prepare_images(array $rows): array
{
    $images=relisting_image_manifest($rows); if (!$images) return [];
    if (!extension_loaded('gd')||!function_exists('imagewebp')) throw new DomainException(t('relisting.image_failed'));
    $directory=market_image_directory(); $prepared=[];
    try {
        foreach ($images as $image) {
            $bytes=relisting_read_image($directory,$image['storage_key']); $info=@getimagesizefromstring($bytes);
            if (!$info||($info['mime']??'')!=='image/webp'||$info[0]!==$image['width']||$info[1]!==$image['height']) throw new DomainException(t('relisting.image_failed'));
            $original=@imagecreatefromstring($bytes); unset($bytes);
            if (!$original instanceof GdImage) throw new DomainException(t('relisting.image_failed'));
            $key=bin2hex(random_bytes(24)); $large=null; $thumb=null;
            $prepared[]=['key'=>$key,'width'=>$image['width'],'height'=>$image['height'],'paths'=>[]]; $index=array_key_last($prepared);
            try {
                $large=market_resize_image($original,$image['width'],$image['height'],1600);
                $thumb=market_resize_image($original,$image['width'],$image['height'],400);
                relisting_write_image($large,$directory.'/'.$key.'.webp',$prepared[$index]['paths']);
                relisting_write_image($thumb,$directory.'/'.$key.'-thumb.webp',$prepared[$index]['paths']);
            } finally {
                imagedestroy($original);
                if ($large instanceof GdImage) imagedestroy($large);
                if ($thumb instanceof GdImage) imagedestroy($thumb);
            }
        }
        return $prepared;
    } catch (Throwable $error) { market_remove_prepared_images($prepared); throw $error; }
}

/** Own transaction; an auction requires a verified closure or buyout. */
function relisting_create_draft(PDO $pdo, int $seller, int $parentId, bool $confirmInventory=false): int
{
    if ($pdo->inTransaction()) throw new LogicException('Relisting draft creation owns its transaction.');
    if ($seller<1||$parentId<1) throw new DomainException(t('relisting.unavailable'));
    $q=$pdo->prepare('SELECT type FROM market_listings WHERE id=? AND seller_id=?'); $q->execute([$parentId,$seller]);
    if (in_array($q->fetchColumn(),['fixed','negotiated'],true)) return relisting_create_general_draft($pdo,$seller,$parentId,$confirmInventory);
    for ($attempt=0;$attempt<3;$attempt++) {
        $prepared=[]; $committing=false; $rolledBack=false;
        try {
            $pdo->beginTransaction(); maintenance_assert_market_available($pdo);
            market_assert_users_active($pdo,[$seller]);
            relisting_source($pdo,$seller,$parentId);
            $q=$pdo->prepare('SELECT root_listing_id,seller_id FROM market_relistings WHERE child_listing_id=?');
            $q->execute([$parentId]); $ancestor=$q->fetch();
            if ($ancestor&&(int)$ancestor['seller_id']!==$seller) throw new DomainException(t('relisting.unavailable'));
            $root=$ancestor?(int)$ancestor['root_listing_id']:$parentId;
            if (!$ancestor) {
                $pdo->prepare('INSERT INTO market_relisting_chains(root_listing_id,seller_id) VALUES (?,?) ON DUPLICATE KEY UPDATE root_listing_id=VALUES(root_listing_id)')->execute([$root,$seller]);
            }
            $q=$pdo->prepare('SELECT * FROM market_relisting_chains WHERE root_listing_id=? FOR UPDATE');
            $q->execute([$root]); $chain=$q->fetch();
            if (!$chain||(int)$chain['seller_id']!==$seller) throw new DomainException(t('relisting.conflict'));
            // Root is held before the source/child locks, matching publication hooks.
            $source=relisting_source($pdo,$seller,$parentId,true);
            $q=$pdo->prepare('SELECT * FROM market_relistings WHERE parent_listing_id=? FOR SHARE'); $q->execute([$parentId]); $link=$q->fetch();
            if ($link) {
                $context=relisting_context_values($link,$chain,$seller);
                if ($context['source_outcome']!==$source['source_outcome']) throw new DomainException(t('relisting.conflict'));
                // Another creator may have committed while we waited for the root.
                // A consistent snapshot taken before that wait cannot see its child.
                $q=$pdo->prepare('SELECT seller_id FROM market_listings WHERE id=? FOR SHARE'); $q->execute([$link['child_listing_id']]);
                if ((int)$q->fetchColumn()!==$seller) throw new DomainException(t('relisting.conflict'));
                $committing=true; $pdo->commit(); return (int)$link['child_listing_id'];
            }
            if ($source['source_outcome']==='sold'&&!$confirmInventory) throw new DomainException(t('relisting.inventory_confirm'));
            if (!geo_country_locked_enabled($pdo,$source['country_code'])||!market_category_locked_allowed($pdo,(int)$source['category_id'])
                ||!catalog_text_allowed($source['title'].' '.$source['description'])) throw new DomainException(t('relisting.unavailable'));
            $q=$pdo->prepare('SELECT storage_key,position,width,height FROM market_images WHERE listing_id=? ORDER BY position FOR SHARE');
            $q->execute([$parentId]); $prepared=relisting_prepare_images($q->fetchAll());
            $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,buyout_cents,status,zip,city,country_code,latitude,longitude,pickup,shipping,shipping_cents,condition_label,auction_fee_eligible) VALUES (?,?,?,?,'auction',?,?,'draft',?,?,?,?,?,?,?,?,?,1)")
                ->execute([$seller,$source['category_id'],$source['title'],$source['description'],$source['price_cents'],$source['buyout_cents'],
                    $source['zip'],$source['city'],$source['country_code'],$source['latitude'],$source['longitude'],$source['pickup'],$source['shipping'],$source['shipping_cents'],$source['condition_label']]);
            $child=(int)$pdo->lastInsertId();
            classification_copy($pdo,$parentId,$child);
            $pdo->prepare('INSERT INTO market_relistings(parent_listing_id,child_listing_id,root_listing_id,seller_id,source_outcome) VALUES (?,?,?,?,?)')->execute([$parentId,$child,$root,$seller,$source['source_outcome']]);
            foreach ($prepared as $position=>$image) {
                $pdo->prepare('INSERT INTO market_images(listing_id,storage_key,position,width,height) VALUES (?,?,?,?,?)')->execute([$child,$image['key'],$position,$image['width'],$image['height']]);
            }
            audit_log($pdo,$seller,'auction.relist_draft_created',$child,['parent_listing_id'=>$parentId,'root_listing_id'=>$root,'source_outcome'=>$source['source_outcome'],'inventory_confirmed'=>$source['source_outcome']==='sold','image_count'=>count($prepared)]);
            $committing=true; $pdo->commit(); return $child;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) { try { $pdo->rollBack(); $rolledBack=true; } catch (Throwable) {} }
            // If commit outcome is unknown, retain private files for reconciliation.
            if (!$committing||$rolledBack) market_remove_prepared_images($prepared);
            $retry=$error instanceof PDOException&&($error->getCode()==='40001'||in_array((int)($error->errorInfo[1]??0),[1205,1213],true));
            if (!$committing&&$retry&&!$pdo->inTransaction()&&$attempt<2) { usleep(random_int(5000,25000)); continue; }
            throw $error;
        }
    }
    throw new LogicException('Relisting retry exhausted.');
}

/** A separate lineage prevents a fixed-price copy from acquiring a free auction entitlement. */
function relisting_create_general_draft(PDO $pdo, int $seller, int $parentId, bool $confirmInventory=false): int
{
    if ($pdo->inTransaction()) throw new LogicException('Relisting draft creation owns its transaction.');
    if ($seller<1||$parentId<1) throw new DomainException(t('relisting.general_unavailable'));
    for ($attempt=0;$attempt<3;$attempt++) {
        $prepared=[]; $committing=false; $rolledBack=false;
        try {
            $pdo->beginTransaction(); maintenance_assert_market_available($pdo);
            $source=relisting_source($pdo,$seller,$parentId,true);
            market_assert_users_active($pdo,[$seller]);
            if (!in_array($source['type'],['fixed','negotiated'],true)) throw new DomainException(t('relisting.general_unavailable'));
            if ($source['status']==='sold'&&!$confirmInventory) throw new DomainException(t('relisting.inventory_confirm'));
            $q=$pdo->prepare('SELECT c.*,l.seller_id AS child_owner FROM market_listing_copies c JOIN market_listings l ON l.id=c.child_listing_id WHERE c.parent_listing_id=? FOR SHARE');
            $q->execute([$parentId]); $link=$q->fetch();
            if ($link) {
                if ((int)$link['seller_id']!==$seller||(int)$link['child_owner']!==$seller||$link['source_type']!==$source['type']) throw new DomainException(t('relisting.extension_conflict'));
                $committing=true; $pdo->commit(); return (int)$link['child_listing_id'];
            }
            if (!geo_country_locked_enabled($pdo,$source['country_code'])||!market_category_locked_allowed($pdo,(int)$source['category_id'])
                ||!catalog_text_allowed($source['title'].' '.$source['description'])) throw new DomainException(t('relisting.general_unavailable'));
            $q=$pdo->prepare('SELECT storage_key,position,width,height FROM market_images WHERE listing_id=? ORDER BY position FOR SHARE');
            $q->execute([$parentId]); $prepared=relisting_prepare_images($q->fetchAll());
            $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,buyout_cents,status,zip,city,country_code,latitude,longitude,pickup,shipping,shipping_cents,condition_label,auction_fee_eligible) VALUES (?,?,?,?,?,?,NULL,'draft',?,?,?,?,?,?,?,?,?,1)")
                ->execute([$seller,$source['category_id'],$source['title'],$source['description'],$source['type'],$source['price_cents'],
                    $source['zip'],$source['city'],$source['country_code'],$source['latitude'],$source['longitude'],$source['pickup'],$source['shipping'],$source['shipping_cents'],$source['condition_label']]);
            $child=(int)$pdo->lastInsertId();
            classification_copy($pdo,$parentId,$child);
            $pdo->prepare('INSERT INTO market_listing_copies(parent_listing_id,child_listing_id,seller_id,source_type,source_status,inventory_confirmed) VALUES (?,?,?,?,?,?)')
                ->execute([$parentId,$child,$seller,$source['type'],$source['status'],(int)($source['status']==='sold'&&$confirmInventory)]);
            foreach ($prepared as $position=>$image) {
                $pdo->prepare('INSERT INTO market_images(listing_id,storage_key,position,width,height) VALUES (?,?,?,?,?)')->execute([$child,$image['key'],$position,$image['width'],$image['height']]);
            }
            audit_log($pdo,$seller,'listing.relist_draft_created',$child,['parent_listing_id'=>$parentId,'source_type'=>$source['type'],
                'inventory_confirmed'=>$source['status']==='sold'&&$confirmInventory,'image_count'=>count($prepared)]);
            $committing=true; $pdo->commit(); return $child;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) { try { $pdo->rollBack(); $rolledBack=true; } catch (Throwable) {} }
            if (!$committing||$rolledBack) market_remove_prepared_images($prepared);
            $retry=$error instanceof PDOException&&($error->getCode()==='40001'||in_array((int)($error->errorInfo[1]??0),[1205,1213],true));
            if (!$committing&&$retry&&!$pdo->inTransaction()&&$attempt<2) { usleep(random_int(5000,25000)); continue; }
            throw $error;
        }
    }
    throw new LogicException('General relisting retry exhausted.');
}

/** Read-only UI state. Mutations repeat every eligibility check under the listing lock. */
function relisting_extension_context(PDO $pdo, int $seller, int $listingId): array
{
    if ($seller<1||$listingId<1) throw new DomainException(t('relisting.extension_unavailable'));
    $q=$pdo->prepare('SELECT * FROM market_listings WHERE id=? AND seller_id=?'); $q->execute([$listingId,$seller]); $listing=$q->fetch();
    if (!$listing) throw new DomainException(t('relisting.extension_unavailable'));
    $q=$pdo->prepare('SELECT * FROM market_listing_extensions WHERE listing_id=? AND seller_id=?'); $q->execute([$listingId,$seller]); $event=$q->fetch();
    if (!$event&&(!relisting_extension_eligible($listing,relisting_has_bid_history($pdo,$listingId))||!trade_listing_available($pdo,$listingId))) {
        throw new DomainException(t('relisting.extension_unavailable'));
    }
    return ['listing'=>$listing,'hash'=>relisting_extension_hash($listing),'days'=>relisting_extension_days($listing['type']),'event'=>$event?:null];
}

/** Exactly one explicit extension, with compare-and-swap and replay of the same confirmation. */
function relisting_extend(PDO $pdo, int $seller, int $listingId, int $days, string $snapshotHash): string
{
    if ($pdo->inTransaction()) throw new LogicException('Listing extension owns its transaction.');
    if ($seller<1||$listingId<1||!preg_match('/\A[a-f0-9]{64}\z/',$snapshotHash)) throw new DomainException(t('relisting.extension_unavailable'));
    $requestHash=hash('sha256',$snapshotHash.':'.$days);
    for ($attempt=0;$attempt<3;$attempt++) {
        $committing=false;
        try {
            $pdo->beginTransaction(); maintenance_assert_market_available($pdo);
            $q=$pdo->prepare('SELECT * FROM market_listings WHERE id=? AND seller_id=? FOR UPDATE'); $q->execute([$listingId,$seller]); $listing=$q->fetch();
            if (!$listing) throw new DomainException(t('relisting.extension_unavailable'));
            market_assert_users_active($pdo,[$seller]);
            $q=$pdo->prepare('SELECT * FROM market_listing_extensions WHERE listing_id=? FOR SHARE'); $q->execute([$listingId]); $event=$q->fetch();
            if ($event) {
                if ((int)$event['seller_id']!==$seller||!hash_equals($event['request_hash'],$requestHash)) throw new DomainException(t('relisting.extension_used'));
                $committing=true; $pdo->commit(); return $event['extended_ends_at'];
            }
            if (!hash_equals(relisting_extension_hash($listing),$snapshotHash)) throw new DomainException(t('relisting.extension_conflict'));
            if (!relisting_extension_eligible($listing,relisting_has_bid_history($pdo,$listingId,true))||!trade_listing_available($pdo,$listingId)
                ||!geo_country_locked_enabled($pdo,$listing['country_code'])||!market_category_locked_allowed($pdo,(int)$listing['category_id'])
                ||!catalog_text_allowed($listing['title'].' '.$listing['description'])) throw new DomainException(t('relisting.extension_unavailable'));
            $deadline=relisting_extension_deadline($listing,$days);
            $q=$pdo->prepare("UPDATE market_listings SET ends_at=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND seller_id=? AND status='active' AND ends_at=? AND ends_at>UTC_TIMESTAMP() AND highest_bidder_id IS NULL AND current_bid_cents IS NULL");
            $q->execute([$deadline,$listingId,$seller,$listing['ends_at']]);
            if ($q->rowCount()!==1) throw new DomainException(t('relisting.extension_conflict'));
            $pdo->prepare('INSERT INTO market_listing_extensions(listing_id,seller_id,listing_type,original_ends_at,extended_ends_at,days,request_hash) VALUES (?,?,?,?,?,?,?)')
                ->execute([$listingId,$seller,$listing['type'],$listing['ends_at'],$deadline,$days,$requestHash]);
            audit_log($pdo,$seller,'listing.extended',$listingId,['original_ends_at'=>$listing['ends_at'],'extended_ends_at'=>$deadline,'days'=>$days,'fee_cents'=>0]);
            $committing=true; $pdo->commit(); return $deadline;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $retry=$error instanceof PDOException&&($error->getCode()==='40001'||in_array((int)($error->errorInfo[1]??0),[1205,1213],true));
            if (!$committing&&$retry&&$attempt<2) { usleep(random_int(5000,25000)); continue; }
            throw $error;
        }
    }
    throw new LogicException('Listing extension retry exhausted.');
}

/** Quote helpers must not attach one seller's billing identity to another lineage. */
function relisting_quote_context(array $payload, array $context): void
{
    $root=relisting_integer($context['root_listing_id']??null); $parent=relisting_integer($context['parent_listing_id']??null); $child=relisting_integer($context['child_listing_id']??null);
    $seller=relisting_integer($context['seller_id']??null);
    if ($root>$parent||$parent>=$child||!array_key_exists('published_at',$context)||$context['published_at']!==null
        ||!is_bool($context['free_available']??null)||!array_key_exists('consumed_by',$context)
        ||!in_array($context['source_outcome']??null,['no_bid','sold'],true)
        ||$context['free_available']!==($context['consumed_by']===null)
        ||!is_array($payload['buyer']??null)||($payload['buyer']['user_id']??null)!==$seller
        ||($payload['currency']??null)!=='EUR'||!is_string($payload['locale']??null)||!isset(i18n_locales()[$payload['locale']])) throw new DomainException(t('relisting.conflict'));
    if ($context['consumed_by']!==null) {
        $used=relisting_integer($context['consumed_by']);
        if ($used<=$root||$used>=$child) throw new DomainException(t('relisting.conflict'));
    }
}

/** Use the contract language without changing the acting browser's locale. */
function relisting_quote_text(string $locale, string $key, array $parameters=[]): string
{
    $dictionary=i18n_dictionary($locale); $fallback=i18n_dictionary('de');
    $text=$dictionary[$key]??$fallback[$key]??$key;
    foreach ($parameters as $name=>$value) if (is_scalar($value)) $text=str_replace('{'.$name.'}',(string)$value,$text);
    return $text;
}

/** Pure quote transformation. Only the listing fee changes; success rates stay frozen. */
function relisting_quote_apply(array $quote, array $context): array
{
    if (!isset($quote['payload'],$quote['documents'],$quote['hash'])||!is_array($quote['payload'])||!is_array($quote['documents'])
        ||!is_string($quote['hash'])||!hash_equals(hash('sha256',auction_billing_json($quote['payload'])),$quote['hash'])
        ||isset($quote['payload']['relisting'])||($quote['payload']['version']??null)!=='auction-billing-1'
        ||!is_bool($context['free_available']??null)) throw new DomainException(t('relisting.conflict'));
    relisting_quote_context($quote['payload'],$context);
    $original=auction_billing_amount($quote['payload'],'listing',null);
    $root=relisting_integer($context['root_listing_id']??null); $parent=relisting_integer($context['parent_listing_id']??null); $child=relisting_integer($context['child_listing_id']??null);
    if ($root>$parent||$parent>=$child) throw new DomainException(t('relisting.conflict'));
    $applied=$context['source_outcome']==='no_bid'&&$context['free_available']&&$original>0;
    $meta=['version'=>'relisting-1','root_listing_id'=>$root,'parent_listing_id'=>$parent,'child_listing_id'=>$child,
        'original_listing_cents'=>$original,'discount_cents'=>$applied?$original:0,'free_applied'=>$applied];
    if ($context['source_outcome']==='sold') $meta['source_outcome']='sold';
    $quote['payload']['relisting']=$meta;
    $quote['payload']['tariff']['listing_cents']=$original-$meta['discount_cents'];
    $quote['payload']['fee_version']='abr1-'.substr(hash('sha256',auction_billing_json(['tariff'=>$quote['payload']['tariff'],'relisting'=>$meta])),0,20);
    $locale=$quote['payload']['locale'];
    $quote['payload']['terms']=relisting_quote_text($locale,'auction_billing.tariff',['listing'=>money_eur($quote['payload']['tariff']['listing_cents'])]).' '.relisting_quote_text($locale,'auction_billing.accept');
    if ($applied) $quote['payload']['terms'].=' '.relisting_quote_text($locale,'relisting.free_available').' '.relisting_quote_text($locale,'relisting.free_label');
    $quote['hash']=hash('sha256',auction_billing_json($quote['payload']));
    return $quote;
}

/** Apply in BOTH quote display and locked quote acceptance, using the concrete draft ID. */
function relisting_discount_quote(PDO $pdo, int $seller, int $listingId, ?array $quote, bool $lock=false): ?array
{
    $context=relisting_context($pdo,$seller,$listingId,$lock);
    if (!$context||$context['published_at']!==null||$quote===null) return $quote;
    return relisting_quote_apply($quote,$context);
}

/** Pure validation of the accepted claim against the CURRENT locked lineage. */
function relisting_quote_claim(array $payload, array $context): bool
{
    relisting_quote_context($payload,$context);
    $meta=$payload['relisting']??null;
    if (!is_array($meta)||($meta['version']??null)!=='relisting-1'
        ||($meta['source_outcome']??'no_bid')!==$context['source_outcome']
        ||!is_bool($meta['free_applied']??null)||!is_bool($context['free_available']??null)
        ||!is_int($meta['original_listing_cents']??null)||$meta['original_listing_cents']<0||$meta['original_listing_cents']>9999999
        ||!is_int($meta['discount_cents']??null)) throw new DomainException(t('relisting.conflict'));
    foreach (['root_listing_id','parent_listing_id','child_listing_id'] as $field)
        if (!is_int($meta[$field]??null)||$meta[$field]!==($context[$field]??null)) throw new DomainException(t('relisting.conflict'));
    $applied=$context['source_outcome']==='no_bid'&&$context['free_available']&&$meta['original_listing_cents']>0;
    $discount=$applied?$meta['original_listing_cents']:0;
    if ($meta['free_applied']!==$applied||$meta['discount_cents']!==$discount
        ||auction_billing_amount($payload,'listing',null)!==$meta['original_listing_cents']-$discount) throw new DomainException(t('relisting.conflict'));
    $version='abr1-'.substr(hash('sha256',auction_billing_json(['tariff'=>$payload['tariff'],'relisting'=>$meta])),0,20);
    if (($payload['fee_version']??null)!==$version) throw new DomainException(t('relisting.conflict'));
    return $applied;
}

/**
 * After the active UPDATE and auction_billing_publish, before the SAME commit.
 * Root must have been locked through relisting_context before the listing lock.
 * No debit/claim for disabled billing or an already-zero normal listing fee.
 */
function relisting_consume(PDO $pdo, int $seller, int $listingId, ?array $acceptedQuote): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Relisting publication requires a caller transaction.');
    $context=relisting_context($pdo,$seller,$listingId,true); if (!$context) return;
    $listing=market_lock_listing($pdo,$listingId);
    if ((int)$listing['seller_id']!==$seller||$listing['type']!=='auction'||$listing['status']!=='active'||market_expired($listing)) throw new DomainException(t('relisting.conflict'));
    if ($context['published_at']!==null) return;
    if ($listing['highest_bidder_id']!==null||$listing['current_bid_cents']!==null||market_has_bids($pdo,$listingId)) throw new DomainException(t('relisting.conflict'));
    $q=$pdo->prepare('SELECT id FROM market_bids WHERE listing_id=? LIMIT 1'); $q->execute([$listingId]);
    if ($q->fetchColumn()!==false) throw new DomainException(t('relisting.conflict'));
    $claim=false;
    if ($acceptedQuote===null) {
        if (auction_billing_enabled($pdo)) throw new DomainException(t('relisting.conflict'));
    } else {
        if (!is_string($acceptedQuote['hash']??null)||!is_array($acceptedQuote['payload']??null)
            ||!hash_equals(hash('sha256',auction_billing_json($acceptedQuote['payload'])),$acceptedQuote['hash'])) throw new DomainException(t('relisting.conflict'));
        $claim=relisting_quote_claim($acceptedQuote['payload'],$context);
        if (auction_billing_contract($pdo,$listingId)!==$acceptedQuote['payload']) throw new DomainException(t('relisting.conflict'));
    }
    if ($claim) {
        $q=$pdo->prepare('UPDATE market_relisting_chains SET free_listing_id=?,free_used_at=UTC_TIMESTAMP() WHERE root_listing_id=? AND seller_id=? AND free_listing_id IS NULL');
        $q->execute([$listingId,$context['root_listing_id'],$seller]);
        if ($q->rowCount()!==1) throw new DomainException(t('relisting.conflict'));
    }
    $q=$pdo->prepare('UPDATE market_relistings SET published_at=UTC_TIMESTAMP() WHERE child_listing_id=? AND seller_id=? AND published_at IS NULL'); $q->execute([$listingId,$seller]);
    if ($q->rowCount()!==1) throw new DomainException(t('relisting.conflict'));
    audit_log($pdo,$seller,'auction.relist_published',$listingId,['root_listing_id'=>$context['root_listing_id'],
        'parent_listing_id'=>$context['parent_listing_id'],'free_claimed'=>$claim]);
}
