<?php
declare(strict_types=1);

require_once __DIR__.'/../config/bootstrap.php';
$user = require_login($pdo);
header('Cache-Control: private, no-store');
$pageTitle = t('identity.title');
$error = '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$unavailable = verification_reason($pdo);
if ($method === 'POST') {
    require_csrf();
    try {
        if ($unavailable !== null) throw new DomainException($unavailable);
        if (post_string('consent') !== '1') throw new InvalidArgumentException(t('identity.consent_required'));
        verification_submit($pdo, (int)$user['id'], $_FILES['documents'] ?? []);
        $_SESSION['identity_submitted'] = true;
        redirect('/user/verification.php');
    } catch (InvalidArgumentException|DomainException $exception) {
        http_response_code(422);
        $error = $exception->getMessage();
    }
}
$submitted = $method === 'GET' && ($_SESSION['identity_submitted'] ?? false) === true;
if ($submitted) unset($_SESSION['identity_submitted']);
$q = $pdo->prepare('SELECT id,status,identity_confirmed,age_confirmed,decision_reason,expires_at,purged_at FROM market_verification_requests WHERE user_id=? ORDER BY id DESC LIMIT 20');
$q->execute([$user['id']]);
$requests = $q->fetchAll();
include __DIR__.'/../includes/header.php';
?>
<main class="container">
    <div class="page-heading"><h1><?=h($pageTitle)?></h1><p><?=h(t('identity.scope'))?></p></div>
    <?php if ($error !== ''):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?>
    <?php if ($submitted):?><p class="notice success" role="status"><?=h(t('identity.submitted'))?></p><?php endif;?>
    <section class="panel">
        <?php if ($unavailable !== null):?>
            <h2><?=h(t('identity.unavailable'))?></h2>
            <p><?=h($unavailable)?></p>
            <p><?=h(t('identity.no_other_uploads'))?></p>
        <?php else:?>
            <h2><?=h(t('identity.application'))?></h2>
            <p><?=h(t('identity.storage_help', ['days'=>(int)site_setting($pdo,'verification_retention_days','0')]))?></p>
            <form method="post" enctype="multipart/form-data" class="form-grid">
                <?=csrf_field()?>
                <label class="field"><?=h(t('identity.images'))?><input type="file" name="documents[]" accept="image/jpeg,image/png" multiple required><small><?=h(t('identity.image_limits'))?></small></label>
                <label><input type="checkbox" name="consent" value="1" required> <?=h(t('identity.consent'))?></label>
                <button class="button" type="submit"><?=h(t('identity.submit'))?></button>
            </form>
        <?php endif;?>
    </section>
    <section class="panel">
        <h2><?=h(t('identity.history'))?></h2>
        <?php foreach ($requests as $request):?>
            <article>
                <h3><?=h(t('identity.request', ['id'=>(int)$request['id']]))?></h3>
                <p><?=h(verification_status_label($request['status']))?> ·
                    <?=h(t($request['identity_confirmed'] ? 'identity.identity_confirmed' : 'identity.identity_unconfirmed'))?> ·
                    <?=h(t($request['age_confirmed'] ? 'identity.age_confirmed' : 'identity.age_unconfirmed'))?></p>
                <?php if ($request['decision_reason'] !== null):?><p><?=nl2br(h($request['decision_reason']))?></p><?php endif;?>
                <p><?=h(t('identity.deadline', ['date'=>$request['expires_at'].' UTC']))?>
                    <?php if ($request['purged_at'] !== null):?> · <?=h(t('identity.deleted'))?><?php endif;?></p>
            </article>
        <?php endforeach;?>
        <?php if ($requests === []):?><p><?=h(t('identity.empty'))?></p><?php endif;?>
    </section>
</main>
<?php include __DIR__.'/../includes/footer.php';?>
