<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/governance.php';

header('Cache-Control: private, no-store, max-age=0');
$user = require_login($pdo);
$pageTitle = t('moderation_customer.report_title');
$error = '';
$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : 'listing';
$target = is_string($_GET['id'] ?? null) ? (int)$_GET['id'] : 0;
$reason = $evidence = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    try {
        $type = post_string('target_type',20);
        $target = (int)post_string('target_id',20);
        $reason = post_string('reason',16000);
        $evidence = post_string('evidence',32000);
        $id = governance_report($pdo,(int)$user['id'],$type,$target,$reason,$evidence);
        redirect('/user/reports.php?id='.$id);
    } catch (InvalidArgumentException|DomainException $failure) {
        http_response_code(422);
        $message = $failure->getMessage();
        $key = match ($message) {
            'Ungültiges Meldeziel.', 'Das Angebot ist nicht verfügbar.' => 'moderation_customer.error_target',
            'Diese Nachricht ist privat.' => 'moderation_customer.error_private_message',
            'Dieses Meldeziel wird nicht unterstützt.' => 'moderation_customer.error_unsupported_target',
            'Dieses Objekt kann so nicht gemeldet werden.' => 'moderation_customer.error_unreportable_target',
            'Bitte einen nachvollziehbaren Grund mit 10 bis 4.000 Zeichen angeben.' => 'moderation_customer.error_report_reason',
            'Konto nicht verfügbar.' => 'moderation_customer.error_account',
            'Das Stundenlimit für Meldungen ist erreicht.' => 'moderation_customer.error_report_limit',
            default => null,
        };
        $error = $key === null ? $message : t($key);
    }
}
try {
    governance_subject($pdo,(int)$user['id'],$type,$target);
} catch (InvalidArgumentException|DomainException) {
    if ($error === '') abort_request(404,t('moderation_customer.error_target'));
}
include __DIR__.'/../includes/header.php';
?>
<main class="container">
  <div class="page-heading">
    <h1><?=h(t('moderation_customer.report_heading'))?></h1>
    <p><?=h(t('moderation_customer.report_intro'))?></p>
  </div>
  <section class="panel">
    <?php if ($error !== ''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif; ?>
    <form method="post" class="form-grid">
      <?=csrf_field()?>
      <input type="hidden" name="target_type" value="<?=h($type)?>">
      <input type="hidden" name="target_id" value="<?=$target?>">
      <label class="field"><?=h(t('moderation_customer.reason'))?>
        <textarea name="reason" minlength="10" maxlength="4000" rows="5" required><?=h($reason)?></textarea>
      </label>
      <label class="field"><?=h(t('moderation_customer.evidence'))?>
        <textarea name="evidence" maxlength="8000" rows="5"><?=h($evidence)?></textarea>
        <small><?=h(t('moderation_customer.evidence_help'))?></small>
      </label>
      <button class="button" type="submit"><?=h(t('moderation_customer.report_submit'))?></button>
    </form>
  </section>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
