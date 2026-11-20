<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';

$stockActor = (int)require_login($pdo)['id'];
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$stockMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($stockMethod, ['GET','HEAD','POST'], true)) {
    header('Allow: GET, HEAD, POST');
    abort_request(405, t('account.method'));
}

$stockTeams = dealer_team_view($pdo, $stockActor);
$stockChoices = [];
if ($stockTeams['owned']) $stockChoices[$stockActor] = ['name'=>t('dealer_stock.own_business'),'role'=>'owner'];
foreach ($stockTeams['teams'] as $stockMembership) {
    if ($stockMembership['status'] === 'active') {
        $stockChoices[(int)$stockMembership['owner_id']] = ['name'=>$stockMembership['company_name'],'role'=>$stockMembership['role']];
    }
}
try {
    $stockOwner = isset($_GET['owner_id']) ? trade_integer($_GET['owner_id']) : array_key_first($stockChoices);
    $stockId = isset($_GET['item_id']) ? trade_integer($_GET['item_id']) : null;
    $stockAfter = isset($_GET['after']) ? trade_integer($_GET['after']) : 0;
} catch (InvalidArgumentException) { abort_request(404, t('dealer_stock.unavailable')); }
if ($stockOwner !== null && !isset($stockChoices[$stockOwner])) abort_request(404, t('dealer_stock.unavailable'));
$stockListUrl = '/user/dealer-stock.php' . ($stockOwner === null ? '' : '?owner_id=' . $stockOwner);
$stockUrl = $stockListUrl . ($stockId === null ? '' : '&item_id=' . $stockId);
$stockError = '';
$stockNote = $stockMethod === 'GET' && !empty($_SESSION['dealer_stock_saved']);
if ($stockMethod === 'GET') unset($_SESSION['dealer_stock_saved']);

if ($stockMethod === 'POST') {
    require_csrf();
    if ($stockOwner === null || !isset($stockChoices[$stockOwner])) abort_request(403, t('dealer_team.forbidden'));
    try {
        $stockAction = post_string('action', 16);
        if ($stockAction === 'create') {
            $stockId = dealer_inventory_create($pdo, $stockActor, $stockOwner, post_string('sku', 64), post_string('label', 640));
            $stockUrl = $stockListUrl . '&item_id=' . $stockId;
        } elseif ($stockId !== null && $stockAction === 'rename') {
            dealer_inventory_rename($pdo,$stockActor,$stockOwner,$stockId,trade_integer(post_string('version'),1,4294967294),post_string('label',640));
        } elseif ($stockId !== null && $stockAction === 'adjust') {
            dealer_inventory_adjust($pdo,$stockActor,$stockOwner,$stockId,trade_integer(post_string('version'),1,4294967294),
                post_string('request_key',32),post_string('amount',8),post_string('reason',960));
        } elseif ($stockId !== null && in_array($stockAction, ['bind_checkout','unbind_checkout'], true)) {
            dealer_checkout_change_binding($pdo,$stockActor,$stockOwner,$stockId,trade_integer(post_string('listing_id')),
                $stockAction === 'unbind_checkout' ? post_string('revision',32) : null);
        } elseif ($stockId !== null && in_array($stockAction, ['restore_checkout_return','close_checkout_review'], true)) {
            if (post_string('confirm_review',1) !== '1') throw new InvalidArgumentException(t('dealer_stock.invalid'));
            dealer_checkout_review($pdo,$stockActor,$stockOwner,$stockId,trade_integer(post_string('trade_id')),$stockAction === 'restore_checkout_return');
        } else { throw new InvalidArgumentException(t('dealer_stock.invalid')); }
        $_SESSION['dealer_stock_saved'] = true;
        redirect($stockUrl);
    } catch (MarketAccessException) {
        abort_request(403, t('dealer_team.forbidden'));
    } catch (MarketNotFoundException) {
        abort_request(404, t('dealer_stock.unavailable'));
    } catch (MarketConflictException $failure) {
        http_response_code(409);
        $stockError = $failure->getMessage();
    } catch (InvalidArgumentException|DomainException $failure) {
        if ($failure->getMessage() === t('dealer_team.forbidden')) abort_request(403, t('dealer_team.forbidden'));
        http_response_code(422);
        $stockError = $failure->getMessage() === t('dealer_stock.out_of_range') ? $failure->getMessage() : t('dealer_stock.invalid');
    }
}

$stockData = $stockItem = $stockOrders = null;
$stockCanWrite = false;
$stockCanExport = false;
if ($stockOwner !== null) {
    $stockRoles = dealer_team_roles();
    $stockPermissions = $stockRoles[$stockChoices[$stockOwner]['role']] ?? [];
    $stockCanWrite = $stockOwner === $stockActor || in_array('inventory.write', $stockPermissions, true);
    try {
        $stockData = dealer_inventory_list($pdo, $stockActor, $stockOwner, $stockAfter);
        if ($stockId !== null) {
            $stockItem = dealer_inventory_details($pdo, $stockActor, $stockOwner, $stockId);
            $stockBindings = dealer_checkout_bindings($pdo,$stockActor,$stockOwner,$stockId);
        }
        if ($stockOwner === $stockActor || in_array('orders.read', $stockPermissions, true)) {
            $stockOrders = dealer_order_list($pdo, $stockActor, $stockOwner);
        }
        $stockCanExport = dealer_subscription_active($pdo,$stockOwner);
    } catch (DomainException|MarketNotFoundException|MarketAccessException) {
        abort_request(404, t('dealer_stock.unavailable'));
    }
}

$pageTitle = t('dealer_stock.title');
include __DIR__ . '/../includes/header.php';
?>
<main class="container narrow" id="main-content">
    <div class="page-heading"><h1><?=h($pageTitle)?></h1><div class="button-row"><a href="/user/dealer-team.php"><?=h(t('dealer_team.title'))?></a><?php if ($stockTeams['owned']): ?><a href="/user/dealer-api-keys.php"><?=h(t('dealer_api.title'))?></a><a href="/user/dealer-subscription.php"><?=h(t('dealer_subscription.title'))?></a><?php endif; ?></div></div>
    <?php if ($stockNote): ?><p class="notice success" role="status"><?=h(t('dealer_stock.saved'))?></p><?php endif; ?>
    <?php if ($stockError !== ''): ?><p class="notice error" role="alert"><?=h($stockError)?></p><?php endif; ?>
    <?php if ($stockData === null): ?>
        <p class="notice"><?=h(t('dealer_stock.no_business'))?></p>
    <?php else: ?>
        <?php if (count($stockChoices) > 1): ?>
            <form method="get" class="panel form-grid">
                <label class="field"><?=h(t('dealer_stock.business'))?><select name="owner_id"><?php foreach ($stockChoices as $stockChoiceId=>$stockChoice): ?>
                    <option value="<?=$stockChoiceId?>" <?=$stockOwner===$stockChoiceId?'selected':''?>><?=h($stockChoice['name'])?></option>
                <?php endforeach; ?></select></label><button class="button secondary" type="submit"><?=h(t('Übernehmen'))?></button>
            </form>
        <?php endif; ?>
        <section class="panel">
            <h2><?=h($stockChoices[$stockOwner]['name'])?></h2>
            <?php if ($stockCanExport): ?><p><a class="button secondary" href="/user/dealer-stock-export.php?owner_id=<?=$stockOwner?>"><?=h(t('dealer_subscription.export_csv'))?></a></p><?php endif; ?>
            <?php if (!$stockData['items']): ?><p><?=h(t('dealer_stock.no_items'))?></p><?php endif; ?>
            <ul><?php foreach ($stockData['items'] as $stockEntry): ?>
                <li><a href="<?=h($stockListUrl . '&item_id=' . $stockEntry['id'])?>"><?=h($stockEntry['label'])?></a> · <?=h($stockEntry['sku'])?> · <?=h(t('dealer_stock.count', ['amount'=>$stockEntry['quantity']]))?></li>
            <?php endforeach; ?></ul>
            <?php if ($stockData['next'] !== null): ?><a href="<?=h($stockListUrl . '&after=' . $stockData['next'])?>"><?=h(t('dealer_stock.more'))?></a><?php endif; ?>
            <?php if ($stockCanWrite): ?>
                <details><summary><?=h(t('dealer_stock.create'))?></summary><form method="post" action="<?=h($stockListUrl)?>" class="form-grid">
                    <?=csrf_field()?><input type="hidden" name="action" value="create">
                    <label class="field"><?=h(t('dealer_stock.sku'))?><input name="sku" maxlength="64" pattern="[A-Za-z0-9][A-Za-z0-9._\-]{0,63}" required></label>
                    <label class="field"><?=h(t('dealer_stock.label'))?><input name="label" maxlength="160" required></label>
                    <button class="button" type="submit"><?=h(t('dealer_stock.create'))?></button>
                </form></details>
            <?php endif; ?>
        </section>
        <?php if ($stockItem !== null): ?>
            <section class="panel">
                <h2><?=h($stockItem['label'])?></h2><p><code><?=h($stockItem['sku'])?></code> · <?=h(t('dealer_stock.count',['amount'=>$stockItem['quantity']]))?></p>
                <?php if ($stockCanWrite): ?>
                    <details><summary><?=h(t('dealer_stock.adjust'))?></summary><form method="post" action="<?=h($stockUrl)?>" class="form-grid">
                        <?=csrf_field()?><input type="hidden" name="action" value="adjust"><input type="hidden" name="version" value="<?=h($stockItem['version'])?>">
                        <input type="hidden" name="request_key" value="<?=h(bin2hex(random_bytes(16)))?>">
                        <label class="field"><?=h(t('dealer_stock.change_amount'))?><input type="number" name="amount" min="-1000000" max="1000000" step="1" required></label>
                        <label class="field"><?=h(t('dealer_stock.reason'))?><input name="reason" maxlength="240" required></label>
                        <button class="button" type="submit"><?=h(t('dealer_stock.adjust'))?></button>
                    </form></details>
                    <details><summary><?=h(t('dealer_stock.rename'))?></summary><form method="post" action="<?=h($stockUrl)?>" class="form-grid">
                        <?=csrf_field()?><input type="hidden" name="action" value="rename"><input type="hidden" name="version" value="<?=h($stockItem['version'])?>">
                        <label class="field"><?=h(t('dealer_stock.label'))?><input name="label" value="<?=h($stockItem['label'])?>" maxlength="160" required></label>
                        <button class="button" type="submit"><?=h(t('dealer_stock.rename'))?></button>
                    </form></details>
                <?php endif; ?>
                <h3><?=h(t('dealer_stock.movements'))?></h3>
                <?php if (!$stockItem['movements']): ?><p><?=h(t('dealer_stock.no_movements'))?></p><?php endif; ?>
                <ul><?php foreach ($stockItem['movements'] as $stockMovement): ?>
                    <li><?=h($stockMovement['created_at'])?> UTC · <?=((int)$stockMovement['delta']>0?'+':'').(int)$stockMovement['delta']?> · <?=h(match ($stockMovement['movement_kind']) {
                        'order_sale'=>t('dealer_checkout.movement_sale'), 'order_cancel'=>t('dealer_checkout.movement_cancel'), default=>$stockMovement['reason'],
                    })?> · <?=h(t('dealer_stock.count',['amount'=>$stockMovement['quantity_after']]))?></li>
                <?php endforeach; ?></ul>
            </section>
            <?php include __DIR__.'/../includes/dealer-stock-bindings.php'; ?>
        <?php endif; ?>
        <?php if ($stockOrders !== null): ?>
            <section class="panel"><h2><?=h(t('dealer_stock.orders'))?></h2>
                <?php if (!$stockOrders): ?><p><?=h(t('dealer_stock.no_orders'))?></p><?php endif; ?>
                <ul><?php foreach ($stockOrders as $stockOrder): ?>
                    <li><?=h($stockOrder['reference'])?> · <?=h(t('trade.status.' . $stockOrder['status']))?> · <?=h(money_eur((int)$stockOrder['total_cents']))?></li>
                <?php endforeach; ?></ul>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
