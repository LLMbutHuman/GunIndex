<?php
declare(strict_types=1);
require_once __DIR__.'/banner_media.php';

const BANNER_AD_PLACEMENTS = ['home', 'search'];
const BANNER_AD_TERMS = [7, 14, 30];

function banner_ads_selection(string $placement, string $days): array
{
    if (!in_array($placement, BANNER_AD_PLACEMENTS, true) || !in_array($days, ['7', '14', '30'], true)) {
        throw new InvalidArgumentException(t('promotion.invalid_selection'));
    }
    return [$placement, (int)$days];
}

function banner_ads_url(string $value): string
{
    $url = business_validate_url($value);
    if (!str_starts_with($url, 'https://')) throw new InvalidArgumentException(t('banner_ads.invalid_url'));
    return $url;
}

function banner_ads_input(PDO $pdo, array $input): array
{
    $title = trim(market_input($input, 'title'));
    $overlay = trim(market_input($input, 'overlay_text'));
    $style = market_input($input, 'overlay_style', 'plain');
    $category = market_input($input, 'category_id');
    $country = business_country(market_input($input, 'country_code'));
    if (!business_country_enabled($pdo, $country) || mb_strlen($title) < 5 || mb_strlen($title) > 120
        || mb_strlen($overlay) > 120 || !catalog_text_allowed($title) || !catalog_text_allowed($overlay)
        || !in_array($style, ['plain', 'dark', 'light'], true)
        || ($category !== '' && (!ctype_digit($category) || !catalog_category_allowed($pdo, (int)$category)))) {
        throw new InvalidArgumentException(t('banner_ads.invalid_details'));
    }
    return [
        'title'=>$title, 'overlay_text'=>$overlay, 'overlay_style'=>$style, 'country_code'=>$country,
        'category_id'=>$category === '' ? null : (int)$category,
        'target_url'=>banner_ads_url(market_input($input, 'target_url')),
    ];
}

function banner_ads_quote(PDO $pdo, string $placement, int $days): array
{
    banner_ads_selection($placement, (string)$days);
    $q = $pdo->prepare('SELECT gross_cents FROM market_banner_rates WHERE placement=? AND term_days=?'.($pdo->inTransaction()?' FOR SHARE':''));
    $q->execute([$placement, $days]);
    $gross = $q->fetchColumn();
    $gross = $gross === false ? intdiv(fees_quote('banner_monthly', null, $pdo)['gross_cents'] * $days + 15, 30) : (int)$gross;
    $lang = i18n_locale();
    $terms = t('banner_ads.customer_terms', ['place'=>t('banner_ads.'.$placement), 'days'=>$days, 'amount'=>money_eur($gross, $lang)]);
    $receipt = $terms."\n\n".t('billing_customer.confirm');
    $hash = hash('sha256', json_encode([$placement,$days,$gross,'EUR',$lang,$receipt], JSON_THROW_ON_ERROR));
    return ['gross_cents'=>$gross, 'language'=>$lang, 'receipt'=>$receipt, 'terms'=>$terms, 'approval_hash'=>$hash,
        'version'=>'banner-ads-1.0-'.substr($hash, 0, 12)];
}

function banner_ads_save_rate(PDO $pdo, int $actor, string $placement, int $days, int $gross): void
{
    banner_ads_selection($placement, (string)$days);
    if ($gross < 0 || $gross > 999999999) throw new InvalidArgumentException(t('banner_ads.invalid_details'));
    $pdo->beginTransaction();
    try {
        business_assert_manager($pdo, $actor);
        $q = $pdo->prepare('SELECT gross_cents FROM market_banner_rates WHERE placement=? AND term_days=? FOR UPDATE');
        $q->execute([$placement, $days]);
        $previous = $q->fetchColumn();
        $q = $pdo->prepare('INSERT INTO market_banner_rates(placement,term_days,gross_cents,updated_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE gross_cents=VALUES(gross_cents),updated_by=VALUES(updated_by)');
        $q->execute([$placement, $days, $gross, $actor]);
        audit_log($pdo, $actor, 'campaign.rate_changed', null, ['place'=>$placement,'days'=>$days,'old_cents'=>$previous===false?null:(int)$previous,'gross_cents'=>$gross]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function banner_ads_insert(PDO $pdo, int $owner, array $details, array $media, string $placement, bool $operator, int $priority,
    ?string $start, ?string $end, ?int $days, ?int $gross, ?string $request): int
{
    $q = $pdo->prepare("INSERT INTO market_campaigns(user_id,country_code,category_id,title,target_url,storage_key,status,placement,operator_owned,priority,creative_format,overlay_text,overlay_style,scheduled_from,scheduled_until,term_days,agreed_gross_cents,submission_key) VALUES (?,?,?,?,?,?,'pending',?,?,?,?,?,?,?,?,?,?,?)");
    $q->execute([$owner,$details['country_code'],$details['category_id'],$details['title'],$details['target_url'],$media['key'],
        $placement,(int)$operator,$priority,$media['format'],$details['overlay_text'],$details['overlay_style'],$start,$end,$days,$gross,$request]);
    return (int)$pdo->lastInsertId();
}

function banner_ads_time(string $value): string
{
    $time = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone('UTC'));
    if (!$time || $time->format('Y-m-d\TH:i') !== $value || $time->format('Y') > '2099') {
        throw new InvalidArgumentException(t('banner_ads.invalid_dates'));
    }
    return $time->format('Y-m-d H:i:s');
}

function banner_ads_operator_save(PDO $pdo, int $actor, ?int $id, array $input, array $upload): int
{
    business_assert_manager($pdo, $actor);
    [$placement] = banner_ads_selection(market_input($input,'placement'), '7');
    $details = banner_ads_input($pdo, $input);
    $start = banner_ads_time(market_input($input, 'scheduled_from'));
    $end = banner_ads_time(market_input($input, 'scheduled_until'));
    if ($end <= $start || $end <= gmdate('Y-m-d H:i:s')) throw new InvalidArgumentException(t('banner_ads.invalid_dates'));
    $priority = market_input($input, 'priority', '0');
    if (!preg_match('/\A\d{1,4}\z/', $priority)) throw new InvalidArgumentException(t('banner_ads.invalid_details'));
    $media = $id !== null && ($upload['error']??UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ? null : banner_prepare_media($upload, $details['overlay_text']);
    try {
        $pdo->beginTransaction();
        business_assert_manager($pdo, $actor);
        if ($id === null) {
            $id = banner_ads_insert($pdo,$actor,$details,$media,$placement,true,(int)$priority,$start,$end,null,null,null);
            $action = 'campaign.operator_created';
        } else {
            $q = $pdo->prepare('SELECT * FROM market_campaigns WHERE id=? AND operator_owned=1 FOR UPDATE');
            $q->execute([$id]);
            $previous = $q->fetch();
            if (!$previous || $previous['status'] === 'removed') throw new DomainException(t('admin_campaigns.campaign_missing'));
            $mediaFormat = $media['format'] ?? $previous['creative_format'];
            if ($mediaFormat === 'text' && $details['overlay_text'] === '') throw new InvalidArgumentException(t('banner_ads.invalid_media'));
            $q = $pdo->prepare("UPDATE market_campaigns SET country_code=?,category_id=?,title=?,target_url=?,placement=?,priority=?,overlay_text=?,overlay_style=?,scheduled_from=?,scheduled_until=?,storage_key=?,creative_format=?,status='pending' WHERE id=?");
            $q->execute([$details['country_code'],$details['category_id'],$details['title'],$details['target_url'],$placement,(int)$priority,
                $details['overlay_text'],$details['overlay_style'],$start,$end,$media['key']??$previous['storage_key'],$mediaFormat,$id]);
            $action = 'campaign.operator_updated';
        }
        audit_log($pdo,$actor,$action,$id,['country'=>$details['country_code'],'category_id'=>$details['category_id'],'place'=>$placement,
            'priority'=>(int)$priority,'starts_at'=>$start,'ends_at'=>$end,'image_changed'=>$media!==null,'state'=>'pending']);
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($media !== null) market_remove_prepared_images([$media]);
        throw $error;
    }
}

/** Reserve from three country/placement slots regardless of category. */
function banner_ads_reserve(PDO $pdo, int $invoice, int $user, int $campaign, string $country, string $placement): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Advertising reservation requires a transaction.');
    $scope = 'banner_'.$placement;
    $insert = $pdo->prepare('INSERT INTO market_business_inventory(country_code,scope,category_id,slot_no) VALUES (?,?,0,?) ON DUPLICATE KEY UPDATE id=id');
    for ($slot = 1; $slot <= 3; $slot++) $insert->execute([$country,$scope,$slot]);
    $q = $pdo->prepare('SELECT id FROM market_business_inventory WHERE country_code=? AND scope=? AND category_id=0 ORDER BY slot_no FOR UPDATE');
    $q->execute([$country,$scope]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $slot) {
        if (business_inventory_busy($pdo,(int)$slot)) continue;
        $until = gmdate('Y-m-d H:i:s', time() + business_reservation_minutes($pdo)*60);
        $q = $pdo->prepare('INSERT INTO market_premium_bookings(invoice_id,user_id,inventory_id,listing_id,campaign_id,country_code,scope,category_id,reserved_until) VALUES (?,?,?,NULL,?,?,?,0,?)');
        $q->execute([$invoice,$user,$slot,$campaign,$country,$scope,$until]);
        return;
    }
    throw new DomainException(t('promotion.fully_booked'));
}

function banner_ads_submit(PDO $pdo, int $userId, array $input, array $upload): array
{
    if (site_setting($pdo,'business_promotions_enabled','0') !== '1') throw new PaymentUnavailable(t('promotion.disabled'));
    if (site_setting($pdo,'dealer_profiles_enabled','1')!=='1' || !business_dealer_profile($pdo,$userId)) throw new DomainException(t('dealer_dashboard.profile_required'));
    [$place,$days] = banner_ads_selection(market_input($input,'placement'), market_input($input,'term_days'));
    $request = market_input($input,'request_key');
    if (!preg_match('/\A[a-f0-9]{64}\z/', $request)) throw new InvalidArgumentException(t('billing_customer.invalid_order'));
    $details = banner_ads_input($pdo, $input);
    $media = banner_prepare_media($upload, $details['overlay_text']);
    try {
        $pdo->beginTransaction();
        maintenance_assert_market_available($pdo);
        $q = $pdo->prepare('SELECT id FROM users WHERE id=? AND is_active=1 FOR UPDATE');
        $q->execute([$userId]);
        if (!$q->fetchColumn()) throw new DomainException(t('billing_customer.account_missing'));
        $q = $pdo->prepare('SELECT c.id,b.invoice_id FROM market_campaigns c LEFT JOIN market_premium_bookings b ON b.campaign_id=c.id WHERE c.user_id=? AND c.submission_key=? FOR UPDATE');
        $q->execute([$userId,$request]);
        if ($previous=$q->fetch()) {
            $pdo->commit();
            market_remove_prepared_images([$media]);
            return [(int)$previous['id'],$previous['invoice_id']===null?null:(int)$previous['invoice_id']];
        }
        $q = $pdo->prepare("SELECT user_id FROM market_dealer_profiles WHERE user_id=? AND status='published' FOR SHARE");
        $q->execute([$userId]);
        if (!$q->fetchColumn()) throw new DomainException(t('dealer_dashboard.profile_required'));
        $q = $pdo->prepare("SELECT COUNT(*) FROM market_campaigns WHERE user_id=? AND placement<>'legacy' AND created_at>UTC_TIMESTAMP()-INTERVAL 1 HOUR");
        $q->execute([$userId]);
        if ((int)$q->fetchColumn() >= 10) throw new PaymentConflict(t('billing_customer.hourly_limit'));
        $quote = banner_ads_quote($pdo,$place,$days);
        $hashes = $input['quote'] ?? null;
        $hash = is_array($hashes) ? ($hashes[$place.'_'.$days] ?? null) : null;
        if (!is_string($hash) || !hash_equals($quote['approval_hash'],$hash)) throw new InvalidArgumentException(t('consent.changed'));
        if (($input['accept_terms']??null) !== '1') throw new InvalidArgumentException(t('billing_customer.terms_required'));
        $gross = $quote['gross_cents'];
        $invoice = null;
        if ($gross > 0) {
            if (($reason = business_reason($pdo)) !== null) throw new PaymentUnavailable($reason);
            $documents = consent_check($pdo,'checkout',$input,$quote['language']);
        }
        $id = banner_ads_insert($pdo,$userId,$details,$media,$place,false,0,null,null,$days,$gross,$request);
        if ($gross > 0) {
            $invoice = manual_store_order($pdo,$userId,null,'banner_advertising',$request,1,$quote);
            banner_ads_reserve($pdo,$invoice,$userId,$id,$details['country_code'],$place);
            finance_prepare_order($pdo,$invoice,$userId);
            consent_record($pdo,$userId,'checkout',$invoice,$documents);
        }
        audit_log($pdo,$userId,'campaign.submitted',$id,['country'=>$details['country_code'],'category_id'=>$details['category_id'],
            'place'=>$place,'days'=>$days,'gross_cents'=>$gross,'invoice_id'=>$invoice,'creative_format'=>$media['format']]);
        $pdo->commit();
        return [$id,$invoice];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        market_remove_prepared_images([$media]);
        throw $error;
    }
}

function banner_ads_revision(array $campaign): string
{
    $fields = ['id','title','target_url','storage_key','creative_format','overlay_text','overlay_style','placement',
        'priority','country_code','category_id','scheduled_from','scheduled_until','status','updated_at'];
    return hash('sha256', json_encode(array_intersect_key($campaign,array_flip($fields)),JSON_THROW_ON_ERROR));
}

function banner_ads_review(PDO $pdo, int $actor, int $campaignId, string $decision, string $reason, string $revision): void
{
    business_assert_manager($pdo,$actor);
    $reason = trim($reason);
    if (!in_array($decision,['approved','rejected','disabled','removed'],true)
        || mb_strlen($reason)<10 || mb_strlen($reason)>1000) throw new InvalidArgumentException(t('admin_campaigns.review_validation'));
    $pdo->beginTransaction();
    try {
        business_assert_manager($pdo,$actor);
        $q = $pdo->prepare('SELECT invoice_id FROM market_premium_bookings WHERE campaign_id=? ORDER BY id DESC LIMIT 1');
        $q->execute([$campaignId]);
        $invoiceId = $q->fetchColumn();
        $booking = null;
        $invoiceStatus = null;
        if ($invoiceId !== false) {
            $q = $pdo->prepare('SELECT status FROM market_invoices WHERE id=? FOR UPDATE');
            $q->execute([$invoiceId]);
            $invoiceStatus = $q->fetchColumn();
            $booking = business_locked_booking($pdo,(int)$invoiceId);
        }
        $q = $pdo->prepare('SELECT * FROM market_campaigns WHERE id=? FOR UPDATE');
        $q->execute([$campaignId]);
        $campaign = $q->fetch();
        if (!$campaign || $campaign['placement'] === 'legacy' || $campaign['status'] === 'removed') {
            throw new DomainException(t('admin_campaigns.campaign_missing'));
        }
        if (!hash_equals(banner_ads_revision($campaign),$revision)) throw new DomainException(t('banner_ads.changed'));
        $start = $campaign['scheduled_from'];
        $end = $campaign['scheduled_until'];
        if ($decision === 'approved') {
            if (!business_country_enabled($pdo,$campaign['country_code'])
                || ($campaign['category_id'] !== null && !catalog_category_allowed($pdo,(int)$campaign['category_id']))
                || (!(bool)$campaign['operator_owned'] && !business_dealer_profile($pdo,(int)$campaign['user_id']))) {
                throw new DomainException(t('admin_campaigns.approval_required'));
            }
            banner_ads_url($campaign['target_url']);
            if ($campaign['agreed_gross_cents'] !== null && (int)$campaign['agreed_gross_cents'] === 0 && $start === null) {
                $start = gmdate('Y-m-d H:i:s');
                $end = (new DateTimeImmutable($start,new DateTimeZone('UTC')))->modify('+'.(int)$campaign['term_days'].' days')->format('Y-m-d H:i:s');
            }
            if ($end !== null && $end <= gmdate('Y-m-d H:i:s')) throw new DomainException(t('banner_ads.invalid_dates'));
        }
        $q = $pdo->prepare('UPDATE market_campaigns SET status=?,moderation_reason=?,moderated_by=?,scheduled_from=?,scheduled_until=? WHERE id=?');
        $q->execute([$decision,$reason,$actor,$start,$end,$campaignId]);
        if ($booking && $decision==='approved' && $booking['status']==='awaiting_review' && $invoiceStatus==='paid'
            && strtotime($booking['reserved_until'].' UTC')>time()) {
            $deadline=gmdate('Y-m-d H:i:s',time()+BUSINESS_PAID_START_DAYS*86400);
            $pdo->prepare("UPDATE market_premium_bookings SET status='awaiting_start',reserved_until=? WHERE id=?")->execute([$deadline,$booking['id']]);
            $user=(int)$campaign['user_id'];
            notify_user($pdo,$user,'booking:'.$booking['id'].':awaiting_start',i18n_user_text($pdo,$user,'promotion.paid_title'),
                i18n_user_text($pdo,$user,'promotion.paid_body',['days'=>BUSINESS_PAID_START_DAYS]),'/user/banner-advertising.php',true);
        } elseif ($booking && in_array($decision,['rejected','removed'],true)
            && in_array($booking['status'],['reserved','awaiting_review','awaiting_start','active'],true)) {
            $outcome=$invoiceStatus==='paid'?'paid_unfulfilled':'cancelled';
            $pdo->prepare('UPDATE market_premium_bookings SET status=? WHERE id=?')->execute([$outcome,$booking['id']]);
        }
        audit_log($pdo,$actor,'campaign.reviewed',$campaignId,['old_state'=>$campaign['status'],'status'=>$decision,'reason'=>$reason,
            'starts_at'=>$start,'ends_at'=>$end,'invoice_id'=>$invoiceId===false?null:(int)$invoiceId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** A paid banner starts only when the verified invoice, approved creative and owner consent agree. */
function banner_ads_booking_start(PDO $pdo, array $booking): array
{
    $q = $pdo->prepare("SELECT * FROM market_campaigns WHERE id=? AND user_id=? AND country_code=? AND placement=? AND status='approved' AND operator_owned=0 AND agreed_gross_cents>0 FOR SHARE");
    $q->execute([$booking['campaign_id'],$booking['user_id'],$booking['country_code'],substr($booking['scope'],7)]);
    $campaign = $q->fetch();
    if (!$campaign || site_setting($pdo,'business_promotions_enabled','0')!=='1'
        || site_setting($pdo,'dealer_profiles_enabled','1')!=='1' || !business_dealer_profile($pdo,(int)$booking['user_id'])
        || ($campaign['category_id'] !== null && !catalog_category_allowed($pdo,(int)$campaign['category_id']))) {
        throw new DomainException(t('promotion.banner_review'));
    }
    banner_ads_url($campaign['target_url']);
    return $campaign;
}

/** Logical status is derived from stored review, verified invoice, start and deadline. */
function banner_ads_state(array $campaign): string
{
    if (in_array($campaign['status'],['rejected','disabled','removed'],true)) return $campaign['status'];
    $booking = $campaign['booking_status'] ?? null;
    if ($booking !== null) {
        if (in_array($booking,['cancelled','paid_unfulfilled','expired'],true)) return $booking;
        if ($booking === 'active' && $campaign['ends_at'] <= gmdate('Y-m-d H:i:s')) return 'expired';
        if ($campaign['invoice_status'] !== 'paid') return 'awaiting_payment';
        if ($campaign['status'] === 'pending') return 'pending';
        return $booking;
    }
    if ($campaign['placement'] === 'legacy') return $campaign['status'];
    if ($campaign['status'] === 'pending') return 'pending';
    $now = gmdate('Y-m-d H:i:s');
    if ($campaign['scheduled_until'] !== null && $campaign['scheduled_until'] <= $now) return 'expired';
    return $campaign['scheduled_from'] > $now ? 'scheduled' : 'active';
}

function banner_ads_rows(PDO $pdo, ?int $owner = null, ?int $before = null, ?string $state = null): array
{
    $conditions = [];
    $params = [];
    if ($owner !== null) { $conditions[] = 'c.user_id=? AND c.operator_owned=0'; $params[] = $owner; }
    if ($before !== null) { $conditions[] = 'c.id<?'; $params[] = $before; }
    $where = $conditions ? ' WHERE '.implode(' AND ',$conditions) : '';
    $filter = '';
    if ($state !== null && $state !== 'all') { $filter = ' HAVING view_state=?'; $params[] = $state; }
    $q = $pdo->prepare('SELECT c.*,u.username,b.id AS booking_id,b.status AS booking_status,b.starts_at,b.ends_at,b.reserved_until,
        i.id AS invoice_id,i.status AS invoice_status,i.gross_cents,i.invoice_number,
        CASE
          WHEN c.status IN (\'rejected\',\'disabled\',\'removed\') THEN c.status
          WHEN b.status IN (\'cancelled\',\'paid_unfulfilled\',\'expired\') THEN b.status
          WHEN b.status=\'active\' AND b.ends_at<=UTC_TIMESTAMP() THEN \'expired\'
          WHEN b.id IS NOT NULL AND i.status<>\'paid\' THEN \'awaiting_payment\'
          WHEN b.status=\'awaiting_review\' THEN b.status
          WHEN c.status=\'pending\' THEN c.status
          WHEN b.id IS NOT NULL THEN b.status
          WHEN c.placement=\'legacy\' THEN c.status
          WHEN c.scheduled_until<=UTC_TIMESTAMP() THEN \'expired\'
          WHEN c.scheduled_from>UTC_TIMESTAMP() THEN \'scheduled\'
          ELSE \'active\' END AS view_state,
        COALESCE((SELECT SUM(s.impressions) FROM market_ad_daily_stats s WHERE s.campaign_id=c.id),0) AS impressions,
        COALESCE((SELECT SUM(s.clicks) FROM market_ad_daily_stats s WHERE s.campaign_id=c.id),0) AS clicks
        FROM market_campaigns c JOIN users u ON u.id=c.user_id LEFT JOIN market_premium_bookings b ON b.id=(SELECT MAX(r.id) FROM market_premium_bookings r WHERE r.campaign_id=c.id)
        LEFT JOIN market_invoices i ON i.id=b.invoice_id'.$where.$filter.' ORDER BY c.id DESC LIMIT 26');
    $q->execute($params);
    return $q->fetchAll();
}

function banner_ads_public(PDO $pdo, string $place, string $country, ?int $category = null, ?int $id = null): array
{
    if (!in_array($place,BANNER_AD_PLACEMENTS,true) || !business_country_enabled($pdo,$country)
        || site_setting($pdo,'business_promotions_enabled','0') !== '1') return [];
    $params = [$place,$country];
    $scope = $id === null ? ' AND (c.category_id IS NULL OR ? IS NULL OR c.category_id=?)' : ' AND c.id=?';
    $params = array_merge($params,$id===null ? [$category,$category] : [$id]);
    $q = $pdo->prepare("SELECT c.*,EXISTS(SELECT 1 FROM market_premium_bookings b JOIN market_invoices i ON i.id=b.invoice_id
        WHERE b.campaign_id=c.id AND b.status='active' AND b.starts_at<=UTC_TIMESTAMP() AND b.ends_at>UTC_TIMESTAMP()
          AND i.status='paid' AND i.gross_cents=c.agreed_gross_cents) AS paid_active
        FROM market_campaigns c JOIN users u ON u.id=c.user_id AND u.is_active=1
        LEFT JOIN market_dealer_profiles p ON p.user_id=c.user_id
        WHERE c.placement=? AND c.country_code=? AND c.status='approved' AND (c.operator_owned=1 OR p.status='published')".$scope.
        " AND (EXISTS(SELECT 1 FROM market_premium_bookings b JOIN market_invoices i ON i.id=b.invoice_id
          WHERE b.campaign_id=c.id AND b.status='active' AND b.starts_at<=UTC_TIMESTAMP() AND b.ends_at>UTC_TIMESTAMP()
            AND i.status='paid' AND i.gross_cents=c.agreed_gross_cents)
          OR ((c.operator_owned=1 OR c.agreed_gross_cents=0) AND c.scheduled_from<=UTC_TIMESTAMP() AND c.scheduled_until>UTC_TIMESTAMP()))
        ORDER BY paid_active DESC,c.priority DESC,c.id DESC LIMIT 100");
    $q->execute($params);
    $rows = [];
    $enabled = site_setting($pdo,'dealer_profiles_enabled','1') === '1';
    $candidates = $q->fetchAll();
    if (!$candidates) return [];
    $categories = catalog_rows($pdo);
    foreach ($candidates as $campaign) {
        if ((!$enabled && !(bool)$campaign['operator_owned']) || ($campaign['category_id'] !== null && !catalog_row_allowed($categories,(int)$campaign['category_id']))) continue;
        try { banner_ads_url($campaign['target_url']); } catch (InvalidArgumentException) { continue; }
        $rows[] = $campaign;
        if (count($rows) === 3) break;
    }
    return $rows;
}

function banner_ads_serve(array $campaign, bool $thumbnail = false, ?callable $onServe = null): never
{
    if ($campaign['creative_format'] === 'gif' && !$thumbnail) {
        $key = $campaign['storage_key'];
        $path = preg_match('/\A[a-f0-9]{48}\z/',$key) ? market_image_directory().'/'.$key.'.gif' : '';
        if (!is_file($path) || is_link($path)) abort_request(404,t('admin_campaigns.banner_missing'));
        if ($onServe !== null && ($_SERVER['REQUEST_METHOD']??'') === 'GET') $onServe();
        header('Content-Type: image/gif');
        header('Content-Length: '.filesize($path));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        if (($_SERVER['REQUEST_METHOD']??'') === 'GET') readfile($path);
        exit;
    }
    if ($campaign['creative_format'] === 'text') {
        if ($onServe !== null && ($_SERVER['REQUEST_METHOD']??'') === 'GET') $onServe();
        header('Content-Type: image/gif');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        if (($_SERVER['REQUEST_METHOD']??'') === 'GET') echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }
    business_output_banner($campaign['storage_key'],$thumbnail,$onServe);
}
