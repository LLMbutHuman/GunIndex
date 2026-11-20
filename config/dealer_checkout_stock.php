<?php
declare(strict_types=1);

function dealer_checkout_bindings(PDO $pdo, int $actor, int $owner, int $item): array
{
    return trade_transaction($pdo, static function () use ($pdo,$actor,$owner,$item): array {
        dealer_team_assert($pdo,$actor,$owner,'inventory.read',true);
        $bound = $pdo->prepare('SELECT l.id,l.title,l.status,b.revision FROM market_dealer_stock_bindings b JOIN market_listings l ON l.id=b.listing_id AND l.seller_id=b.owner_id WHERE b.owner_id=? AND b.item_id=? ORDER BY l.id DESC LIMIT 100');
        $bound->execute([$owner,$item]);
        $canWrite = true;
        try { dealer_team_assert($pdo,$actor,$owner,'inventory.write',true); }
        catch (MarketAccessException) { $canWrite = false; }
        $availableRows = [];
        if ($canWrite) {
            $available = $pdo->prepare("SELECT l.id,l.title FROM market_listings l LEFT JOIN market_dealer_stock_bindings b ON b.listing_id=l.id WHERE l.seller_id=? AND b.listing_id IS NULL AND l.type IN ('fixed','negotiated') AND l.status IN ('active','draft','paused') AND l.trade_checkout_enabled=1 ORDER BY l.id DESC LIMIT 100");
            $available->execute([$owner]);
            $availableRows = $available->fetchAll();
        }
        $open = " WHERE r.owner_id=? AND r.item_id=? AND r.status IN ('return_required','count_limit')";
        $count = $pdo->prepare('SELECT COUNT(*) FROM market_dealer_trade_stock r' . $open);
        $count->execute([$owner,$item]);
        $reviewCount = (int)$count->fetchColumn();
        $reviewRows = [];
        if ($reviewCount > 0 && $canWrite) {
            $reviews = $pdo->prepare('SELECT t.id AS trade_id,t.reference,r.status FROM market_dealer_trade_stock r JOIN market_trades t ON t.id=r.trade_id'
                . $open . ' ORDER BY r.trade_id DESC LIMIT 100');
            $reviews->execute([$owner,$item]);
            $reviewRows = $reviews->fetchAll();
        }
        return ['bound'=>$bound->fetchAll(),'available'=>$availableRows,'reviews'=>$reviewRows,'review_count'=>$reviewCount];
    });
}

function dealer_checkout_change_binding(PDO $pdo, int $actor, int $owner, int $item, int $listing, ?string $revision): void
{
    if ($revision !== null) $revision = trade_hex($revision);
    trade_transaction($pdo, static function () use ($pdo,$actor,$owner,$item,$listing,$revision): void {
        $offer = market_lock_listing($pdo, $listing);
        dealer_team_assert($pdo,$actor,$owner,'inventory.write',true);
        if ((int)$offer['seller_id'] !== $owner || !in_array($offer['type'], ['fixed','negotiated'], true)) {
            throw new MarketNotFoundException(t('dealer_checkout.unavailable'));
        }
        $bound = dealer_checkout_stock_row($pdo, $owner, $listing, true);
        if ($revision !== null) {
            if (!$bound || (int)$bound['id'] !== $item || !hash_equals($bound['revision'], $revision)) {
                throw new MarketConflictException(t('dealer_checkout.changed'));
            }
            $pdo->prepare('DELETE FROM market_dealer_stock_bindings WHERE listing_id=? AND owner_id=? AND item_id=?')->execute([$listing,$owner,$item]);
            audit_log($pdo,$actor,'dealer.stock.unbound',$listing,['owner_id'=>$owner,'item_id'=>$item]);
            return;
        }
        if ($bound) {
            if ((int)$bound['id'] === $item) return;
            throw new MarketConflictException(t('dealer_checkout.changed'));
        }
        if (!in_array($offer['status'], ['active','draft','paused'], true) || (int)$offer['trade_checkout_enabled'] !== 1
            || !market_category_locked_allowed($pdo,(int)$offer['category_id']) || !geo_country_locked_enabled($pdo,$offer['country_code'])) {
            throw new MarketAccessException(t('dealer_checkout.unavailable'));
        }
        $stock = $pdo->prepare('SELECT id FROM market_dealer_inventory WHERE owner_id=? AND id=? FOR SHARE');
        $stock->execute([$owner,$item]);
        if ($stock->fetchColumn() === false) throw new MarketNotFoundException(t('dealer_checkout.unavailable'));
        $pdo->prepare('INSERT INTO market_dealer_stock_bindings(listing_id,owner_id,item_id,revision) VALUES (?,?,?,?)')
            ->execute([$listing,$owner,$item,bin2hex(random_bytes(16))]);
        audit_log($pdo,$actor,'dealer.stock.bound',$listing,['owner_id'=>$owner,'item_id'=>$item]);
    });
}

function dealer_checkout_stock_row(PDO $pdo, int $owner, int $listing, bool $lock = false): ?array
{
    $query = $pdo->prepare('SELECT i.id,i.quantity,i.version,b.revision FROM market_dealer_stock_bindings b JOIN market_dealer_inventory i ON i.id=b.item_id AND i.owner_id=b.owner_id WHERE b.listing_id=? AND b.owner_id=?' . ($lock ? ' FOR UPDATE' : ''));
    $query->execute([$listing,$owner]);
    return $query->fetch() ?: null;
}

function dealer_checkout_movement(PDO $pdo, int $actor, int $owner, array $item, string $kind): void
{
    $debit = $kind === 'order_sale';
    $delta = $debit ? -1 : 1;
    $quantity = (int)$item['quantity'] + $delta;
    if ($quantity < 0 || $quantity > 1000000) throw new DomainException(t($debit ? 'dealer_checkout.sold_out' : 'dealer_stock.out_of_range'));
    $id = (int)$item['id'];
    $key = bin2hex(random_bytes(16));
    $version = (int)$item['version'] + 1;
    $pdo->prepare('UPDATE market_dealer_inventory SET quantity=?,version=? WHERE owner_id=? AND id=?')->execute([$quantity,$version,$owner,$id]);
    $pdo->prepare('INSERT INTO market_dealer_stock_movements(owner_id,item_id,actor_id,request_key,delta,quantity_after,version_after,reason,movement_kind) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$owner,$id,$actor,$key,$delta,$quantity,$version,'',$kind]);
}

function dealer_checkout_reserve(PDO $pdo, int $trade, int $seller, ?array $item): void
{
    if ($item === null) return;
    dealer_checkout_movement($pdo,$seller,$seller,$item,'order_sale');
    $pdo->prepare('INSERT INTO market_dealer_trade_stock(trade_id,owner_id,item_id) VALUES (?,?,?)')->execute([$trade,$seller,$item['id']]);
}

function dealer_checkout_trade_item(PDO $pdo, int $trade, int $owner): ?array
{
    $query = $pdo->prepare('SELECT i.id,i.quantity,i.version,r.status FROM market_dealer_trade_stock r JOIN market_dealer_inventory i ON i.id=r.item_id AND i.owner_id=r.owner_id WHERE r.trade_id=? AND r.owner_id=? FOR UPDATE');
    $query->execute([$trade,$owner]);
    return $query->fetch() ?: null;
}

function dealer_checkout_cancel(PDO $pdo, int $trade, int $seller): void
{
    $item = dealer_checkout_trade_item($pdo, $trade, $seller);
    if ($item === null || $item['status'] !== 'reserved') return;
    $sent = $pdo->prepare("SELECT 1 FROM market_trade_events WHERE trade_id=? AND action='dispatch' LIMIT 1");
    $sent->execute([$trade]);
    $status = $sent->fetchColumn() !== false ? 'return_required' : ((int)$item['quantity'] >= 1000000 ? 'count_limit' : 'restored');
    if ($status === 'restored') dealer_checkout_movement($pdo,$seller,$seller,$item,'order_cancel');
    $pdo->prepare('UPDATE market_dealer_trade_stock SET status=? WHERE trade_id=? AND owner_id=?')->execute([$status,$trade,$seller]);
}

function dealer_checkout_review(PDO $pdo, int $actor, int $owner, int $itemId, int $trade, bool $returned): void
{
    trade_transaction($pdo, static function () use ($pdo,$actor,$owner,$itemId,$trade,$returned): void {
        dealer_team_assert($pdo,$actor,$owner,'inventory.write',true);
        $item = dealer_checkout_trade_item($pdo,$trade,$owner);
        if ($item === null || (int)$item['id'] !== $itemId) throw new MarketNotFoundException(t('dealer_checkout.unavailable'));
        $next = $returned ? 'restored' : 'review_closed';
        if ($item['status'] === $next) return;
        if (!in_array($item['status'], ['return_required','count_limit'], true)) throw new MarketConflictException(t('dealer_checkout.changed'));
        if ($returned) dealer_checkout_movement($pdo,$actor,$owner,$item,'order_cancel');
        $pdo->prepare('UPDATE market_dealer_trade_stock SET status=? WHERE trade_id=? AND owner_id=?')->execute([$next,$trade,$owner]);
        audit_log($pdo,$actor,'dealer.stock.reviewed',$trade,['owner_id'=>$owner,'restored'=>$returned]);
    });
}
