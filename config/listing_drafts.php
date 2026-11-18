<?php
declare(strict_types=1);

const LISTING_DRAFT_FIELDS = [
    'title'=>600,'description'=>20000,'category_id'=>10,'condition_label'=>16,'type'=>16,
    'price'=>13,'buyout_price'=>13,'shipping_price'=>13,'country_code'=>2,'zip'=>16,'city'=>400,
    'duration_days'=>2,'listing_duration_days'=>2,'status'=>8,'pickup'=>1,'shipping'=>1,'trade_checkout_enabled'=>1,
    'image_revision'=>64,
];

function listing_draft_payload(array $input): array
{
    $values=[];
    foreach (LISTING_DRAFT_FIELDS as $name=>$limit) {
        if (!array_key_exists($name,$input)) continue;
        $value=$input[$name];
        if (!is_string($value)||strlen($value)>$limit||!preg_match('//u',$value)||str_contains($value,"\0")) {
            throw new InvalidArgumentException(t('draft.invalid'));
        }
        $values[$name]=$value;
    }
    foreach (['pickup','shipping','trade_checkout_enabled'] as $name) $values[$name]=($values[$name]??'0')==='1'?'1':'0';
    foreach (['attributes'=>[16,640],'image_positions'=>[5,1]] as $name=>[$count,$limit]) {
        $items=$input[$name]??[];
        if (!is_array($items)||count($items)>$count) throw new InvalidArgumentException(t('draft.invalid'));
        foreach ($items as $id=>$value) {
            if (!preg_match('/\A[1-9][0-9]{0,18}\z/',(string)$id)||!is_string($value)||strlen($value)>$limit
                ||!preg_match('//u',$value)||str_contains($value,"\0")) throw new InvalidArgumentException(t('draft.invalid'));
            $values[$name][$id]=$value;
        }
        if (isset($values[$name])) ksort($values[$name]);
        elseif ($name==='attributes') $values[$name]=[];
    }
    return $values;
}

/** Lock the new-listing workspace to serialize all of this owner's workspaces. */
function listing_draft_lock_owner(PDO $pdo, int $owner): void
{
    market_assert_users_active($pdo,[$owner]);
    $q=$pdo->prepare("INSERT INTO market_listing_drafts(seller_id,editor_id,revision,base_hash,payload) VALUES (?,0,'0',?,NULL)"
        .' ON DUPLICATE KEY UPDATE seller_id=VALUES(seller_id)');
    $q->execute([$owner,listing_draft_base(null,[])]);
}

function listing_draft_source(PDO $pdo, int $owner, int $editor, bool $lock=false, bool $editable=true): ?array
{
    if ($editor===0) return null;
    $q=$pdo->prepare('SELECT * FROM market_listings WHERE id=? AND seller_id=?'.($lock?' FOR UPDATE':''));
    $q->execute([$editor,$owner]);
    $row=$q->fetch();
    if (!$row||($editable&&(!in_array($row['status'],['draft','active','paused'],true)||market_has_bids($pdo,$editor)
        ||($row['type']==='auction'&&$row['status']==='active'&&market_expired($row))))) {
        throw new MarketNotFoundException(t('draft.unavailable'));
    }
    return $row;
}

function listing_draft_images(PDO $pdo, int $editor, bool $lock=false): array
{
    if ($editor===0) return [];
    $q=$pdo->prepare('SELECT id,position FROM market_images WHERE listing_id=? ORDER BY position'.($lock?' FOR SHARE':''));
    $q->execute([$editor]);
    return $q->fetchAll();
}

function listing_draft_base(?array $source, array $images): string
{
    return hash('sha256',json_encode([$source,image_order_revision($images)],JSON_THROW_ON_ERROR));
}

function listing_draft_workspace(PDO $pdo, int $owner, int $editor, bool $lock=false): ?array
{
    $q=$pdo->prepare('SELECT revision,base_hash,payload,updated_at FROM market_listing_drafts WHERE seller_id=? AND editor_id=?'.($lock?' FOR UPDATE':''));
    $q->execute([$owner,$editor]);
    $row=$q->fetch();
    if (!$row) return null;
    $row['payload']=$row['payload']===null?null:json_decode($row['payload'],true,16,JSON_THROW_ON_ERROR);
    return $row;
}

function listing_draft_image_count(PDO $pdo,int $owner,int $editor): int
{
    $q=$pdo->prepare('SELECT COUNT(*) FROM market_listing_draft_images WHERE seller_id=? AND editor_id=?');
    $q->execute([$owner,$editor]);
    return (int)$q->fetchColumn();
}

function listing_draft_guard(?array $workspace, string $base, array $input, bool $discard=false): void
{
    $revision=$input['draft_revision']??null;
    if (!is_string($revision)||!hash_equals($workspace['revision']??'0',$revision)
        || (!$discard&&(!is_string($input['draft_base']??null)||!hash_equals($base,$input['draft_base'])
            ||(isset($workspace['payload'])&&!hash_equals($base,$workspace['base_hash']))))) {
        throw new MarketConflictException(t('draft.conflict'));
    }
}

/** Staging never changes the listing, audit acceptance, or fee state. */
function listing_draft_save(PDO $pdo,int $owner,int $editor,array $input,array $uploads=[],bool $discard=false): array
{
    $payload=$discard?null:listing_draft_payload($input);
    $clearImages=$discard||($input['clear_draft_images']??null)==='1';
    $prepared=[];
    try {
        $pdo->beginTransaction();
        maintenance_assert_market_available($pdo);
        listing_draft_lock_owner($pdo,$owner);
        $source=listing_draft_source($pdo,$owner,$editor,true,!$discard);
        $images=listing_draft_images($pdo,$editor,true);
        $base=listing_draft_base($source,$images);
        $workspace=listing_draft_workspace($pdo,$owner,$editor,true);
        listing_draft_guard($workspace,$base,$input,$discard);
        $existing=$clearImages?0:listing_draft_image_count($pdo,$owner,$editor);
        if ($payload!==null&&!isset($workspace['payload'])) {
            $q=$pdo->prepare('SELECT COUNT(*) FROM market_listing_drafts WHERE seller_id=? AND payload IS NOT NULL');
            $q->execute([$owner]);
            if ((int)$q->fetchColumn()>=12) throw new DomainException(t('draft.limit'));
        }
        if (!$discard) $prepared=market_prepare_images($uploads,count($images)+$existing);
        $revision=bin2hex(random_bytes(16));
        $q=$pdo->prepare('INSERT INTO market_listing_drafts(seller_id,editor_id,revision,base_hash,payload) VALUES (?,?,?,?,?)'
            .' ON DUPLICATE KEY UPDATE revision=VALUES(revision),base_hash=VALUES(base_hash),payload=VALUES(payload),updated_at=UTC_TIMESTAMP()');
        $q->execute([$owner,$editor,$revision,$base,$payload===null?null:json_encode($payload,JSON_THROW_ON_ERROR)]);
        if ($clearImages) $pdo->prepare('DELETE FROM market_listing_draft_images WHERE seller_id=? AND editor_id=?')->execute([$owner,$editor]);
        if ($prepared) {
            $q=$pdo->prepare('SELECT COALESCE(SUM(OCTET_LENGTH(large_webp)+OCTET_LENGTH(thumb_webp)),0) FROM market_listing_draft_images WHERE seller_id=?');
            $q->execute([$owner]);
            $used=(int)$q->fetchColumn();
            $q=$pdo->prepare('INSERT INTO market_listing_draft_images(seller_id,editor_id,position,width,height,large_webp,thumb_webp) VALUES (?,?,?,?,?,?,?)');
            foreach ($prepared as $position=>$image) {
                $large=file_get_contents($image['paths'][0]);
                $thumb=file_get_contents($image['paths'][1]);
                if (!is_string($large)||$large===''||!is_string($thumb)||$thumb==='') throw new RuntimeException('Prepared image missing.');
                $used+=strlen($large)+strlen($thumb);
                if ($used>24*1024*1024) throw new DomainException(t('draft.limit'));
                $q->execute([$owner,$editor,$position+$existing,$image['width'],$image['height'],$large,$thumb]);
            }
        }
        $pdo->commit();
        return ['revision'=>$revision,'base'=>$base,'images'=>$existing+count($prepared)];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    } finally {
        market_remove_prepared_images($prepared);
    }
}

/** Copy only recoded images from the owner's locked workspace into the listing transaction. */
function listing_draft_materialize(PDO $pdo,int $owner,int $editor): array
{
    $q=$pdo->prepare('SELECT width,height,large_webp,thumb_webp FROM market_listing_draft_images WHERE seller_id=? AND editor_id=? ORDER BY position');
    $q->execute([$owner,$editor]);
    $prepared=[];
    try {
        while ($row=$q->fetch()) {
            $directory=market_image_directory();
            $key=bin2hex(random_bytes(24));
            $prepared[]=['key'=>$key,'width'=>(int)$row['width'],'height'=>(int)$row['height'],'paths'=>[]];
            $index=array_key_last($prepared);
            foreach (['large_webp'=>'.webp','thumb_webp'=>'-thumb.webp'] as $field=>$suffix) {
                $path=$directory.'/'.$key.$suffix;
                $handle=fopen($path,'x+b');
                if (!$handle) throw new RuntimeException('Private image write failed.');
                $prepared[$index]['paths'][]=$path;
                try {
                    chmod($path,0600);
                    if (fwrite($handle,$row[$field])!==strlen($row[$field])||!fflush($handle)) throw new RuntimeException('Private image write incomplete.');
                } finally { fclose($handle); }
            }
        }
        return $prepared;
    } catch (Throwable $error) {
        market_remove_prepared_images($prepared);
        throw $error;
    }
}

/** A tombstone also rejects retry of the original publish form once committed. */
function listing_draft_finish(PDO $pdo,int $owner,int $editor,string $base): void
{
    $pdo->prepare('DELETE FROM market_listing_draft_images WHERE seller_id=? AND editor_id=?')->execute([$owner,$editor]);
    $q=$pdo->prepare('INSERT INTO market_listing_drafts(seller_id,editor_id,revision,base_hash,payload) VALUES (?,?,?,?,NULL)'
        .' ON DUPLICATE KEY UPDATE revision=VALUES(revision),base_hash=VALUES(base_hash),payload=NULL,updated_at=UTC_TIMESTAMP()');
    $q->execute([$owner,$editor,bin2hex(random_bytes(16)),$base]);
}

function listing_draft_id(mixed $raw): int
{
    if (!is_string($raw)||!preg_match('/\A(?:0|[1-9][0-9]{0,18})\z/',$raw)) throw new InvalidArgumentException(t('draft.invalid'));
    $id=filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
    if ($id===false) throw new InvalidArgumentException(t('draft.invalid'));
    return $id;
}

function listing_draft_form_values(PDO $pdo,array $listing): array
{
    $decimal=static fn(int $amount):string=>intdiv($amount,100).'.'.str_pad((string)($amount%100),2,'0',STR_PAD_LEFT);
    $values=array_map(static fn($value):string=>(string)($value??''),$listing);
    $values['price']=$decimal((int)$listing['price_cents']);
    $values['shipping_price']=$decimal((int)$listing['shipping_cents']);
    $values['buyout_price']=$listing['buyout_cents']===null?'':$decimal((int)$listing['buyout_cents']);
    $values['attributes']=array_column(classification_entries($pdo,(int)$listing['id']),'value','id');
    $values['duration_days']='7';
    $values['status']=$listing['status']==='draft'?'draft':'active';
    return $values;
}

function listing_draft_context(PDO $pdo,int $owner,int $editor,?array $source,array $images): array
{
    $workspace=listing_draft_workspace($pdo,$owner,$editor);
    $base=listing_draft_base($source,$images);
    return ['base'=>$base,'revision'=>$workspace['revision']??'0','payload'=>$workspace['payload']??null,
        'stale'=>isset($workspace['payload'])&&!hash_equals($base,$workspace['base_hash']),
        'images'=>listing_draft_image_count($pdo,$owner,$editor),'editor'=>$editor];
}

function listing_draft_editor_url(int $editor): string
{
    return $editor===0?'/user/create-listing.php':'/user/edit-listing.php?id='.$editor;
}
