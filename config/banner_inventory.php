<?php
declare(strict_types=1);

const BANNER_COUNTRY_CAPACITY = 3;
const BANNER_BOOKING_SCOPES = ['banner_country','banner_home','banner_search','banner_monthly'];

function banner_ads_lock_country(PDO $pdo, string $country, bool $enabled = false): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Banner inventory requires a transaction.');
    $q = $pdo->prepare('SELECT is_active FROM market_countries WHERE code=? FOR UPDATE');
    $q->execute([$country]);
    $status = $q->fetchColumn();
    if ($status === false || ($enabled && (int)$status !== 1)) throw new DomainException(t('promotion.country_disabled'));
}

/** End times are exclusive. Only time ranges intersecting the proposed interval consume capacity. */
function banner_ads_capacity_available(array $claims, string $start, string $end): bool
{
    $events = [];
    foreach ($claims as [$from,$until]) {
        $from = max($from,$start);
        $until = min($until,$end);
        if ($from >= $until) continue;
        $events[$from] = ($events[$from] ?? 0) + 1;
        $events[$until] = ($events[$until] ?? 0) - 1;
    }
    ksort($events,SORT_STRING);
    $occupied = 0;
    foreach ($events as $delta) {
        $occupied += $delta;
        if ($occupied >= BANNER_COUNTRY_CAPACITY) return false;
    }
    return $start < $end;
}

function banner_ads_capacity_claims(PDO $pdo, string $country, ?int $exceptBooking = null, ?int $exceptCampaign = null): array
{
    // Locking reads see the last committed slot changes even inside the buyer's existing transaction.
    $locking = $pdo->inTransaction();
    $q = $pdo->prepare("SELECT b.status,b.starts_at,b.ends_at,b.reserved_until,b.scope,c.term_days
        FROM market_premium_bookings b JOIN market_campaigns c ON c.id=b.campaign_id
        WHERE b.country_code=? AND b.id<>? AND b.scope IN ('banner_country','banner_home','banner_search','banner_monthly')
        AND ((b.status='active' AND b.ends_at>UTC_TIMESTAMP()) OR
        (b.status IN ('reserved','awaiting_start','awaiting_review') AND b.reserved_until>UTC_TIMESTAMP()))".($locking?' FOR UPDATE OF b':''));
    $q->execute([$country,$exceptBooking ?? 0]);
    $now = gmdate('Y-m-d H:i:s');
    $claims = [];
    foreach ($q->fetchAll() as $row) {
        if ($row['status'] === 'active') {
            $claims[] = [$row['starts_at'],$row['ends_at']];
            continue;
        }
        $pending = match ($row['status']) {
            'awaiting_start' => 0,
            'awaiting_review' => BUSINESS_PAID_START_DAYS,
            default => BUSINESS_PAID_START_DAYS + ($row['scope'] === 'banner_monthly' ? 0 : BUSINESS_BANNER_REVIEW_DAYS),
        };
        $term = $row['scope'] === 'banner_monthly' ? 31 : (int)$row['term_days'];
        $claims[] = [$now,gmdate('Y-m-d H:i:s',strtotime($row['reserved_until'].' UTC') + ($pending+$term)*86400)];
    }
    $q = $pdo->prepare("SELECT scheduled_from,scheduled_until FROM market_campaigns
        WHERE country_code=? AND id<>? AND status='approved' AND scheduled_from IS NOT NULL AND scheduled_until>UTC_TIMESTAMP()
        AND (operator_owned=1 OR agreed_gross_cents=0)".($locking?' FOR UPDATE':''));
    $q->execute([$country,$exceptCampaign ?? 0]);
    return array_merge($claims,$q->fetchAll(PDO::FETCH_NUM));
}

function banner_ads_assert_capacity(PDO $pdo, string $country, string $start, string $end, ?int $booking = null, ?int $campaign = null): void
{
    if (!banner_ads_capacity_available(banner_ads_capacity_claims($pdo,$country,$booking,$campaign),$start,$end)) {
        throw new DomainException(t('promotion.fully_booked'));
    }
}

function banner_ads_reservation_inventory(PDO $pdo, string $country, string $until, int $term, bool $needsReview): int
{
    banner_ads_lock_country($pdo,$country,true);
    $finalDay = $term + BUSINESS_PAID_START_DAYS + ($needsReview ? BUSINESS_BANNER_REVIEW_DAYS : 0);
    $end = gmdate('Y-m-d H:i:s',strtotime($until.' UTC')+$finalDay*86400);
    banner_ads_assert_capacity($pdo,$country,gmdate('Y-m-d H:i:s'),$end);
    $insert = $pdo->prepare("INSERT INTO market_business_inventory(country_code,scope,category_id,slot_no) VALUES (?,'banner_country',0,?) ON DUPLICATE KEY UPDATE id=id");
    for ($slot=1; $slot<=BANNER_COUNTRY_CAPACITY; $slot++) $insert->execute([$country,$slot]);
    $q = $pdo->prepare("SELECT id FROM market_business_inventory WHERE country_code=? AND scope='banner_country' AND category_id=0 AND slot_no BETWEEN 1 AND 3 ORDER BY slot_no FOR UPDATE");
    $q->execute([$country]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) {
        if (!business_inventory_busy($pdo,(int)$id)) return (int)$id;
    }
    throw new DomainException(t('promotion.fully_booked'));
}

function banner_ads_track_conflict(PDO $pdo, int $booking): void
{
    $pdo->prepare('INSERT INTO market_banner_capacity_cases(booking_id) VALUES (?) ON DUPLICATE KEY UPDATE booking_id=booking_id')->execute([$booking]);
}

function banner_ads_capacity_cases(PDO $pdo, ?string $country, ?int $before = null): array
{
    $q = $pdo->prepare("SELECT x.*, b.country_code,b.status,b.starts_at,b.ends_at,b.immediate_start_at,c.title,
        i.status AS invoice_status,i.invoice_number FROM market_banner_capacity_cases x
        JOIN market_premium_bookings b ON b.id=x.booking_id JOIN market_campaigns c ON c.id=b.campaign_id
        JOIN market_invoices i ON i.id=b.invoice_id
        WHERE x.resolved_at IS NULL AND (? IS NULL OR b.country_code=?) AND (? IS NULL OR x.booking_id<?)
        ORDER BY x.booking_id DESC LIMIT 26");
    $q->execute([$country,$country,$before,$before]);
    return $q->fetchAll();
}

function banner_ads_resolve_capacity(PDO $pdo, int $actor, int $bookingId, string $reason, string $replacement, bool $agreed): void
{
    business_assert_manager($pdo,$actor);
    governance_assert_actor($pdo,$actor,'finance.manage');
    $reason = trim($reason);
    if (!$agreed || mb_strlen($reason)<10 || mb_strlen($reason)>1000) throw new InvalidArgumentException(t('banner_ads.capacity_resolution_invalid'));
    $newStart = $replacement === '' ? null : banner_ads_time($replacement);
    $pdo->beginTransaction();
    try {
        business_assert_manager($pdo,$actor);
        governance_assert_actor($pdo,$actor,'finance.manage');
        $q = $pdo->prepare('SELECT i.id,i.status FROM market_premium_bookings b JOIN market_invoices i ON i.id=b.invoice_id WHERE b.id=? FOR UPDATE OF i');
        $q->execute([$bookingId]);
        $invoice = $q->fetch();
        $booking = $invoice ? business_locked_booking($pdo,(int)$invoice['id']) : null;
        $q = $pdo->prepare('SELECT booking_id FROM market_banner_capacity_cases WHERE booking_id=? AND resolved_at IS NULL FOR UPDATE');
        $q->execute([$bookingId]);
        if (!$booking || !$q->fetchColumn() || !in_array($booking['scope'],BANNER_BOOKING_SCOPES,true)) {
            throw new DomainException(t('banner_ads.changed'));
        }
        $newEnd = null;
        if ($newStart !== null) {
            if ($invoice['status'] !== 'paid' || $booking['immediate_start_at'] === null || $newStart < gmdate('Y-m-d H:i:s')
                || !in_array($booking['status'],['active','expired','paid_unfulfilled'],true)) {
                throw new DomainException(t('banner_ads.capacity_resolution_invalid'));
            }
            $country = $booking['country_code'];
            banner_ads_lock_country($pdo,$country,true);
            $q = $pdo->prepare("SELECT * FROM market_campaigns WHERE id=? AND status='approved' AND user_id=? AND country_code=? FOR UPDATE");
            $q->execute([$booking['campaign_id'],$booking['user_id'],$country]);
            $campaign = $q->fetch();
            if (!$campaign || !business_dealer_profile($pdo,(int)$booking['user_id'])
                || site_setting($pdo,'business_promotions_enabled','0')!=='1' || site_setting($pdo,'dealer_profiles_enabled','1')!=='1'
                || ($campaign['category_id'] !== null && !catalog_category_allowed($pdo,(int)$campaign['category_id']))) {
                throw new DomainException(t('promotion.banner_review'));
            }
            banner_ads_destination($campaign);
            $newEnd = $booking['scope']==='banner_monthly' ? business_end_time($newStart,'banner_monthly')
                : (new DateTimeImmutable($newStart,new DateTimeZone('UTC')))->modify('+'.(int)$campaign['term_days'].' days')->format('Y-m-d H:i:s');
            banner_ads_assert_capacity($pdo,$country,$newStart,$newEnd,$bookingId);
            $pdo->prepare("UPDATE market_premium_bookings SET status='active',starts_at=?,ends_at=? WHERE id=?")->execute([$newStart,$newEnd,$bookingId]);
        }
        $pdo->prepare('UPDATE market_banner_capacity_cases SET resolved_at=UTC_TIMESTAMP(),resolved_by=?,resolution=? WHERE booking_id=?')
            ->execute([$actor,$reason,$bookingId]);
        audit_log($pdo,$actor,'campaign.capacity_resolved',$bookingId,['reason'=>$reason,'invoice_id'=>(int)$invoice['id'],
            'previous_start'=>$booking['starts_at'],'previous_end'=>$booking['ends_at'],'replacement_start'=>$newStart,'replacement_end'=>$newEnd]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
