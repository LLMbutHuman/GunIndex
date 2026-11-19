<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';

header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$actor = (int)require_permission($pdo, 'finance.manage')['id'];
if (!escrow_simulation_enabled($pdo)) abort_request(404, t('escrow.unavailable'));
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    abort_request(405, t('account.method'));
}

$error = '';
$caseId = null;
$providerActions = escrow_provider_actions();
$decisions = ['request_release', 'request_refund'];
$allowed = array_merge($decisions, array_map(static fn(string $action): string => 'simulate_' . $action, $providerActions));
if ($method === 'POST') {
    require_csrf();
    try {
        $caseId = trade_integer($_POST['case_id'] ?? null);
        $action = post_string('action', 32);
        if (!in_array($action, $allowed, true)) throw new InvalidArgumentException(t('escrow.invalid'));
        escrow_apply($pdo, $actor, $caseId, $action, $_POST, true);
        $_SESSION['escrow_finance_notice'] = true;
        redirect('/admin/escrow.php?case_id=' . $caseId);
    } catch (MarketNotFoundException) {
        abort_request(404, t('escrow.unavailable'));
    } catch (MarketAccessException) {
        abort_request(403, t('escrow.forbidden'));
    } catch (MarketConflictException) {
        http_response_code(409);
        $error = t('escrow.changed');
    } catch (DomainException|InvalidArgumentException) {
        http_response_code(422);
        $error = t('escrow.invalid');
    } catch (Throwable $failure) {
        error_log('Escrow simulation decision failed ('.get_class($failure).')');
        abort_request(503, t('escrow.error'));
    }
}
$notice = $method === 'GET' && !empty($_SESSION['escrow_finance_notice']);
if ($notice) unset($_SESSION['escrow_finance_notice']);

try {
    $cases = escrow_finance_cases($pdo, $actor);
    if ($method !== 'POST' && isset($_GET['case_id'])) $caseId = trade_integer($_GET['case_id']);
    $selected = $caseId !== null ? escrow_details($pdo, $actor, $caseId, true) : null;
} catch (MarketNotFoundException|InvalidArgumentException) {
    abort_request(404, t('escrow.unavailable'));
} catch (MarketAccessException) {
    abort_request(403, t('escrow.forbidden'));
} catch (Throwable $failure) {
    error_log('Escrow simulation finance cases could not be read ('.get_class($failure).')');
    abort_request(503, t('escrow.error'));
}
$label = static function (string $type, string $value): string {
    $key = 'escrow.' . $type . '_' . $value;
    $text = t($key);
    return $text !== $key ? $text : t('escrow.unknown');
};
$pageTitle = t('escrow.admin_title');
include __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/trade.css">
<main class="container admin-page">
  <div class="page-heading"><h1><?=h($pageTitle)?></h1></div>
  <div class="admin-layout">
    <?php include __DIR__.'/../includes/admin-nav.php';?>
    <div class="admin-content" style="overflow-wrap:anywhere">
      <p class="notice"><?=h(t('escrow.admin_intro'))?></p>
      <?php if ($error !== ''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?>
      <?php if ($notice): ?><p class="notice success" role="status"><?=h(t('escrow.saved'))?></p><?php endif;?>

      <section class="panel">
        <h2><?=h(t('escrow.cases'))?></h2>
        <?php if (!$cases): ?><p><?=h(t('escrow.empty'))?></p><?php endif;?>
        <?php if ($cases): ?>
          <ul>
            <?php foreach ($cases as $case): ?>
              <li><a href="?case_id=<?=(int)$case['id']?>"<?= (int)($selected['id'] ?? 0) === (int)$case['id'] ? ' aria-current="page"' : ''?>>
                <?=h($case['reference'])?> · <?=h($label('status', $case['status']))?>
              </a></li>
            <?php endforeach;?>
          </ul>
        <?php endif;?>
      </section>

      <?php if ($selected !== null): ?>
        <section class="panel">
          <h2><?=h($selected['reference'])?></h2>
          <p data-escrow-status="<?=h($selected['status'])?>"><strong><?=h($label('status', $selected['status']))?></strong></p>
          <p><a href="/marketplace/view-item.php?id=<?=(int)$selected['listing_id']?>"><?=h(t('trade.view_listing'))?></a></p>
          <dl class="trade-terms">
            <dt><?=h(t('escrow.role_buyer'))?></dt><dd>#<?=(int)$selected['buyer_id']?></dd>
            <dt><?=h(t('escrow.role_seller'))?></dt><dd>#<?=(int)$selected['seller_id']?></dd>
            <dt><?=h(t('trade.price'))?></dt><dd><?=h(money_eur((int)$selected['price_cents']))?></dd>
            <dt><?=h(t('trade.shipping_cost'))?></dt><dd><?=h(money_eur((int)$selected['shipping_cents']))?></dd>
            <dt><?=h(t('trade.total'))?></dt><dd><?=h(money_eur((int)$selected['price_cents'] + (int)$selected['shipping_cents']))?></dd>
            <dt><?=h(t('escrow.payment_reference'))?></dt><dd><code><?=h($selected['payment_reference'])?></code></dd>
            <?php if ($selected['deadline'] !== null): ?>
              <dt><?=h(t('escrow.deadline'))?></dt><dd><time datetime="<?=h(str_replace(' ', 'T', $selected['deadline']) . 'Z')?>"><?=h($selected['deadline'])?> UTC</time></dd>
            <?php endif;?>
          </dl>
        </section>
        <section class="panel">
          <h2><?=h(t('trade.next_steps'))?></h2>
          <?php $actions = array_intersect($selected['actions'], $allowed);?>
          <?php if (!$actions): ?><p><?=h(t('trade.no_actions'))?></p><?php endif;?>
          <?php foreach ($actions as $action): ?>
            <details>
              <summary><?=h($label('action', $action))?></summary>
              <form method="post" action="/admin/escrow.php?case_id=<?=(int)$selected['id']?>" class="form-grid">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="<?=h($action)?>">
                <input type="hidden" name="case_id" value="<?=(int)$selected['id']?>">
                <input type="hidden" name="version" value="<?=(int)$selected['version']?>">
                <input type="hidden" name="request_key" value="<?=h(bin2hex(random_bytes(16)))?>">
                <?php if (in_array($action, $decisions, true)): ?>
                  <label class="field"><?=h(t('escrow.test_details'))?><textarea name="details" rows="3" maxlength="1000" required></textarea></label>
                <?php endif;?>
                <label><input type="checkbox" name="confirm" value="1" required> <?=h(t('escrow.confirm_action'))?></label>
                <button class="button" type="submit"><?=h($label('action', $action))?></button>
              </form>
            </details>
          <?php endforeach;?>
        </section>
        <section class="panel">
          <h2><?=h(t('trade.history'))?></h2>
          <ol class="trade-history">
            <?php foreach ($selected['events'] as $event):
                $action = (in_array($event['action'], $providerActions, true) ? 'simulate_' : '') . $event['action'];
            ?>
              <li>
                <strong><?=h($label('action', $action))?></strong>
                <span><?=h($label('role', $event['actor_role']))?> · <?=h($label('status', $event['to_status']))?></span>
                <time datetime="<?=h(str_replace(' ', 'T', $event['created_at']) . 'Z')?>"><?=h($event['created_at'])?> UTC</time>
                <?php if (isset($event['price_cents'])): ?><span><?=h(t('trade.price'))?> <?=h(money_eur((int)$event['price_cents']))?></span><?php endif;?>
                <?php if (is_string($event['detail']) && $event['detail'] !== ''): ?><p class="trade-text"><?=h($event['detail'])?></p><?php endif;?>
              </li>
            <?php endforeach;?>
          </ol>
        </section>
      <?php endif;?>
    </div>
  </div>
</main>
<?php include __DIR__.'/../includes/footer.php';?>
