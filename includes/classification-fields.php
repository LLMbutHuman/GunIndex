<?php
declare(strict_types=1);

function classification_fields(array $definitions, array $values, bool $search): void
{
    if (!$definitions) return;
    ?>
    <fieldset class="panel form-grid"><legend><?=h(t($search?'classification.filters':'classification.fields'))?></legend>
    <?php foreach ($definitions as $id=>$definition):
        $label = $definition['label'].($definition['unit']!==''?' ('.$definition['unit'].')':'');
        $required = !$search&&(int)$definition['is_required']===1;
        $raw = $values['attributes'][$id] ?? '';
        $value = is_string($raw)?$raw:'';
        if ($search&&$definition['kind']==='number'):
            if ($value !== ''): ?>
                <label class="field"><?=h($label)?>
                    <input name="attributes[<?=$id?>]" inputmode="decimal" maxlength="14" value="<?=h($value)?>">
                </label>
            <?php endif;
            foreach (['attribute_min'=>'classification.minimum','attribute_max'=>'classification.maximum'] as $key=>$translation):
                $bound = $values[$key][$id] ?? '';
                ?>
                <label class="field"><?=h(t($translation,['field'=>$label]))?>
                    <input name="<?=$key?>[<?=$id?>]" inputmode="decimal" maxlength="14" value="<?=h(is_string($bound)?$bound:'')?>">
                </label>
            <?php endforeach; continue;
        endif;
        ?>
        <label class="field"><?=h($label)?>
            <?php if ($definition['kind']==='choice'||$definition['kind']==='boolean'):
                $options = $definition['kind']==='choice'?array_combine($definition['choices'],$definition['choices']):['0'=>t('classification.no'),'1'=>t('classification.yes')]; ?>
                <select name="attributes[<?=$id?>]">
                    <option value=""><?=h(t('classification.unspecified'))?></option>
                    <?php foreach ($options as $option=>$caption): ?>
                        <option value="<?=h((string)$option)?>" <?=$value===(string)$option?'selected':''?>><?=h($caption)?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input name="attributes[<?=$id?>]" <?= $definition['kind']==='number'?'inputmode="decimal" maxlength="14"':'maxlength="160"' ?> value="<?=h($value)?>">
            <?php endif; ?>
            <?php if ($required): ?><small><?=h(t('classification.required_publish'))?></small><?php endif; ?>
        </label>
    <?php endforeach; ?>
    </fieldset>
    <?php
}

function classification_summary(array $entries): void
{
    if (!$entries) return;
    ?>
    <section class="panel"><h2><?=h(t('classification.fields'))?></h2><dl class="detail-facts">
    <?php foreach ($entries as $entry):
        $value = $entry['kind']==='boolean'?t($entry['value']==='1'?'classification.yes':'classification.no'):$entry['value'];
        ?>
        <div><dt><?=h($entry['label'])?></dt><dd><?=h($value.($entry['unit']!==''?' '.$entry['unit']:''))?></dd></div>
    <?php endforeach; ?>
    </dl></section>
    <?php
}
