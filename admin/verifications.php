<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$admin = require_permission($pdo, 'identity.review');
header('Cache-Control: private, no-store');
$pageTitle = t('admin_nav.identity');
$error = '';
$token = '';
$selectedId = is_string($_GET['id'] ?? null) ? (int)$_GET['id'] : 0;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$saved = $method === 'GET' && ($_SESSION['identity_reviewed'] ?? null) === $selectedId;
if ($saved) unset($_SESSION['identity_reviewed']);
if ($method === 'POST') {
    require_csrf();
    $actorId = (int)$admin['id'];
    try {
        $action = post_string('action');
        $selectedId = (int)post_string('request_id');
        if ($action === 'access') {
            $token = verification_access_token($pdo, $actorId, (int)post_string('document_id'), post_string('reason', 1000));
        } elseif ($action === 'decide') {
            verification_decide($pdo, $actorId, $selectedId, post_string('decision'), post_string('reason', 2000), ($_POST['identity_confirmed'] ?? '') === '1', ($_POST['age_confirmed'] ?? '') === '1');
            $_SESSION['identity_reviewed'] = $selectedId;
            redirect('/admin/verifications.php?id='.$selectedId);
        } elseif ($action === 'privacy') {
            privacy_review_deletion($pdo, $actorId, (int)post_string('deletion_id'), post_string('decision'), post_string('reason', 4000), ($_POST['retention_confirmed'] ?? '') === '1');
            redirect('/admin/verifications.php');
        } else {
            throw new InvalidArgumentException(t('identity.invalid_action'));
        }
    } catch (InvalidArgumentException|DomainException $exception) {
        http_response_code(422);
        $error = $exception->getMessage();
    }
}
$q=$pdo->prepare('SELECT r.*,u.username FROM market_verification_requests r JOIN users u ON u.id=r.user_id WHERE r.id=?');$q->execute([$selectedId]);$selected=$q->fetch();$documents=[];if($selected){$q=$pdo->prepare('SELECT id,deleted_at FROM market_verification_documents WHERE request_id=? ORDER BY id');$q->execute([$selectedId]);$documents=$q->fetchAll();}
$queue=$pdo->query("SELECT r.id,r.status,r.expires_at,u.username FROM market_verification_requests r JOIN users u ON u.id=r.user_id ORDER BY CASE r.status WHEN 'pending' THEN 0 WHEN 'needs_information' THEN 1 ELSE 2 END,r.id DESC LIMIT 100")->fetchAll();
include __DIR__.'/../includes/header.php';?>
<main class="container admin-page">
    <div class="page-heading"><h1><?=h($pageTitle)?></h1><p><?=h(t('identity.review_scope'))?></p></div>
    <div class="admin-layout">
        <?php include __DIR__.'/../includes/admin-nav.php';?>
        <div class="admin-content">
            <?php if ($error !== ''):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?>
            <?php if ($saved):?><p class="notice success" role="status"><?=h(t('admin_privacy.saved'))?></p><?php endif;?>
            <?php if ($token !== ''):?>
                <section class="panel">
                    <h2><?=h(t('identity.one_time_access'))?></h2>
                    <form action="/marketplace/verification-document.php" method="post" target="_blank" rel="noopener noreferrer">
                        <?=csrf_field()?><input type="hidden" name="token" value="<?=h($token)?>">
                        <button class="button" type="submit"><?=h(t('identity.open_document'))?></button>
                    </form>
                    <p><?=h(t('identity.token_scope'))?></p>
                </section>
            <?php endif;?>
            <?php if ($selected):?>
                <section class="panel">
                    <h2><?=h(t('admin_privacy.request', ['id'=>(int)$selected['id'], 'name'=>$selected['username']]))?></h2>
                    <p><?=h(verification_status_label($selected['status']))?> · <?=h(t('identity.deadline', ['date'=>$selected['expires_at'].' UTC']))?></p>
                    <?php foreach ($documents as $document):?>
                        <form method="post" class="form-grid">
                            <?=csrf_field()?>
                            <input type="hidden" name="action" value="access">
                            <input type="hidden" name="request_id" value="<?=(int)$selected['id']?>">
                            <input type="hidden" name="document_id" value="<?=(int)$document['id']?>">
                            <label class="field"><?=h(t('identity.document_reason', ['id'=>(int)$document['id']]))?><input name="reason" minlength="10" maxlength="1000" required></label>
                            <button class="button secondary" type="submit" <?=$document['deleted_at'] ? 'disabled' : ''?>><?=h(t('identity.request_access'))?></button>
                        </form>
                    <?php endforeach;?>
                    <form method="post" class="form-grid">
                        <?=csrf_field()?>
                        <input type="hidden" name="action" value="decide">
                        <input type="hidden" name="request_id" value="<?=(int)$selected['id']?>">
                        <label class="field"><?=h(t('admin_privacy.decision'))?>
                            <select name="decision">
                                <option value="approved"><?=h(t('identity.record_findings'))?></option>
                                <option value="rejected"><?=h(t('identity.reject'))?></option>
                                <option value="needs_information"><?=h(t('identity.request_information'))?></option>
                            </select>
                        </label>
                        <label><input type="checkbox" name="identity_confirmed" value="1"> <?=h(t('identity.identity_checked'))?></label>
                        <label><input type="checkbox" name="age_confirmed" value="1"> <?=h(t('identity.age_checked'))?></label>
                        <label class="field"><?=h(t('identity.decision_reason'))?><textarea name="reason" minlength="10" maxlength="2000" required></textarea></label>
                        <button class="button" type="submit"><?=h(t('admin_privacy.submit'))?></button>
                    </form>
                </section>
            <?php endif;?>
            <section class="panel">
                <h2><?=h(t('identity.queue'))?></h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th scope="col"><?=h(t('identity.case'))?></th><th scope="col"><?=h(t('identity.account'))?></th><th scope="col"><?=h(t('identity.status'))?></th><th scope="col"><?=h(t('identity.retention_end'))?></th></tr></thead>
                        <tbody>
                        <?php foreach ($queue as $row):?>
                            <tr><td><a href="?id=<?=(int)$row['id']?>">#<?=(int)$row['id']?></a></td><td><?=h($row['username'])?></td><td><?=h(verification_status_label($row['status']))?></td><td><?=h($row['expires_at'])?> UTC</td></tr>
                        <?php endforeach;?>
                        <?php if ($queue === []):?><tr><td colspan="4"><?=h(t('admin_privacy.empty'))?></td></tr><?php endif;?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</main>
<?php include __DIR__.'/../includes/footer.php';?>
