<?php
declare(strict_types=1);
$field=static function(string $key,string $fallback='') use ($values):string { $value=$values[$key] ?? $fallback; return is_string($value)?$value:$fallback; };
?>
<form method="post" enctype="multipart/form-data" class="panel listing-form" data-listing-form>
<?= csrf_field() ?>
<div class="form-grid"><label class="field"><?=h(t('Land'))?><select name="country_code"><?php foreach(geo_active_countries($pdo) as $code=>$label):?><option value="<?=h($code)?>" <?=$field('country_code',geo_country())===$code?'selected':''?>><?=h($label.' ('.$code.')')?></option><?php endforeach;?></select></label>
<label class="field span-2"> <?=h(t('Titel'))?> <input name="title" maxlength="150" minlength="5" required value="<?= h($field('title')) ?>" placeholder="Zum Beispiel: Leichter Campingrucksack, 35 Liter"></label>
<label class="field"> <?=h(t('Kategorie'))?> <select name="category_id" required><option value=""><?=h(t('Kategorie auswählen'))?></option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= $field('category_id')===(string)$category['id']?'selected':'' ?>><?= h(str_repeat('— ',min(10,(int)($category['depth'] ?? 0))).i18n_category($pdo,$category)) ?></option><?php endforeach; ?></select></label>
<label class="field"> <?=h(t('Zustand'))?> <select name="condition_label"><?php foreach (['used'=>t('Gebraucht'),'like_new'=>t('Wie neu'),'new'=>t('Neu')] as $value=>$label): ?><option value="<?= h($value) ?>" <?= $field('condition_label','used')===$value?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
<label class="field span-2"> <?=h(t('Beschreibung'))?> <textarea name="description" minlength="20" maxlength="20000" rows="7" required placeholder="Zustand, Lieferumfang, Gebrauchsspuren und alles, was Käufer wissen sollten."><?= h($field('description')) ?></textarea></label>
<label class="field"> <?=h(t('Angebotsart'))?> <select name="type"><?php foreach (['fixed'=>t('Festpreis'),'auction'=>t('Auktion')] as $value=>$label): ?><option value="<?= h($value) ?>" <?= $field('type','fixed')===$value?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
<label class="field"> <?=h(t('Preis / Startpreis in Euro'))?> <input name="price" inputmode="decimal" maxlength="13" required value="<?= h($field('price')) ?>" placeholder="79,00"></label>
<label class="field"> <?=h(t('Auktionsdauer in Tagen'))?> <select name="duration_days"><?php for ($day=1;$day<=14;$day++): ?><option value="<?= $day ?>" <?= $field('duration_days','7')===(string)$day?'selected':'' ?>><?= $day ?> <?= $day===1?t('Tag'):t('Tage') ?></option><?php endfor; ?></select><small><?=h(t('Bei laufenden Auktionen bleibt der Endzeitpunkt beim Bearbeiten erhalten.'))?></small></label>
<label class="field"> <?=h(t('Sofortkaufpreis in Euro'))?> <small><?=h(t('Optional, nur bei Auktionen vor dem ersten Gebot'))?></small><input name="buyout_price" inputmode="decimal" maxlength="13" value="<?= h($field('buyout_price')) ?>" placeholder="<?=h(t('Optional'))?>"></label>
<label class="field"> <?=h(t('Postleitzahl'))?> <input name="zip" maxlength="16" required value="<?= h($field('zip')) ?>" autocomplete="postal-code"></label>
<label class="field"> <?=h(t('Ort'))?> <input name="city" minlength="2" maxlength="100" required value="<?= h($field('city')) ?>" autocomplete="address-level2"></label>
<label class="check"><input type="checkbox" name="pickup" value="1" <?= $field('pickup','1')==='1'?'checked':'' ?>> <?=h(t('Abholung möglich'))?> </label>
<label class="check"><input type="checkbox" name="shipping" value="1" <?= $field('shipping','0')==='1'?'checked':'' ?>> <?=h(t('Versand nach Vereinbarung möglich'))?> </label>
<label class="field span-2"> <?=h(t('Produktbilder'))?> <input name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple><small><?=h(t('Bis zu fünf Bilder insgesamt, je maximal 5 MiB und 20 Megapixel. JPEG, PNG und WebP werden sicher neu codiert. Keine Ausweise oder anderen Identitätsdokumente hochladen. Bereits gespeicherte Bilder bleiben erhalten.'))?></small></label>
<label class="field"> <?=h(t('Veröffentlichung'))?> <select name="status"><option value="active" <?= $field('status','active')==='active'?'selected':'' ?>> <?=h(t('Veröffentlichen'))?> </option><option value="draft" <?= $field('status')==='draft'?'selected':'' ?>> <?=h(t('Als Entwurf speichern'))?> </option></select></label>
</div>
<?php if (($existingImageCount ?? 0)>0): ?><p class="muted"><?= (int)$existingImageCount ?><?=h(t('Produktbild(er) bereits gespeichert.'))?></p><?php endif; ?>
<?php if (!empty($existingImageRows)): ?>
<fieldset class="panel"><legend><?=h(t('image_order.title'))?></legend>
<p><?=h(t('image_order.help'))?></p>
<input type="hidden" name="image_revision" value="<?=h(image_order_revision($existingImageRows))?>">
<div class="form-grid">
<?php foreach($existingImageRows as $imageRow): ?>
<label class="field"><img src="/user/listing-image.php?id=<?=(int)$listingId?>&amp;position=<?=(int)$imageRow['position']?>" alt="<?=h(t('image_order.image',['number'=>(int)$imageRow['position']+1]))?>" width="160" height="120" loading="lazy">
<?=h(t('image_order.position',['number'=>(int)$imageRow['position']+1]))?>
<select name="image_positions[<?=(int)$imageRow['id']?>]">
<?php for($imagePosition=1;$imagePosition<=count($existingImageRows);$imagePosition++): ?>
<option value="<?=$imagePosition?>" <?=$imagePosition===(int)$imageRow['position']+1?'selected':''?>><?=$imagePosition?></option>
<?php endfor; ?></select></label>
<?php endforeach; ?></div></fieldset>
<?php endif; ?>
<p class="notice"><?=h(t('auction_billing.goods_notice'))?> <?=h(t('auction_billing.fixed_notice'))?></p>
<?php $auctionFeeOffered=auction_billing_form($pdo,(int)$user['id'],$listing??null); ?>
<label class="check"><input type="checkbox" name="accept_rules" value="1" required <?= $field('accept_rules')==='1'?'checked':'' ?>><?=h(t('Ich bestätige die allgemeinen Produktregeln (Version general-1.0), die Richtigkeit der Angaben und meine Berechtigung zum Verkauf.'))?></label>
<div class="button-row"><button class="button" type="submit" data-listing-save><?= isset($listingId)?t('Änderungen speichern'):t('Inserat speichern') ?></button><?php if($auctionFeeOffered):?><button class="button" type="submit" name="auction_fee_action" value="publish" data-auction-publish><?=h(t('auction_billing.publish'))?></button><?php endif;?><a class="button button-ghost" href="/user/dashboard.php"> <?=h(t('Zum Dashboard'))?> </a></div>
</form>
