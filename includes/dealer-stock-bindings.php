<?php declare(strict_types=1); ?>
<section aria-labelledby="dealer-checkout-bindings">
    <h3 id="dealer-checkout-bindings"><?=h(t('dealer_checkout.title'))?></h3>
    <p><?=h(t('dealer_checkout.shared_stock'))?></p>
    <?php if (!$stockBindings['bound']): ?>
        <p><?=h(t('dealer_checkout.no_bindings'))?></p>
    <?php else: ?>
        <ul>
            <?php foreach ($stockBindings['bound'] as $checkoutListing): ?>
                <li>
                    <?php if (in_array($checkoutListing['status'], ['active','ended','sold'], true)): ?>
                        <a href="/marketplace/view-item.php?id=<?=(int)$checkoutListing['id']?>"><?=h($checkoutListing['title'])?></a>
                    <?php else: ?>
                        <?=h($checkoutListing['title'])?>
                    <?php endif; ?>
                    <?php if ($stockCanWrite): ?>
                        <form method="post" action="<?=h($stockUrl)?>" class="button-row">
                            <?=csrf_field()?>
                            <input type="hidden" name="action" value="unbind_checkout">
                            <input type="hidden" name="listing_id" value="<?=(int)$checkoutListing['id']?>">
                            <input type="hidden" name="revision" value="<?=h($checkoutListing['revision'])?>">
                            <button class="button secondary" type="submit" aria-label="<?=h(t('dealer_checkout.unbind_listing',['title'=>$checkoutListing['title']]))?>"><?=h(t('dealer_checkout.unbind'))?></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($stockCanWrite && $stockBindings['available']): ?>
        <form method="post" action="<?=h($stockUrl)?>" class="form-grid">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="bind_checkout">
            <label class="field">
                <?=h(t('dealer_checkout.listing'))?>
                <select name="listing_id" required>
                    <option value=""><?=h(t('dealer_checkout.pick_listing'))?></option>
                    <?php foreach ($stockBindings['available'] as $checkoutListing): ?>
                        <option value="<?=(int)$checkoutListing['id']?>"><?=h($checkoutListing['title'])?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button" type="submit"><?=h(t('dealer_checkout.bind'))?></button>
        </form>
    <?php elseif ($stockCanWrite): ?>
        <p><?=h(t('dealer_checkout.no_available'))?></p>
    <?php endif; ?>

    <?php if ($stockBindings['review_count'] > 0): ?>
        <p class="notice" role="status"><?=h(t('dealer_checkout.review',['count'=>$stockBindings['review_count']]))?></p>
        <?php if ($stockCanWrite && !empty($stockBindings['reviews'])): ?>
            <ul>
                <?php foreach ($stockBindings['reviews'] as $checkoutReview): ?>
                    <li>
                        <p><strong><?=h($checkoutReview['reference'])?></strong> · <?=h(t($checkoutReview['status'] === 'count_limit' ? 'dealer_checkout.review_count_limit' : 'dealer_checkout.review_return_required'))?></p>
                        <?php foreach ([
                            ['restore_checkout_return','dealer_checkout.restore','dealer_checkout.confirm_restore'],
                            ['close_checkout_review','dealer_checkout.close_review','dealer_checkout.confirm_close'],
                        ] as [$checkoutAction,$checkoutLabel,$checkoutConfirm]): ?>
                            <form method="post" action="<?=h($stockUrl)?>" class="button-row">
                                <?=csrf_field()?>
                                <input type="hidden" name="action" value="<?=h($checkoutAction)?>">
                                <input type="hidden" name="trade_id" value="<?=(int)$checkoutReview['trade_id']?>">
                                <label><input type="checkbox" name="confirm_review" value="1" required> <?=h(t($checkoutConfirm))?></label>
                                <button class="button secondary" type="submit"><?=h(t($checkoutLabel))?></button>
                            </form>
                        <?php endforeach; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>
