<?php
declare(strict_types=1);

function dealer_api_available(): bool
{
    return getenv('DEALER_API_ENABLED') === '1';
}

function dealer_api_scope_bits(): array
{
    return ['inventory.read'=>1,'inventory.write'=>2,'orders.read'=>4];
}

function dealer_api_keys(PDO $pdo, int $owner): array
{
    // The account that owns the keys may inspect and revoke them during review.
    $query = $pdo->prepare('SELECT k.id,k.label,k.scopes,k.created_at,k.expires_at,k.revoked_at FROM market_dealer_api_keys k JOIN users u ON u.id=k.owner_id AND u.is_active=1 WHERE k.owner_id=? ORDER BY (k.revoked_at IS NULL AND k.expires_at>UTC_TIMESTAMP()) DESC,k.id DESC LIMIT 100');
    $query->execute([$owner]);
    $listed = $query->fetchAll();
    foreach ($listed as &$row) {
        $mask = (int)$row['scopes'];
        $row['scopes'] = array_keys(array_filter(dealer_api_scope_bits(), static fn(int $bit): bool => ($mask & $bit) !== 0));
    }
    unset($row);
    return $listed;
}

function dealer_api_issue(PDO $pdo, int $owner, string $label, array $scopes, #[SensitiveParameter] string $password, string $requestKey): string
{
    if (!dealer_api_available()) throw new DomainException(t('dealer_api.unavailable'));
    $label = trim($label);
    if (!preg_match('/\A[^\x00-\x1f\x7f]+\z/u', $label) || mb_strlen($label, 'UTF-8') > 80
        || !array_is_list($scopes) || count($scopes) > 3 || !preg_match('/\A[a-f0-9]{32}\z/', $requestKey)) {
        throw new InvalidArgumentException(t('dealer_api.invalid'));
    }
    $mask = 0;
    foreach ($scopes as $scope) {
        if (!is_string($scope) || !isset(dealer_api_scope_bits()[$scope])) throw new InvalidArgumentException(t('dealer_api.invalid'));
        $mask |= dealer_api_scope_bits()[$scope];
    }
    if ($mask === 0 || strlen($password) > 1024 || $password === '' || str_contains($password, "\0")) {
        throw new InvalidArgumentException(t('dealer_api.invalid'));
    }

    return trade_transaction($pdo, static function () use ($pdo,$owner,$label,$mask,$password,$requestKey): string {
        dealer_team_lock_owner($pdo, $owner);
        $account = $pdo->prepare('SELECT password_hash FROM users WHERE id=? AND is_active=1 FOR SHARE');
        $account->execute([$owner]);
        $hash = $account->fetchColumn();
        if (!is_string($hash) || !password_verify($password, $hash)) throw new DomainException(t('dealer_api.forbidden'));
        $prior = $pdo->prepare('SELECT id FROM market_dealer_api_keys WHERE owner_id=? AND request_key=?');
        $prior->execute([$owner,$requestKey]);
        if ($prior->fetchColumn() !== false) throw new DomainException(t('dealer_api.duplicate'));
        $count = $pdo->prepare('SELECT COUNT(*) FROM market_dealer_api_keys WHERE owner_id=? AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP()');
        $count->execute([$owner]);
        if ((int)$count->fetchColumn() >= 20) throw new DomainException(t('dealer_api.unavailable'));
        $selector = bin2hex(random_bytes(12));
        $secret = bin2hex(random_bytes(32));
        $query = $pdo->prepare('INSERT INTO market_dealer_api_keys(owner_id,label,selector,token_hash,scopes,request_key,expires_at) VALUES (?,?,?,?,?,?,UTC_TIMESTAMP()+INTERVAL 90 DAY)');
        $query->execute([$owner,$label,$selector,hash('sha256',$secret),$mask,$requestKey]);
        audit_log($pdo,$owner,'dealer.api.created',(int)$pdo->lastInsertId(),['scope_mask'=>$mask]);
        return 'gxdk_' . $selector . '_' . $secret;
    });
}

function dealer_api_revoke(PDO $pdo, int $owner, int $id): void
{
    trade_transaction($pdo, static function () use ($pdo,$owner,$id): void {
        $find = $pdo->prepare('SELECT revoked_at FROM market_dealer_api_keys WHERE owner_id=? AND id=? FOR UPDATE');
        $find->execute([$owner,$id]);
        $current = $find->fetch();
        if (!$current) throw new DomainException(t('dealer_api.unavailable'));
        market_assert_users_active($pdo, [$owner]);
        if ($current['revoked_at'] !== null) return;
        $pdo->prepare('UPDATE market_dealer_api_keys SET revoked_at=UTC_TIMESTAMP() WHERE id=? AND owner_id=?')->execute([$id,$owner]);
        audit_log($pdo,$owner,'dealer.api.revoked',$id);
    });
}

final class DealerApiError extends RuntimeException {}

function dealer_api_authenticate(PDO $pdo, #[SensitiveParameter] string $authorization, string $scope): array
{
    if (!$pdo->inTransaction()) throw new LogicException('API authentication must share the operation transaction.');
    if (!preg_match('/\A(?i:Bearer) gxdk_([a-f0-9]{24})_([a-f0-9]{64})\z/', $authorization, $parts)) {
        throw new DealerApiError('invalid_token', 401);
    }
    $query = $pdo->prepare('SELECT id,owner_id,token_hash,scopes,(revoked_at IS NULL AND expires_at>UTC_TIMESTAMP()) AS available FROM market_dealer_api_keys WHERE selector=? FOR SHARE');
    $query->execute([$parts[1]]);
    $key = $query->fetch();
    $matches = hash_equals($key['token_hash'] ?? str_repeat('0', 64), hash('sha256', $parts[2]));
    if (!$matches || !$key || (int)$key['available'] !== 1) throw new DealerApiError('invalid_token', 401);
    $owner = (int)$key['owner_id'];
    try { dealer_team_assert($pdo, $owner, $owner, $scope, true); }
    catch (MarketAccessException) { throw new DealerApiError('invalid_token', 401); }
    if (((int)$key['scopes'] & (dealer_api_scope_bits()[$scope] ?? 0)) === 0) throw new DealerApiError('insufficient_scope', 403);
    return ['owner'=>$owner,'id'=>(int)$key['id']];
}

function dealer_api_execute(PDO $pdo, #[SensitiveParameter] string $authorization, string $resource, array $input): array
{
    if (!dealer_api_available()) throw new DealerApiError('not_found', 404);
    $scope = match ($resource) {
        'inventory' => 'inventory.read', 'orders' => 'orders.read', 'stock_movement' => 'inventory.write',
        default => throw new DealerApiError('not_found', 404),
    };
    return trade_transaction($pdo, static function () use ($pdo,$authorization,$resource,$scope,$input): array {
        $key = dealer_api_authenticate($pdo, $authorization, $scope);
        $owner = $key['owner'];
        if ($resource === 'orders') return ['orders'=>dealer_order_list($pdo, $owner, $owner)];
        if ($resource === 'inventory') {
            if (isset($input['id'])) return ['item'=>dealer_inventory_details($pdo,$owner,$owner,trade_integer($input['id']))];
            return dealer_inventory_list($pdo,$owner,$owner, isset($input['after']) ? trade_integer($input['after']) : 0);
        }
        $id = $input['id'] ?? null;
        $version = $input['version'] ?? null;
        $request = $input['request_key'] ?? null;
        $amount = $input['amount'] ?? null;
        $reason = $input['reason'] ?? null;
        if (!is_int($id) || !is_int($version) || !is_string($request) || !is_int($amount) || !is_string($reason)) {
            throw new InvalidArgumentException('Invalid stock movement.');
        }
        if (dealer_inventory_adjust($pdo,$owner,$owner,trade_integer($id),trade_integer($version,1,4294967294),$request,(string)$amount,$reason)) {
            audit_log($pdo,$owner,'dealer.api.stock_movement',$id,['key_id'=>$key['id']]);
        }
        return ['id'=>$id,'recorded'=>true];
    });
}
