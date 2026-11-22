<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$admin=require_permission($pdo,'site.manage');$error='';
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','HEAD','POST'],true)){
    header('Allow: GET, HEAD, POST');abort_request(405,t('account.method'));
}
$saved=$method==='GET' && ($_SESSION['geography_saved']??false)===true;
if($saved)unset($_SESSION['geography_saved']);
if($method==='POST'){
    require_csrf();
    try{
        $action=post_string('action');$pdo->beginTransaction();settings_assert_admin($pdo,(int)$admin['id']);
        if($action==='country'){
            $country=geo_country(post_string('country_code',2));$active=post_string('is_active');if(!in_array($active,['0','1'],true))throw new InvalidArgumentException(t('admin_geography.invalid_status'));
            $q=$pdo->prepare('SELECT code FROM market_countries WHERE code=? FOR UPDATE');$q->execute([$country]);if(!$q->fetchColumn())throw new InvalidArgumentException(t('admin_geography.country_missing'));
            $pdo->prepare('UPDATE market_countries SET is_active=? WHERE code=?')->execute([(int)$active,$country]);audit_log($pdo,(int)$admin['id'],'geography.country_changed',null,['country'=>$country,'active'=>(int)$active]);
        }elseif($action==='source'){
            $id=filter_var($_POST['import_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$active=post_string('is_active');if(!$id||!in_array($active,['0','1'],true))throw new InvalidArgumentException(t('admin_geography.source_invalid'));
            $q=$pdo->prepare('SELECT id FROM geo_imports WHERE id=? FOR UPDATE');$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException(t('admin_geography.source_missing'));$pdo->prepare('UPDATE geo_imports SET is_active=? WHERE id=?')->execute([(int)$active,$id]);audit_log($pdo,(int)$admin['id'],'geography.source_changed',$id,['active'=>(int)$active]);
        }elseif($action==='translation'){
            $locale=post_string('locale',8);$key=post_string('setting_key',100);$text=trim(post_string('content',2000));
            if(!isset(i18n_locales()[$locale])||$locale==='de'||!in_array($key,['tagline','hero_title','hero_text'],true)||!preg_match('//u',$text)||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u',$text))throw new InvalidArgumentException(t('admin_geography.translation_invalid'));
            $max=$key==='hero_text'?500:120;if(mb_strlen($text)>$max)throw new InvalidArgumentException(t('admin_geography.translation_long'));
            $pdo->prepare('INSERT INTO market_content_translations(setting_key,locale,content,updated_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE content=VALUES(content),updated_by=VALUES(updated_by)')->execute([$key,$locale,$text,$admin['id']]);audit_log($pdo,(int)$admin['id'],'geography.content_translated',null,['key'=>$key,'locale'=>$locale]);
        }elseif($action==='category_translation'){
            $locale=post_string('locale',8);$id=filter_var($_POST['category_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$name=trim(post_string('name',400));
            if(!$id||!isset(i18n_locales()[$locale])||$locale==='de'||!preg_match('/\A.{1,100}\z/us',$name)||preg_match('/[\x00-\x1f\x7f]/u',$name)||!catalog_text_allowed($name))throw new InvalidArgumentException(t('admin_geography.category_invalid'));
            $q=$pdo->prepare('SELECT id FROM market_categories WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException(t('admin_geography.category_missing'));
            $pdo->prepare('INSERT INTO market_category_translations(category_id,locale,name,updated_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),updated_by=VALUES(updated_by)')->execute([$id,$locale,$name,$admin['id']]);audit_log($pdo,(int)$admin['id'],'geography.category_translated',$id,['locale'=>$locale]);
        }else throw new InvalidArgumentException(t('admin_geography.action_invalid'));
        $pdo->commit();$_SESSION['geography_saved']=true;redirect('/admin/geography.php');
    }catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code(422);$error=$e->getMessage();}
    catch(Throwable){if($pdo->inTransaction())$pdo->rollBack();http_response_code(503);$error=t('admin_geography.save_failed');}
}
$countries=$pdo->query('SELECT c.code,c.is_active,(SELECT COUNT(*) FROM geo_locations p JOIN geo_imports i ON i.id=p.import_id WHERE p.country_code=c.code AND i.is_active=1 AND i.license_reviewed=1) AS locations FROM market_countries c ORDER BY c.code')->fetchAll();
$imports=$pdo->query('SELECT * FROM geo_imports ORDER BY id DESC LIMIT 50')->fetchAll();$categories=catalog_categories($pdo,false);$translations=$pdo->query('SELECT setting_key,locale,content FROM market_content_translations ORDER BY locale,setting_key')->fetchAll();
$pageTitle=t('admin_geography.title');
$countryNames=geo_countries();
$targetLanguages=array_diff_key(i18n_locales(),['de'=>true]);
$brandKeys=['tagline','hero_title','hero_text'];
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page">
    <div class="page-heading"><h1><?=h($pageTitle)?></h1><p><?=h(t('admin_geography.intro'))?></p></div>
    <div class="admin-layout"><?php include __DIR__.'/../includes/admin-nav.php'; ?>
        <div class="admin-content">
            <?php if ($error !== ''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif; ?>
            <?php if ($saved): ?><p class="notice success" role="status"><?=h(t('admin_geography.saved'))?></p><?php endif; ?>
            <section class="panel">
                <h2><?=h(t('admin_geography.markets'))?></h2>
                <p><?=h(t('admin_geography.markets_help'))?></p>
                <div class="table-wrap"><table class="data-table">
                    <thead><tr><th><?=h(t('Land'))?></th><th><?=h(t('admin_geography.locations'))?></th><th><?=h(t('admin_geography.market_status'))?></th></tr></thead>
                    <tbody><?php foreach ($countries as $country): ?>
                        <tr><td><?=h($countryNames[$country['code']]??$country['code'])?> (<?=h($country['code'])?>)</td><td><?=(int)$country['locations']?></td>
                            <td><form method="post">
                                <?=csrf_field()?>
                                <input type="hidden" name="action" value="country">
                                <input type="hidden" name="country_code" value="<?=h($country['code'])?>">
                                <select name="is_active" aria-label="<?=h(t('admin_geography.market_status'))?>">
                                    <option value="1" <?=$country['is_active']?'selected':''?>><?=h(t('Aktiv'))?></option>
                                    <option value="0" <?=!$country['is_active']?'selected':''?>><?=h(t('admin_geography.inactive'))?></option>
                                </select>
                                <button class="button secondary" type="submit"><?=h(t('admin_settings.save'))?></button>
                            </form></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table></div>
            </section>
            <section class="panel">
                <h2><?=h(t('admin_geography.sources'))?></h2>
                <p><?=h(t('admin_geography.sources_help'))?></p>
                <p><?=h(t('admin_geography.import_help',['command'=>'ops/import-postcodes.php','guide'=>'docs/INTERNATIONAL.md']))?></p>
                <?php foreach ($imports as $source): ?>
                    <details>
                        <summary><?=h($source['country_code'].' · '.$source['source_name'])?> · <?=h(t('admin_geography.source_points',['count'=>(int)$source['record_count']]))?></summary>
                        <p><?=h($source['license_note'])?></p>
                        <p>SHA-256 <code><?=h($source['source_sha256'])?></code></p>
                        <?php if (!$source['license_reviewed']): ?><p><?=h(t('admin_geography.review_required'))?></p><?php endif; ?>
                        <form method="post">
                            <?=csrf_field()?>
                            <input type="hidden" name="action" value="source">
                            <input type="hidden" name="import_id" value="<?=(int)$source['id']?>">
                            <input type="hidden" name="is_active" value="<?=$source['is_active']?'0':'1'?>">
                            <button class="button secondary" type="submit"><?=h(t($source['is_active']?'admin_geography.source_disable':'admin_geography.source_enable'))?></button>
                        </form>
                    </details>
                <?php endforeach; ?>
            </section>
            <section class="panel">
                <h2><?=h(t('admin_geography.brand_title'))?></h2><p><?=h(t('admin_geography.brand_help'))?></p>
                <form method="post" class="form-grid">
                    <?=csrf_field()?><input type="hidden" name="action" value="translation">
                    <label class="field"><?=h(t('Sprache'))?><select name="locale"><?php foreach ($targetLanguages as $code=>$label): ?><option value="<?=h($code)?>"><?=h($label)?></option><?php endforeach; ?></select></label>
                    <label class="field"><?=h(t('admin_geography.text'))?><select name="setting_key"><?php foreach ($brandKeys as $key): ?><option value="<?=h($key)?>"><?=h(t('admin_settings.field.'.$key))?></option><?php endforeach; ?></select></label>
                    <label class="field"><?=h(t('admin_geography.translation'))?><textarea name="content" maxlength="500"></textarea></label>
                    <button class="button" type="submit"><?=h(t('admin_geography.translation_save'))?></button>
                </form>
                <?php foreach ($translations as $translation): ?>
                    <p><strong><?=h(($targetLanguages[$translation['locale']]??$translation['locale']).' · '.t('admin_settings.field.'.$translation['setting_key']))?></strong> · <?=h($translation['content'])?></p>
                <?php endforeach; ?>
            </section>
            <section class="panel">
                <h2><?=h(t('admin_geography.category_title'))?></h2>
                <form method="post" class="form-grid">
                    <?=csrf_field()?><input type="hidden" name="action" value="category_translation">
                    <label class="field"><?=h(t('Kategorie'))?><select name="category_id"><?php foreach ($categories as $category): ?><option value="<?=(int)$category['id']?>"><?=h(i18n_category($pdo,$category))?></option><?php endforeach; ?></select></label>
                    <label class="field"><?=h(t('Sprache'))?><select name="locale"><?php foreach ($targetLanguages as $code=>$label): ?><option value="<?=h($code)?>"><?=h($label)?></option><?php endforeach; ?></select></label>
                    <label class="field"><?=h(t('admin_geography.category_name'))?><input name="name" maxlength="100" required></label>
                    <button class="button" type="submit"><?=h(t('admin_geography.translation_save'))?></button>
                </form>
            </section>
        </div>
    </div>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
