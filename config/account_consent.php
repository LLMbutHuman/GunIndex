<?php
declare(strict_types=1);

function consent_account_enabled(PDO $pdo, bool $lock = false): bool
{
    if (!$lock) return site_setting($pdo,'legal_existing_account_required','0') === '1';
    if (!$pdo->inTransaction()) throw new LogicException('Account consent requires a transaction lock.');
    $query = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key='legal_existing_account_required' FOR SHARE");
    $query->execute();
    return $query->fetchColumn() === '1';
}

function consent_account_pending(PDO $pdo, int $user, string $locale, bool $lock = false): array
{
    if (!consent_account_enabled($pdo,$lock)) return [];
    $documents = consent_documents($pdo,'registration',$locale,$lock);
    if (!$documents) throw new DomainException(t('consent.unavailable'));
    $ids = array_map('intval',array_column($documents,'id'));
    $placeholders = implode(',', array_fill(0,count($ids),'?'));
    $query = $pdo->prepare("SELECT document_id,content_hash FROM market_contract_receipts WHERE user_id=? AND scope='registration' AND context_key='account' AND document_id IN ($placeholders)" . ($lock ? ' FOR SHARE' : ''));
    $query->execute([$user,...$ids]);
    $accepted = $query->fetchAll(PDO::FETCH_KEY_PAIR);
    return array_values(array_filter($documents, static function (array $document) use ($accepted): bool {
        $hash = $accepted[(int)$document['id']] ?? null;
        if ($hash === null) return true;
        if (!hash_equals($document['content_hash'],$hash)) throw new DomainException(t('consent.unavailable'));
        return false;
    }));
}

function consent_account_submit(PDO $pdo, int $user, string $locale, array $input): void
{
    trade_transaction($pdo, static function () use ($pdo,$user,$locale,$input): void {
        $account = $pdo->prepare('SELECT id FROM users WHERE id=? AND is_active=1 FOR UPDATE');
        $account->execute([$user]);
        if ($account->fetchColumn() === false) throw new DomainException(t('consent.unavailable'));
        $pending = consent_account_pending($pdo,$user,$locale,true);
        if (!$pending) return;
        consent_validate_submission($pending,$input,$locale);
        consent_record($pdo,$user,'registration',null,$pending,'updated_terms');
    });
}

function consent_account_forms(): array
{
    return ['user/create-listing.php','user/edit-listing.php','user/checkout.php','user/relist.php','user/extend-listing.php'];
}

function consent_account_required_route(string $route, string $method, array $input = []): bool
{
    if (in_array($route,consent_account_forms(),true)) return in_array($method,['GET','HEAD','POST'],true);
    if ($method !== 'POST') return false;
    return match ($route) {
        'actions/place-bid.php','marketplace/buyout-request.php','user/dealer-subscription.php','user/banner-advertising.php' => true,
        'actions/manage-auction.php' => ($input['action'] ?? null) === 'publish',
        'user/billing.php','user/promotions.php' => ($input['action'] ?? null) === 'order',
        'user/offers.php' => ($input['action'] ?? null) === 'create_offer',
        'user/dealer.php' => in_array($input['action'] ?? null,['import','campaign'],true),
        default => false,
    };
}

function consent_account_return(mixed $return): string
{
    $allowed = array_merge(consent_account_forms(), ['user/dashboard.php','user/billing.php','user/promotions.php','user/offers.php',
        'user/dealer.php','user/dealer-subscription.php','user/banner-advertising.php','marketplace/view-item.php','marketplace/view-auction.php']);
    if (!is_string($return) || strlen($return)>1024 || !str_starts_with($return,'/') || str_starts_with($return,'//')
        || preg_match('/[\x00-\x20\x7f\\\\]/',rawurldecode($return))) return '/user/dashboard.php';
    $path = parse_url($return,PHP_URL_PATH);
    if (!is_string($path) || !in_array(substr($path,1),$allowed,true)) return '/user/dashboard.php';
    return $return;
}

function consent_account_gate_http(PDO $pdo, string $route): void
{
    if (PHP_SAPI === 'cli' || !consent_account_required_route($route,$_SERVER['REQUEST_METHOD'] ?? 'GET',$_POST)
        || !consent_account_enabled($pdo)) return;
    $user = current_user($pdo);
    if (!$user) return;
    try {
        if (!consent_account_pending($pdo,(int)$user['id'],i18n_locale())) return;
    } catch (DomainException|InvalidArgumentException) {
        // The consent screen explains unavailable or incomplete approved documents.
    }
    $return = consent_account_return($_SERVER['REQUEST_URI'] ?? null);
    redirect('/user/account-consent.php?return=' . rawurlencode($return));
}
