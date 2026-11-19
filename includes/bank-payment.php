<?php
declare(strict_types=1);
// The selected invoice has already been loaded under the current user's account.
?>
<?php if ($bankOrder): ?>
<section class="panel">
<h3><?=h(t('bank.bank_transfer'))?></h3>
<?php if ($selected['status']==='pending'&&$bankAvailable): ?>
<dl>
<dt><?=h(t('bank.beneficiary'))?></dt><dd><?=h($bankOrder['beneficiary'])?></dd>
<dt><?=h(t('bank.iban'))?></dt><dd><code><?=h($bankOrder['iban'])?></code></dd>
<dt><?=h(t('bank.reference'))?></dt><dd><code><?=h($bankOrder['reference'])?></code></dd>
<dt><?=h(t('Betrag'))?></dt><dd><?=h(money_eur((int)$bankOrder['amount_cents']))?></dd>
</dl>
<p><?=h(t('bank.waiting'))?></p>
<?php elseif (in_array($selected['status'],['paid','partially_refunded','refunded'],true)): ?>
<p><?=h(t('bank.completed'))?></p>
<?php elseif ($selected['status']==='pending'): ?>
<p><?=h(t('bank.review_required'))?></p>
<?php else: ?>
<p><?=h(t('bank.expired'))?></p>
<?php endif; ?>
</section>
<?php elseif ($bankAvailable&&$canStartDirect): ?>
<form action="/actions/payment-checkout.php" method="post" class="form-grid">
<?=csrf_field()?>
<input type="hidden" name="invoice_id" value="<?=(int)$selected['id']?>">
<input type="hidden" name="provider" value="bank">
<p><?=h(t('bank.start_help'))?></p>
<button class="button secondary" type="submit"><?=h(t('bank.start'))?></button>
</form>
<?php endif; ?>
