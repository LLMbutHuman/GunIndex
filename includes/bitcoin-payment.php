<?php
declare(strict_types=1);
// The selected invoice has already been loaded under the current user's account.
?>
<?php if ($bitcoinOrder): ?>
<section class="panel">
<h3><?=h(t('bitcoin.title'))?></h3>
<?php if($bitcoinOrder['chain']==='regtest'):?><p class="notice"><?=h(t('bitcoin.regtest'))?></p><?php endif;?>
<p><?=h(t('bitcoin.status_'.$bitcoinOrder['status']))?></p>
<?php if ($bitcoinOrder['status']==='pending'&&$bitcoinAvailable&&$selected['status']==='pending'&&strtotime($bitcoinOrder['expires_at'].' UTC')>time()): ?>
<dl>
<dt><?=h(t('bitcoin.network'))?></dt><dd><?=h(t('bitcoin.chain_'.$bitcoinOrder['chain']))?></dd>
<dt><?=h(t('bitcoin.address'))?></dt><dd><code style="overflow-wrap:anywhere"><?=h($bitcoinOrder['address'])?></code></dd>
<dt><?=h(t('bitcoin.amount'))?></dt><dd><strong><?=h(bitcoin_decimal((int)$bitcoinOrder['amount_sats']))?> BTC</strong></dd>
<dt><?=h(t('bitcoin.deadline'))?></dt><dd><?=h($bitcoinOrder['expires_at'])?> UTC</dd>
</dl>
<p><?=h(t('bitcoin.start_help'))?></p>
<?php endif;?>
<?php if($bitcoinOrder['confirmations']!==null):?>
<p><?=h(t('bitcoin.confirmations'))?> <?=max(0,(int)$bitcoinOrder['confirmations'])?> / <?=(int)$bitcoinOrder['required_confirmations']?></p>
<?php endif;?>
<?php if($bitcoinOrder['review_reason']!==null):?><p class="notice"><?=h(t('bitcoin.reason_'.$bitcoinOrder['review_reason']))?></p><?php endif;?>
</section>
<?php elseif ($bitcoinCanStart&&$canStartDirect): ?>
<form action="/actions/payment-checkout.php" method="post" class="form-grid">
<?=csrf_field()?>
<input type="hidden" name="invoice_id" value="<?=(int)$selected['id']?>">
<input type="hidden" name="provider" value="bitcoin">
<p><?=h(t('bitcoin.start_help'))?></p>
<button class="button secondary" type="submit"><?=h(t('bitcoin.start'))?></button>
</form>
<?php endif; ?>
