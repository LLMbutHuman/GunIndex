<?php
declare(strict_types=1);

/** API requests already hold their key authorization in the outer transaction. */
function dealer_inventory_transaction(PDO $pdo, callable $operation): mixed
{
    if (!$pdo->inTransaction()) return trade_transaction($pdo, $operation);
    maintenance_assert_market_available($pdo);
    return $operation();
}

function dealer_inventory_sku(string $sku): string
{
    $sku = strtoupper(trim($sku));
    if (!preg_match('/\A[A-Z0-9][A-Z0-9._-]{0,63}\z/', $sku)) throw new InvalidArgumentException(t('dealer_stock.invalid'));
    return $sku;
}

function dealer_inventory_text(string $value, int $limit): string
{
    $value = trim($value);
    if (!preg_match('/\A[^\x00-\x1f\x7f]+\z/u', $value) || mb_strlen($value, 'UTF-8') > $limit) {
        throw new InvalidArgumentException(t('dealer_stock.invalid'));
    }
    return $value;
}

function dealer_inventory_list(PDO $pdo, int $actor, int $owner, int $after = 0): array
{
    return dealer_inventory_transaction($pdo, static function () use ($pdo,$actor,$owner,$after): array {
        dealer_team_assert($pdo, $actor, $owner, 'inventory.read', true);
        $query = $pdo->prepare('SELECT id,sku,label,quantity,version,updated_at FROM market_dealer_inventory WHERE owner_id=? AND id>? ORDER BY id LIMIT 51');
        $query->execute([$owner, max(0, $after)]);
        $items = $query->fetchAll();
        $more = count($items) > 50;
        if ($more) array_pop($items);
        return ['items'=>$items, 'next'=>$more ? (int)end($items)['id'] : null];
    });
}

function dealer_inventory_details(PDO $pdo, int $actor, int $owner, int $id): array
{
    return dealer_inventory_transaction($pdo, static function () use ($pdo,$actor,$owner,$id): array {
        dealer_team_assert($pdo, $actor, $owner, 'inventory.read', true);
        $query = $pdo->prepare('SELECT id,sku,label,quantity,version,updated_at FROM market_dealer_inventory WHERE owner_id=? AND id=?');
        $query->execute([$owner,$id]);
        $row = $query->fetch();
        if (!$row) throw new MarketNotFoundException(t('dealer_stock.unavailable'));
        $entry = $pdo->prepare('SELECT id,delta,quantity_after,reason,created_at FROM market_dealer_stock_movements WHERE owner_id=? AND item_id=? ORDER BY id DESC LIMIT 100');
        $entry->execute([$owner,$id]);
        $row['movements'] = $entry->fetchAll();
        return $row;
    });
}

function dealer_inventory_create(PDO $pdo, int $actor, int $owner, string $sku, string $label): int
{
    $sku = dealer_inventory_sku($sku);
    $label = dealer_inventory_text($label, 160);
    if (!catalog_text_allowed($label)) throw new InvalidArgumentException(t('dealer_stock.invalid'));
    return trade_transaction($pdo, static function () use ($pdo,$actor,$owner,$sku,$label): int {
        dealer_team_assert($pdo, $actor, $owner, 'inventory.write', true);
        $query = $pdo->prepare('SELECT id,label FROM market_dealer_inventory WHERE owner_id=? AND sku=? FOR UPDATE');
        $query->execute([$owner,$sku]);
        if ($existing = $query->fetch()) {
            if ($existing['label'] === $label) return (int)$existing['id'];
            throw new MarketConflictException(t('dealer_stock.duplicate'));
        }
        $pdo->prepare('INSERT INTO market_dealer_inventory(owner_id,sku,label) VALUES (?,?,?)')->execute([$owner,$sku,$label]);
        return (int)$pdo->lastInsertId();
    });
}

function dealer_inventory_rename(PDO $pdo, int $actor, int $owner, int $id, int $version, string $label): void
{
    $label = dealer_inventory_text($label, 160);
    if (!catalog_text_allowed($label)) throw new InvalidArgumentException(t('dealer_stock.invalid'));
    trade_transaction($pdo, static function () use ($pdo,$actor,$owner,$id,$version,$label): void {
        dealer_team_assert($pdo, $actor, $owner, 'inventory.write', true);
        $item = $pdo->prepare('UPDATE market_dealer_inventory SET label=?,version=version+1 WHERE owner_id=? AND id=? AND version=?');
        $item->execute([$label,$owner,$id,$version]);
        if ($item->rowCount() !== 1) throw new MarketConflictException(t('dealer_stock.changed'));
    });
}

function dealer_inventory_adjust(PDO $pdo, int $actor, int $owner, int $id, int $version, string $request, string $amount, string $reason): bool
{
    $request = trade_hex($request);
    $reason = dealer_inventory_text($reason, 240);
    if (!preg_match('/\A-?[1-9][0-9]{0,6}\z/', $amount) || abs((int)$amount) > 1000000) {
        throw new InvalidArgumentException(t('dealer_stock.invalid'));
    }
    $delta = (int)$amount;
    return dealer_inventory_transaction($pdo, static function () use ($pdo,$actor,$owner,$id,$version,$request,$reason,$delta): bool {
        dealer_team_assert($pdo, $actor, $owner, 'inventory.write', true);
        $item = $pdo->prepare('SELECT quantity,version FROM market_dealer_inventory WHERE owner_id=? AND id=? FOR UPDATE');
        $item->execute([$owner,$id]);
        $current = $item->fetch();
        if (!$current) throw new MarketNotFoundException(t('dealer_stock.unavailable'));
        $previous = $pdo->prepare('SELECT item_id,actor_id,delta,version_after,reason FROM market_dealer_stock_movements WHERE owner_id=? AND request_key=? FOR UPDATE');
        $previous->execute([$owner,$request]);
        if ($existing = $previous->fetch()) {
            if ((int)$existing['item_id'] === $id && (int)$existing['actor_id'] === $actor && (int)$existing['delta'] === $delta
                && (int)$existing['version_after'] === $version + 1 && $existing['reason'] === $reason) return false;
            throw new MarketConflictException(t('dealer_stock.changed'));
        }
        if ((int)$current['version'] !== $version) throw new MarketConflictException(t('dealer_stock.changed'));
        $quantity = (int)$current['quantity'] + $delta;
        if ($quantity < 0 || $quantity > 1000000) throw new DomainException(t('dealer_stock.out_of_range'));
        $pdo->prepare('UPDATE market_dealer_inventory SET quantity=?,version=version+1 WHERE owner_id=? AND id=?')->execute([$quantity,$owner,$id]);
        $pdo->prepare('INSERT INTO market_dealer_stock_movements(owner_id,item_id,actor_id,request_key,delta,quantity_after,version_after,reason) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$owner,$id,$actor,$request,$delta,$quantity,$version+1,$reason]);
        return true;
    });
}

function dealer_order_list(PDO $pdo, int $actor, int $owner): array
{
    return dealer_inventory_transaction($pdo, static function () use ($pdo,$actor,$owner): array {
        dealer_team_assert($pdo, $actor, $owner, 'orders.read', true);
        $query = $pdo->prepare('SELECT reference,status,delivery,total_cents,created_at,updated_at FROM market_trades WHERE seller_id=? ORDER BY id DESC LIMIT 50');
        $query->execute([$owner]);
        return $query->fetchAll();
    });
}
