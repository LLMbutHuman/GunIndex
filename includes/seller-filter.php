<?php
declare(strict_types=1);
?>
<label class="field"><?=h(t('refine.seller_type'))?>
    <select name="seller_type">
        <?php foreach ([''=>'all', 'private'=>'private', 'dealer'=>'dealer'] as $sellerTypeValue=>$sellerTypeLabel): ?>
            <option value="<?=h($sellerTypeValue)?>" <?=$sellerTypeSelection===$sellerTypeValue?'selected':''?>><?=h(t('refine.seller_'.$sellerTypeLabel))?></option>
        <?php endforeach; ?>
    </select>
</label>
