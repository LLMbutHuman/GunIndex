<?php
declare(strict_types=1);

/** Single-item goods contracts, deliberately independent of provider payment state. */
function trade_integer(mixed $value,int $minimum=1,int $maximum=PHP_INT_MAX): int
{
    if ((!is_int($value)&&!is_string($value)) || !preg_match('/\A(?:0|[1-9][0-9]*)\z/',(string)$value)
        || strlen((string)$value)>strlen((string)PHP_INT_MAX)
        || (strlen((string)$value)===strlen((string)PHP_INT_MAX)&&strcmp((string)$value,(string)PHP_INT_MAX)>0)
        || (int)$value<$minimum || (int)$value>$maximum) throw new InvalidArgumentException(t('trade.invalid'));
    return (int)$value;
}

function trade_hex(mixed $value,int $length=32): string
{
    if (!is_string($value)||!preg_match('/\A[a-f0-9]{'.$length.'}\z/',$value)) throw new InvalidArgumentException(t('trade.invalid'));
    return $value;
}

function trade_json(array $value): string { return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
function trade_listing_hash(array $listing): string
{
    $fields=['id','seller_id','category_id','title','description','type','price_cents','shipping_cents','pickup','shipping',
        'country_code','zip','city','condition_label','ends_at'];
    $data=[]; foreach($fields as $key) $data[$key]=isset($listing[$key])?(string)$listing[$key]:null;
    if(isset($listing['attribute_digest']))$data['attribute_digest']=$listing['attribute_digest'];
    return hash('sha256',trade_json($data));
}

function trade_delivery(array $listing,string $delivery): int
{
    if (!in_array($delivery,['pickup','shipping'],true)||(int)($listing[$delivery]??0)!==1) throw new InvalidArgumentException(t('trade.unavailable'));
    return $delivery==='shipping'?trade_integer($listing['shipping_cents']??0,0,9999999999):0;
}

/** Retry only rolled-back database work, never a provider call or an uncertain commit. */
function trade_transaction(PDO $pdo,callable $work): mixed
{
    if($pdo->inTransaction()) throw new LogicException('Trade transactions may not be nested.');
    for($attempt=0;;$attempt++){
        $committing=false;
        try {
            $pdo->beginTransaction(); maintenance_assert_market_available($pdo);
            $result=$work(); $committing=true; $pdo->commit(); return $result;
        } catch(Throwable $error){
            if($pdo->inTransaction()) $pdo->rollBack();
            if(!$committing&&$attempt<2&&$error instanceof PDOException
                &&($error->getCode()==='40001'||in_array((int)($error->errorInfo[1]??0),[1205,1213],true))){usleep(20000*($attempt+1));continue;}
            throw $error;
        }
    }
}

function trade_listing_available(PDO $pdo,int $id): bool
{
    if (escrow_listing_reserved($pdo,$id)) return false;
    $q=$pdo->prepare("SELECT id FROM market_trades WHERE listing_id=? AND status<>'cancelled' LIMIT 1".($pdo->inTransaction()?' FOR SHARE':''));
    $q->execute([$id]); return $q->fetchColumn()===false;
}

function trade_listing(PDO $pdo,int $actor,int $id,bool $lock=false): array
{
    trade_integer($actor); trade_integer($id);
    if($lock) $listing=market_lock_listing($pdo,$id);
    else { $q=$pdo->prepare('SELECT * FROM market_listings WHERE id=?');$q->execute([$id]);$listing=$q->fetch(); }
    if(!$listing||!in_array($listing['type'],['fixed','negotiated'],true)||$listing['status']!=='active'||(int)$listing['trade_checkout_enabled']!==1
        ||($listing['ends_at']!==null&&strtotime($listing['ends_at'].' UTC')<=time())) throw new DomainException(t('trade.unavailable'));
    market_assert_users_active($pdo,[$actor,(int)$listing['seller_id']]);
    if(!($lock?market_category_locked_allowed($pdo,(int)$listing['category_id']):catalog_category_allowed($pdo,(int)$listing['category_id']))
        ||!($lock?geo_country_locked_enabled($pdo,$listing['country_code']):geo_country_enabled($pdo,$listing['country_code']))) throw new DomainException(t('trade.unavailable'));
    return $listing;
}

function trade_offer_row(PDO $pdo,int $id,bool $lock=false): array
{
    $q=$pdo->prepare('SELECT * FROM market_trade_offers WHERE id=?'.($lock?' FOR UPDATE':''));$q->execute([$id]);$row=$q->fetch();
    if(!$row) throw new DomainException(t('trade.unavailable')); return $row;
}

function trade_offer_live(array $offer,array $listing): void
{
    if((int)$offer['listing_id']!==(int)$listing['id']||$offer['status']!=='open'||strtotime($offer['expires_at'].' UTC')<=time()) throw new DomainException(t('trade.expired'));
    if(!hash_equals($offer['listing_hash'],trade_listing_hash($listing))) throw new DomainException(t('trade.changed'));
}

function trade_quote_for(PDO $pdo,int $actor,array $listing,string $delivery,?int $offerId,bool $lock): array
{
    $offer=null; $shipping=trade_delivery($listing,$delivery); $price=trade_integer($listing['price_cents'],1,9999999999);
    if($offerId!==null){
        $offer=trade_offer_row($pdo,trade_integer($offerId),$lock); trade_offer_live($offer,$listing);
        if(!in_array($actor,[(int)$offer['seller_id'],(int)$offer['buyer_id']],true)||(int)$offer['proposer_id']===$actor) throw new DomainException(t('trade.forbidden'));
        if($delivery!==$offer['delivery']||$shipping!==(int)$offer['shipping_cents']) throw new DomainException(t('trade.changed'));
        $price=(int)$offer['price_cents'];
    } elseif($listing['type']!=='fixed'||(int)$listing['seller_id']===$actor) throw new DomainException(t('trade.forbidden'));
    if(chat_blocked($pdo,(int)$listing['seller_id'],$offer?(int)$offer['buyer_id']:$actor))throw new DomainException(t('trade.forbidden'));
    $locale=i18n_locale();$documents=consent_documents($pdo,'checkout',$locale,$lock);
    if($offer!==null){
        $proposed=consent_documents($pdo,'checkout',$offer['consent_locale'],$lock);
        if(!hash_equals($offer['consent_hash'],trade_consent_hash($proposed)))throw new DomainException(t('trade.changed'));
    }
    $attributes=classification_entries($pdo,(int)$listing['id']);
    if($offer!==null){
        $proposedSnapshot=json_decode(crypto_decrypt('trade_snapshot',$offer['snapshot_cipher']),true,32,JSON_THROW_ON_ERROR);
        if(($proposedSnapshot['attributes']??[])!==$attributes)throw new DomainException(t('trade.changed'));
    }
    $stock=dealer_checkout_stock_row($pdo,(int)$listing['seller_id'],(int)$listing['id'],$lock);
    if($stock!==null&&(int)$stock['quantity']===0)throw new DomainException(t('dealer_checkout.sold_out'));
    $terms=[trade_listing_hash($listing),$actor,$delivery,$price,$shipping,$offerId,
        $offer['proposer_id']??null,$offer['expires_at']??null,$locale,array_column($documents,'content_hash','id')];
    if($attributes)$terms[]=$attributes;
    if($stock!==null)$terms[]=['stock_revision'=>$stock['revision']];
    $hash=hash('sha256',trade_json($terms));
    return ['listing'=>$listing,'hash'=>$hash,'documents'=>$documents,'locale'=>$locale,'delivery'=>$delivery,
        'price_cents'=>$price,'shipping_cents'=>$shipping,'total_cents'=>$price+$shipping,'offer'=>$offer,'attributes'=>$attributes,'dealer_stock'=>$stock];
}

function trade_quote(PDO $pdo,int $actor,int $listing,string $delivery,?int $offerId=null): array
{
    return trade_quote_for($pdo,$actor,trade_listing($pdo,$actor,$listing),$delivery,$offerId,false);
}

function trade_snapshot(array $listing,int $buyer,string $delivery,int $price,int $shipping): array
{
    return ['listing_id'=>(int)$listing['id'],'title'=>$listing['title'],'description'=>$listing['description'],
        'seller_id'=>(int)$listing['seller_id'],'buyer_id'=>$buyer,'type'=>$listing['type'],'delivery'=>$delivery,
        'price_cents'=>$price,'shipping_cents'=>$shipping,'total_cents'=>$price+$shipping,'currency'=>'EUR',
        'condition_label'=>$listing['condition_label'],'country_code'=>$listing['country_code']];
}

function trade_receipts(PDO $pdo,int $actor,?int $trade,?int $offer,array $documents): void
{
    $q=$pdo->prepare('INSERT INTO market_trade_consents(trade_id,offer_id,user_id,document_id,content_hash,locale) VALUES (?,?,?,?,?,?)');
    foreach($documents as $doc){legal_accept($pdo,$actor,(int)$doc['id'],'checkout');$q->execute([$trade,$offer,$actor,$doc['id'],$doc['content_hash'],$doc['language']]);}
}

function trade_consent_hash(array $documents): string
{
    return hash('sha256',trade_json(array_column($documents,'content_hash','id')));
}

function trade_request_hash(string $operation,int $id,?int $related,array $input): string
{
    // Canonical request body: POST ordering cannot change retry identity.
    $canonical=static function(array $data) use (&$canonical):array {ksort($data);foreach($data as &$value)if(is_array($value))$value=$canonical($value);unset($value);return $data;};
    unset($input['csrf_token'],$input['csrf']);
    try {return hash('sha256',trade_json([$operation,$id,$related,$canonical($input)]));}
    catch(JsonException){throw new InvalidArgumentException(t('trade.invalid'));}
}

function trade_purchase(PDO $pdo,int $actor,int $listing,array $input,?int $offerId=null): int
{
    $key=trade_hex($input['request_key']??null);$hash=trade_request_hash('purchase',$listing,$offerId,$input);
    if(($input['confirm_purchase']??null)!=='1') throw new InvalidArgumentException(t('trade.confirm'));
    return trade_transaction($pdo,function()use($pdo,$actor,$listing,$input,$offerId,$key,$hash):int {
        market_lock_listing($pdo,$listing);
        $q=$pdo->prepare('SELECT id,request_hash FROM market_trades WHERE request_actor=? AND request_key=? FOR UPDATE');$q->execute([$actor,$key]);$old=$q->fetch();
        if($old){if(!hash_equals($old['request_hash'],$hash))throw new DomainException(t('trade.duplicate'));return (int)$old['id'];}
        $row=trade_listing($pdo,$actor,$listing,true);
        if (escrow_listing_reserved($pdo,$listing)) throw new DomainException(t('trade.unavailable'));
        $quote=trade_quote_for($pdo,$actor,$row,market_input($input,'delivery'),$offerId,true);
        if(!hash_equals($quote['hash'],trade_hex($input['quote_hash']??null,64)))throw new DomainException(t('trade.changed'));
        consent_validate_submission($quote['documents'],$input,$quote['locale']);
        $buyer=$quote['offer']?(int)$quote['offer']['buyer_id']:$actor;
        market_assert_users_active($pdo,[$buyer,(int)$row['seller_id']]);
        if(chat_blocked($pdo,(int)$row['seller_id'],$buyer))throw new DomainException(t('trade.forbidden'));
        $q=$pdo->prepare('SELECT id FROM market_trades WHERE listing_id=? FOR UPDATE');$q->execute([$listing]);
        if($q->fetchColumn()!==false)throw new DomainException(t('trade.unavailable'));
        $snapshot=trade_snapshot($row,$buyer,$quote['delivery'],$quote['price_cents'],$quote['shipping_cents']);
        $snapshot['attributes']=$quote['attributes'];
        $q=$pdo->prepare('INSERT INTO market_trades(reference,listing_id,seller_id,buyer_id,offer_id,delivery,price_cents,shipping_cents,total_cents,snapshot_cipher,request_actor,request_key,request_hash) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute(['GT-'.strtoupper(bin2hex(random_bytes(10))),$listing,$row['seller_id'],$buyer,$offerId,$quote['delivery'],$quote['price_cents'],$quote['shipping_cents'],$quote['total_cents'],crypto_encrypt('trade_snapshot',trade_json($snapshot)),$actor,$key,$hash]);
        $id=(int)$pdo->lastInsertId();trade_receipts($pdo,$actor,$id,null,$quote['documents']);
        dealer_checkout_reserve($pdo,$id,(int)$row['seller_id'],$quote['dealer_stock']);
        $pdo->prepare("UPDATE market_listings SET status='sold',updated_at=UTC_TIMESTAMP() WHERE id=?")->execute([$listing]);
        $pdo->prepare("UPDATE market_trade_offers SET status=CASE WHEN id=? THEN 'accepted' ELSE 'expired' END WHERE listing_id=? AND status='open'")->execute([$offerId,$listing]);
        trade_event($pdo,$id,$actor,'purchase',null,'agreed',1,$key,$hash,'');
        audit_log($pdo,$actor,'trade.created',$id,['listing_id'=>$listing,'offer_id'=>$offerId]);
        trade_notify($pdo,(int)$row['seller_id'],$buyer,'purchase:'.$id,'/user/trade.php?id='.$id);
        return $id;
    });
}

function trade_offer_create(PDO $pdo,int $actor,int $listing,array $input): int
{
    $key=trade_hex($input['request_key']??null);$hash=trade_request_hash('offer',$listing,null,$input);
    if(($input['confirm_offer']??null)!=='1')throw new InvalidArgumentException(t('trade.confirm'));
    $amount=money_parse(market_input($input,'amount'));if($amount<1)throw new InvalidArgumentException(t('trade.invalid'));
    $hours=trade_integer($input['hours']??null,1,168);
    $parent=isset($input['parent_id'])&&$input['parent_id']!==''?trade_integer($input['parent_id']):null;
    return trade_transaction($pdo,function()use($pdo,$actor,$listing,$input,$key,$hash,$amount,$hours,$parent):int {
        market_lock_listing($pdo,$listing);
        $q=$pdo->prepare('SELECT id,request_hash FROM market_trade_offers WHERE proposer_id=? AND request_key=? FOR UPDATE');$q->execute([$actor,$key]);$old=$q->fetch();
        if($old){if(!hash_equals($old['request_hash'],$hash))throw new DomainException(t('trade.duplicate'));return (int)$old['id'];}
        $row=trade_listing($pdo,$actor,$listing,true);
        if($row['type']!=='negotiated')throw new DomainException(t('trade.forbidden'));
        if(!hash_equals(trade_listing_hash($row),trade_hex($input['listing_hash']??null,64)))throw new DomainException(t('trade.changed'));
        $buyer=$actor;
        if($parent!==null){
            $previous=trade_offer_row($pdo,$parent,true);trade_offer_live($previous,$row);
            if(!in_array($actor,[(int)$previous['buyer_id'],(int)$previous['seller_id']],true)||(int)$previous['proposer_id']===$actor)throw new DomainException(t('trade.forbidden'));
            $buyer=(int)$previous['buyer_id'];
        } elseif($actor===(int)$row['seller_id'])throw new DomainException(t('trade.forbidden'));
        market_assert_users_active($pdo,[$buyer,(int)$row['seller_id']]);
        $pdo->prepare("UPDATE market_trade_offers SET status='expired' WHERE listing_id=? AND status='open' AND (expires_at<=UTC_TIMESTAMP() OR listing_hash<>?)")
            ->execute([$listing,trade_listing_hash($row)]);
        $q=$pdo->prepare("SELECT id FROM market_trade_offers WHERE listing_id=? AND buyer_id=? AND status='open' FOR UPDATE");$q->execute([$listing,$buyer]);
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $open)if((int)$open!==$parent)throw new DomainException(t('trade.duplicate'));
        $delivery=market_input($input,'delivery');$shipping=trade_delivery($row,$delivery);
        if(chat_blocked($pdo,(int)$row['seller_id'],$buyer))throw new DomainException(t('trade.forbidden'));
        $locale=i18n_locale();$documents=consent_check($pdo,'checkout',$input,$locale);
        if($parent!==null)$pdo->prepare("UPDATE market_trade_offers SET status='countered' WHERE id=?")->execute([$parent]);
        $snapshot=trade_snapshot($row,$buyer,$delivery,$amount,$shipping);
        $snapshot['attributes']=classification_entries($pdo,$listing);
        $q=$pdo->prepare('INSERT INTO market_trade_offers(listing_id,seller_id,buyer_id,proposer_id,parent_id,delivery,price_cents,shipping_cents,total_cents,listing_hash,snapshot_cipher,request_key,request_hash,expires_at,consent_locale,consent_hash) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$listing,$row['seller_id'],$buyer,$actor,$parent,$delivery,$amount,$shipping,$amount+$shipping,trade_listing_hash($row),crypto_encrypt('trade_snapshot',trade_json($snapshot)),$key,$hash,gmdate('Y-m-d H:i:s',time()+$hours*3600),$locale,trade_consent_hash($documents)]);
        $id=(int)$pdo->lastInsertId();trade_receipts($pdo,$actor,null,$id,$documents);
        audit_log($pdo,$actor,'trade.offer_created',$id,['listing_id'=>$listing,'parent_id'=>$parent]);
        trade_notify($pdo,(int)$row['seller_id'],$buyer,'offer:'.$id,'/user/offers.php?listing_id='.$listing);return $id;
    });
}

function trade_offer_action(PDO $pdo,int $actor,int $offer,string $action): void
{
    if(!in_array($action,['reject','withdraw'],true))throw new InvalidArgumentException(t('trade.invalid'));
    $hint=trade_offer_row($pdo,$offer);
    trade_transaction($pdo,function()use($pdo,$actor,$offer,$action,$hint):void {
        market_lock_listing($pdo,(int)$hint['listing_id']);$row=trade_offer_row($pdo,$offer,true);
        market_assert_users_active($pdo,[$actor]);
        $proposer=(int)$row['proposer_id']===$actor;
        if(!in_array($actor,[(int)$row['buyer_id'],(int)$row['seller_id']],true)||($action==='withdraw'?!$proposer:$proposer))throw new DomainException(t('trade.forbidden'));
        $target=$action==='reject'?'rejected':'withdrawn';if($row['status']===$target)return;
        if($row['status']!=='open')throw new DomainException(t('trade.changed'));
        $pdo->prepare('UPDATE market_trade_offers SET status=? WHERE id=?')->execute([$target,$offer]);
        audit_log($pdo,$actor,'trade.offer_'.$target,$offer,[]);
        trade_notify($pdo,(int)$row['seller_id'],(int)$row['buyer_id'],$target.':'.$offer,'/user/offers.php?listing_id='.(int)$row['listing_id']);
    });
}

function trade_offer_context(PDO $pdo,int $actor,int $listing): array
{
    trade_integer($actor);$q=$pdo->prepare('SELECT * FROM market_listings WHERE id=?');$q->execute([$listing]);$row=$q->fetch();
    if(!$row||$row['type']!=='negotiated'||!catalog_category_allowed($pdo,(int)$row['category_id'])||!geo_country_enabled($pdo,$row['country_code']))throw new DomainException(t('trade.unavailable'));
    market_assert_users_active($pdo,[$actor,(int)$row['seller_id']]);
    if($row['status']!=='active'&&(int)$row['seller_id']!==$actor){
        $q=$pdo->prepare('SELECT id FROM market_trade_offers WHERE listing_id=? AND buyer_id=? LIMIT 1');$q->execute([$listing,$actor]);
        if($q->fetchColumn()===false)throw new DomainException(t('trade.unavailable'));
    }
    $q=$pdo->prepare('SELECT o.*,l.title FROM market_trade_offers o JOIN market_listings l ON l.id=o.listing_id WHERE o.listing_id=? AND (o.seller_id=? OR o.buyer_id=?) ORDER BY o.id DESC LIMIT 100');$q->execute([$listing,$actor,$actor]);
    $offers=$q->fetchAll();foreach($offers as &$offer)$offer['actionable']=(int)$row['trade_checkout_enabled']===1&&$row['status']==='active'&&($row['ends_at']===null||strtotime($row['ends_at'].' UTC')>time())&&$offer['status']==='open'&&strtotime($offer['expires_at'].' UTC')>time()&&hash_equals($offer['listing_hash'],trade_listing_hash($row));unset($offer);
    return ['listing'=>$row,'hash'=>trade_listing_hash($row),'locale'=>i18n_locale(),'documents'=>consent_documents($pdo,'checkout',i18n_locale()),'offers'=>$offers];
}

function trade_allowed_actions(array $row,int $actor,bool $staff=false): array
{
    if($staff&&$row['status']==='disputed'&&!in_array($actor,[(int)$row['seller_id'],(int)$row['buyer_id']],true))return ['resolve_complete','resolve_cancel'];
    if(!in_array($actor,[(int)$row['seller_id'],(int)$row['buyer_id']],true))return [];
    $status=$row['status'];$actions=[];
    if(in_array($status,['agreed','dispatched'],true)){
        if($actor===(int)$row['seller_id']){
            if($status==='agreed')$actions[]='dispatch';
            if(!(int)$row['seller_confirmed'])$actions[]='settle';
        } elseif($status==='dispatched'&&!(int)$row['buyer_confirmed'])$actions[]='receive';
        $actions[]='cancel_request';$actions[]='dispute';
    } elseif($status==='cancel_requested'){
        if($actor!==(int)$row['requested_by']){$actions[]='cancel_accept';$actions[]='cancel_decline';}
        $actions[]='dispute';
    } elseif($status==='completed')$actions[]='dispute';
    return $actions;
}

function trade_row(PDO $pdo,int $id,bool $lock=false): array
{
    $q=$pdo->prepare('SELECT * FROM market_trades WHERE id=?'.($lock?' FOR UPDATE':''));$q->execute([$id]);$row=$q->fetch();
    if(!$row)throw new DomainException(t('trade.unavailable'));return $row;
}

function trade_get(PDO $pdo,int $actor,int $id): array
{
    $row=trade_row($pdo,$id);
    if(!in_array($actor,[(int)$row['seller_id'],(int)$row['buyer_id']],true))throw new DomainException(t('trade.forbidden'));
    return trade_details($pdo,$row,$actor);
}

/** Caller must authorize parties or MFA reports.review before requesting private details. */
function trade_details(PDO $pdo,array $row,int $actor): array
{
    if(!in_array($actor,[(int)$row['seller_id'],(int)$row['buyer_id']],true)){
        governance_assert_actor($pdo,$actor,'reports.review');
        $q=$pdo->prepare("SELECT 1 FROM market_trade_events WHERE trade_id=? AND action='dispute' LIMIT 1");$q->execute([$row['id']]);
        if(!$q->fetchColumn())throw new DomainException(t('trade.forbidden'));
        audit_log($pdo,$actor,'trade.dispute_viewed',(int)$row['id']);
    }
    $row['snapshot']=json_decode(crypto_decrypt('trade_snapshot',$row['snapshot_cipher']),true,32,JSON_THROW_ON_ERROR);
    $q=$pdo->prepare('SELECT * FROM market_trade_events WHERE trade_id=? ORDER BY id');$q->execute([$row['id']]);$row['events']=$q->fetchAll();
    foreach($row['events'] as &$event){$event['details']=crypto_decrypt('trade_event',$event['details_cipher']);unset($event['details_cipher'],$event['request_hash'],$event['request_key']);}unset($event);
    $q=$pdo->prepare("SELECT * FROM market_ratings WHERE trade_id=? AND status='active' ORDER BY id");$q->execute([$row['id']]);$row['ratings']=$q->fetchAll();
    $q=$pdo->prepare('SELECT 1 FROM market_ratings WHERE listing_id=? AND sender_id=?');
    $q->execute([$row['listing_id'],$actor]);
    $row['rating_submitted']=$q->fetchColumn()!==false;
    $row['actions']=trade_allowed_actions($row,$actor);unset($row['snapshot_cipher'],$row['request_key'],$row['request_hash']);return $row;
}

function trade_list(PDO $pdo,int $actor,int $page=1): array
{
    trade_integer($actor);$page=max(1,min(10000,$page));
    $q=$pdo->prepare('SELECT t.*,l.title FROM market_trades t JOIN market_listings l ON l.id=t.listing_id WHERE t.buyer_id=? OR t.seller_id=? ORDER BY t.id DESC LIMIT 25 OFFSET '.(($page-1)*25));$q->execute([$actor,$actor]);$trades=$q->fetchAll();
    $q=$pdo->prepare("SELECT o.*,l.title,l.status AS listing_status,l.ends_at AS listing_ends_at FROM market_trade_offers o JOIN market_listings l ON l.id=o.listing_id WHERE (o.buyer_id=? OR o.seller_id=?) AND o.status='open' ORDER BY o.id DESC LIMIT 100");$q->execute([$actor,$actor]);$offers=$q->fetchAll();
    foreach($offers as &$offer)$offer['actionable']=$offer['listing_status']==='active'&&strtotime($offer['expires_at'].' UTC')>time()&&($offer['listing_ends_at']===null||strtotime($offer['listing_ends_at'].' UTC')>time());unset($offer);
    return ['trades'=>$trades,'offers'=>$offers,'page'=>$page];
}

function trade_event(PDO $pdo,int $id,int $actor,string $action,?string $from,string $to,int $version,string $key,string $hash,string $details): void
{
    $q=$pdo->prepare('INSERT INTO market_trade_events(trade_id,actor_id,action,from_status,to_status,version_after,request_key,request_hash,details_cipher) VALUES (?,?,?,?,?,?,?,?,?)');
    $q->execute([$id,$actor,$action,$from,$to,$version,$key,$hash,crypto_encrypt('trade_event',$details)]);
}

function trade_transition(PDO $pdo,int $actor,int $id,string $action,array $input): void
{
    $key=trade_hex($input['request_key']??null);$version=trade_integer($input['version']??null,1,4294967294);
    if(($input['confirm_action']??null)!=='1')throw new InvalidArgumentException(t('trade.confirm'));
    $details=trim(market_input($input,'details'));if(!mb_check_encoding($details,'UTF-8')||mb_strlen($details)>1000||($action==='dispute'&&$details===''))throw new InvalidArgumentException(t('trade.invalid'));
    $hash=trade_request_hash($action,$id,null,$input);$hint=trade_row($pdo,$id);
    trade_transaction($pdo,function()use($pdo,$actor,$id,$action,$key,$version,$details,$hash,$hint):void {
        market_lock_listing($pdo,(int)$hint['listing_id']);$row=trade_row($pdo,$id,true);market_assert_users_active($pdo,[$actor]);
        $staff=in_array($action,['resolve_complete','resolve_cancel'],true);
        if($staff){governance_assert_actor($pdo,$actor,'reports.review');if(mb_strlen($details)<10)throw new InvalidArgumentException(t('trade.invalid'));}
        elseif(!in_array($actor,[(int)$row['buyer_id'],(int)$row['seller_id']],true))throw new DomainException(t('trade.forbidden'));
        $q=$pdo->prepare('SELECT trade_id,request_hash FROM market_trade_events WHERE actor_id=? AND request_key=? FOR UPDATE');$q->execute([$actor,$key]);$old=$q->fetch();
        if($old){if((int)$old['trade_id']!==$id||!hash_equals($old['request_hash'],$hash))throw new DomainException(t('trade.duplicate'));return;}
        if((int)$row['version']!==$version)throw new DomainException(t('trade.changed'));
        if(!in_array($action,trade_allowed_actions($row,$actor,$staff),true))throw new DomainException(t('trade.forbidden'));
        $from=$row['status'];$to=$from;$previous=$row['previous_status'];$requested=$row['requested_by'];
        $seller=(int)$row['seller_confirmed'];$buyer=(int)$row['buyer_confirmed'];
        switch($action){
            case 'dispatch':$to='dispatched';break;
            case 'receive':$buyer=1;break;
            case 'settle':$seller=1;break;
            case 'cancel_request':$previous=$from;$requested=$actor;$to='cancel_requested';break;
            case 'cancel_accept':$to='cancelled';$previous=null;$requested=null;break;
            case 'cancel_decline':$to=$previous;$previous=null;$requested=null;break;
            case 'dispute':$previous=$from;$requested=$actor;$to='disputed';break;
            case 'resolve_complete':$to='completed';$previous=null;$requested=null;break;
            case 'resolve_cancel':$to='cancelled';$previous=null;$requested=null;break;
        }
        if(in_array($to,['agreed','dispatched'],true)&&$seller&&$buyer)$to='completed';
        if($to==='cancelled')dealer_checkout_cancel($pdo,$id,(int)$row['seller_id']);
        $pdo->prepare('UPDATE market_trades SET status=?,version=version+1,seller_confirmed=?,buyer_confirmed=?,previous_status=?,requested_by=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$to,$seller,$buyer,$previous,$requested,$id]);
        trade_event($pdo,$id,$actor,$action,$from,$to,$version+1,$key,$hash,$details);
        audit_log($pdo,$actor,'trade.'.$action,$id,['from'=>$from,'to'=>$to]);
        trade_notify($pdo,(int)$row['seller_id'],(int)$row['buyer_id'],'event:'.$id.':'.($version+1),'/user/trade.php?id='.$id);
    });
}

function trade_rate(PDO $pdo,int $actor,int $id,int $stars,string $comment): void
{
    $comment=trim($comment);if($stars<1||$stars>5||!mb_check_encoding($comment,'UTF-8')||mb_strlen($comment)>1000||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$comment))throw new InvalidArgumentException(t('trade.invalid'));
    $hint=trade_row($pdo,$id);
    trade_transaction($pdo,function()use($pdo,$actor,$id,$stars,$comment,$hint):void {
        market_lock_listing($pdo,(int)$hint['listing_id']);$row=trade_row($pdo,$id,true);market_assert_users_active($pdo,[$actor]);
        if($row['status']!=='completed'||!in_array($actor,[(int)$row['buyer_id'],(int)$row['seller_id']],true))throw new DomainException(t('trade.forbidden'));
        $q=$pdo->prepare('SELECT id FROM market_ratings WHERE listing_id=? AND sender_id=? FOR UPDATE');$q->execute([$row['listing_id'],$actor]);
        if($q->fetchColumn()!==false)throw new DomainException(t('trade.duplicate'));
        $receiver=$actor===(int)$row['seller_id']?(int)$row['buyer_id']:(int)$row['seller_id'];
        if ($receiver===$actor) throw new DomainException(t('trade.forbidden'));
        $pdo->prepare('INSERT INTO market_ratings(listing_id,trade_id,sender_id,receiver_id,stars,comment) VALUES (?,?,?,?,?,?)')->execute([$row['listing_id'],$id,$actor,$receiver,$stars,$comment]);
    });
}

function trade_public_ratings(PDO $pdo, int $user, int $page): array
{
    $from = " FROM market_ratings r JOIN market_listings l ON l.id=r.listing_id
        JOIN users s ON s.id=r.sender_id WHERE r.receiver_id=? AND r.status='active' AND r.sender_id<>r.receiver_id
        AND ((r.trade_id IS NULL AND l.status IN ('ended','sold')) OR EXISTS (
            SELECT 1 FROM market_trades rt WHERE rt.id=r.trade_id AND rt.listing_id=r.listing_id AND rt.status='completed'
            AND ((rt.buyer_id=r.sender_id AND rt.seller_id=r.receiver_id) OR (rt.seller_id=r.sender_id AND rt.buyer_id=r.receiver_id))
        ))";
    $summary = $pdo->prepare('SELECT COUNT(*) AS count,AVG(r.stars) AS average'.$from);
    $summary->execute([$user]);
    $summary = $summary->fetch();
    $rows = $pdo->prepare('SELECT r.id,r.sender_id,r.stars,r.comment,r.created_at,s.username'.$from.' ORDER BY r.id DESC LIMIT 20 OFFSET ?');
    $rows->bindValue(1,$user,PDO::PARAM_INT);
    $rows->bindValue(2,(max(1,min(10000,$page))-1)*20,PDO::PARAM_INT);
    $rows->execute();
    return ['count'=>(int)$summary['count'],'average'=>$summary['average'],'rows'=>$rows->fetchAll()];
}

function trade_auction_rating_recipient(PDO $pdo, array $listing, int $actor): ?int
{
    $seller = (int)$listing['seller_id'];
    $winner = (int)($listing['highest_bidder_id'] ?? 0);
    if ($winner < 1 || $seller === $winner || !in_array($actor,[$seller,$winner],true) || $listing['type'] !== 'auction'
        || !in_array($listing['status'],['ended','sold'],true)) return null;
    $eventKey = ($listing['status'] === 'sold' ? 'buyout-' : 'close-').$listing['id'];
    $query = $pdo->prepare('SELECT event_type,idempotency_key,user_id,amount_cents FROM market_bids WHERE listing_id=? AND idempotency_key=?');
    $query->execute([$listing['id'],$eventKey]);
    $event = $query->fetch();
    if (!$event) return null;
    $proxy = $pdo->prepare('SELECT 1 FROM market_proxy_bids WHERE listing_id=? LIMIT 1');
    $proxy->execute([$listing['id']]);
    if (!relisting_auction_sale_eligible($listing,$event,$proxy->fetchColumn()!==false)) return null;
    return $actor === $seller ? $winner : $seller;
}

function trade_rate_auction(PDO $pdo, int $actor, int $listingId, int $stars, string $comment): void
{
    $comment = trim($comment);
    if ($stars < 1 || $stars > 5 || strlen($comment) > 3000 || !preg_match('//u',$comment)
        || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$comment)) throw new InvalidArgumentException(t('trade.auction_rating_invalid'));
    trade_transaction($pdo,static function () use ($pdo,$actor,$listingId,$stars,$comment): void {
        $listing = market_lock_listing($pdo,$listingId);
        market_assert_users_active($pdo,[$actor]);
        $recipient = trade_auction_rating_recipient($pdo,$listing,$actor);
        if ($recipient === null) throw new MarketAccessException(t('trade.auction_rating_parties'));
        try {
            $pdo->prepare('INSERT INTO market_ratings(listing_id,sender_id,receiver_id,stars,comment) VALUES (?,?,?,?,?)')
                ->execute([$listingId,$actor,$recipient,$stars,$comment]);
        } catch (PDOException $error) {
            if ((int)($error->errorInfo[1] ?? 0) !== 1062) throw $error;
            throw new MarketConflictException(t('trade.duplicate'));
        }
    });
}

function trade_expire(PDO $pdo,int $limit=100): int
{
    $limit=trade_integer($limit,1,1000);$count=0;
    $ids=$pdo->query("SELECT DISTINCT listing_id FROM market_trade_offers WHERE status='open' AND expires_at<=UTC_TIMESTAMP() ORDER BY listing_id LIMIT ".$limit)->fetchAll(PDO::FETCH_COLUMN);
    foreach($ids as $id)$count+=trade_transaction($pdo,function()use($pdo,$id):int{
        market_lock_listing($pdo,(int)$id);$q=$pdo->prepare("UPDATE market_trade_offers SET status='expired' WHERE listing_id=? AND status='open' AND expires_at<=UTC_TIMESTAMP()");$q->execute([$id]);return $q->rowCount();
    });return $count;
}

function trade_notify(PDO $pdo,int $seller,int $buyer,string $event,string $path): void
{
    foreach([$seller,$buyer] as $recipient)notify_user($pdo,$recipient,'trade:'.$event.':'.$recipient,
        i18n_user_text($pdo,$recipient,'trade.updated_title'),i18n_user_text($pdo,$recipient,'trade.updated_body'),$path,true);
}

/** The existing scheduler processes both auction and general listing deadlines. */
function trade_close_due(PDO $pdo,int $limit=100): int
{
    $limit=trade_integer($limit,1,1000);$count=0;
    $ids=$pdo->query("SELECT id FROM market_listings WHERE type IN ('fixed','negotiated') AND status='active' AND ends_at<=UTC_TIMESTAMP() ORDER BY ends_at,id LIMIT ".$limit)->fetchAll(PDO::FETCH_COLUMN);
    foreach($ids as $id)$count+=trade_transaction($pdo,function()use($pdo,$id):int{
        $row=market_lock_listing($pdo,(int)$id);
        if(!in_array($row['type'],['fixed','negotiated'],true)||$row['status']!=='active'||$row['ends_at']===null||strtotime($row['ends_at'].' UTC')>time())return 0;
        if(!trade_listing_available($pdo,(int)$id))return 0;
        $pdo->prepare("UPDATE market_listings SET status='ended',updated_at=UTC_TIMESTAMP() WHERE id=?")->execute([$id]);
        listing_alert_deadline($pdo,$row,'ended');
        $pdo->prepare("UPDATE market_trade_offers SET status='expired' WHERE listing_id=? AND status='open'")->execute([$id]);
        return 1;
    });return $count;
}

function trade_export_record(string $name,array $row): array
{
    if(in_array($name,['trades','trade_offers'],true))$row['snapshot']=json_decode(crypto_decrypt('trade_snapshot',$row['snapshot_cipher']),true,32,JSON_THROW_ON_ERROR);
    elseif($name==='trade_events')$row['details']=crypto_decrypt('trade_event',$row['details_cipher']);
    return $row;
}
