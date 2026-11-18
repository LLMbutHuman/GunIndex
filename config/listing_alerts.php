<?php
declare(strict_types=1);

/** Call in the same transaction that changes the published price. */
function listing_alert_price(PDO $pdo,array $previous,array $new): void
{
    if ($previous['status']!=='active'||$new['status']!=='active'||$previous['type']!==$new['type']
        ||!in_array($new['type'],['fixed','negotiated'],true)||(int)$previous['price_cents']===(int)$new['price_cents']) return;
    $q=$pdo->prepare("INSERT INTO market_listing_notice_events(listing_id,kind,old_price,new_price) VALUES (?,'price',?,?)");
    $q->execute([$previous['id'],$previous['price_cents'],$new['price_cents']]);
}

/** The listing lock and unique concrete end time make scheduling replay safe. */
function listing_alert_deadline(PDO $pdo,array $listing,string $kind): bool
{
    if (!in_array($kind,['ending','ended'],true)||!is_string($listing['ends_at']??null)) throw new LogicException('Invalid deadline event.');
    $q=$pdo->prepare('INSERT INTO market_listing_notice_events(listing_id,kind,deadline) VALUES (?,?,?)'
        .' ON DUPLICATE KEY UPDATE id=id');
    $q->execute([$listing['id'],$kind,$listing['ends_at']]);
    return $q->rowCount()===1;
}

function listing_alert_due(PDO $pdo,int $limit=100): int
{
    $limit=max(1,min(1000,$limit));
    $ids=$pdo->query("SELECT l.id FROM market_listings l WHERE l.status='active' AND l.ends_at>UTC_TIMESTAMP()"
        ." AND l.ends_at<=UTC_TIMESTAMP()+INTERVAL 24 HOUR AND NOT EXISTS (SELECT 1 FROM market_listing_notice_events e"
        ." WHERE e.listing_id=l.id AND e.kind='ending' AND e.deadline BETWEEN l.ends_at-INTERVAL 1 HOUR AND l.ends_at+INTERVAL 1 HOUR)"
        .' ORDER BY l.ends_at,l.id LIMIT '.$limit)->fetchAll(PDO::FETCH_COLUMN);
    $scheduled=0;
    foreach ($ids as $id) $scheduled+=trade_transaction($pdo,static function()use($pdo,$id):int {
        $listing=market_lock_listing($pdo,(int)$id);
        $deadline=isset($listing['ends_at'])?strtotime($listing['ends_at'].' UTC'):false;
        if ($listing['status']!=='active'||$deadline===false||$deadline<=time()||$deadline>time()+86400) return 0;
        $q=$pdo->prepare("SELECT 1 FROM market_listing_notice_events WHERE listing_id=? AND kind='ending' AND deadline BETWEEN ? AND ? LIMIT 1 FOR SHARE");
        $q->execute([$id,gmdate('Y-m-d H:i:s',$deadline-3600),gmdate('Y-m-d H:i:s',$deadline+3600)]);
        if ($q->fetchColumn()!==false) return 0;
        return (int)listing_alert_deadline($pdo,$listing,'ending');
    });
    return $scheduled;
}

function listing_alert_current(PDO $pdo,array $listing,array $event): bool
{
    if (!market_category_locked_allowed($pdo,(int)$listing['category_id'])||!geo_country_locked_enabled($pdo,$listing['country_code'])) return false;
    $seller=$pdo->prepare('SELECT is_active FROM users WHERE id=? FOR SHARE');
    $seller->execute([$listing['seller_id']]);
    if ((int)$seller->fetchColumn()!==1) return false;
    if ($event['kind']==='ended') return $listing['status']==='ended'&&$event['deadline']===$listing['ends_at'];
    if ($listing['status']!=='active'||($listing['ends_at']!==null&&market_expired($listing))) return false;
    if ($event['kind']==='ending') return $event['deadline']===$listing['ends_at'];
    if (!in_array($listing['type'],['fixed','negotiated'],true)||(int)$listing['price_cents']!==(int)$event['new_price']) return false;
    $q=$pdo->prepare("SELECT 1 FROM market_listing_notice_events WHERE listing_id=? AND kind='price' AND id>? LIMIT 1 FOR SHARE");
    $q->execute([$listing['id'],$event['id']]);
    return $q->fetchColumn()===false;
}

function listing_alert_notify(PDO $pdo,int $user,array $event,array $listing): void
{
    if ((int)notification_preferences($pdo,$user)['listing_alerts_enabled']!==1) return;
    $key='listing_alert.'.$event['kind'];
    $params=['listing'=>$listing['title']];
    if ($event['kind']==='price') {
        $params['old']=money_eur((int)$event['old_price']);
        $params['new']=money_eur((int)$event['new_price']);
    }
    if ($event['deadline']!==null) $params['deadline']=$event['deadline'];
    $target=($listing['type']==='auction'?'/marketplace/view-auction.php':'/marketplace/view-item.php').'?id='.(int)$listing['id'];
    notify_user($pdo,$user,'listing-alert:'.$event['id'],i18n_user_text($pdo,$user,$key.'.title'),
        i18n_user_text($pdo,$user,$key.'.body',$params),$target,true);
}

/** Resume the bounded recipient set after each committed batch. */
function listing_alert_deliver(PDO $pdo,int $events=20,int $recipients=100): int
{
    $events=max(1,min(100,$events));
    $recipients=max(1,min(200,$recipients));
    $work=$pdo->query('SELECT id,listing_id FROM market_listing_notice_events WHERE finished_at IS NULL ORDER BY processed_at,id LIMIT '.$events)->fetchAll();
    $processed=0;
    foreach ($work as $hint) $processed+=trade_transaction($pdo,static function()use($pdo,$hint,$recipients):int {
        $listing=market_lock_listing($pdo,(int)$hint['listing_id']);
        $q=$pdo->prepare('SELECT * FROM market_listing_notice_events WHERE id=? AND finished_at IS NULL FOR UPDATE');
        $q->execute([$hint['id']]);
        $event=$q->fetch();
        if (!$event) return 0;
        $last=(int)$event['last_user_id'];
        $complete=true;
        if (listing_alert_current($pdo,$listing,$event)) {
            $seller=(int)$listing['seller_id'];
            if (!(int)$event['seller_done']&&($event['kind']==='ending'||($event['kind']==='ended'&&$listing['type']!=='auction'))) {
                listing_alert_notify($pdo,$seller,$event,$listing);
            }
            $q=$pdo->prepare('SELECT user_id FROM market_watchlist WHERE listing_id=? AND user_id>? AND user_id<>? AND created_at<=?'
                .' AND user_id<>? ORDER BY user_id LIMIT '.($recipients+1));
            $winner=$event['kind']==='ended'&&$listing['type']==='auction'?(int)$listing['highest_bidder_id']:0;
            $q->execute([$listing['id'],$last,$seller,$event['created_at'],$winner]);
            $watchers=$q->fetchAll(PDO::FETCH_COLUMN);
            $complete=count($watchers)<=$recipients;
            foreach (array_slice($watchers,0,$recipients) as $user) {
                $last=(int)$user;
                listing_alert_notify($pdo,$last,$event,$listing);
            }
        }
        $pdo->prepare('UPDATE market_listing_notice_events SET last_user_id=?,seller_done=1,processed_at=UTC_TIMESTAMP(6),'
            .' finished_at=CASE WHEN ?=1 THEN UTC_TIMESTAMP(6) ELSE NULL END WHERE id=?')->execute([$last,(int)$complete,$event['id']]);
        return 1;
    });
    return $processed;
}
