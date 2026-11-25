<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';require_once __DIR__.'/../config/governance.php';
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$admin=require_permission($pdo,'legal.manage');$pageTitle=t('legal_admin.title');$error='';$selectedId=is_string($_GET['id']??null)?(int)$_GET['id']:0;
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','HEAD','POST'],true)) { header('Allow: GET, HEAD, POST'); abort_request(405,t('account.method')); }
$statusLabel=static fn(string $status):string=>t(match($status){
    'draft'=>'legal_admin.draft','approved'=>'legal_admin.approved','scheduled'=>'legal_admin.scheduled',default=>'legal_admin.unknown',
});
$values=['code'=>'','version'=>'1.0','language'=>i18n_locale(),'title'=>'','body'=>''];
if ($method === 'POST') {
    require_csrf();
    try {
        $action = post_string('action');
        if ($action === 'create') {
            foreach ($values as $key => $_) $values[$key] = post_string($key, $key === 'body' ? 200000 : 500);
            $selectedId = legal_create($pdo,(int)$admin['id'],$values['code'],$values['version'],$values['language'],$values['title'],$values['body']);
            redirect('/admin/legal.php?id='.$selectedId);
        } elseif ($action === 'approve') {
            $selectedId = (int)post_string('document_id');
            $checks = [];
            for ($i=1; $i<=5; $i++) $checks[] = post_string('check_'.$i);
            legal_approve($pdo,(int)$admin['id'],$selectedId,post_string('reviewer',200),post_string('effective_at',10),$checks);
            redirect('/admin/legal.php?id='.$selectedId);
        } elseif ($action === 'import_bundled') {
            $_SESSION['legal_bundled_import'] = legal_import_bundled_drafts($pdo,(int)$admin['id']);
            redirect('/admin/legal.php');
        } elseif ($action === 'import_file') {
            $_SESSION['legal_bundled_import'] = legal_import_draft_file($pdo,(int)$admin['id'],(array)($_FILES['drafts']??[]));
            redirect('/admin/legal.php');
        } else {
            throw new InvalidArgumentException(t('legal_admin.invalid_action'));
        }
    } catch (InvalidArgumentException|DomainException $errorResponse) {
        http_response_code(422);
        $error = $errorResponse->getMessage();
    } catch (PDOException $errorResponse) {
        if ($errorResponse->getCode() !== '23000') throw $errorResponse;
        http_response_code(409);
        $error = t('legal_admin.duplicate');
    }
}
$imported = $method === 'GET' ? ($_SESSION['legal_bundled_import'] ?? null) : null;
if ($imported !== null) unset($_SESSION['legal_bundled_import']);
$q=$pdo->prepare('SELECT * FROM market_legal_documents WHERE id=?');$q->execute([$selectedId]);$selected=$q->fetch();$documents=$pdo->query('SELECT id,document_code,version,language,title,status,content_hash,effective_at,reviewer_name FROM market_legal_documents ORDER BY document_code,language,id DESC LIMIT 500')->fetchAll();
$bundledCodes = array_keys(require __DIR__.'/../docs/legal/manifest.php');
$languages = i18n_locales();
$coverage = legal_language_coverage($pdo,$bundledCodes,array_keys($languages));
if ($method === 'GET' && $selected && ($_GET['copy'] ?? null) === '1') {
    $values = ['code'=>$selected['document_code'],'version'=>'','language'=>$selected['language'],
        'title'=>$selected['title'],'body'=>$selected['body_text']];
} elseif ($method === 'GET') {
    $code = $_GET['code'] ?? '';
    $language = $_GET['language'] ?? '';
    if (is_string($code) && in_array($code,$bundledCodes,true) && is_string($language) && isset($languages[$language])) {
        $values['code'] = $code;
        $values['language'] = $language;
    }
}
include __DIR__.'/../includes/header.php';?>
<main class="container admin-page"><div class="page-heading"><h1><?=h($pageTitle)?></h1><p><?=h(t('legal_admin.intro'))?></p></div><div class="admin-layout"><?php include __DIR__.'/../includes/admin-nav.php';?><div class="admin-content"><?php if($error):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?>
<?php if($selected):?><section class="panel"><h2><?=h($selected['title'])?> · <?=h($selected['version'])?> / <?=h($selected['language'])?></h2><p><?=h($statusLabel($selected['status']))?> · SHA-256 <code><?=h($selected['content_hash'])?></code></p><details><summary><?=h(t('legal_admin.content'))?></summary><div style="white-space:pre-wrap;overflow-wrap:anywhere"><?=h($selected['body_text'])?></div></details>
<p><a class="button secondary" href="?id=<?=(int)$selected['id']?>&amp;copy=1#legal-editor"><?=h(t('legal_admin.copy_version'))?></a></p>
<?php if($selected['status']==='draft'):?><h3><?=h(t('legal_admin.review'))?></h3><p><?=h(t('legal_admin.review_help'))?></p><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="approve"><input type="hidden" name="document_id" value="<?=(int)$selected['id']?>"><label class="field"><?=h(t('legal_admin.reviewer'))?><input name="reviewer" minlength="5" maxlength="200" required></label><label class="field"><?=h(t('legal_admin.effective'))?><input type="date" name="effective_at" required></label>
<?php foreach([t('legal_admin.check_1'),t('legal_admin.check_2'),t('legal_admin.check_3'),t('legal_admin.check_4'),t('legal_admin.check_5')] as $i=>$text):?><label><input type="checkbox" name="check_<?=$i+1?>" value="1" required> <?=h($text)?></label><?php endforeach;?><button class="button" type="submit"><?=h(t('legal_admin.approve'))?></button></form><?php elseif($selected['status']==='approved'):?><p><?=h(t('legal_admin.release',['reviewer'=>$selected['reviewer_name'],'date'=>$selected['effective_at']]))?></p><?php endif;?></section><?php endif;?>
<?php if ($imported !== null):?><p class="notice success" role="status"><?=h(t('legal_admin.bundled_result', $imported))?></p><?php endif;?>
<section class="panel">
    <h2><?=h(t('legal_admin.bundled_title'))?></h2>
    <p><?=h(t('legal_admin.bundled_help'))?></p>
    <form method="post" action="/admin/legal.php">
        <?=csrf_field()?><input type="hidden" name="action" value="import_bundled">
        <button class="button secondary" type="submit"><?=h(t('legal_admin.bundled_import'))?></button>
    </form>
</section>
<section class="panel">
    <h2><?=h(t('legal_admin.import_file_title'))?></h2>
    <p><?=h(t('legal_admin.import_file_help'))?></p>
    <form method="post" action="/admin/legal.php" enctype="multipart/form-data">
        <?=csrf_field()?><input type="hidden" name="action" value="import_file">
        <label class="field"><?=h(t('legal_admin.import_file_label'))?><input name="drafts" type="file" accept="application/json,.json" required></label>
        <button class="button secondary" type="submit"><?=h(t('legal_admin.bundled_import'))?></button>
    </form>
</section>
<details class="panel" id="legal-coverage">
    <summary><?=h(t('legal_admin.coverage_title'))?></summary>
    <p><?=h(t('legal_admin.coverage_help'))?></p>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th scope="col"><?=h(t('legal_admin.document'))?></th>
            <?php foreach ($languages as $name): ?><th scope="col"><?=h($name)?></th><?php endforeach; ?>
        </tr></thead>
        <tbody><?php foreach ($bundledCodes as $code): ?>
            <tr><th scope="row"><?=h($code)?></th><?php foreach ($languages as $language=>$name): ?>
                <?php $variant = $coverage[$code][$language] ?? null; ?>
                <td><?php if ($variant): ?>
                    <a href="?id=<?=(int)$variant['id']?>"><?=h($statusLabel($variant['review_state']))?> · <?=h($variant['version'])?></a>
                <?php else: ?>
                    <a href="?code=<?=h($code)?>&amp;language=<?=h($language)?>#legal-editor"><?=h(t('legal_admin.coverage_missing'))?></a>
                <?php endif; ?></td>
            <?php endforeach; ?></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</details>
<section class="panel" id="legal-editor"><h2><?=h(t('legal_admin.create'))?></h2><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="create"><label class="field"><?=h(t('legal_admin.code'))?><input name="code" pattern="[a-z0-9-]{2,80}" value="<?=h($values['code'])?>" required></label><label class="field"><?=h(t('legal_admin.version'))?><input name="version" pattern="[a-zA-Z0-9.-]{1,30}" value="<?=h($values['version'])?>" required></label><label class="field"><?=h(t('legal_admin.language'))?><input name="language" pattern="[a-z]{2}(-[A-Z]{2})?" value="<?=h($values['language'])?>" required></label><label class="field"><?=h(t('legal_admin.document_title'))?><input name="title" maxlength="200" value="<?=h($values['title'])?>" required></label><label class="field full-width"><?=h(t('legal_admin.draft_full'))?><textarea name="body" rows="16" minlength="200" maxlength="200000" required><?=h($values['body'])?></textarea></label><button class="button" type="submit"><?=h(t('legal_admin.save'))?></button></form></section>
<section class="panel"><h2><?=h(t('legal_admin.versions'))?></h2><div class="table-wrap"><table class="data-table"><thead><tr><th><?=h(t('legal_admin.document'))?></th><th><?=h(t('legal_admin.version'))?></th><th><?=h(t('legal_admin.language'))?></th><th><?=h(t('legal_admin.status'))?></th></tr></thead><tbody><?php foreach($documents as $doc):?><tr><td><a href="?id=<?=(int)$doc['id']?>"><?=h($doc['title'])?></a></td><td><?=h($doc['version'])?></td><td><?=h($doc['language'])?></td><td><?=h($statusLabel($doc['status']))?></td></tr><?php endforeach;?></tbody></table></div></section></div></div></main><?php include __DIR__.'/../includes/footer.php';?>
