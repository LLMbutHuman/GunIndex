<?php
declare(strict_types=1);
$field=static function(string $key,string $fallback='') use ($values):string { $value=$values[$key] ?? $fallback; return is_string($value)?$value:$fallback; };
?>
<form method="post" enctype="multipart/form-data" class="panel listing-form" data-listing-form>
<?= csrf_field() ?>
<div class="form-grid">
<label class="field span-2">Titel<input name="title" maxlength="150" minlength="5" required value="<?= h($field('title')) ?>" placeholder="Zum Beispiel: Leichter Campingrucksack, 35 Liter"></label>
<label class="field">Kategorie<select name="category_id" required><option value="">Kategorie auswählen</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= $field('category_id')===(string)$category['id']?'selected':'' ?>><?= h(str_repeat('— ',min(10,(int)($category['depth'] ?? 0))).$category['name']) ?></option><?php endforeach; ?></select></label>
<label class="field">Zustand<select name="condition_label"><?php foreach (['used'=>'Gebraucht','like_new'=>'Wie neu','new'=>'Neu'] as $value=>$label): ?><option value="<?= h($value) ?>" <?= $field('condition_label','used')===$value?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
<label class="field span-2">Beschreibung<textarea name="description" minlength="20" maxlength="20000" rows="7" required placeholder="Zustand, Lieferumfang, Gebrauchsspuren und alles, was Käufer wissen sollten."><?= h($field('description')) ?></textarea></label>
<label class="field">Angebotsart<select name="type"><?php foreach (['fixed'=>'Festpreis','auction'=>'Auktion'] as $value=>$label): ?><option value="<?= h($value) ?>" <?= $field('type','fixed')===$value?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
<label class="field">Preis / Startpreis in Euro<input name="price" inputmode="decimal" maxlength="13" required value="<?= h($field('price')) ?>" placeholder="79,00"></label>
<label class="field">Auktionsdauer in Tagen<select name="duration_days"><?php for ($day=1;$day<=14;$day++): ?><option value="<?= $day ?>" <?= $field('duration_days','7')===(string)$day?'selected':'' ?>><?= $day ?> <?= $day===1?'Tag':'Tage' ?></option><?php endfor; ?></select><small>Bei laufenden Auktionen bleibt der Endzeitpunkt beim Bearbeiten erhalten.</small></label>
<label class="field">Sofortkaufpreis in Euro <small>Optional, nur bei Auktionen vor dem ersten Gebot</small><input name="buyout_price" inputmode="decimal" maxlength="13" value="<?= h($field('buyout_price')) ?>" placeholder="Optional"></label>
<label class="field">Postleitzahl<input name="zip" pattern="[0-9]{5}" maxlength="5" inputmode="numeric" required value="<?= h($field('zip')) ?>" autocomplete="postal-code"></label>
<label class="field">Ort<input name="city" minlength="2" maxlength="100" required value="<?= h($field('city')) ?>" autocomplete="address-level2"></label>
<label class="check"><input type="checkbox" name="pickup" value="1" <?= $field('pickup','1')==='1'?'checked':'' ?>>Abholung möglich</label>
<label class="check"><input type="checkbox" name="shipping" value="1" <?= $field('shipping','0')==='1'?'checked':'' ?>>Versand nach Vereinbarung möglich</label>
<label class="field span-2">Produktbilder<input name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple><small>Bis zu fünf Bilder insgesamt, je maximal 5 MiB und 20 Megapixel. JPEG, PNG und WebP werden sicher neu codiert. Keine Ausweise oder anderen Identitätsdokumente hochladen. Bereits gespeicherte Bilder bleiben erhalten.</small></label>
<label class="field">Veröffentlichung<select name="status"><option value="active" <?= $field('status','active')==='active'?'selected':'' ?>>Veröffentlichen</option><option value="draft" <?= $field('status')==='draft'?'selected':'' ?>>Als Entwurf speichern</option></select></label>
</div>
<?php if (($existingImageCount ?? 0)>0): ?><p class="muted"><?= (int)$existingImageCount ?> Produktbild(er) bereits gespeichert.</p><?php endif; ?>
<p class="notice">Dieser Marktplatz unterstützt allgemeine unregulierte Waren. Waffen, Munition und andere nicht freigegebene regulierte Artikel dürfen hier nicht angeboten werden. In diesem Entwicklungsstand werden keine Plattformgebühren erhoben; der Warenkaufpreis wird nicht über die Plattform eingezogen.</p>
<label class="check"><input type="checkbox" name="accept_rules" value="1" required <?= $field('accept_rules')==='1'?'checked':'' ?>>Ich bestätige die allgemeinen Produktregeln (Version general-1.0), die Richtigkeit der Angaben und meine Berechtigung zum Verkauf.</label>
<div class="button-row"><button class="button" type="submit"><?= isset($listingId)?'Änderungen speichern':'Inserat speichern' ?></button><a class="button button-ghost" href="/user/dashboard.php">Zum Dashboard</a></div>
</form>
