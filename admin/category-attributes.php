<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$admin = require_permission($pdo,'categories.manage');
$method = $_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','HEAD','POST'],true)) {
    header('Allow: GET, HEAD, POST');
    abort_request(405,t('account.method'));
}
$input = $method==='POST'?$_POST:$_GET;
$category = filter_var($input['category_id']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>4294967295]]);
$categories = catalog_rows($pdo);
if (!$category||!isset($categories[$category])) abort_request(404,t('classification.invalid'));
$error = '';
if ($method === 'POST') {
    require_csrf();
    try {
        $id = filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>4294967295]]);
        if ($id===false) throw new InvalidArgumentException(t('classification.invalid'));
        classification_admin_save($pdo,(int)$admin['id'],$category,$id,$_POST);
        redirect('/admin/category-attributes.php?category_id='.$category.'&saved=1');
    } catch (InvalidArgumentException|DomainException $e) {
        http_response_code(422);
        $error = $e->getMessage();
    }
}
$definitions = classification_definitions($pdo,$category,false);
$pageTitle = t('classification.admin');
$formValue = static fn(string $key):string => ($_POST['id']??null)==='0'&&is_string($_POST[$key]??null)?$_POST[$key]:'';
include __DIR__.'/../includes/header.php';
?>
<main class="page admin-page">
<h1><?=h($pageTitle)?> · <?=h($categories[$category]['name'])?></h1>
<p><a href="/admin/categories.php"><?=h(t('Kategorie'))?></a></p>
<p><?=h(t('classification.definition_help'))?></p>
<?php if($error!==''):?><p role="alert" class="notice error"><?=h($error)?></p><?php endif;?>
<?php if(($_GET['saved']??null)==='1'):?><p role="status" class="notice success"><?=h(t('dashboard.saved'))?></p><?php endif;?>
<section class="panel"><h2><?=h(t('classification.new'))?></h2>
<form method="post" class="form-grid">
<?=csrf_field()?><input type="hidden" name="category_id" value="<?=$category?>"><input type="hidden" name="id" value="0">
<label class="field"><?=h(t('classification.code'))?><input name="code" maxlength="48" pattern="[a-z][a-z0-9]*(?:-[a-z0-9]+)*" required value="<?=h($formValue('code'))?>"></label>
<label class="field"><?=h(t('classification.label'))?><input name="label" maxlength="80" required value="<?=h($formValue('label'))?>"></label>
<label class="field"><?=h(t('classification.kind'))?><select name="kind">
<?php foreach(['text','number','choice','boolean'] as $kind):?><option value="<?=$kind?>" <?=$formValue('kind')===$kind?'selected':''?>><?=h(t('classification.kind.'.$kind))?></option><?php endforeach;?>
</select></label>
<label class="field"><?=h(t('classification.unit'))?><input name="unit" maxlength="24" value="<?=h($formValue('unit'))?>"></label>
<label class="field"><?=h(t('classification.choices'))?><textarea name="choices" maxlength="6000" rows="4"><?=h($formValue('choices'))?></textarea><small><?=h(t('classification.choices_help'))?></small></label>
<label class="field"><?=h(t('classification.required_publish'))?><select name="is_required"><option value="0"><?=h(t('classification.no'))?></option><option value="1"><?=h(t('classification.yes'))?></option></select></label>
<input type="hidden" name="is_active" value="1"><button class="button" type="submit"><?=h(t('Änderungen speichern'))?></button>
</form></section>
<?php foreach($definitions as $definition):?>
<section class="panel"><h2><?=h($definition['label'])?></h2><p><?=h($definition['code'].' · '.t('classification.kind.'.$definition['kind']).($definition['unit']!==''?' · '.$definition['unit']:''))?></p>
<?php if($definition['choices']):?><p><?=h(implode(' · ',$definition['choices']))?></p><?php endif;?>
<form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="category_id" value="<?=$category?>"><input type="hidden" name="id" value="<?=(int)$definition['id']?>">
<label class="field"><?=h(t('classification.required_publish'))?><select name="is_required">
<option value="0"><?=h(t('classification.no'))?></option><option value="1" <?=(int)$definition['is_required']===1?'selected':''?>><?=h(t('classification.yes'))?></option></select></label>
<label class="field"><?=h(t('Aktiv'))?><select name="is_active"><option value="0"><?=h(t('classification.no'))?></option><option value="1" <?=(int)$definition['is_active']===1?'selected':''?>><?=h(t('classification.yes'))?></option></select></label>
<button class="button" type="submit"><?=h(t('Änderungen speichern'))?></button></form></section>
<?php endforeach;?>
</main><?php include __DIR__.'/../includes/footer.php'; ?>
