<?php
declare(strict_types=1);

final class MarketNotFoundException extends RuntimeException {}
final class MarketAccessException extends RuntimeException {}
final class MarketConflictException extends RuntimeException {}

/** All commerce reads use the new tables. Historical listings are never imported. */
function market_category_ids(PDO $pdo): array
{
    return array_map(static fn(array $row):int=>(int)$row['id'],catalog_categories($pdo,true));
}

function market_find(PDO $pdo, int $id, bool $includeEnded = true, ?array $origin = null): ?array
{
    if ($id < 1) {
        return null;
    }
    $q = $pdo->prepare('SELECT l.*,u.username AS seller_username,u.username AS seller_name,c.name AS category_name,(SELECT COUNT(*) FROM market_images i WHERE i.listing_id=l.id) AS image_count FROM market_listings l JOIN users u ON u.id=l.seller_id JOIN market_categories c ON c.id=l.category_id WHERE l.id=? AND u.is_active=1');
    $q->execute([$id]);
    $row = $q->fetch();
    if (!$row || !in_array($row['status'], $includeEnded ? ['active','ended','sold'] : ['active'], true)
        || !catalog_category_allowed($pdo, (int)$row['category_id']) || !geo_country_enabled($pdo, $row['country_code'])) {
        return null;
    }
    $row['distance_km']=null;
    if($origin!==null){$point=geo_postcode($pdo,$row['country_code'],$row['zip']);if($point&&geo_coordinates_valid($origin['latitude']??null,$origin['longitude']??null))$row['distance_km']=geo_distance_km((float)$origin['latitude'],(float)$origin['longitude'],(float)$point['latitude'],(float)$point['longitude']);}
    return $row;
}

function market_search(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 24): array
{
    $page=max(1,min(10000,$page));$perPage=max(1,min(48,$perPage));
    $categories=market_category_ids($pdo);
    $countryRaw=$filters['country_code']??null;
    if($countryRaw!==null&&!is_string($countryRaw))throw new InvalidArgumentException('Ungültiger Ländermarkt.');
    $country=geo_country($countryRaw===''?null:$countryRaw);
    $scope=$filters['scope']??'country';
    if(!is_string($scope)||!in_array($scope,['country','international'],true))throw new InvalidArgumentException('Ungültiger Suchbereich.');
    if(!$categories||($scope==='country'&&!geo_country_enabled($pdo,$country)))return ['items'=>[],'total'=>0,'page'=>$page,'per_page'=>$perPage,'country_code'=>$country,'scope'=>$scope];
    $where=["l.status='active'",'EXISTS(SELECT 1 FROM users seller WHERE seller.id=l.seller_id AND seller.is_active=1)',
        'EXISTS(SELECT 1 FROM market_countries mc WHERE mc.code=l.country_code AND mc.is_active=1)',
        'l.category_id IN ('.implode(',',array_fill(0,count($categories),'?')).')'];
    $parameters=$categories;
    if($scope==='country'){$where[]='l.country_code=?';$parameters[]=$country;}
    foreach(['listing_id','seller_id'] as $key){
        if(isset($filters[$key])&&$filters[$key]!==''){
            $value=$filters[$key];if(!is_scalar($value)||!ctype_digit((string)$value)||(int)$value<1)throw new InvalidArgumentException('Ungültiger Angebotsfilter.');
            $where[]='l.'.($key==='listing_id'?'id':'seller_id').'=?';$parameters[]=(int)$value;
        }
    }
    $query=$filters['q']??'';
    if(!is_string($query)||strlen($query)>200)throw new InvalidArgumentException('Die Suchanfrage ist zu lang.');
    if(trim($query)!==''){$where[]="(l.title LIKE ? ESCAPE '!' OR l.description LIKE ? ESCAPE '!')";$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],trim($query)).'%';$parameters[]=$term;$parameters[]=$term;}
    $category=$filters['category_id']??'';
    if($category!==''&&$category!==null){if(!is_scalar($category)||!ctype_digit((string)$category))throw new InvalidArgumentException('Ungültige Kategorie.');$where[]='l.category_id=?';$parameters[]=(int)$category;}
    $type=$filters['type']??'';
    if($type!==''&&$type!==null){if(!is_string($type)||!in_array($type,['fixed','auction'],true))throw new InvalidArgumentException('Ungültige Angebotsart.');$where[]='l.type=?';$parameters[]=$type;}
    foreach(['min_price'=>'>=','max_price'=>'<='] as $key=>$operator){
        $value=$filters[$key]??'';
        if($value!==''&&$value!==null){if(!is_string($value))throw new InvalidArgumentException('Ungültiger Preisfilter.');$where[]='COALESCE(l.current_bid_cents,l.price_cents) '.$operator.' ?';$parameters[]=money_parse($value);}
    }
    $city=$filters['city']??'';if($city!==''){if(!is_string($city)||strlen($city)>100)throw new InvalidArgumentException('Ungültiger Standortfilter.');$where[]='l.city=?';$parameters[]=trim($city);}
    $postal=$filters['zip']??'';$originCountryRaw=$filters['origin_country']??$country;
    if(!is_string($postal)||!is_string($originCountryRaw))throw new InvalidArgumentException('Ungültiger Standortfilter.');
    $originCountry=geo_country($originCountryRaw===''?$country:$originCountryRaw);
    $postal=trim($postal);
    if($postal!==''&&!geo_validate_postcode($originCountry,$postal))throw new InvalidArgumentException('Die Postleitzahl passt nicht zum ausgewählten Land.');
    $radiusRaw=$filters['radius_km']??'';$radius=null;
    if($radiusRaw!==''&&$radiusRaw!==null){
        if(!is_scalar($radiusRaw)||!preg_match('/\A[1-9][0-9]{0,3}\z/',(string)$radiusRaw)||(int)$radiusRaw>2000)throw new InvalidArgumentException('Der Suchradius muss zwischen 1 und 2000 km liegen.');
        $radius=(int)$radiusRaw;if($postal==='')throw new InvalidArgumentException('Für die Entfernungssuche ist eine Bezugs-Postleitzahl erforderlich.');
    }
    $origin=$postal!==''?geo_postcode($pdo,$originCountry,$postal):null;
    if($radius!==null&&$origin===null)throw new InvalidArgumentException('Für diese Postleitzahl liegen noch keine freigegebenen lokalen Koordinaten vor. Eine exakte PLZ-Suche ist weiterhin möglich.');
    $geoJoin='';$distanceSelect='NULL';$selectParameters=[];
    if($origin!==null){
        $geoJoin=" LEFT JOIN geo_locations gp ON gp.country_code=l.country_code AND gp.postal_code=REPLACE(REPLACE(l.zip,' ',''),'-','') LEFT JOIN geo_imports gi ON gi.id=gp.import_id AND gi.license_reviewed=1 AND gi.is_active=1";
        $distance="(6371.0088*2*ASIN(SQRT(LEAST(1.0,POW(SIN(RADIANS(gp.latitude-?)/2),2)+COS(RADIANS(?))*COS(RADIANS(gp.latitude))*POW(SIN(RADIANS(gp.longitude-?)/2),2)))))";
        $point=[(float)$origin['latitude'],(float)$origin['latitude'],(float)$origin['longitude']];
        $distanceSelect='CASE WHEN gi.id IS NOT NULL THEN '.$distance.' ELSE NULL END';$selectParameters=$point;
        if($radius!==null){
            $where[]='gi.id IS NOT NULL';
            $box=geo_bounding_box((float)$origin['latitude'],(float)$origin['longitude'],(float)$radius);
            $where[]='gp.latitude BETWEEN ? AND ?';$parameters[]=$box['min_lat'];$parameters[]=$box['max_lat'];
            // At a date-line/polar edge the exact distance still applies; do not wrongly exclude wrapped longitudes.
            if($box['min_lon']>=-180&&$box['max_lon']<=180){$where[]='gp.longitude BETWEEN ? AND ?';$parameters[]=$box['min_lon'];$parameters[]=$box['max_lon'];}
            $where[]=$distance.'<=?';array_push($parameters,...$point);$parameters[]=$radius;
        }
    }
    if($postal!==''&&$radius===null){$where[]='l.country_code=?';$parameters[]=$originCountry;$where[]="REPLACE(REPLACE(l.zip,' ',''),'-','')=?";$parameters[]=geo_postcode_key($originCountry,$postal);}
    $sort=$filters['sort']??'newest';
    $orders=['newest'=>'COALESCE(l.bumped_at,l.created_at) DESC,l.id DESC','price_asc'=>'COALESCE(l.current_bid_cents,l.price_cents),l.id DESC','price_desc'=>'COALESCE(l.current_bid_cents,l.price_cents) DESC,l.id DESC','ending'=>'l.ends_at IS NULL,l.ends_at,l.id','distance'=>'distance_km IS NULL,distance_km,l.id DESC'];
    if(!is_string($sort)||!isset($orders[$sort]))throw new InvalidArgumentException('Ungültige Sortierung.');
    if($sort==='distance'&&$origin===null)throw new InvalidArgumentException('Für die Entfernungssortierung ist eine Postleitzahl mit freigegebenen Koordinaten erforderlich.');
    $condition=implode(' AND ',$where);
    $count=$pdo->prepare('SELECT COUNT(*) FROM market_listings l'.$geoJoin.' WHERE '.$condition);$count->execute($parameters);$total=(int)$count->fetchColumn();
    $sql='SELECT l.*,u.username AS seller_username,u.username AS seller_name,c.name AS category_name,(SELECT COUNT(*) FROM market_images i WHERE i.listing_id=l.id) AS image_count,'.$distanceSelect.' AS distance_km FROM market_listings l JOIN users u ON u.id=l.seller_id JOIN market_categories c ON c.id=l.category_id'.$geoJoin.' WHERE '.$condition.' ORDER BY '.$orders[$sort].' LIMIT '.$perPage.' OFFSET '.(($page-1)*$perPage);
    $q=$pdo->prepare($sql);$q->execute(array_merge($selectParameters,$parameters));$items=$q->fetchAll();
    if($postal!=='')foreach($items as &$item){$item['origin_zip']=geo_postcode_key($originCountry,$postal);$item['origin_country']=$originCountry;}unset($item);
    return ['items'=>$items,'total'=>$total,'page'=>$page,'per_page'=>$perPage,'origin'=>$origin,'country_code'=>$country,'scope'=>$scope];
}

function market_input(array $input, string $key, string $default = ''): string
{
    $value = $input[$key] ?? $default;
    if (!is_string($value)) {
        throw new InvalidArgumentException('Bitte nur einzelne Formularwerte verwenden.');
    }
    return $value;
}

function market_validate(PDO $pdo, array $input): array
{
    $title = trim(market_input($input, 'title'));
    $description = trim(market_input($input, 'description'));
    $category = market_input($input, 'category_id');
    $type = market_input($input, 'type', 'fixed');
    $status = market_input($input, 'status', 'active');
    $country=geo_country(market_input($input,'country_code',geo_country()));
    if(!geo_country_enabled($pdo,$country))throw new InvalidArgumentException('Dieser Ländermarkt ist derzeit nicht verfügbar.');
    $zip = geo_postcode_key($country,market_input($input, 'zip'));
    $city = trim(market_input($input, 'city'));
    $condition = market_input($input, 'condition_label', 'used');
    if (strlen($title) < 5 || strlen($title) > 150 || strlen($description) < 20 || strlen($description) > 20000) {
        throw new InvalidArgumentException('Titel: 5–150 Zeichen. Beschreibung: 20–20.000 Zeichen.');
    }
    if (!catalog_text_allowed($title.' '.$description)) {
        throw new InvalidArgumentException('Dieses Produkt ist auf dem allgemeinen Marktplatz nicht zugelassen.');
    }
    if (!ctype_digit($category) || !catalog_category_allowed($pdo, (int)$category)) {
        throw new InvalidArgumentException('Bitte eine aktive allgemeine Kategorie auswählen.');
    }
    if (!in_array($type, ['fixed','auction'], true) || !in_array($status, ['draft','active'], true)) {
        throw new InvalidArgumentException('Ungültige Angebotsart oder Veröffentlichungsform.');
    }
    if (!geo_validate_postcode($country,$zip) || strlen($city) < 2 || strlen($city) > 100) {
        throw new InvalidArgumentException('Bitte eine gültige Postleitzahl für das Land und einen Ort angeben.');
    }
    if (!in_array($condition, ['new','like_new','used'], true)) {
        throw new InvalidArgumentException('Bitte den Artikelzustand auswählen.');
    }
    $price = money_parse(market_input($input, 'price'));
    if ($price < 1) {
        throw new InvalidArgumentException('Der Preis muss mindestens 0,01 € betragen.');
    }
    $buyoutText = trim(market_input($input, 'buyout_price'));
    $buyout = $type === 'auction' && $buyoutText !== '' ? money_parse($buyoutText) : null;
    if ($buyout !== null && $buyout < $price) {
        throw new InvalidArgumentException('Der Sofortkaufpreis darf nicht unter dem Startpreis liegen.');
    }
    $durationText = market_input($input, 'duration_days', '7');
    if (!ctype_digit($durationText) || (int)$durationText < 1 || (int)$durationText > 14) {
        throw new InvalidArgumentException('Die Auktionsdauer muss 1 bis 14 Tage betragen.');
    }
    if (market_input($input, 'accept_rules') !== '1') {
        throw new InvalidArgumentException('Bitte die allgemeinen Produktregeln ausdrücklich bestätigen.');
    }
    return ['title'=>$title,'description'=>$description,'category_id'=>(int)$category,'type'=>$type,'status'=>$status,
        'price_cents'=>$price,'buyout_cents'=>$buyout,'duration_days'=>(int)$durationText,
        'country_code'=>$country,'zip'=>$zip,'city'=>$city,'pickup'=>market_input($input,'pickup','0')==='1'?1:0,
        'shipping'=>market_input($input,'shipping','0')==='1'?1:0,'condition_label'=>$condition];
}

function market_save_listing(PDO $pdo, int $sellerId, array $input, array $files = [], ?int $listingId = null): int
{
    $data = market_validate($pdo, $input);
    $prepared = [];
    try {
        $pdo->beginTransaction(); maintenance_assert_market_available($pdo);
        market_assert_users_active($pdo,[$sellerId]);
        if (!geo_country_locked_enabled($pdo,$data['country_code'])) throw new MarketAccessException('Dieser Ländermarkt ist derzeit nicht verfügbar.');
        if (!market_category_locked_allowed($pdo,(int)$data['category_id'])) throw new MarketAccessException('Diese Kategorie ist nicht mehr verfügbar.');
        $existingImages = 0;
        $existing = null;
        $relistingContext = null;
        if ($listingId !== null) {
            // A lineage is locked before its listing, including ordinary draft saves.
            $relistingContext=relisting_context($pdo,$sellerId,$listingId,true);
            $existing = market_lock_listing($pdo, $listingId);
            if ((int)$existing['seller_id'] !== $sellerId) {
                throw new MarketAccessException('Nur eigene Angebote können bearbeitet werden.');
            }
            relisting_assert_target($pdo,$sellerId,$listingId,$data);
            if (!in_array($existing['status'], ['draft','active','paused'], true) || market_has_bids($pdo, $listingId)
                || ($existing['type']==='auction' && $existing['status']==='active' && market_expired($existing))) {
                throw new MarketConflictException('Dieses Angebot kann nicht mehr bearbeitet werden.');
            }
            $q=$pdo->prepare('SELECT COUNT(*) FROM market_images WHERE listing_id=?');
            $q->execute([$listingId]);
            $existingImages=(int)$q->fetchColumn();
            if (array_key_exists('image_positions',$input)) {
                if (!is_array($input['image_positions'])) throw new InvalidArgumentException(t('image_order.invalid'));
                image_order_apply($pdo,$listingId,$input['image_positions'],$input['image_revision']??null);
                if (function_exists('audit_log')) audit_log($pdo,$sellerId,'listing.images_order_saved',$listingId,['positions'=>$input['image_positions']]);
            }
        }
        $feeQuote=auction_billing_prepare($pdo,$sellerId,$data,$input,$existing);
        $prepared = market_prepare_images($files, $existingImages);
        $ends = $data['type'] === 'auction' && $data['status'] === 'active' ? gmdate('Y-m-d H:i:s', time()+$data['duration_days']*86400) : null;
        if ($existing && $existing['type']==='auction' && $data['type']==='auction' && $existing['status']==='active' && $data['status']==='active') {
            $ends=$existing['ends_at'];
        }
        if(!empty($feeQuote['existing']))$ends=$existing['ends_at'];
        if($relistingContext!==null&&$relistingContext['published_at']!==null)$ends=$existing['ends_at'];
        $point=geo_postcode($pdo,$data['country_code'],$data['zip']);
        $values=[$data['country_code'],$point['latitude']??null,$point['longitude']??null,$data['category_id'],$data['title'],$data['description'],$data['type'],$data['price_cents'],$data['buyout_cents'],$data['status'],$ends,$data['zip'],$data['city'],$data['pickup'],$data['shipping'],$data['condition_label']];
        if ($listingId === null) {
            $q=$pdo->prepare('INSERT INTO market_listings(seller_id,country_code,latitude,longitude,category_id,title,description,type,price_cents,buyout_cents,status,ends_at,zip,city,pickup,shipping,condition_label,created_at,updated_at,auction_fee_eligible) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),1)');
            $q->execute(array_merge([$sellerId],$values));
            $listingId=(int)$pdo->lastInsertId();
        } else {
            $q=$pdo->prepare('UPDATE market_listings SET country_code=?,latitude=?,longitude=?,category_id=?,title=?,description=?,type=?,price_cents=?,buyout_cents=?,status=?,ends_at=?,zip=?,city=?,pickup=?,shipping=?,condition_label=?,updated_at=UTC_TIMESTAMP() WHERE id=?');
            $q->execute(array_merge($values,[$listingId]));
        }
        if($data['type']==='auction'&&$data['status']==='active'){
            auction_billing_publish($pdo,$listingId,$sellerId,$feeQuote);
            relisting_consume($pdo,$sellerId,$listingId,$feeQuote);
        }
        foreach ($prepared as $index=>$image) {
            $q=$pdo->prepare('INSERT INTO market_images(listing_id,storage_key,position,width,height,created_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP())');
            $q->execute([$listingId,$image['key'],$existingImages+$index,$image['width'],$image['height']]);
        }
        // A bounded structured record captures the accepted rules without private descriptions.
        if (function_exists('audit_log')) {
            audit_log($pdo,$sellerId,'listing.rules_accepted',$listingId,['version'=>'general-1.0','status'=>$data['status']]);
        }
        if($data['status']==='active'&&function_exists('discovery_enqueue_listing'))discovery_enqueue_listing($pdo,$listingId);
        $pdo->commit();
        return $listingId;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        market_remove_prepared_images($prepared);
        throw $error;
    }
}

function market_lock_listing(PDO $pdo, int $id): array
{
    $q=$pdo->prepare('SELECT * FROM market_listings WHERE id=? FOR UPDATE');
    $q->execute([$id]);
    $row=$q->fetch();
    if (!$row) {
        throw new MarketNotFoundException('Angebot nicht gefunden.');
    }
    return $row;
}

function market_expired(array $listing): bool
{
    return !is_string($listing['ends_at']) || strtotime($listing['ends_at'].' UTC') <= time();
}

function market_has_bids(PDO $pdo, int $id): bool
{
    $q=$pdo->prepare('SELECT 1 FROM market_proxy_bids WHERE listing_id=? LIMIT 1');
    $q->execute([$id]);
    return $q->fetchColumn() !== false;
}

/** Shared locks make suspension/category changes serialize with a transaction. */
function market_assert_users_active(PDO $pdo, array $ids): void
{
    $ids=array_values(array_unique(array_map('intval',$ids)));
    sort($ids,SORT_NUMERIC);
    $q=$pdo->prepare('SELECT id,is_active FROM users WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY id FOR SHARE');
    $q->execute($ids);
    $rows=$q->fetchAll();
    if (count($rows)!==count($ids)) throw new MarketAccessException('Ein beteiligtes Konto ist nicht verfügbar.');
    foreach ($rows as $row) if ((int)$row['is_active']!==1) throw new MarketAccessException('Ein beteiligtes Konto ist gesperrt.');
}

function market_category_locked_allowed(PDO $pdo, int $id): bool
{
    $q=$pdo->query('SELECT id,parent_id,name,is_active FROM market_categories ORDER BY id LIMIT 1001 FOR SHARE');
    $rows=[];
    foreach ($q->fetchAll() as $row) $rows[(int)$row['id']]=$row;
    if (count($rows)>1000) return false;
    $seen=[];
    while ($id!==0) {
        if (isset($seen[$id]) || !isset($rows[$id]) || (int)$rows[$id]['is_active']!==1 || !catalog_text_allowed($rows[$id]['name'])) return false;
        $seen[$id]=true;
        $id=(int)($rows[$id]['parent_id'] ?? 0);
    }
    return true;
}

function market_assert_auction(PDO $pdo, array $listing): void
{
    if(!geo_country_locked_enabled($pdo,$listing['country_code']))throw new MarketAccessException('Dieser Ländermarkt ist derzeit nicht verfügbar.');
    if (!market_category_locked_allowed($pdo,(int)$listing['category_id'])) {
        throw new MarketAccessException('Diese Produktkategorie ist nicht verfügbar.');
    }
    if ($listing['type'] !== 'auction' || $listing['status'] !== 'active' || market_expired($listing)) {
        throw new MarketConflictException('Diese Auktion nimmt keine Gebote mehr an.');
    }
}

function market_leaders(PDO $pdo, int $id): array
{
    $q=$pdo->prepare('SELECT id,user_id,max_cents FROM market_proxy_bids WHERE listing_id=? ORDER BY max_cents DESC,id ASC LIMIT 2');
    $q->execute([$id]);
    return $q->fetchAll();
}

function market_visible_price(array $listing, array $leaders): int
{
    if (!$leaders) {
        return (int)$listing['price_cents'];
    }
    $top=(int)$leaders[0]['max_cents'];
    $start=(int)$listing['price_cents'];
    $stored=$listing['current_bid_cents'] === null ? $start : (int)$listing['current_bid_cents'];
    if ($stored > $top || $start > $top) {
        throw new MarketConflictException('Die Auktion benötigt eine manuelle Prüfung.');
    }
    $competitive=count($leaders)>1 ? min($top,(int)$leaders[1]['max_cents']+100) : $start;
    return max($start,$stored,$competitive);
}

function market_bid(PDO $pdo, int $listingId, int $userId, int $maximum): array
{
    if ($maximum < 1 || $maximum > 9999999999) {
        throw new InvalidArgumentException('Das Gebot liegt außerhalb des erlaubten Bereichs.');
    }
    try {
        $pdo->beginTransaction(); maintenance_assert_market_available($pdo);
        $listing=market_lock_listing($pdo,$listingId);
        market_assert_auction($pdo,$listing);
        market_assert_users_active($pdo,[$userId,(int)$listing['seller_id']]);
        if ((int)$listing['seller_id'] === $userId) {
            throw new MarketAccessException('Eigene Angebote dürfen nicht beboten werden.');
        }
        $q=$pdo->prepare('SELECT max_cents FROM market_proxy_bids WHERE listing_id=? AND user_id=?');
        $q->execute([$listingId,$userId]);
        $previous=$q->fetchColumn();
        if ($previous !== false && $maximum <= (int)$previous) {
            throw new MarketConflictException('Ein Maximalgebot kann nur erhöht werden.');
        }
        $leaders=market_leaders($pdo,$listingId);
        $isLeader=$leaders && (int)$leaders[0]['user_id']===$userId;
        $minimum=$listing['current_bid_cents']===null ? (int)$listing['price_cents'] : (int)$listing['current_bid_cents']+100;
        if (!$isLeader && $maximum < $minimum) {
            throw new MarketConflictException('Das Gebot erreicht den nächsten Gebotsschritt nicht.');
        }
        $previousLeader=(int)($listing['highest_bidder_id']??0);
        $q=$pdo->prepare('INSERT INTO market_proxy_bids(listing_id,user_id,max_cents,created_at,updated_at) VALUES (?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE max_cents=VALUES(max_cents),updated_at=UTC_TIMESTAMP(6)');
        $q->execute([$listingId,$userId,$maximum]);
        $leaders=market_leaders($pdo,$listingId);
        $price=market_visible_price($listing,$leaders);
        $end=max(strtotime($listing['ends_at'].' UTC'),time()+120);
        $q=$pdo->prepare('UPDATE market_listings SET current_bid_cents=?,highest_bidder_id=?,ends_at=?,updated_at=UTC_TIMESTAMP() WHERE id=?');
        $q->execute([$price,(int)$leaders[0]['user_id'],gmdate('Y-m-d H:i:s',$end),$listingId]);
        $event=$pdo->prepare("INSERT INTO market_bids(listing_id,user_id,amount_cents,event_type,created_at) VALUES (?,?,?,'bid',UTC_TIMESTAMP(6))");
        $event->execute([$listingId,(int)$leaders[0]['user_id'],$price]);
        $bidEvent=(int)$pdo->lastInsertId();
        if(function_exists('notify_user')){
            if($previousLeader>0&&$previousLeader!==(int)$leaders[0]['user_id'])notify_user($pdo,$previousLeader,'outbid:'.$bidEvent,i18n_user_text($pdo,$previousLeader,'notification.outbid.title'),i18n_user_text($pdo,$previousLeader,'notification.outbid.body',['listing'=>$listing['title']]),'/marketplace/view-auction.php?id='.$listingId,true);
            notify_user($pdo,(int)$listing['seller_id'],'bid:'.$bidEvent,i18n_user_text($pdo,(int)$listing['seller_id'],'notification.bid.title'),i18n_user_text($pdo,(int)$listing['seller_id'],'notification.bid.body',['listing'=>$listing['title']]),'/marketplace/view-auction.php?id='.$listingId,true);
        }
        $pdo->commit();
        return ['listing_id'=>$listingId,'price_cents'=>$price,'highest_bidder_id'=>(int)$leaders[0]['user_id'],'ends_at'=>gmdate('Y-m-d H:i:s',$end),'leading'=>(int)$leaders[0]['user_id']===$userId];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function market_buy_now(PDO $pdo, int $listingId, int $userId): array
{
    try {
        $pdo->beginTransaction(); maintenance_assert_market_available($pdo);
        $listing=market_lock_listing($pdo,$listingId);
        market_assert_auction($pdo,$listing);
        market_assert_users_active($pdo,[$userId,(int)$listing['seller_id']]);
        if ((int)$listing['seller_id']===$userId) throw new MarketAccessException('Kein Sofortkauf eigener Angebote.');
        if (market_has_bids($pdo,$listingId) || (int)$listing['buyout_cents']<1) throw new MarketConflictException('Ein Sofortkauf ist nicht mehr verfügbar.');
        $amount=(int)$listing['buyout_cents'];
        $q=$pdo->prepare("UPDATE market_listings SET status='sold',highest_bidder_id=?,current_bid_cents=?,updated_at=UTC_TIMESTAMP() WHERE id=?");
        $q->execute([$userId,$amount,$listingId]);
        $q=$pdo->prepare("INSERT INTO market_bids(listing_id,user_id,amount_cents,event_type,idempotency_key,created_at) VALUES (?,?,?,'buyout',?,UTC_TIMESTAMP(6))");
        $q->execute([$listingId,$userId,$amount,'buyout-'.$listingId]);
        auction_billing_charge($pdo,$listingId,'success',$amount);
        if(function_exists('notify_user')){
            foreach(['buyout_buyer'=>$userId,'buyout_seller'=>(int)$listing['seller_id']] as $event=>$recipient)notify_user($pdo,$recipient,'buyout:'.$listingId.':'.$event,i18n_user_text($pdo,$recipient,'notification.'.$event.'.title'),i18n_user_text($pdo,$recipient,'notification.'.$event.'.body',['listing'=>$listing['title']]),'/marketplace/view-auction.php?id='.$listingId,true);
        }
        $pdo->commit();
        return ['listing_id'=>$listingId,'status'=>'sold','price_cents'=>$amount,'highest_bidder_id'=>$userId];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function market_transition(PDO $pdo, int $listingId, int $userId, string $action): void
{
    if (!in_array($action,['pause','publish','archive','sold'],true)) throw new InvalidArgumentException('Ungültige Angebotsaktion.');
    try {
        $pdo->beginTransaction(); maintenance_assert_market_available($pdo);
        $listing=market_lock_listing($pdo,$listingId);
        market_assert_users_active($pdo,[$userId]);
        if ((int)$listing['seller_id']!==$userId) throw new MarketAccessException('Nur eigene Angebote können geändert werden.');
        if (market_has_bids($pdo,$listingId) || !in_array($listing['status'],['draft','active','paused'],true)) throw new MarketConflictException('Der aktuelle Angebotszustand erlaubt diese Änderung nicht.');
        if ($listing['type']==='auction' && $listing['status']==='active' && market_expired($listing)) throw new MarketConflictException('Abgelaufene Auktionen werden durch den Abschlussjob verarbeitet.');
        $states=['pause'=>'paused','publish'=>'active','archive'=>'archived','sold'=>'sold'];
        if ($action==='sold' && $listing['type']!=='fixed') throw new MarketConflictException('Auktionen werden ausschließlich durch Gebote oder Sofortkauf abgeschlossen.');
        if ($action==='pause' && $listing['status']!=='active') throw new MarketConflictException('Nur veröffentlichte Angebote können pausiert werden.');
        if ($action==='publish') {
            if(!geo_country_locked_enabled($pdo,$listing['country_code']))throw new MarketAccessException('Dieser Ländermarkt ist derzeit nicht verfügbar.');
            if (!market_category_locked_allowed($pdo,(int)$listing['category_id']) || !catalog_text_allowed($listing['title'].' '.$listing['description'])) throw new MarketAccessException('Dieses Angebot kann nicht veröffentlicht werden.');
            if ($listing['type']==='auction') throw new MarketConflictException('Eine Auktionslaufzeit bitte über die Bearbeitung festlegen.');
        }
        $q=$pdo->prepare('UPDATE market_listings SET status=?,updated_at=UTC_TIMESTAMP() WHERE id=?');
        $q->execute([$states[$action],$listingId]);
        if($action==='publish'&&function_exists('discovery_enqueue_listing'))discovery_enqueue_listing($pdo,$listingId);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function market_worker_conflicts(): array
{
    return $GLOBALS['market_close_conflicts'] ?? [];
}

function market_close_due(PDO $pdo, int $limit = 100): int
{
    $limit=max(1,min(1000,$limit));
    $GLOBALS['market_close_conflicts']=[];
    $ids=$pdo->query("SELECT id FROM market_listings WHERE type='auction' AND status='active' AND ends_at<=UTC_TIMESTAMP() ORDER BY ends_at,id LIMIT ".$limit)->fetchAll(PDO::FETCH_COLUMN);
    $closed=0;
    foreach ($ids as $id) {
        try {
            $pdo->beginTransaction(); maintenance_assert_market_available($pdo);
            $listing=market_lock_listing($pdo,(int)$id);
            if ($listing['status']!=='active' || $listing['type']!=='auction' || !market_expired($listing)) {
                $pdo->commit();
                continue;
            }
            if (!geo_country_locked_enabled($pdo,$listing['country_code'])) throw new MarketConflictException('Ländermarkt deaktiviert; manuelle Prüfung.');
            if (!market_category_locked_allowed($pdo,(int)$listing['category_id'])) throw new MarketConflictException('Kategorie deaktiviert; manuelle Prüfung.');
            $leaders=market_leaders($pdo,(int)$id);
            try {
                market_assert_users_active($pdo,$leaders?[(int)$listing['seller_id'],(int)$leaders[0]['user_id']]:[(int)$listing['seller_id']]);
            } catch (MarketAccessException $error) {
                throw new MarketConflictException('Gesperrtes beteiligtes Konto; manuelle Prüfung.');
            }
            $price=$leaders ? market_visible_price($listing,$leaders) : null;
            $winner=$leaders ? (int)$leaders[0]['user_id'] : null;
            $q=$pdo->prepare("UPDATE market_listings SET status='ended',highest_bidder_id=?,current_bid_cents=?,updated_at=UTC_TIMESTAMP() WHERE id=?");
            $q->execute([$winner,$price,$id]);
            $q=$pdo->prepare('INSERT INTO market_bids(listing_id,user_id,amount_cents,event_type,idempotency_key,created_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP(6))');
            $q->execute([$id,$winner,$price,$winner===null?'close_no_bid':'close','close-'.$id]);
            if($winner!==null)auction_billing_charge($pdo,(int)$id,'success',$price);
            if(function_exists('notify_user')){
                notify_user($pdo,(int)$listing['seller_id'],'auction-close:'.$id.':seller',i18n_user_text($pdo,(int)$listing['seller_id'],'notification.auction_seller_closed.title'),i18n_user_text($pdo,(int)$listing['seller_id'],'notification.auction_seller_closed.body',['listing'=>$listing['title']]),'/marketplace/view-auction.php?id='.(int)$id,true);
                if($winner!==null)notify_user($pdo,$winner,'auction-close:'.$id.':winner',i18n_user_text($pdo,$winner,'notification.auction_won.title'),i18n_user_text($pdo,$winner,'notification.auction_won.body',['listing'=>$listing['title']]),'/marketplace/view-auction.php?id='.(int)$id,true);
            }
            $pdo->commit();
            $closed++;
        } catch (MaintenancePaused $error) {
            if($pdo->inTransaction())$pdo->rollBack();
            return $closed;
        } catch (MarketConflictException $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // Quarantine inconsistent rows. Preserve every bid; never invent a winner.
            try {
                $pdo->beginTransaction(); maintenance_assert_market_available($pdo);
                $listing=market_lock_listing($pdo,(int)$id);
                if ($listing['status']==='active' && market_expired($listing)) {
                    $q=$pdo->prepare("UPDATE market_listings SET status='review',updated_at=UTC_TIMESTAMP() WHERE id=?");
                    $q->execute([$id]);
                    $q=$pdo->prepare("INSERT INTO market_bids(listing_id,user_id,amount_cents,event_type,idempotency_key,created_at) VALUES (?,NULL,NULL,'close_review',?,UTC_TIMESTAMP(6))");
                    $q->execute([$id,'close-review-'.$id]);
                }
                $pdo->commit();
            } catch (MaintenancePaused $quarantineError) {
                if($pdo->inTransaction())$pdo->rollBack();
                return $closed;
            } catch (Throwable $quarantineError) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $quarantineError;
            }
            $GLOBALS['market_close_conflicts'][]=(int)$id;
            error_log('market_auction_close_review listing_id='.(int)$id);
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
    return $closed;
}

function market_public_bid_history(PDO $pdo, int $listingId, int $limit = 20): array
{
    if (market_find($pdo,$listingId)===null) return [];
    $limit=max(1,min(100,$limit));
    $q=$pdo->prepare("SELECT amount_cents,event_type,created_at FROM market_bids WHERE listing_id=? AND event_type IN ('bid','buyout','close','close_no_bid') ORDER BY id DESC LIMIT ".$limit);
    $q->execute([$listingId]);
    return $q->fetchAll();
}
