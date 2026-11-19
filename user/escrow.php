<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';

header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$actor = (int)require_login($pdo)['id'];
if (!escrow_simulation_enabled($pdo)) abort_request(404, t('escrow.unavailable'));
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    abort_request(405, t('account.method'));
}
if ($method === 'POST') require_csrf();

try {
    $listing = trade_integer($_GET['listing_id'] ?? null);
    $peer = trade_integer($_GET['user_id'] ?? null);
    $caseId = isset($_GET['case_id']) ? trade_integer($_GET['case_id']) : null;
    $context = chat_context($pdo, $actor, $listing, $peer);
} catch (MarketNotFoundException|MarketAccessException|InvalidArgumentException) {
    abort_request(404, t('escrow.unavailable'));
} catch (Throwable $failure) {
    error_log('Escrow simulation conversation could not be read ('.get_class($failure).')');
    abort_request(503, t('escrow.error'));
}
$conversation = http_build_query(['listing_id' => $listing, 'user_id' => $peer]);
$url = '/user/escrow.php?' . $conversation;
$error = '';

if ($method === 'POST') {
    try {
        $action = post_string('action', 32);
        if ($action === 'create') {
            $caseId = escrow_create($pdo, $actor, $listing, $peer, $_POST);
        } else {
            $caseId = trade_integer($_POST['case_id'] ?? null);
            $before = escrow_details($pdo, $actor, $caseId);
            $other = $actor === (int)$before['seller_id'] ? (int)$before['buyer_id'] : (int)$before['seller_id'];
            if ((int)$before['listing_id'] !== $listing || $other !== $peer) {
                throw new MarketNotFoundException(t('escrow.unavailable'));
            }
            escrow_apply($pdo, $actor, $caseId, $action, $_POST);
        }
        $_SESSION['escrow_notice'] = true;
        redirect($url . '&case_id=' . $caseId);
    } catch (MarketNotFoundException) {
        abort_request(404, t('escrow.unavailable'));
    } catch (MarketAccessException) {
        http_response_code(403);
        $error = t('escrow.forbidden');
    } catch (MarketConflictException) {
        http_response_code(409);
        $error = t('escrow.changed');
    } catch (DomainException|InvalidArgumentException $failure) {
        http_response_code(422);
        $error = $failure->getMessage() === t('escrow.invalid_contact') ? t('escrow.invalid_contact') : t('escrow.invalid');
    } catch (Throwable $failure) {
        error_log('Escrow simulation request failed ('.get_class($failure).')');
        abort_request(503, t('escrow.error'));
    }
}
$notice = $method === 'GET' && !empty($_SESSION['escrow_notice']);
if ($notice) unset($_SESSION['escrow_notice']);

try {
    $cases = escrow_cases($pdo, $actor, $listing, $peer);
    $selected = $caseId !== null || $cases
        ? escrow_details($pdo, $actor, $caseId ?? (int)$cases[0]['id'])
        : null;
    if ($selected !== null) {
        $other = $actor === (int)$selected['seller_id'] ? (int)$selected['buyer_id'] : (int)$selected['seller_id'];
        if ((int)$selected['listing_id'] !== $listing || $other !== $peer) {
            throw new MarketNotFoundException(t('escrow.unavailable'));
        }
    }
    $item = $context['item'];
    $open = array_filter($cases, static fn(array $case): bool => $case['status'] === 'offered'
        && $case['deadline'] !== null && strtotime($case['deadline'] . ' UTC') > time());
    $mayCreate = $actor === (int)$item['seller_id'] && $context['existing'] && $context['can_send']
        && in_array($item['type'], ['fixed', 'negotiated'], true) && $item['status'] === 'active'
        && (int)$item['trade_checkout_enabled'] === 1 && (int)$item['shipping'] === 1
        && ($item['ends_at'] === null || strtotime($item['ends_at'] . ' UTC') > time())
        && !$open && !escrow_listing_reserved($pdo, $listing);
} catch (MarketAccessException|MarketNotFoundException) {
    abort_request(404, t('escrow.unavailable'));
} catch (Throwable $failure) {
    error_log('Escrow simulation could not be read ('.get_class($failure).')');
    abort_request(503, t('escrow.error'));
}
$ownContact = $selected['own_contact'] ?? null;
if (!is_string($ownContact) || !str_ends_with(strtolower($ownContact), '@example.invalid')) $ownContact = '';
$label = static function (string $type, string $value): string {
    $key = 'escrow.' . $type . '_' . $value;
    $text = t($key);
    return $text !== $key ? $text : t('escrow.unknown');
};
$providerActions = escrow_provider_actions();
$chatPath = e2ee_enabled() ? '/user/secure-chat.php?' : '/user/chat.php?';
$pageTitle = t('escrow.title');
include __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/trade.css">
<main class="trade">
  <div class="trade-heading">
    <div><h1><?=h($pageTitle)?></h1><p><?=h($item['title'])?></p></div>
    <a href="<?=h($chatPath . $conversation)?>"><?=h(t('escrow.back_chat'))?></a>
  </div>
  <p class="notice"><?=h(t('escrow.intro'))?></p>
  <?php if ($error !== ''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?>
  <?php if ($notice): ?><p class="notice success" role="status"><?=h(t('escrow.saved'))?></p><?php endif;?>

  <section class="panel">
    <h2><?=h(t('escrow.cases'))?></h2>
    <?php if (!$cases): ?><p><?=h(t('escrow.empty'))?></p><?php endif;?>
    <?php if ($cases): ?>
      <ul>
        <?php foreach ($cases as $case): ?>
          <li><a href="<?=h($url . '&case_id=' . (int)$case['id'])?>"<?= (int)($selected['id'] ?? 0) === (int)$case['id'] ? ' aria-current="page"' : ''?>>
            <?=h($case['reference'])?> · <?=h($label('status', $case['status']))?>
          </a></li>
        <?php endforeach;?>
      </ul>
    <?php endif;?>
    <?php if ($mayCreate): ?>
      <details>
        <summary><?=h(t('escrow.action_offer'))?></summary>
        <p><?=h(t('escrow.shipping_preview', ['amount' => money_eur((int)$item['shipping_cents'])]))?></p>
        <form method="post" action="<?=h($url)?>" class="form-grid">
          <?=csrf_field()?>
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="request_key" value="<?=h(bin2hex(random_bytes(16)))?>">
          <label class="field"><?=h(t('trade.offer_amount'))?><input name="amount" inputmode="decimal" maxlength="16" required></label>
          <label><input type="checkbox" name="confirm" value="1" required> <?=h(t('escrow.confirm_action'))?></label>
          <button class="button" type="submit"><?=h(t('escrow.action_offer'))?></button>
        </form>
      </details>
    <?php endif;?>
  </section>

  <?php if ($selected !== null): ?>
    <div class="trade-grid">
      <div class="trade-stack">
        <section class="panel">
          <h2><?=h($selected['reference'])?></h2>
          <p class="trade-status" data-escrow-status="<?=h($selected['status'])?>"><?=h($label('status', $selected['status']))?></p>
          <p><?=h(t($actor === (int)$selected['seller_id'] ? 'trade.role.seller' : 'trade.role.buyer'))?></p>
          <dl class="trade-terms">
            <dt><?=h(t('trade.price'))?></dt><dd><?=h(money_eur((int)$selected['price_cents']))?></dd>
            <dt><?=h(t('trade.shipping_cost'))?></dt><dd><?=h(money_eur((int)$selected['shipping_cents']))?></dd>
            <dt><?=h(t('trade.total'))?></dt><dd><?=h(money_eur((int)$selected['price_cents'] + (int)$selected['shipping_cents']))?></dd>
            <dt><?=h(t('escrow.payment_reference'))?></dt><dd><code><?=h($selected['payment_reference'])?></code></dd>
            <?php if ($selected['deadline'] !== null): ?>
              <dt><?=h(t('escrow.deadline'))?></dt><dd><time datetime="<?=h(str_replace(' ', 'T', $selected['deadline']) . 'Z')?>"><?=h($selected['deadline'])?> UTC</time></dd>
            <?php endif;?>
            <?php if ($ownContact !== ''): ?><dt><?=h(t('escrow.own_contact'))?></dt><dd><?=h($ownContact)?></dd><?php endif;?>
          </dl>
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
      </div>
      <div class="trade-stack">
        <section class="panel">
          <h2><?=h(t('trade.next_steps'))?></h2>
          <?php if (!$selected['actions']): ?><p><?=h(t('trade.no_actions'))?></p><?php endif;?>
          <?php foreach ($selected['actions'] as $action): ?>
            <details>
              <summary><?=h($label('action', $action))?></summary>
              <form method="post" action="<?=h($url . '&case_id=' . (int)$selected['id'])?>" class="form-grid">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="<?=h($action)?>">
                <input type="hidden" name="case_id" value="<?=(int)$selected['id']?>">
                <input type="hidden" name="version" value="<?=(int)$selected['version']?>">
                <input type="hidden" name="request_key" value="<?=h(bin2hex(random_bytes(16)))?>">
                <?php if ($action === 'counter'): ?>
                  <label class="field"><?=h(t('trade.offer_amount'))?><input name="amount" inputmode="decimal" maxlength="16" required></label>
                <?php elseif ($action === 'contact'): ?>
                  <label class="field"><?=h(t('escrow.own_contact'))?>
                    <input name="contact" type="email" placeholder="test@example.invalid" value="<?=h($ownContact)?>" autocomplete="off" required>
                  </label>
                  <p><?=h(t('escrow.contact_hint'))?></p>
                <?php elseif (in_array($action, ['dispatch', 'dispute'], true)): ?>
                  <label class="field"><?=h(t('escrow.test_details'))?><textarea name="details" rows="3" maxlength="1000" required></textarea></label>
                <?php endif;?>
                <label><input type="checkbox" name="confirm" value="1" required> <?=h(t('escrow.confirm_action'))?></label>
                <button class="button" type="submit"><?=h($label('action', $action))?></button>
              </form>
            </details>
          <?php endforeach;?>
        </section>
      </div>
    </div>
  <?php endif;?>
</main>
<?php include __DIR__.'/../includes/footer.php';?>
