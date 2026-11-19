<?php
declare(strict_types=1);

/** This adapter is deliberately tied to the selected synthetic schema. */
function escrow_simulation_enabled(PDO $pdo): bool
{
    return getenv('ESCROW_SIMULATION') === '1' && getenv('APP_ENV') === 'development'
        && getenv('DB_DATABASE') === 'gunindex_rebuild_test'
        && $pdo->query('SELECT DATABASE()')->fetchColumn() === 'gunindex_rebuild_test';
}

function escrow_require_simulation(PDO $pdo): void
{
    if (!escrow_simulation_enabled($pdo)) throw new MarketNotFoundException(t('escrow.unavailable'));
}

function escrow_chat_available(PDO $pdo, array $context): bool
{
    return (bool)$context['existing'] && in_array($context['item']['type'], ['fixed','negotiated'], true)
        && ((int)$context['item']['shipping'] === 1 || $context['item']['status'] === 'sold') && escrow_simulation_enabled($pdo);
}

function escrow_listing_reserved(PDO $pdo, int $listing): bool
{
    if (!escrow_simulation_enabled($pdo)) return false;
    $query = $pdo->prepare("SELECT id FROM market_escrow_cases WHERE listing_id=? AND status IN ('awaiting_funding','payment_failed','funded','shipped','received','disputed','release_pending','refund_pending','released','charged_back') LIMIT 1" . ($pdo->inTransaction() ? ' FOR SHARE' : ''));
    $query->execute([$listing]);
    return $query->fetchColumn() !== false;
}

function escrow_row(PDO $pdo, int $id, bool $lock = false): array
{
    $query = $pdo->prepare('SELECT * FROM market_escrow_cases WHERE id=?' . ($lock ? ' FOR UPDATE' : ''));
    $query->execute([$id]);
    $row = $query->fetch();
    if (!$row) throw new MarketNotFoundException(t('escrow.unavailable'));
    return $row;
}

function escrow_role(PDO $pdo, array $row, int $actor, bool $finance): string
{
    $party = match ($actor) {
        (int)$row['seller_id'] => 'seller',
        (int)$row['buyer_id'] => 'buyer',
        default => null,
    };
    if ($finance) {
        governance_assert_actor($pdo, $actor, 'finance.manage');
        if ($party !== null) throw new MarketAccessException(t('escrow.forbidden'));
        return 'finance';
    }
    if ($party === null) throw new MarketNotFoundException(t('escrow.unavailable'));
    return $party;
}

function escrow_provider_actions(): array
{
    return ['funds_confirmed','payment_failed','retry_allowed','payout_confirmed','payout_failed','refund_confirmed','refund_failed','chargeback'];
}

function escrow_actions(array $row, int $actor, string $role): array
{
    $status = $row['status'];
    if ($row['deadline'] !== null && strtotime($row['deadline'] . ' UTC') <= time()) return [];
    if ($status === 'offered' && $role !== 'finance') {
        $policyRole = $actor === (int)$row['proposer_id'] ? 'proposer' : 'recipient';
    } else {
        $policyRole = $role;
    }
    $actions = escrow_workflow_actions($status, $policyRole);
    if ($role === 'finance') {
        foreach (array_intersect(escrow_provider_actions(), escrow_workflow_actions($status, 'provider')) as $action) {
            if ($action === 'funds_confirmed' && ($row['seller_contact_cipher'] === null || $row['buyer_contact_cipher'] === null)) continue;
            $actions[] = 'simulate_' . $action;
        }
    } elseif (in_array($status, ['offered', 'awaiting_funding', 'payment_failed'], true)) {
        $actions[] = 'contact';
    }
    return $actions;
}

function escrow_public_fields(array $row): array
{
    return array_intersect_key($row, array_flip([
        'id','listing_id','seller_id','buyer_id','proposer_id','reference','payment_reference',
        'price_cents','shipping_cents','currency','status','version','deadline','created_at','updated_at',
    ]));
}

function escrow_cases(PDO $pdo, int $actor, int $listing, int $peer): array
{
    escrow_require_simulation($pdo);
    $context = chat_context($pdo, $actor, $listing, $peer);
    $seller = (int)$context['item']['seller_id'];
    $query = $pdo->prepare('SELECT * FROM market_escrow_cases WHERE listing_id=? AND seller_id=? AND buyer_id=? ORDER BY id DESC LIMIT 25');
    $query->execute([$listing, $seller, $actor === $seller ? $peer : $actor]);
    return array_map('escrow_public_fields', $query->fetchAll());
}

function escrow_finance_cases(PDO $pdo, int $actor): array
{
    escrow_require_simulation($pdo);
    governance_assert_actor($pdo, $actor, 'finance.manage');
    $query = $pdo->prepare('SELECT * FROM market_escrow_cases WHERE seller_id<>? AND buyer_id<>? ORDER BY id DESC LIMIT 50');
    $query->execute([$actor, $actor]);
    return array_map('escrow_public_fields', $query->fetchAll());
}

function escrow_details(PDO $pdo, int $actor, int $id, bool $finance = false): array
{
    escrow_require_simulation($pdo);
    $row = escrow_row($pdo, $id);
    $role = escrow_role($pdo, $row, $actor, $finance);
    $result = escrow_public_fields($row);
    $cipher = $role === 'finance' ? null : $row[$role . '_contact_cipher'];
    $result['own_contact'] = $cipher === null ? null : crypto_decrypt('escrow_contact', $cipher);
    $result['actions'] = escrow_actions($row, $actor, $role);
    $query = $pdo->prepare('SELECT action,actor_role,to_status,created_at,details_cipher FROM market_escrow_events WHERE case_id=? ORDER BY id DESC LIMIT 200');
    $query->execute([$id]);
    $result['events'] = [];
    foreach ($query->fetchAll() as $event) {
        $details = $event['details_cipher'] === null ? [] : json_decode(crypto_decrypt('escrow_event', $event['details_cipher']), true, 8, JSON_THROW_ON_ERROR);
        $event['detail'] = $details['details'] ?? '';
        $event['price_cents'] = $details['price'] ?? null;
        unset($event['details_cipher']);
        $result['events'][] = $event;
    }
    return $result;
}

function escrow_notify(PDO $pdo, array $row): void
{
    $seller = (int)$row['seller_id'];
    $buyer = (int)$row['buyer_id'];
    foreach ([$seller=>$buyer,$buyer=>$seller] as $user=>$peer) {
        notify_user($pdo, $user, 'escrow-sim:' . $row['id'] . ':' . $row['version'],
            i18n_user_text($pdo, $user, 'escrow.notification_title'), i18n_user_text($pdo, $user, 'escrow.notification_body'),
            '/user/escrow.php?listing_id=' . $row['listing_id'] . '&user_id=' . $peer . '&case_id=' . $row['id']);
    }
}

function escrow_event(PDO $pdo, array $row, array $event): void
{
    $receipt = $event['receipt'] ?? [];
    $details = $event['details'] ?? [];
    $query = $pdo->prepare('INSERT INTO market_escrow_events(case_id,actor_id,actor_role,action,from_status,to_status,version_after,request_key,details_cipher,provider_event_id,provider_amount_cents,provider_currency) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $query->execute([
        $row['id'], $event['actor'] ?? null, $event['role'], $event['action'], $event['from'], $row['status'], $row['version'],
        $event['key'], $details === [] ? null : crypto_encrypt('escrow_event', trade_json($details)),
        $receipt['event_id'] ?? null, $receipt['amount_cents'] ?? null, $receipt['currency'] ?? null,
    ]);
}

function escrow_price(array $input): int
{
    return trade_integer(money_parse(market_input($input, 'amount')), 1, 9999999999);
}

function escrow_create(PDO $pdo, int $actor, int $listing, int $peer, #[SensitiveParameter] array $input): int
{
    escrow_require_simulation($pdo);
    $key = trade_hex($input['request_key'] ?? null);
    if (($input['confirm'] ?? null) !== '1') throw new InvalidArgumentException(t('escrow.invalid'));
    $price = escrow_price($input);
    return trade_transaction($pdo, static function () use ($pdo,$actor,$listing,$peer,$key,$price): int {
        $item = market_lock_listing($pdo, $listing);
        market_assert_users_active($pdo, [$actor,$peer]);
        $prior = $pdo->prepare('SELECT id,listing_id,buyer_id FROM market_escrow_cases WHERE seller_id=? AND create_key=? FOR UPDATE');
        $prior->execute([$actor,$key]);
        if ($existing = $prior->fetch()) {
            $query = $pdo->prepare('SELECT details_cipher FROM market_escrow_events WHERE case_id=? AND version_after=1');
            $query->execute([$existing['id']]);
            $details = json_decode(crypto_decrypt('escrow_event', (string)$query->fetchColumn()), true, 8, JSON_THROW_ON_ERROR);
            if ((int)$existing['listing_id'] === $listing && (int)$existing['buyer_id'] === $peer && $details['price'] === $price) return (int)$existing['id'];
            throw new MarketConflictException(t('escrow.changed'));
        }
        $context = chat_context($pdo, $actor, $listing, $peer);
        if ($actor !== (int)$item['seller_id'] || !$context['can_send'] || !$context['existing'] || escrow_listing_reserved($pdo, $listing)) {
            throw new MarketAccessException(t('escrow.forbidden'));
        }
        $item = trade_listing($pdo, $actor, $listing, true);
        $shipping = trade_delivery($item, 'shipping');
        $open = $pdo->prepare("SELECT id FROM market_escrow_cases WHERE listing_id=? AND buyer_id=? AND status='offered' AND deadline>UTC_TIMESTAMP() LIMIT 1 FOR UPDATE");
        $open->execute([$listing,$peer]);
        if ($open->fetchColumn() !== false) throw new MarketConflictException(t('escrow.changed'));
        $query = $pdo->prepare("INSERT INTO market_escrow_cases(listing_id,seller_id,buyer_id,proposer_id,reference,payment_reference,listing_hash,price_cents,shipping_cents,status,create_key,deadline) VALUES (?,?,?,?,?,?,?,?,?,'offered',?,UTC_TIMESTAMP()+INTERVAL 7 DAY)");
        $query->execute([$listing,$actor,$peer,$actor,'GXE-'.strtoupper(bin2hex(random_bytes(12))),'GXP-'.strtoupper(bin2hex(random_bytes(12))),trade_listing_hash($item),$price,$shipping,$key]);
        $id = (int)$pdo->lastInsertId();
        escrow_event($pdo, ['id'=>$id,'status'=>'offered','version'=>1], ['actor'=>$actor,'role'=>'seller','action'=>'offer','from'=>null,'key'=>$key,'details'=>['price'=>$price]]);
        escrow_notify($pdo, ['id'=>$id,'listing_id'=>$listing,'seller_id'=>$actor,'buyer_id'=>$peer,'version'=>1]);
        return $id;
    });
}

function escrow_input(string $action, #[SensitiveParameter] array $input): array
{
    if ($action === 'counter') return ['price' => escrow_price($input)];
    if ($action === 'contact') {
        $contact = strtolower(trim(market_input($input, 'contact')));
        if (!mail_address_valid($contact) || !str_ends_with($contact, '@example.invalid')) {
            throw new InvalidArgumentException(t('escrow.invalid_contact'));
        }
        return ['contact' => $contact];
    }
    if (in_array($action, ['dispatch','dispute','request_release','request_refund'], true)) {
        $details = trim(market_input($input, 'details'));
        if ($details === '' || strlen($details) > 1000 || !preg_match('/\A[^\x00-\x08\x0b-\x1f\x7f]+\z/u', $details)) {
            throw new InvalidArgumentException(t('escrow.invalid'));
        }
        return ['details' => $details];
    }
    return [];
}

function escrow_simulate_receipt(array $row, string $action, string $key): array
{
    return [
        'event_id' => hash('sha256', implode(':', ['simulator-v1', $row['payment_reference'], $action, $key])),
        'reference' => $row['payment_reference'], 'currency' => 'EUR', 'action' => $action,
        'amount_cents' => (int)$row['price_cents'] + (int)$row['shipping_cents'],
    ];
}

function escrow_validate_simulated_receipt(array $row, string $action, string $key, array $receipt): void
{
    $expected = escrow_simulate_receipt($row, $action, $key);
    if ($receipt !== $expected || $receipt['currency'] !== $row['currency']) {
        throw new MarketConflictException(t('escrow.changed'));
    }
}

function escrow_deadline(array $row, string $next, string $action): ?string
{
    $seconds = match ($next) {
        'offered' => 7 * 86400,
        'awaiting_funding' => $action === 'retry_allowed' ? 0 : 3 * 86400,
        'payment_failed' => 0,
        'funded' => 7 * 86400,
        'shipped' => 30 * 86400,
        default => null,
    };
    return $seconds === null ? null : ($seconds === 0 ? $row['deadline'] : gmdate('Y-m-d H:i:s', time() + $seconds));
}

function escrow_apply(PDO $pdo, int $actor, int $id, string $action, #[SensitiveParameter] array $input, bool $finance = false): void
{
    escrow_require_simulation($pdo);
    $key = trade_hex($input['request_key'] ?? null);
    $version = trade_integer($input['version'] ?? null, 1, 4294967294);
    if (($input['confirm'] ?? null) !== '1') throw new InvalidArgumentException(t('escrow.invalid'));
    $details = escrow_input($action, $input);
    $hint = escrow_row($pdo, $id);
    escrow_role($pdo, $hint, $actor, $finance);
    trade_transaction($pdo, static function () use ($pdo,$actor,$id,$action,$key,$version,$finance,$details,$hint): void {
        $listing = (int)$hint['listing_id'];
        market_lock_listing($pdo, $listing);
        $row = escrow_row($pdo, $id, true);
        market_assert_users_active($pdo, [$actor]);
        $role = escrow_role($pdo, $row, $actor, $finance);
        $simulate = str_starts_with($action, 'simulate_');
        $providerAction = $simulate ? substr($action, 9) : '';
        $prior = $pdo->prepare('SELECT actor_id,action,version_after,details_cipher FROM market_escrow_events WHERE case_id=? AND request_key=?');
        $prior->execute([$id,$key]);
        if ($existing = $prior->fetch()) {
            $raw = $existing['details_cipher'] === null ? [] : json_decode(crypto_decrypt('escrow_event', $existing['details_cipher']), true, 8, JSON_THROW_ON_ERROR);
            if ((int)$existing['actor_id'] === $actor && $existing['action'] === ($simulate ? $providerAction : $action)
                && (int)$existing['version_after'] === $version + 1 && $raw === $details) return;
            throw new MarketConflictException(t('escrow.changed'));
        }
        if ((int)$row['version'] !== $version) throw new MarketConflictException(t('escrow.changed'));
        if (!in_array($action, escrow_actions($row, $actor, $role), true)) throw new MarketAccessException(t('escrow.forbidden'));
        $from = $row['status'];
        $policyRole = $role;
        $next = $from;
        $receipt = null;
        if ($simulate) {
            $receipt = escrow_simulate_receipt($row, $providerAction, $key);
            escrow_validate_simulated_receipt($row, $providerAction, $key, $receipt);
            $policyRole = 'provider';
            $next = escrow_workflow_next($from, $policyRole, $providerAction);
        } elseif ($action !== 'contact') {
            if ($from === 'offered') $policyRole = $actor === (int)$row['proposer_id'] ? 'proposer' : 'recipient';
            $next = escrow_workflow_next($from, $policyRole, $action);
        }

        if (in_array($action, ['accept','counter'], true)) {
            market_assert_users_active($pdo, [(int)$row['buyer_id'],(int)$row['seller_id']]);
            $item = trade_listing($pdo, $actor, $listing, true);
            if (!hash_equals($row['listing_hash'], trade_listing_hash($item)) || !trade_listing_available($pdo, $listing)
                || chat_blocked($pdo, (int)$row['buyer_id'], (int)$row['seller_id'])) throw new MarketConflictException(t('escrow.changed'));
        }
        if ($action === 'contact') $row[$role . '_contact_cipher'] = crypto_encrypt('escrow_contact', $details['contact']);
        if ($action === 'counter') {
            $row['price_cents'] = $details['price'];
            $row['proposer_id'] = $actor;
        }
        $row['status'] = $next;
        $row['version'] = $version + 1;
        if ($action !== 'contact') $row['deadline'] = escrow_deadline($row, $next, $simulate ? $providerAction : $action);
        $query = $pdo->prepare('UPDATE market_escrow_cases SET status=?,version=?,deadline=?,price_cents=?,proposer_id=?,seller_contact_cipher=?,buyer_contact_cipher=? WHERE id=?');
        $query->execute([$next,$row['version'],$row['deadline'],$row['price_cents'],$row['proposer_id'],$row['seller_contact_cipher'],$row['buyer_contact_cipher'],$id]);
        if ($next === 'released') $pdo->prepare("UPDATE market_listings SET status='sold' WHERE id=? AND status='active'")->execute([$listing]);
        escrow_event($pdo, $row, ['actor'=>$actor,'role'=>$policyRole,'action'=>$simulate ? $providerAction : $action,'from'=>$from,'key'=>$key,'details'=>$details,'receipt'=>$receipt]);
        if ($finance) audit_log($pdo, $actor, 'escrow.simulation.' . $action, $id, ['from'=>$from,'to'=>$next]);
        if ($action !== 'contact') escrow_notify($pdo, $row);
    });
}

function escrow_expire(PDO $pdo, int $limit = 100): int
{
    if (!escrow_simulation_enabled($pdo)) return 0;
    $limit = max(1, min(100, $limit));
    $due = $pdo->query("SELECT id,listing_id FROM market_escrow_cases WHERE deadline<=UTC_TIMESTAMP() ORDER BY deadline,id LIMIT $limit")->fetchAll();
    $count = 0;
    foreach ($due as $candidate) {
        $count += trade_transaction($pdo, static function () use ($pdo,$candidate): int {
            market_lock_listing($pdo, (int)$candidate['listing_id']);
            $row = escrow_row($pdo, (int)$candidate['id'], true);
            if ($row['deadline'] === null || strtotime($row['deadline'] . ' UTC') > time()) return 0;
            $from = $row['status'];
            $row['status'] = escrow_workflow_next($from, 'worker', 'expire');
            $row['version'] = (int)$row['version'] + 1;
            $pdo->prepare('UPDATE market_escrow_cases SET status=?,version=?,deadline=NULL WHERE id=?')->execute([$row['status'],$row['version'],$row['id']]);
            escrow_event($pdo, $row, ['role'=>'worker','action'=>'expire','from'=>$from,'key'=>bin2hex(random_bytes(16))]);
            escrow_notify($pdo, $row);
            return 1;
        });
    }
    return $count;
}
