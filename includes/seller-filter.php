<?php
declare(strict_types=1);
?>
<label class="field"><?=h(t('refine.seller_type'))?>
    <select name="seller_type">
        <?php foreach ([''=>'all', 'private'=>'private', 'dealer'=>'dealer'] as $value=>$label): ?>
            <option value="<?=h($value)?>" <?=$sellerTypeSelection===$value?'selected':''?>><?=h(t('refine.seller_'.$label))?></option>
        <?php endforeach; ?>
    </select>
</label>
