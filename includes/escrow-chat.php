<?php
declare(strict_types=1);
if (!$context || !escrow_chat_available($pdo, $context)) return;
$escrowListing = $context['item'];
$escrowPeer = (int)$context['peer']['id'];
$escrowUrl = '/user/escrow.php?listing_id=' . (int)$escrowListing['id'] . '&user_id=' . $escrowPeer;
$escrowCanOffer = $uid === (int)$escrowListing['seller_id'] && $context['can_send']
    && $escrowListing['status'] === 'active' && (int)$escrowListing['trade_checkout_enabled'] === 1
    && (int)$escrowListing['shipping'] === 1 && trade_listing_available($pdo, (int)$escrowListing['id']);
?>
<section class="panel" aria-label="<?=h(t('escrow.chat_link'))?>">
    <a class="button secondary" href="<?=h($escrowUrl)?>"><?=h(t('escrow.chat_link'))?></a>
    <?php if ($escrowCanOffer): ?>
        <details>
            <summary><?=h(t('escrow.action_offer'))?></summary>
            <p><?=h(t('escrow.shipping_preview', ['amount'=>money_eur((int)$escrowListing['shipping_cents'])]))?></p>
            <form method="post" action="<?=h($escrowUrl)?>" class="form-grid" data-escrow-chat-offer>
                <?=csrf_field()?>
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="request_key" value="<?=h(bin2hex(random_bytes(16)))?>">
                <label class="field"><?=h(t('trade.offer_amount'))?><input name="amount" inputmode="decimal" maxlength="16" required></label>
                <label><input type="checkbox" name="confirm" value="1" required> <?=h(t('escrow.confirm_action'))?></label>
                <button class="button" type="submit"><?=h(t('escrow.action_offer'))?></button>
            </form>
        </details>
    <?php endif; ?>
</section>
