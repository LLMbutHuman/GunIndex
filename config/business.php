<?php
declare(strict_types=1);

function business_country(?string $country): string
{
    if (function_exists('geo_country')) return geo_country($country);
    $country = strtoupper($country ?? 'DE');
    if (!in_array($country, ['DE','AT','CH','FR','BE','NL','LU','DK','PL','CZ','LI'], true)) throw new InvalidArgumentException('Ungültiges Land.');
    return $country;
}

function business_country_enabled(PDO $pdo, string $country): bool
{
    return !function_exists('geo_country_enabled') || geo_country_enabled($pdo, $country);
}

function business_reason(PDO $pdo): ?string
{
    if (site_setting($pdo, 'business_promotions_enabled', '0') !== '1') return 'Premiumplätze und Händlerwerbung sind noch nicht freigegeben.';
    return manual_payment_reason($pdo);
}

function business_terms(PDO $pdo,string $scope,array $listing):string
{
    $spec=business_scope($scope);$country=business_country($listing['country_code']??'DE');
    return $spec['label'].' für '. $country .', Laufzeit '.$spec['duration'].'. Maximal '.$spec['limit'].' Plätze in diesem Bereich. Reservierung '.business_reservation_minutes($pdo).' Minuten ab Bestellung. Zahlung wird manuell geprüft. Nach rechtzeitiger Freigabe muss der Beginn innerhalb von sieben Tagen ausdrücklich angefordert werden. Bei verspätetem Eingang bzw. abgelaufener Reservierung wird keine Überbuchung vorgenommen; Erfüllung oder Erstattung muss vom Betreiber geklärt werden. Werbung ist als Gesponsert gekennzeichnet. Keine Garantie bestimmter Klick-, Reichweiten- oder Verkaufszahlen. Gesetzliche Verbraucherrechte bleiben bestehen; kein vorzeitiger Beginn wird allein durch diese Bestellung erklärt. Version premium-1.0.';
}

function business_label(string $label):string {return function_exists('t')?t($label):$label;}

function business_scope(string $scope): array
{
    $spec = match ($scope) {
        'category_top' => ['limit'=>3, 'label'=>'Kategorie-Top', 'duration'=>'Sieben Tage'],
        'home_top' => ['limit'=>2, 'label'=>'Startseiten-Top', 'duration'=>'Sieben Tage'],
        'banner_monthly' => ['limit'=>2, 'label'=>'Händlerbanner', 'duration'=>'Ein Kalendermonat'],
        default => throw new InvalidArgumentException('Unbekannte Platzierung.'),
    };
    $spec['label']=business_label($spec['label']);return $spec;
}

function business_state_label(string $state): string
{
    $label = ['reserved'=>'Reserviert · unbezahlt','awaiting_start'=>'Bezahlt · Start wählen','active'=>'Aktiv','expired'=>'Abgelaufen','paid_unfulfilled'=>'Bezahlt · Erfüllung oder Erstattung prüfen','cancelled'=>'Beendet','pending'=>'Prüfung ausstehend','approved'=>'Freigegeben','rejected'=>'Abgelehnt','disabled'=>'Deaktiviert','published'=>'Veröffentlicht'][$state] ?? 'In Bearbeitung';return business_label($label);
}

function business_assert_manager(PDO $pdo,int $actorId):void
{
    if(function_exists('governance_assert_actor'))governance_assert_actor($pdo,$actorId,'campaigns.manage');
    else settings_assert_admin($pdo,$actorId);
}

function business_profile_revision(array $profile):string
{
    return hash('sha256',json_encode(array_intersect_key($profile,array_flip(['user_id','company_name','address_text','country_code','contact_email','website','legal_notice'])),JSON_THROW_ON_ERROR));
}

function business_review_dealer(PDO $pdo,int $actorId,int $userId,string $status,string $reason,string $revision):void
{
    business_assert_manager($pdo,$actorId);
    if(!in_array($status,['published','rejected'],true)||mb_strlen(trim($reason))<10||mb_strlen($reason)>1000)throw new InvalidArgumentException('Bitte eine zulässige Entscheidung und nachvollziehbare Begründung angeben.');
    $pdo->beginTransaction();try{business_assert_manager($pdo,$actorId);$q=$pdo->prepare('SELECT * FROM market_dealer_profiles WHERE user_id=? FOR UPDATE');$q->execute([$userId]);$profile=$q->fetch();if(!$profile)throw new DomainException('Profil nicht gefunden.');if(!hash_equals(business_profile_revision($profile),$revision))throw new DomainException('Die Angaben wurden geändert. Bitte den aktuellen Inhalt erneut prüfen.');$pdo->prepare('UPDATE market_dealer_profiles SET status=?,moderation_reason=? WHERE user_id=?')->execute([$status,trim($reason),$userId]);audit_log($pdo,$actorId,'dealer.reviewed',$userId,['status'=>$status,'reason'=>trim($reason)]);$pdo->commit();}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw$error;}
}

function business_review_campaign(PDO $pdo,int $actorId,int $campaignId,string $status,string $reason):void
{
    business_assert_manager($pdo,$actorId);
    if(!in_array($status,['approved','rejected','disabled'],true)||mb_strlen(trim($reason))<10||mb_strlen($reason)>1000)throw new InvalidArgumentException('Bitte eine zulässige Entscheidung und nachvollziehbare Begründung angeben.');
    $pdo->beginTransaction();try{business_assert_manager($pdo,$actorId);$q=$pdo->prepare('SELECT * FROM market_campaigns WHERE id=? FOR UPDATE');$q->execute([$campaignId]);$campaign=$q->fetch();if(!$campaign)throw new DomainException('Kampagne nicht gefunden.');if($status==='approved'&&(!business_dealer_profile($pdo,(int)$campaign['user_id'])||!catalog_category_allowed($pdo,(int)$campaign['category_id'])))throw new DomainException('Händlerprofil oder Kategorie ist nicht freigegeben.');$pdo->prepare('UPDATE market_campaigns SET status=?,moderation_reason=?,moderated_by=? WHERE id=?')->execute([$status,trim($reason),$actorId,$campaignId]);audit_log($pdo,$actorId,'campaign.reviewed',$campaignId,['status'=>$status,'reason'=>trim($reason)]);$pdo->commit();}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw$error;}
}

function business_output_banner(string $key,bool $thumbnail=false,?callable $onServe=null):never
{
    if(!preg_match('/\A[a-f0-9]{48}\z/',$key))abort_request(404,'Banner nicht gefunden.');
    $directory=market_image_directory();$path=$directory.'/'.$key.($thumbnail?'-thumb':'').'.webp';
    if(!is_file($path)||is_link($path)||realpath(dirname($path))!==realpath($directory))abort_request(404,'Banner nicht gefunden.');
    if($onServe!==null&&($_SERVER['REQUEST_METHOD']??'GET')==='GET')$onServe();
    header('Content-Type: image/webp');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');header('Content-Length: '.filesize($path));
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')readfile($path);exit;
}

function business_validate_url(string $url): string
{
    $url = trim($url); $parts = parse_url($url);
    if (strlen($url) > 1000 || preg_match('/[\x00-\x20\\\\]/', $url) || filter_var($url, FILTER_VALIDATE_URL) === false || !$parts
        || !in_array($parts['scheme'] ?? '', ['https','http'], true) || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['port']) || !isset($parts['host']) || filter_var($parts['host'], FILTER_VALIDATE_IP)
        || !preg_match('/\A[a-z0-9](?:[a-z0-9.-]{0,251})\.[a-z]{2,63}\z/i', $parts['host'])
        || preg_match('/(?:^|\.)(?:localhost|local|internal|test|invalid)\z/i', $parts['host'])) {
        throw new InvalidArgumentException('Bitte eine öffentliche HTTP-/HTTPS-Website ohne Zugangsdaten oder Sonderport angeben.');
    }
    return $url;
}

function business_dealer_profile(PDO $pdo, int $userId, bool $publicOnly = true): ?array
{
    $q = $pdo->prepare('SELECT p.* FROM market_dealer_profiles p JOIN users u ON u.id=p.user_id WHERE p.user_id=? AND u.is_active=1' . ($publicOnly ? " AND p.status='published'" : ''));
    $q->execute([$userId]); $row = $q->fetch();
    return $row ?: null;
}

function business_save_dealer(PDO $pdo, int $userId, array $input): void
{
    if (site_setting($pdo, 'dealer_profiles_enabled', '1') !== '1') throw new DomainException('Händlerprofile sind vorübergehend deaktiviert.');
    $name = trim(market_input($input, 'company_name')); $address = trim(market_input($input, 'address_text'));
    $email = trim(market_input($input, 'contact_email')); $notice = trim(market_input($input, 'legal_notice'));
    $website = trim(market_input($input, 'website')); $country = business_country(market_input($input, 'country_code', 'DE'));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 150 || mb_strlen($address) < 10 || mb_strlen($address) > 500 || mb_strlen($notice) < 20 || mb_strlen($notice) > 10000
        || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !business_country_enabled($pdo, $country)) throw new InvalidArgumentException('Firmenname, vollständige Geschäftsadresse, Kontakt und rechtliche Angaben sind erforderlich.');
    if ($website !== '') $website = business_validate_url($website);
    $pdo->beginTransaction();
    try {
        market_assert_users_active($pdo, [$userId]);
        $q = $pdo->prepare("INSERT INTO market_dealer_profiles(user_id,company_name,address_text,country_code,contact_email,website,legal_notice,status) VALUES (?,?,?,?,?,?,?,'pending') ON DUPLICATE KEY UPDATE company_name=VALUES(company_name),address_text=VALUES(address_text),country_code=VALUES(country_code),contact_email=VALUES(contact_email),website=VALUES(website),legal_notice=VALUES(legal_notice),status='pending',moderation_reason=''");
        $q->execute([$userId,$name,$address,$country,$email,$website,$notice]);
        audit_log($pdo, $userId, 'dealer.profile_submitted', $userId, ['country'=>$country]); $pdo->commit();
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}

function business_create_campaign(PDO $pdo, int $userId, array $input, array $files): int
{
    $profile = business_dealer_profile($pdo, $userId);
    if (!$profile) throw new DomainException('Für Händlerwerbung ist ein veröffentlichtes Händlerprofil erforderlich.');
    $country = business_country(market_input($input, 'country_code', $profile['country_code']));
    $category = market_input($input, 'category_id'); $title = trim(market_input($input, 'title'));
    if (!ctype_digit($category) || !catalog_category_allowed($pdo, (int)$category) || !business_country_enabled($pdo, $country)
        || mb_strlen($title) < 5 || mb_strlen($title) > 120 || !catalog_text_allowed($title)) throw new InvalidArgumentException('Bitte Titel, freigegebenes Land und aktive allgemeine Kategorie prüfen.');
    $url = business_validate_url(market_input($input, 'target_url'));
    $images = market_prepare_images($files);
    try {
        if (count($images) !== 1) throw new InvalidArgumentException('Bitte genau ein Bannerbild auswählen.');
        $pdo->beginTransaction(); market_assert_users_active($pdo, [$userId]);
        $q = $pdo->prepare("INSERT INTO market_campaigns(user_id,country_code,category_id,title,target_url,storage_key,status) VALUES (?,?,?,?,?,?,'pending')");
        $q->execute([$userId,$country,(int)$category,$title,$url,$images[0]['key']]); $id = (int)$pdo->lastInsertId();
        audit_log($pdo,$userId,'campaign.submitted',$id,['country'=>$country,'category_id'=>(int)$category]); $pdo->commit(); return $id;
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); market_remove_prepared_images($images); throw $error; }
}

function business_reservation_minutes(PDO $pdo): int
{
    $raw = site_setting($pdo, 'business_reservation_minutes', '1440');
    if (!ctype_digit($raw) || (int)$raw < 15 || (int)$raw > 2880) throw new RuntimeException('Die Reservierungsdauer muss zwischen 15 Minuten und 48 Stunden liegen.');
    return (int)$raw;
}

/** The inventory row is the common lock for reservation, activation, expiry and cancellation. */
function business_lock_inventory(PDO $pdo, int $id): array
{
    $q = $pdo->prepare('SELECT * FROM market_business_inventory WHERE id=? FOR UPDATE'); $q->execute([$id]);
    return $q->fetch() ?: throw new DomainException('Werbeplatz nicht gefunden.');
}

function business_inventory_busy(PDO $pdo, int $inventoryId, ?int $exceptBooking = null, bool $locking = true): bool
{
    $q = $pdo->prepare("SELECT id FROM market_premium_bookings WHERE inventory_id=? AND id<>? AND ((status IN ('reserved','awaiting_start') AND reserved_until>UTC_TIMESTAMP()) OR (status='active' AND ends_at>UTC_TIMESTAMP())) LIMIT 1" . ($locking ? ' FOR UPDATE' : ''));
    $q->execute([$inventoryId,$exceptBooking ?? 0]); return (bool)$q->fetchColumn();
}

function business_availability(PDO $pdo, string $country, string $scope, int $categoryId): array
{
    $spec = business_scope($scope); $country = business_country($country); $categoryId = $scope === 'home_top' ? 0 : $categoryId;
    $q = $pdo->prepare("SELECT COUNT(*) FROM market_premium_bookings b WHERE b.country_code=? AND b.scope=? AND b.category_id=? AND ((b.status IN ('reserved','awaiting_start') AND b.reserved_until>UTC_TIMESTAMP()) OR (b.status='active' AND b.ends_at>UTC_TIMESTAMP()))");
    $q->execute([$country,$scope,$categoryId]); $used = (int)$q->fetchColumn();
    return ['capacity'=>$spec['limit'],'occupied'=>$used,'available'=>max(0,$spec['limit']-$used)];
}

/** Called only inside the atomic manual-order transaction. */
function business_reserve_order(PDO $pdo, int $invoiceId, int $userId, array $listing, string $scope, ?int $campaignId = null): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Reservation requires transaction.');
    if (($reason = business_reason($pdo)) !== null) throw new PaymentUnavailable($reason);
    $spec = business_scope($scope); $country = business_country($listing['country_code'] ?? 'DE'); $categoryId = $scope === 'home_top' ? 0 : (int)$listing['category_id'];
    if (!business_country_enabled($pdo,$country)) throw new DomainException('Dieses Land ist nicht freigegeben.');
    if ($scope === 'banner_monthly') {
        $q = $pdo->prepare("SELECT c.id FROM market_campaigns c JOIN market_dealer_profiles p ON p.user_id=c.user_id JOIN users u ON u.id=c.user_id WHERE c.id=? AND c.user_id=? AND c.status='approved' AND p.status='published' AND u.is_active=1 AND c.country_code=? AND c.category_id=?");
        $q->execute([$campaignId,$userId,$country,$categoryId]); if (!$q->fetchColumn()) throw new DomainException('Das Banner muss freigegeben sein und zu Land und Kategorie dieses Angebots passen.');
    } else $campaignId = null;
    $q = $pdo->prepare("SELECT id FROM market_premium_bookings WHERE user_id=? AND country_code=? AND scope=? AND category_id=? AND status IN ('reserved','awaiting_start') AND reserved_until>UTC_TIMESTAMP() LIMIT 1 FOR UPDATE");
    $q->execute([$userId,$country,$scope,$categoryId]); if ($q->fetchColumn()) throw new DomainException('Du hast bereits eine offene Reservierung für diesen Bereich.');
    if($scope==='banner_monthly'){
        $q=$pdo->prepare("SELECT id FROM market_premium_bookings WHERE user_id=? AND country_code=? AND scope='banner_monthly' AND category_id=? AND status='active' AND ends_at>UTC_TIMESTAMP() LIMIT 1 FOR UPDATE");$q->execute([$userId,$country,$categoryId]);if($q->fetchColumn())throw new DomainException('Du besitzt bereits einen aktiven Bannerplatz in diesem Land und dieser Kategorie.');
    }
    $insert = $pdo->prepare('INSERT INTO market_business_inventory(country_code,scope,category_id,slot_no) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE id=id');
    for ($slot=1;$slot<=$spec['limit'];$slot++) $insert->execute([$country,$scope,$categoryId,$slot]);
    $q = $pdo->prepare('SELECT * FROM market_business_inventory WHERE country_code=? AND scope=? AND category_id=? ORDER BY slot_no FOR UPDATE'); $q->execute([$country,$scope,$categoryId]);
    $available = null;
    foreach ($q->fetchAll() as $inventory) if (!business_inventory_busy($pdo,(int)$inventory['id'])) { $available = (int)$inventory['id']; break; }
    if ($available === null) throw new DomainException('Alle Plätze sind belegt oder reserviert. Es wurde kein neuer Zahlungsauftrag angelegt.');
    $until = gmdate('Y-m-d H:i:s',time()+business_reservation_minutes($pdo)*60);
    $pdo->prepare('INSERT INTO market_premium_bookings(invoice_id,user_id,inventory_id,listing_id,campaign_id,country_code,scope,category_id,reserved_until) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$invoiceId,$userId,$available,$listing['id'],$campaignId,$country,$scope,$categoryId,$until]);
}

function business_locked_booking(PDO $pdo, int $invoiceId): ?array
{
    $q = $pdo->prepare('SELECT inventory_id FROM market_premium_bookings WHERE invoice_id=?'); $q->execute([$invoiceId]); $inventory = $q->fetchColumn();
    if ($inventory === false) return null;
    business_lock_inventory($pdo,(int)$inventory);
    $q = $pdo->prepare('SELECT * FROM market_premium_bookings WHERE invoice_id=? FOR UPDATE'); $q->execute([$invoiceId]); return $q->fetch() ?: null;
}

/** A late payment is recorded, but never grants a scarce slot twice. */
function business_fulfill_manual(PDO $pdo, int $invoiceId): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Fulfillment requires transaction.');
    $booking = business_locked_booking($pdo,$invoiceId); if (!$booking) throw new DomainException('Die Platzreservierung fehlt.');
    if (in_array($booking['status'],['active','awaiting_start'],true)) return;
    $valid = $booking['status'] === 'reserved' && strtotime($booking['reserved_until'].' UTC') > time()
        && !business_inventory_busy($pdo,(int)$booking['inventory_id'],(int)$booking['id']);
    $state = $valid ? 'awaiting_start' : 'paid_unfulfilled'; $until = $valid ? gmdate('Y-m-d H:i:s',time()+7*86400) : $booking['reserved_until'];
    $pdo->prepare('UPDATE market_premium_bookings SET status=?,reserved_until=? WHERE id=?')->execute([$state,$until,$booking['id']]);
    if (function_exists('notify_user')) notify_user($pdo,(int)$booking['user_id'],'booking:'.$booking['id'].':'.$state,$valid?'Platzierung bezahlt – Start wählen':'Zahlung benötigt manuelle Klärung',$valid?'Deine Platzierung ist bezahlt. Starte sie innerhalb von sieben Tagen ausdrücklich.':'Die Reservierung ist abgelaufen. Der Betreiber muss Erfüllung oder Erstattung klären.','/user/promotions.php',true);
}

function business_end_time(string $start, string $scope): string
{
    $date = new DateTimeImmutable($start,new DateTimeZone('UTC'));
    if ($scope !== 'banner_monthly') return $date->modify('+7 days')->format('Y-m-d H:i:s');
    $next = $date->modify('first day of next month');
    return $next->setDate((int)$next->format('Y'),(int)$next->format('m'),min((int)$date->format('d'),(int)$next->format('t')))->format('Y-m-d H:i:s');
}

function business_start_booking(PDO $pdo, int $userId, int $bookingId, bool $immediate): void
{
    if (!$immediate) throw new InvalidArgumentException('Bitte den sofortigen Beginn ausdrücklich verlangen.');
    $q = $pdo->prepare('SELECT invoice_id FROM market_premium_bookings WHERE id=? AND user_id=?'); $q->execute([$bookingId,$userId]); $invoiceId = $q->fetchColumn();
    if (!$invoiceId) throw new DomainException('Buchung nicht gefunden.');
    $pdo->beginTransaction();
    try {
        market_assert_users_active($pdo,[$userId]);
        $paidQuery=$pdo->prepare('SELECT status FROM market_invoices WHERE id=? FOR SHARE');$paidQuery->execute([$invoiceId]);$paidStatus=$paidQuery->fetchColumn();
        $booking = business_locked_booking($pdo,(int)$invoiceId);
        if (!$booking || (int)$booking['user_id'] !== $userId || $booking['status'] !== 'awaiting_start' || strtotime($booking['reserved_until'].' UTC') <= time()) throw new DomainException('Diese Buchung ist nicht zum Start verfügbar.');
        if(!(function_exists('geo_country_locked_enabled')?geo_country_locked_enabled($pdo,$booking['country_code']):business_country_enabled($pdo,$booking['country_code'])))throw new DomainException('Der Zielmarkt ist nicht mehr freigegeben. Bitte den Betreiber zur Klärung kontaktieren.');
        $listing = market_find($pdo,(int)$booking['listing_id'],false);
        if (!$listing || (int)$listing['seller_id'] !== $userId || business_country($listing['country_code'] ?? 'DE') !== $booking['country_code'] || ($booking['scope'] !== 'home_top' && (int)$listing['category_id'] !== (int)$booking['category_id']) || ($listing['type'] === 'auction' && market_expired($listing))) throw new DomainException('Das zugeordnete Angebot ist nicht mehr passend aktiv. Bitte den Betreiber zur Klärung kontaktieren.');
        if ($paidStatus !== 'paid') throw new DomainException('Die Zahlung ist nicht bestätigt.');
        if ($booking['scope'] === 'banner_monthly') {
            $q = $pdo->prepare("SELECT c.id FROM market_campaigns c JOIN market_dealer_profiles p ON p.user_id=c.user_id WHERE c.id=? AND c.status='approved' AND p.status='published' FOR SHARE"); $q->execute([$booking['campaign_id']]); if (!$q->fetchColumn()) throw new DomainException('Das Banner oder Händlerprofil ist nicht freigegeben.');
        }
        if (business_inventory_busy($pdo,(int)$booking['inventory_id'],(int)$booking['id'])) throw new DomainException('Dieser Platz wurde zwischenzeitlich belegt. Keine Überbuchung wurde vorgenommen.');
        $start = gmdate('Y-m-d H:i:s'); $end = business_end_time($start,$booking['scope']);
        $pdo->prepare("UPDATE market_premium_bookings SET status='active',starts_at=?,ends_at=?,immediate_start_at=UTC_TIMESTAMP() WHERE id=?")->execute([$start,$end,$booking['id']]);
        audit_log($pdo,$userId,'booking.started',$bookingId,['scope'=>$booking['scope'],'country'=>$booking['country_code'],'explicit_start'=>true,'ends_at'=>$end]); $pdo->commit();
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}

/** Finance calls these under its existing invoice transaction. */
function business_cancel_invoice(PDO $pdo, int $invoiceId): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Cancellation requires transaction.');
    $booking = business_locked_booking($pdo,$invoiceId);
    if ($booking) $pdo->prepare("UPDATE market_premium_bookings SET status='cancelled' WHERE id=?")->execute([$booking['id']]);
}

function business_refund_invoice(PDO $pdo, int $invoiceId): void
{
    business_cancel_invoice($pdo,$invoiceId);
    $pdo->prepare('UPDATE market_bump_credits SET remaining=0 WHERE invoice_id=?')->execute([$invoiceId]);
}

function business_sponsored(PDO $pdo, string $country, ?int $category = null, string $scope = 'home_top', int $limit = 3): array
{
    $country = business_country($country); if (!business_country_enabled($pdo,$country) || !in_array($scope,['home_top','category_top'],true)) return [];
    $categoryKey = $scope === 'home_top' ? 0 : (int)$category; $limit = max(1,min(business_scope($scope)['limit'],$limit));
    $q = $pdo->prepare("SELECT l.*,c.name AS category_name,u.username AS seller_username,(SELECT COUNT(*) FROM market_images m WHERE m.listing_id=l.id) AS image_count,b.id AS booking_id FROM market_premium_bookings b JOIN market_listings l ON l.id=b.listing_id JOIN market_categories c ON c.id=l.category_id JOIN users u ON u.id=l.seller_id JOIN market_invoices i ON i.id=b.invoice_id WHERE b.country_code=? AND b.scope=? AND b.category_id=? AND b.status='active' AND b.starts_at<=UTC_TIMESTAMP() AND b.ends_at>UTC_TIMESTAMP() AND i.status='paid' AND l.status='active' AND u.is_active=1 ORDER BY b.id LIMIT ".$limit);
    $q->execute([$country,$scope,$categoryKey]); $out=[];
    foreach ($q->fetchAll() as $row) if (catalog_category_allowed($pdo,(int)$row['category_id']) && business_country($row['country_code'] ?? 'DE') === $country && ($row['type'] !== 'auction' || !market_expired($row))) { $row['sponsored']=true; $out[]=$row; }
    return $out;
}

function business_banners(PDO $pdo, string $country, ?int $category = null, int $limit = 2): array
{
    $country = business_country($country); if (!business_country_enabled($pdo,$country)) return [];
    $limit=max(1,min(2,$limit));
    $q=$pdo->prepare("SELECT DISTINCT c.id,c.title,c.country_code,c.category_id FROM market_campaigns c JOIN market_premium_bookings b ON b.campaign_id=c.id JOIN market_invoices i ON i.id=b.invoice_id JOIN users u ON u.id=c.user_id JOIN market_dealer_profiles p ON p.user_id=c.user_id WHERE c.country_code=? AND c.status='approved' AND p.status='published' AND u.is_active=1 AND b.status='active' AND b.starts_at<=UTC_TIMESTAMP() AND b.ends_at>UTC_TIMESTAMP() AND i.status='paid'".($category!==null?' AND c.category_id=?':'').' ORDER BY c.id LIMIT '.$limit);
    $q->execute($category!==null?[$country,$category]:[$country]); $out=[];
    foreach($q->fetchAll() as $row)if(catalog_category_allowed($pdo,(int)$row['category_id'])){$row['image_url']='/marketplace/banner.php?id='.$row['id'].'&country='.$country;$row['click_url']='/marketplace/banner-click.php?id='.$row['id'].'&country='.$country;$out[]=$row;}
    return $out;
}

function business_public_campaign(PDO $pdo,int $id,string $country):?array
{
    $country=business_country($country);if(!business_country_enabled($pdo,$country))return null;
    $q=$pdo->prepare("SELECT c.* FROM market_campaigns c JOIN users u ON u.id=c.user_id JOIN market_dealer_profiles p ON p.user_id=c.user_id WHERE c.id=? AND c.country_code=? AND c.status='approved' AND u.is_active=1 AND p.status='published' AND EXISTS(SELECT 1 FROM market_premium_bookings b JOIN market_invoices i ON i.id=b.invoice_id WHERE b.campaign_id=c.id AND b.status='active' AND b.starts_at<=UTC_TIMESTAMP() AND b.ends_at>UTC_TIMESTAMP() AND i.status='paid')");$q->execute([$id,$country]);$row=$q->fetch();
    return $row&&catalog_category_allowed($pdo,(int)$row['category_id'])?$row:null;
}

function business_record_metric(PDO $pdo,int $campaignId,string $kind):void
{
    if(!in_array($kind,['impression','click'],true))throw new InvalidArgumentException('Invalid metric.');
    $key=getenv('APP_KEY');if(!is_string($key)||$key==='')return;
    $metricKey=hash_hkdf('sha256',$key,32,'gunindex.ad.metrics.v1');$bucket=intdiv(time(),3600);$visitor=hash_hmac('sha256',$campaignId.':'.$kind.':'.$bucket.':'.($_SERVER['REMOTE_ADDR']??''),$metricKey);
    $pdo->beginTransaction();
    try{
        $q=$pdo->prepare('INSERT IGNORE INTO market_ad_metric_dedupe(campaign_id,kind,hour_bucket,visitor_hash) VALUES (?,?,?,?)');$q->execute([$campaignId,$kind,$bucket,$visitor]);
        if($q->rowCount()===1){$column=$kind==='click'?'clicks':'impressions';$pdo->prepare('INSERT INTO market_ad_daily_stats(campaign_id,day_date,'.$column.') VALUES (?,UTC_DATE(),1) ON DUPLICATE KEY UPDATE '.$column.'='.$column.'+1')->execute([$campaignId]);}
        $pdo->commit();
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();error_log('Advertisement metric unavailable');}
}

function business_worker_once(PDO $pdo,int $limit=200):array
{
    $limit=max(1,min(1000,$limit));$ids=$pdo->query("SELECT invoice_id FROM market_premium_bookings WHERE (status IN ('reserved','awaiting_start') AND reserved_until<=UTC_TIMESTAMP()) OR (status='active' AND ends_at<=UTC_TIMESTAMP()) ORDER BY id LIMIT ".$limit)->fetchAll(PDO::FETCH_COLUMN);$changed=0;$review=0;
    foreach($ids as $id){$pdo->beginTransaction();try{$paidQuery=$pdo->prepare('SELECT status FROM market_invoices WHERE id=? FOR SHARE');$paidQuery->execute([$id]);$paid=$paidQuery->fetchColumn()==='paid';$booking=business_locked_booking($pdo,(int)$id);if(!$booking){$pdo->commit();continue;}$state=null;if($booking['status']==='active'&&strtotime($booking['ends_at'].' UTC')<=time())$state='expired';elseif(in_array($booking['status'],['reserved','awaiting_start'],true)&&strtotime($booking['reserved_until'].' UTC')<=time())$state=$paid?'paid_unfulfilled':'expired';if($state!==null){$pdo->prepare('UPDATE market_premium_bookings SET status=? WHERE id=?')->execute([$state,$booking['id']]);$changed++;if($state==='paid_unfulfilled')$review++;}$pdo->commit();}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw$error;}}
    $pdo->exec('DELETE FROM market_ad_metric_dedupe WHERE created_at<UTC_TIMESTAMP()-INTERVAL 2 DAY');
    return ['changed'=>$changed,'payment_reviews'=>$review];
}

function business_bulk_transition(PDO $pdo,int $userId,array $ids,string $action):int
{
    if(!in_array($action,['pause','archive'],true)||$ids===[]||count($ids)>100)throw new InvalidArgumentException('Bitte höchstens 100 eigene Inserate und eine zulässige Aktion auswählen.');
    $clean=[];foreach($ids as $id){if(!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<1)throw new InvalidArgumentException('Ungültige Inseratsauswahl.');$clean[]=(int)$id;}$clean=array_values(array_unique($clean));sort($clean,SORT_NUMERIC);
    $pdo->beginTransaction();try{market_assert_users_active($pdo,[$userId]);foreach($clean as $id){$listing=market_lock_listing($pdo,$id);if((int)$listing['seller_id']!==$userId||!in_array($listing['status'],['draft','active','paused'],true)||market_has_bids($pdo,$id)||($listing['type']==='auction'&&$listing['status']==='active'&&market_expired($listing))||($action==='pause'&&$listing['status']!=='active'))throw new DomainException('Mindestens ein Angebot ist fremd, bereits gebunden oder nicht im passenden Zustand. Es wurde nichts geändert.');}
        $q=$pdo->prepare('UPDATE market_listings SET status=?,updated_at=UTC_TIMESTAMP() WHERE id=?');foreach($clean as $id)$q->execute([$action==='pause'?'paused':'archived',$id]);audit_log($pdo,$userId,'dealer.bulk_'.$action,null,['listing_ids'=>$clean]);$pdo->commit();return count($clean);
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw$error;}
}

function business_csv_cell(mixed $value):string
{
    $value=(string)$value;return preg_match('/\A\s*[=+@\-\t\r]/u',$value)?"'".$value:$value;
}

function business_import_csv(PDO $pdo,int $userId,array $file,bool $rules):array
{
    if(!$rules)throw new InvalidArgumentException('Bitte die Produktregeln für den Import bestätigen.');
    if(($file['error']??null)!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name'])||!is_int($file['size']??null)||$file['size']<1||$file['size']>1048576||filesize($file['tmp_name'])!==$file['size'])throw new InvalidArgumentException('Bitte eine vollständige CSV-Datei bis 1 MiB hochladen.');
    $handle=fopen($file['tmp_name'],'rb');if(!$handle)throw new RuntimeException('CSV nicht lesbar.');
    $columns=['title','description','category_slug','type','price','buyout_price','duration_days','country_code','zip','city','condition_label','pickup','shipping'];
    $records=[];
    try{
        $header=fgetcsv($handle,20001,',','"','');if(is_array($header)&&isset($header[0]))$header[0]=preg_replace('/\A\xEF\xBB\xBF/','',$header[0]);
        if($header!==$columns)throw new InvalidArgumentException('Die CSV-Kopfzeile stimmt nicht mit der Vorlage überein.');
        while(($values=fgetcsv($handle,30001,',','"',''))!==false){
            if(count($records)>=100)throw new InvalidArgumentException('Maximal 100 Datensätze pro Import. Es wurde noch kein Datensatz aus dieser Datei angelegt.');
            $records[]=$values;
        }
    }finally{fclose($handle);}
    $lock=$pdo->prepare('SELECT GET_LOCK(?,5)');$lock->execute(['dealer.import.'.$userId]);
    if((int)$lock->fetchColumn()!==1)throw new DomainException('Ein Import für dieses Konto läuft bereits.');
    $report=['created'=>0,'duplicates'=>0,'errors'=>[]];
    try{
        foreach($records as $index=>$values){
            try{
                if(count($values)!==count($columns))throw new InvalidArgumentException('Falsche Spaltenanzahl.');
                foreach($values as $value)if(!is_string($value)||!preg_match('//u',$value)||strlen($value)>20000)throw new InvalidArgumentException('Ungültiger UTF-8-Text oder Feldlänge.');
                $data=array_combine($columns,$values);$q=$pdo->prepare('SELECT id FROM market_categories WHERE slug=?');$q->execute([$data['category_slug']]);$data['category_id']=(string)($q->fetchColumn()?:'');$data['accept_rules']='1';$data['status']='active';
                $valid=market_validate($pdo,$data);$country=business_country($data['country_code']);
                $q=$pdo->prepare('SELECT id FROM market_listings WHERE seller_id=? AND title=? AND description=? AND price_cents=? AND zip=? AND city=? AND country_code=? LIMIT 1');
                $q->execute([$userId,$valid['title'],$valid['description'],$valid['price_cents'],$valid['zip'],$valid['city'],$country]);
                if($q->fetchColumn()){$report['duplicates']++;continue;}
                market_save_listing($pdo,$userId,$data);$report['created']++;
            }catch(InvalidArgumentException|DomainException|MarketAccessException|MarketConflictException $error){$report['errors'][]=['row'=>$index+2,'message'=>$error->getMessage()];}
            catch(Throwable){$report['errors'][]=['row'=>$index+2,'message'=>'Technischer Fehler; dieser Datensatz wurde nicht bestätigt.'];}
        }
    }finally{$q=$pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute(['dealer.import.'.$userId]);}
    return $report;
}
