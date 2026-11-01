<?php
declare(strict_types=1);

/** Local, bounded text matching. User text is never interpreted as code or SQL. */
function discovery_normalize(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','á'=>'a','à'=>'a','â'=>'a','å'=>'a','ó'=>'o','ò'=>'o','ô'=>'o','ø'=>'o','ú'=>'u','ù'=>'u','û'=>'u','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ç'=>'c','č'=>'c','š'=>'s','ž'=>'z','ł'=>'l','ń'=>'n','ą'=>'a','ę'=>'e','ř'=>'r','ě'=>'e','ý'=>'y','ź'=>'z','ż'=>'z','ť'=>'t','ď'=>'d','ň'=>'n','ů'=>'u','æ'=>'ae','œ'=>'oe','ÿ'=>'y']);
    return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? '');
}

function discovery_canonical(string $word): string
{
    return ['couch'=>'sofa','backpack'=>'rucksack','binoculars'=>'fernglas','fahrrad'=>'bike','bicycle'=>'bike','notebook'=>'laptop','handy'=>'smartphone','tv'=>'fernseher','tent'=>'zelt','camera'=>'kamera','books'=>'buch','buecher'=>'buch','book'=>'buch'][$word] ?? $word;
}

function discovery_tokens(string $text, int $limit = 8): array
{
    $stop = array_fill_keys(['suche','gesucht','gesuch','suchanzeige','wanted','looking','for','search','the','a','an','and','und','oder','ein','eine','einen','mit','von','der','die','das','zum','zur','in','am','im','aus','de','la','le','les','et','un','une','des','het','een','en','van','ich','wir','benoetige','brauche'], true);
    $tokens = [];
    foreach (explode(' ', discovery_normalize($text)) as $word) {
        if (mb_strlen($word, 'UTF-8') < 2 || mb_strlen($word, 'UTF-8') > 32 || isset($stop[$word])) continue;
        $tokens[discovery_canonical($word)] = true;
        if (count($tokens) >= $limit) break;
    }
    return array_keys($tokens);
}

function discovery_distance_km(float $latA, float $lonA, float $latB, float $lonB): float
{
    foreach ([$latA, $latB] as $lat) if (!is_finite($lat) || abs($lat) > 90) throw new InvalidArgumentException('Ungültiger Breitengrad.');
    foreach ([$lonA, $lonB] as $lon) if (!is_finite($lon) || abs($lon) > 180) throw new InvalidArgumentException('Ungültiger Längengrad.');
    $a = sin(deg2rad($latB - $latA) / 2) ** 2 + cos(deg2rad($latA)) * cos(deg2rad($latB)) * sin(deg2rad($lonB - $lonA) / 2) ** 2;
    return 6371.0088 * 2 * asin(sqrt(min(1.0, max(0.0, $a))));
}

/** Hard filters first; then an explainable text score. No private owner fields. */
function discovery_postcode_key(string $country,string $postal):string
{
    if(function_exists('geo_postcode_key'))return geo_postcode_key($country,$postal);
    $postal=strtoupper(trim($postal));return in_array($country,['NL','PL','CZ'],true)?str_replace([' ','-'],'',$postal):$postal;
}

function discovery_listing_live(array $listing): bool
{
    return ($listing['status'] ?? '') === 'active' && (($listing['type'] ?? '') !== 'auction' || (is_string($listing['ends_at'] ?? null) && strtotime($listing['ends_at'].' UTC') > time()));
}

function discovery_score(array $wanted, array $listing): array
{
    $none = ['score'=>0, 'reasons'=>[]];
    if (!discovery_listing_live($listing) || (int)($listing['seller_id'] ?? 0) === (int)($wanted['user_id'] ?? -1)) return $none;
    if (($wanted['scope'] ?? 'country') !== 'international' && ($listing['country_code'] ?? 'DE') !== ($wanted['country_code'] ?? 'DE')) return $none;
    if (!empty($wanted['category_id']) && (int)$wanted['category_id'] !== (int)$listing['category_id']) return $none;
    $price = (int)($listing['current_bid_cents'] ?? $listing['price_cents'] ?? 0);
    if (($wanted['min_cents'] ?? null) !== null && $price < (int)$wanted['min_cents']) return $none;
    if (($wanted['max_cents'] ?? null) !== null && $price > (int)$wanted['max_cents']) return $none;
    if (($wanted['condition_label'] ?? 'any') !== 'any' && $wanted['condition_label'] !== ($listing['condition_label'] ?? '')) return $none;
    if (($wanted['radius_km'] ?? null) === null && ($wanted['zip'] ?? '') !== '' && (($listing['country_code'] ?? 'DE') !== ($wanted['country_code'] ?? 'DE') || discovery_postcode_key((string)($listing['country_code']??'DE'),(string)($listing['zip']??'')) !== discovery_postcode_key((string)($wanted['country_code']??'DE'),(string)$wanted['zip']))) return $none;
    $distance = null;
    if (($wanted['radius_km'] ?? null) !== null) {
        foreach (['latitude','longitude'] as $field) if (($wanted[$field] ?? null) === null || ($listing[$field] ?? null) === null) return $none;
        $distance = discovery_distance_km((float)$wanted['latitude'], (float)$wanted['longitude'], (float)$listing['latitude'], (float)$listing['longitude']);
        if ($distance > (int)$wanted['radius_km']) return $none;
    }
    $haystack = discovery_normalize(mb_substr((string)$listing['title'] . ' ' . (string)($listing['description'] ?? ''), 0, 5000, 'UTF-8'));
    $words = discovery_tokens($haystack, 100);
    foreach (['manufacturer','model'] as $required) {
        $value = discovery_normalize((string)($wanted[$required] ?? ''));
        if ($value !== '' && !str_contains(' ' . $haystack . ' ', ' ' . $value . ' ')) return $none;
    }
    $requested = discovery_tokens((string)(($wanted['keywords'] ?? '') ?: ($wanted['title'] ?? '')), 8);
    if (!$requested) return $none;
    $matched = 0; $fuzzy = 0;
    foreach ($requested as $term) {
        if (in_array($term, $words, true)) { ++$matched; continue; }
        if (strlen($term) < 4 || strlen($term) > 32 || preg_match('/[^a-z0-9]/', $term)) continue;
        foreach ($words as $word) {
            if (abs(strlen($word) - strlen($term)) > 2 || strlen($word) > 32 || preg_match('/[^a-z0-9]/', $word)) continue;
            if (levenshtein($word, $term) <= (strlen($term) >= 8 ? 2 : 1)) { ++$matched; ++$fuzzy; break; }
        }
    }
    if ($matched === 0 || $matched / count($requested) < 0.6) return $none;
    $score = (int)round(60 * $matched / count($requested));
    $reasons = ['Suchbegriffe: ' . $matched . '/' . count($requested)];
    if ($fuzzy) $reasons[] = 'Ähnliche Schreibweise berücksichtigt';
    if (!empty($wanted['category_id'])) { $score += 15; $reasons[] = 'Passende Kategorie'; }
    if (($wanted['min_cents'] ?? null) !== null || ($wanted['max_cents'] ?? null) !== null) { $score += 10; $reasons[] = 'Im Preisbereich'; }
    if (($wanted['condition_label'] ?? 'any') !== 'any') { $score += 5; $reasons[] = 'Passender Zustand'; }
    if ($distance !== null) { $score += 10; $reasons[] = 'Ca. ' . number_format($distance, 1, ',', '.') . ' km Luftlinie'; }
    return ['score'=>min(100, $score), 'reasons'=>$reasons];
}

function discovery_text(array $input, string $key, int $max = 500, string $default = ''): string
{
    $value = $input[$key] ?? $default;
    if (!is_string($value) || !preg_match('//u', $value) || mb_strlen($value, 'UTF-8') > $max || str_contains($value, "\0")) throw new InvalidArgumentException('Bitte gültige einzelne Textwerte eingeben.');
    return trim($value);
}

function discovery_frequency(array $input): string
{
    $mode = discovery_text($input, 'notify_mode', 10, 'instant');
    if (!in_array($mode, ['instant','daily','off'], true)) throw new InvalidArgumentException('Ungültige Benachrichtigungshäufigkeit.');
    return $mode;
}

function discovery_wanted_validate(PDO $pdo, array $input): array
{
    $title = discovery_text($input, 'title', 150); $description = discovery_text($input, 'description', 10000);
    $keywords = discovery_text($input, 'keywords', 500); $manufacturer = discovery_text($input, 'manufacturer', 100); $model = discovery_text($input, 'model', 100);
    if (mb_strlen($title, 'UTF-8') < 5 || mb_strlen($description, 'UTF-8') < 20 || !discovery_tokens($keywords ?: $title)) throw new InvalidArgumentException('Bitte einen konkreten Titel und mindestens 20 Zeichen Beschreibung angeben.');
    if (!catalog_text_allowed($title . ' ' . $description . ' ' . $keywords . ' ' . $manufacturer . ' ' . $model)) throw new InvalidArgumentException('Diese Produktart ist für den allgemeinen Marktplatz nicht zugelassen.');
    $category = discovery_text($input, 'category_id', 20);
    if ($category !== '' && (!ctype_digit($category) || !catalog_category_allowed($pdo, (int)$category))) throw new InvalidArgumentException('Bitte eine aktive Kategorie auswählen.');
    $minimum = discovery_text($input, 'min_price', 13); $maximum = discovery_text($input, 'max_price', 13);
    $min = $minimum === '' ? null : money_parse($minimum); $max = $maximum === '' ? null : money_parse($maximum);
    if ($min !== null && $max !== null && $min > $max) throw new InvalidArgumentException('Der Mindestpreis darf den Höchstpreis nicht übersteigen.');
    $condition = discovery_text($input, 'condition_label', 20, 'any');
    if (!in_array($condition, ['any','new','like_new','used'], true)) throw new InvalidArgumentException('Ungültiger Zustand.');
    $country = geo_country(discovery_text($input, 'country_code', 2, 'DE'));
    if(!geo_country_enabled($pdo,$country))throw new InvalidArgumentException('Dieses Land ist derzeit nicht aktiv.');
    $scope = discovery_text($input, 'scope', 16, 'country');
    if (!in_array($scope, ['country','international'], true)) throw new InvalidArgumentException('Ungültiger Suchbereich.');
    $zip = discovery_text($input, 'zip', 16);
    if ($zip !== '' && !geo_validate_postcode($country, $zip)) throw new InvalidArgumentException('Bitte eine gültige Postleitzahl für das gewählte Land angeben.');
    $zip = $zip === '' ? '' : geo_normalize_postcode($country, $zip);
    $radiusText = discovery_text($input, 'radius_km', 4); $radius = null; $location = null;
    if ($radiusText !== '') {
        if (!ctype_digit($radiusText) || (int)$radiusText < 1 || (int)$radiusText > 2000 || $zip === '') throw new InvalidArgumentException('Ein Suchradius von 1–2000 km benötigt eine Bezugs-PLZ.');
        $radius = (int)$radiusText; $location = geo_postcode($pdo, $country, $zip);
        if ($location === null) throw new InvalidArgumentException('Für diese Bezugs-PLZ fehlen freigegebene lokale Koordinaten.');
    }
    return ['title'=>$title,'description'=>$description,'keywords'=>$keywords,'manufacturer'=>$manufacturer,'model'=>$model,'category_id'=>$category === '' ? null : (int)$category,'min_cents'=>$min,'max_cents'=>$max,'condition_label'=>$condition,'country_code'=>$country,'scope'=>$scope,'zip'=>$zip,'latitude'=>$location['latitude'] ?? null,'longitude'=>$location['longitude'] ?? null,'radius_km'=>$radius,'notify_mode'=>discovery_frequency($input)];
}

function discovery_table(string $kind): string
{
    return match ($kind) { 'wanted'=>'market_wanted','search'=>'market_saved_searches',default=>throw new InvalidArgumentException('Ungültige Suchart.') };
}

function discovery_owner_query(PDO $pdo, string $kind, int $id, int $userId, bool $lock = false): array
{
    $q = $pdo->prepare('SELECT * FROM ' . discovery_table($kind) . ' WHERE id=? AND user_id=?' . ($lock ? ' FOR UPDATE' : '')); $q->execute([$id,$userId]);
    $row = $q->fetch(); if (!$row) throw new DomainException('Dein Suchauftrag wurde nicht gefunden.'); return $row;
}

function discovery_lock_user(PDO $pdo,int $userId):void
{
    $q=$pdo->prepare('SELECT id FROM users WHERE id=? AND is_active=1 FOR UPDATE');$q->execute([$userId]);if(!$q->fetchColumn())throw new DomainException('Das Konto ist nicht aktiv.');
}

function discovery_active_quota(PDO $pdo,string $kind,int $userId):void
{
    $q=$pdo->prepare('SELECT COUNT(*) FROM '.discovery_table($kind)." WHERE user_id=? AND status='active'");$q->execute([$userId]);if((int)$q->fetchColumn()>=50)throw new InvalidArgumentException('Das Limit von 50 aktiven Suchaufträgen ist erreicht.');
}

function discovery_quota(PDO $pdo, string $kind, int $userId): void
{
    $q = $pdo->prepare('SELECT id FROM users WHERE id=? AND is_active=1 FOR UPDATE'); $q->execute([$userId]);
    if (!$q->fetchColumn()) throw new DomainException('Das Konto ist nicht aktiv.');
    $q = $pdo->prepare('SELECT COUNT(*) FROM ' . discovery_table($kind) . ' WHERE user_id=?'); $q->execute([$userId]);
    if ((int)$q->fetchColumn() >= 200) throw new InvalidArgumentException('Maximal 200 gespeicherte Suchaufträge je Art sind möglich.');
    $q = $pdo->prepare('SELECT COUNT(*) FROM ' . discovery_table($kind) . " WHERE user_id=? AND status='active'"); $q->execute([$userId]);
    if ((int)$q->fetchColumn() >= 50) throw new InvalidArgumentException('Bitte zuerst einen der 50 aktiven Suchaufträge pausieren.');
}

function discovery_save_wanted(PDO $pdo, int $userId, array $input, ?int $id = null): int
{
    $data = discovery_wanted_validate($pdo, $input);
    $pdo->beginTransaction();
    try {
        discovery_lock_user($pdo,$userId);
        if ($id === null) discovery_quota($pdo, 'wanted', $userId); else { $previous=discovery_owner_query($pdo, 'wanted', $id, $userId, true); if($previous['status']==='archived')throw new DomainException('Archivierte Gesuche können nicht bearbeitet werden.'); if($previous['status']==='review')discovery_active_quota($pdo,'wanted',$userId); }
        $target = (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM market_listings')->fetchColumn();
        $event = (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM market_discovery_events')->fetchColumn();
        $columns = array_keys($data); $values = array_values($data);
        if ($id === null) {
            $q = $pdo->prepare('INSERT INTO market_wanted(user_id,' . implode(',', $columns) . ',backfill_target,last_event_id) VALUES (' . implode(',', array_fill(0, count($columns)+3, '?')) . ')');
            $q->execute(array_merge([$userId],$values,[$target,$event])); $id = (int)$pdo->lastInsertId();
        } else {
            $q = $pdo->prepare('UPDATE market_wanted SET ' . implode(',',array_map(static fn($c)=>$c.'=?',$columns)) . ",status=IF(status='review','active',status),backfill_id=0,backfill_target=?,backfill_complete=0,last_event_id=?,last_processed_at=NULL,updated_at=UTC_TIMESTAMP() WHERE id=? AND user_id=? AND status<>'archived'");
            $q->execute(array_merge($values,[$target,$event,$id,$userId]));
            $pdo->prepare("DELETE FROM market_discovery_matches WHERE query_kind='wanted' AND query_id=?")->execute([$id]);
        }
        $pdo->commit(); return $id;
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}

function discovery_validate_search(PDO $pdo, array $input): array
{
    $allowed = ['q','category_id','type','min_price','max_price','country_code','scope','zip','origin_country','radius_km','sort'];
    $filters = [];
    foreach ($allowed as $key) $filters[$key] = discovery_text($input, $key, $key === 'q' ? 200 : 40);
    $filters['country_code'] = geo_country($filters['country_code'] ?: 'DE');
    $filters['origin_country'] = geo_country($filters['origin_country'] ?: $filters['country_code']);
    $filters['scope'] = $filters['scope'] ?: 'country';
    $filters['sort'] = $filters['sort'] ?: 'newest';
    if (!in_array($filters['scope'], ['country','international'], true)) throw new InvalidArgumentException('Ungültiger Suchbereich.');
    if ($filters['q'] !== '' && !catalog_text_allowed($filters['q'])) throw new InvalidArgumentException('Diese Produktart ist nicht zugelassen.');
    if ($filters['category_id'] !== '' && (!ctype_digit($filters['category_id']) || !catalog_category_allowed($pdo, (int)$filters['category_id']))) throw new InvalidArgumentException('Bitte eine aktive Kategorie auswählen.');
    if ($filters['min_price'] !== '' && $filters['max_price'] !== '' && money_parse($filters['min_price']) > money_parse($filters['max_price'])) throw new InvalidArgumentException('Der Preisbereich ist ungültig.');
    market_search($pdo, $filters, 1, 1);
    ksort($filters); return $filters;
}

function discovery_save_search(PDO $pdo, int $userId, array $input, ?int $id = null): int
{
    $name = discovery_text($input, 'name', 100); if (mb_strlen($name,'UTF-8') < 2) throw new InvalidArgumentException('Bitte einen Namen für die Suche angeben.');
    $filters = discovery_validate_search($pdo, $input);
    $json = json_encode($filters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE); $hash = hash('sha256', $json); $mode = discovery_frequency($input);
    $pdo->beginTransaction();
    try {
        discovery_lock_user($pdo,$userId);
        if ($id === null) discovery_quota($pdo, 'search', $userId); else { $previous=discovery_owner_query($pdo, 'search', $id, $userId, true); if($previous['status']==='archived')throw new DomainException('Archivierte Suchaufträge können nicht bearbeitet werden.'); if($previous['status']==='review')discovery_active_quota($pdo,'search',$userId); }
        $target = (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM market_listings')->fetchColumn(); $event = (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM market_discovery_events')->fetchColumn();
        if ($id === null) {
            $q = $pdo->prepare('INSERT INTO market_saved_searches(user_id,name,criteria_json,criteria_hash,notify_mode,backfill_target,last_event_id) VALUES (?,?,?,?,?,?,?)'); $q->execute([$userId,$name,$json,$hash,$mode,$target,$event]); $id = (int)$pdo->lastInsertId();
        } else {
            $q = $pdo->prepare("UPDATE market_saved_searches SET name=?,criteria_json=?,criteria_hash=?,notify_mode=?,status=IF(status='review','active',status),backfill_id=0,backfill_target=?,backfill_complete=0,last_event_id=?,last_processed_at=NULL,updated_at=UTC_TIMESTAMP() WHERE id=? AND user_id=? AND status<>'archived'"); $q->execute([$name,$json,$hash,$mode,$target,$event,$id,$userId]);
            $pdo->prepare("DELETE FROM market_discovery_matches WHERE query_kind='search' AND query_id=?")->execute([$id]);
        }
        $pdo->commit(); return $id;
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); if ($error instanceof PDOException && $error->getCode()==='23000') throw new InvalidArgumentException('Diese Suche ist bereits gespeichert.'); throw $error; }
}

function discovery_transition(PDO $pdo, string $kind, int $id, int $userId, string $action): void
{
    $states = ['pause'=>'paused','resume'=>'active','archive'=>'archived'];
    if (!isset($states[$action])) throw new InvalidArgumentException('Ungültige Aktion.');
    $pdo->beginTransaction();
    try {
        discovery_lock_user($pdo,$userId);
        $query = discovery_owner_query($pdo, $kind, $id, $userId, true);
        if ($query['status'] === 'archived') throw new DomainException('Archivierte Suchaufträge können nicht erneut aktiviert werden.');
        if ($action === 'resume' && $query['status'] !== 'active') {
            $q = $pdo->prepare('SELECT COUNT(*) FROM ' . discovery_table($kind) . " WHERE user_id=? AND status='active'"); $q->execute([$userId]);
            if ((int)$q->fetchColumn() >= 50) throw new InvalidArgumentException('Das Limit aktiver Suchaufträge ist erreicht.');
        }
        if($action==='resume'){
            if($query['status']==='review')throw new DomainException('Bitte zuerst die Suchkriterien bearbeiten und prüfen.');
            $target=(int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM market_listings')->fetchColumn();$event=(int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM market_discovery_events')->fetchColumn();
            $pdo->prepare('UPDATE '.discovery_table($kind).' SET backfill_id=0,backfill_target=?,backfill_complete=0,last_event_id=?,last_processed_at=NULL WHERE id=?')->execute([$target,$event,$id]);
        }else{$pdo->prepare("UPDATE market_discovery_matches SET notification_state='off' WHERE query_kind=? AND query_id=? AND notification_state='pending'")->execute([$kind,$id]);}
        $pdo->prepare('UPDATE ' . discovery_table($kind) . ' SET status=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND user_id=?')->execute([$states[$action],$id,$userId]); $pdo->commit();
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}

/** Call within the publication transaction; event ids also cover old draft ids. */
function discovery_enqueue_listing(PDO $pdo, int $listingId): void
{
    $listing = market_find($pdo, $listingId, false); if (!$listing) return;
    $pdo->prepare('INSERT INTO market_discovery_events(listing_id,event_key) VALUES (?,?)')->execute([$listingId,bin2hex(random_bytes(16))]);
}

function discovery_prepare_query(PDO $pdo,string $kind,array $query):array
{
    if($kind==='wanted'){
        $query['_country_enabled']=geo_country_enabled($pdo,$query['country_code']);
        if($query['radius_km']!==null)$query['_origin_point']=geo_postcode($pdo,$query['country_code'],$query['zip']);
    }else{
        $criteria=json_decode($query['criteria_json'],true,16,JSON_THROW_ON_ERROR);
        if(!is_array($criteria))throw new InvalidArgumentException('Gespeicherte Suche ist beschädigt.');
        $query['_criteria']=discovery_validate_search($pdo,$criteria);
    }
    $query['_prepared']=true;return$query;
}

function discovery_query_score(PDO $pdo, string $kind, array $query, array $listing): array
{
    $none=['score'=>0,'reasons'=>[]];
    if(!discovery_listing_live($listing)||(int)$query['user_id']===(int)$listing['seller_id'])return$none;
    if(empty($query['_prepared']))$query=discovery_prepare_query($pdo,$kind,$query);
    if($kind==='wanted'){
        if(!$query['_country_enabled'])return$none;
        if($query['radius_km']!==null){
            $origin=$query['_origin_point'];$point=geo_postcode($pdo,$listing['country_code'],$listing['zip']);
            if(!$origin||!$point)return$none;
            $query['latitude']=$origin['latitude'];$query['longitude']=$origin['longitude'];
            $listing['latitude']=$point['latitude'];$listing['longitude']=$point['longitude'];
        }
        return discovery_score($query,$listing);
    }
    $criteria=$query['_criteria'];$criteria['listing_id']=(string)$listing['id'];
    $result=market_search($pdo,$criteria,1,1);
    return($result['items']&&(int)$result['items'][0]['id']===(int)$listing['id'])?['score'=>100,'reasons'=>['Erfüllt die gespeicherten Suchfilter']]:$none;
}

function discovery_user_text(PDO $pdo,int $userId,string $key,array $parameters=[]):string
{
    $text=$key;
    if(function_exists('i18n_dictionary')){$q=$pdo->prepare('SELECT preferred_locale FROM users WHERE id=?');$q->execute([$userId]);$locale=$q->fetchColumn();$dictionary=i18n_dictionary(is_string($locale)?$locale:'de');$fallback=i18n_dictionary('de');$text=$dictionary[$key]??$fallback[$key]??$key;}
    foreach($parameters as $name=>$value)if(is_scalar($value))$text=str_replace('{'.$name.'}',(string)$value,$text);
    return$text;
}

function discovery_notify_match(PDO $pdo, string $kind, array $query, array $listing, int $matchId): void
{
    $mode = $query['notify_mode'];
    if ($mode === 'daily') return;
    if ($mode === 'instant') {
        notify_user($pdo,(int)$query['user_id'],'discovery:'.$kind.':'.$query['id'].':'.$listing['id'],discovery_user_text($pdo,(int)$query['user_id'],'Neues passendes Angebot'),(string)$listing['title'],$kind === 'wanted'?'/user/wanted.php?id='.$query['id']:'/user/saved-searches.php?id='.$query['id'],true);
    }
    $pdo->prepare('UPDATE market_discovery_matches SET notification_state=? WHERE id=?')->execute([$mode === 'off' ? 'off' : 'processed',$matchId]);
}

function discovery_scan_query(PDO $pdo, string $kind, int $id, int $limit = 50): array
{
    $table = discovery_table($kind); $limit = max(1,min(100,$limit)); $matched = 0; $processed = 0;
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT q.* FROM '.$table.' q JOIN users u ON u.id=q.user_id WHERE q.id=? AND u.is_active=1 FOR UPDATE'); $q->execute([$id]); $query = $q->fetch();
        if (!$query || $query['status'] !== 'active') { $pdo->commit(); return ['processed'=>0,'matched'=>0]; }
        if ($kind === 'wanted' && $query['category_id'] !== null && !catalog_category_allowed($pdo,(int)$query['category_id'])) { $pdo->prepare('UPDATE '.$table.' SET last_processed_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]); $pdo->commit(); return ['processed'=>0,'matched'=>0]; }
        $query=discovery_prepare_query($pdo,$kind,$query);
        $backfill = !(bool)$query['backfill_complete'];
        if ($backfill) {
            $q = $pdo->prepare("SELECT id AS cursor_id,id AS listing_id FROM market_listings WHERE id>? AND id<=? AND status='active' ORDER BY id LIMIT ".$limit); $q->execute([$query['backfill_id'],$query['backfill_target']]);
        } else {
            $q = $pdo->prepare('SELECT id AS cursor_id,listing_id FROM market_discovery_events WHERE id>? ORDER BY id LIMIT '.$limit); $q->execute([$query['last_event_id']]);
        }
        $events = $q->fetchAll(); $cursor = (int)($backfill ? $query['backfill_id'] : $query['last_event_id']);
        foreach ($events as $event) {
            ++$processed; $cursor = (int)$event['cursor_id']; $listing = market_find($pdo,(int)$event['listing_id'],false);
            $result=$listing?discovery_query_score($pdo,$kind,$query,$listing):['score'=>0,'reasons'=>[]];
            if($result['score']<1){$pdo->prepare('DELETE FROM market_discovery_matches WHERE query_kind=? AND query_id=? AND listing_id=?')->execute([$kind,$id,$event['listing_id']]);continue;}
            $state = $backfill ? 'existing' : ($query['notify_mode']==='off'?'off':'pending');
            $q = $pdo->prepare('INSERT INTO market_discovery_matches(user_id,query_kind,query_id,listing_id,score,reasons_json,notification_state) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE score=VALUES(score),reasons_json=VALUES(reasons_json)');
            $q->execute([$query['user_id'],$kind,$id,$listing['id'],$result['score'],json_encode($result['reasons'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$state]);
            if ($q->rowCount() === 1) { ++$matched; $matchId = (int)$pdo->lastInsertId(); if (!$backfill) discovery_notify_match($pdo,$kind,$query,$listing,$matchId); }
        }
        if ($backfill) $pdo->prepare('UPDATE '.$table.' SET backfill_id=?,backfill_complete=?,last_processed_at=UTC_TIMESTAMP() WHERE id=?')->execute([$cursor,count($events)<$limit||$cursor>=(int)$query['backfill_target']?1:0,$id]);
        else $pdo->prepare('UPDATE '.$table.' SET last_event_id=?,last_processed_at=UTC_TIMESTAMP() WHERE id=?')->execute([$cursor,$id]);
        $q = $pdo->prepare('SELECT id FROM market_discovery_matches WHERE query_kind=? AND query_id=? ORDER BY id DESC LIMIT 1 OFFSET 499'); $q->execute([$kind,$id]); $cutoff=$q->fetchColumn();
        if ($cutoff) $pdo->prepare('DELETE FROM market_discovery_matches WHERE query_kind=? AND query_id=? AND id<?')->execute([$kind,$id,$cutoff]);
        $pdo->commit(); return ['processed'=>$processed,'matched'=>$matched];
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}

function discovery_daily_digests(PDO $pdo, int $limit = 25): int
{
    $hour = getenv('DISCOVERY_DIGEST_HOUR_UTC'); $hour = is_string($hour)&&ctype_digit($hour)&&(int)$hour<=23?(int)$hour:7;
    if ((int)gmdate('G') < $hour) return 0;
    $limit=max(1,min(100,$limit)); $sent=0;
    $q=$pdo->query("SELECT DISTINCT m.user_id FROM market_discovery_matches m JOIN users u ON u.id=m.user_id WHERE u.is_active=1 AND m.notification_state='pending' AND m.matched_at<UTC_DATE() AND NOT EXISTS(SELECT 1 FROM market_discovery_digests d WHERE d.user_id=m.user_id AND d.digest_date=UTC_DATE()) ORDER BY m.user_id LIMIT ".$limit);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $userId) {
        $pdo->beginTransaction();
        try {
            $u=$pdo->prepare('SELECT id FROM users WHERE id=? AND is_active=1 FOR UPDATE');$u->execute([$userId]);if(!$u->fetchColumn()){$pdo->commit();continue;}
            $matches=$pdo->prepare("SELECT m.id,m.query_kind,m.query_id,m.listing_id FROM market_discovery_matches m WHERE m.user_id=? AND m.notification_state='pending' AND m.matched_at<UTC_DATE() ORDER BY m.id LIMIT 100 FOR UPDATE");$matches->execute([$userId]);$rows=$matches->fetchAll();$valid=[];$listingIds=[];
            $queryCache=[];
            foreach($rows as $row){
                $key=$row['query_kind'].':'.$row['query_id'];
                try{if(!isset($queryCache[$key]))$queryCache[$key]=discovery_prepare_query($pdo,$row['query_kind'],discovery_owner_query($pdo,$row['query_kind'],(int)$row['query_id'],(int)$userId));$query=$queryCache[$key];$live=market_find($pdo,(int)$row['listing_id'],false);$acceptable=$query['status']==='active'&&$query['notify_mode']==='daily'&&$live&&discovery_query_score($pdo,$row['query_kind'],$query,$live)['score']>0;}catch(JsonException|InvalidArgumentException|DomainException){$acceptable=false;}
                if($acceptable){$valid[]=(int)$row['id'];$listingIds[(int)$row['listing_id']]=true;}else $pdo->prepare("UPDATE market_discovery_matches SET notification_state='off' WHERE id=?")->execute([$row['id']]);
            }
            if(!$valid){$pdo->commit();continue;}
            $insert=$pdo->prepare('INSERT INTO market_discovery_digests(user_id,digest_date) VALUES (?,UTC_DATE()) ON DUPLICATE KEY UPDATE id=id');$insert->execute([$userId]);if($insert->rowCount()!==1){$pdo->commit();continue;}
            notify_user($pdo,(int)$userId,'discovery:digest:'.gmdate('Y-m-d'),discovery_user_text($pdo,(int)$userId,'Deine tägliche Suchzusammenfassung'),discovery_user_text($pdo,(int)$userId,'{count} neue passende Angebote aus deinen Gesuchen und Suchalarmen.',['count'=>count($listingIds)]),'/user/wanted.php',true);
            $pdo->prepare("UPDATE market_discovery_matches SET notification_state='processed' WHERE id IN (".implode(',',array_fill(0,count($valid),'?')).')')->execute($valid);
            $pdo->commit();++$sent;
        } catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw$error;}
    }
    return $sent;
}

function discovery_worker_once(PDO $pdo, int $queries = 20, int $listingsPerQuery = 50): array
{
    $queries=max(1,min(100,$queries));$listingsPerQuery=max(1,min(100,$listingsPerQuery));$result=['queries'=>0,'processed'=>0,'matched'=>0,'digests'=>0,'review'=>0];
    $lock='gunindex.discovery.'.substr(hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn()),0,24);$q=$pdo->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if((int)$q->fetchColumn()!==1)return$result;
    try {
        $q=$pdo->query("SELECT kind,id FROM (SELECT 'wanted' AS kind,id,last_processed_at FROM market_wanted WHERE status='active' UNION ALL SELECT 'search' AS kind,id,last_processed_at FROM market_saved_searches WHERE status='active') jobs ORDER BY last_processed_at,id,kind LIMIT ".$queries);
        foreach($q->fetchAll() as $job){
            try{$scanned=discovery_scan_query($pdo,$job['kind'],(int)$job['id'],$listingsPerQuery);++$result['queries'];$result['processed']+=$scanned['processed'];$result['matched']+=$scanned['matched'];}
            catch(JsonException|InvalidArgumentException|DomainException){$pdo->prepare('UPDATE '.discovery_table($job['kind'])." SET status='review',last_processed_at=UTC_TIMESTAMP() WHERE id=? AND status='active'")->execute([$job['id']]);++$result['review'];}
        }
        $result['digests']=discovery_daily_digests($pdo);return$result;
    } finally {$pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);}
}

function discovery_matches(PDO $pdo, string $kind, int $id, int $userId, int $page = 1): array
{
    $query=discovery_owner_query($pdo,$kind,$id,$userId);$page=max(1,min(21,$page));
    try{$query=discovery_prepare_query($pdo,$kind,$query);}catch(JsonException|InvalidArgumentException|DomainException){return[];}
    $q=$pdo->prepare('SELECT m.listing_id FROM market_discovery_matches m WHERE m.query_kind=? AND m.query_id=? AND m.user_id=? ORDER BY m.score DESC,m.id DESC LIMIT 24 OFFSET '.(($page-1)*24));$q->execute([$kind,$id,$userId]);$items=[];
    foreach($q->fetchAll() as $match){$listing=market_find($pdo,(int)$match['listing_id'],false);if(!$listing)continue;$score=discovery_query_score($pdo,$kind,$query,$listing);if($score['score']>0){$listing['relevance_score']=$score['score'];$listing['match_reasons']=$score['reasons'];$items[]=$listing;}}
    return$items;
}

function discovery_wanted_count(PDO $pdo,int $listingId):int
{
    if(!market_find($pdo,$listingId,false))return 0;
    $q=$pdo->prepare("SELECT COUNT(*) FROM market_discovery_matches m JOIN market_wanted w ON w.id=m.query_id JOIN users u ON u.id=w.user_id WHERE m.query_kind='wanted' AND m.listing_id=? AND w.status='active' AND u.is_active=1");$q->execute([$listingId]);return(int)$q->fetchColumn();
}
