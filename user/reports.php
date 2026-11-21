<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/governance.php';

header('Cache-Control: private, no-store, max-age=0');
$user = require_login($pdo);
$pageTitle = t('moderation_customer.cases_title');
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    try {
        $caseId = (int)post_string('case_id');
        governance_appeal($pdo,(int)$user['id'],$caseId,post_string('appeal',16000));
        redirect('/user/reports.php?id='.$caseId);
    } catch (InvalidArgumentException|DomainException $failure) {
        http_response_code(422);
        $message = $failure->getMessage();
        $key = match ($message) {
            'Bitte die Beschwerde mit 10 bis 4.000 Zeichen begründen.' => 'moderation_customer.error_appeal_reason',
            'Für diesen Fall ist derzeit keine neue Beschwerde möglich.' => 'moderation_customer.error_appeal_unavailable',
            default => null,
        };
        $error = $key === null ? $message : t($key);
    }
}
$selected = null;
if (isset($_GET['id'])) {
    try {
        $selected = governance_case_for_user($pdo,(int)(is_string($_GET['id']) ? $_GET['id'] : 0),(int)$user['id']);
    } catch (DomainException) {
        abort_request(404,t('moderation_customer.error_case_missing'));
    }
}
$query = $pdo->prepare('SELECT id,case_code,target_type,status,created_at FROM market_reports WHERE reporter_id=? OR subject_user_id=? ORDER BY id DESC LIMIT 100');
$query->execute([$user['id'],$user['id']]);
$cases = $query->fetchAll();
$reportTypes = [
    'listing'=>t('moderation_admin.target_listing'),
    'user'=>t('moderation_admin.target_user'),
    'message'=>t('moderation_admin.target_message'),
    'rating'=>t('moderation_admin.target_rating'),
    'advertisement'=>t('moderation_admin.target_advertisement'),
];
$reportStatuses = [
    'open'=>t('moderation_admin.status_open'),
    'reviewing'=>t('moderation_admin.status_reviewing'),
    'resolved'=>t('moderation_admin.status_resolved'),
    'appealed'=>t('moderation_admin.status_appealed'),
];
include __DIR__.'/../includes/header.php';
?>
<main class="container">
  <div class="page-heading">
    <h1><?=h(t('moderation_customer.cases_heading'))?></h1>
    <p><?=h(t('moderation_customer.cases_intro'))?></p>
  </div>
  <?php if ($error !== ''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif; ?>
  <?php if ($selected): ?>
    <section class="panel">
      <h2><?=h($selected['case_code'])?></h2>
      <p><?=h($reportStatuses[$selected['status']] ?? t('moderation_admin.status_unknown'))?>
        · <?=h($reportTypes[$selected['target_type']] ?? t('moderation_admin.target_unknown'))?> #<?=(int)$selected['target_id']?></p>
      <h3><?=h(t('moderation_admin.report_reason'))?></h3><p><?=nl2br(h($selected['reason']))?></p>
      <?php if ($selected['decision_reason']): ?>
        <h3><?=h(t('moderation_customer.decision'))?></h3><p><?=nl2br(h($selected['decision_reason']))?></p>
      <?php endif; ?>
      <?php if ($selected['appeal_resolution']): ?>
        <h3><?=h(t('moderation_customer.appeal_reply'))?></h3><p><?=nl2br(h($selected['appeal_resolution']))?></p>
      <?php endif; ?>
      <?php if ($selected['status'] === 'resolved' && $selected['appeal_text'] === null): ?>
        <form method="post" class="form-grid">
          <?=csrf_field()?>
          <input type="hidden" name="case_id" value="<?=(int)$selected['id']?>">
          <label class="field"><?=h(t('moderation_customer.appeal_reason'))?>
            <textarea name="appeal" minlength="10" maxlength="4000" required></textarea>
          </label>
          <button class="button secondary" type="submit"><?=h(t('moderation_customer.appeal_submit'))?></button>
        </form>
      <?php elseif ($selected['appeal_text']): ?>
        <h3><?=h(t('moderation_customer.your_appeal'))?></h3><p><?=nl2br(h($selected['appeal_text']))?></p>
      <?php endif; ?>
    </section>
  <?php endif; ?>
  <section class="panel">
    <h2><?=h(t('moderation_customer.cases_overview'))?></h2>
    <?php if (!$cases): ?><p><?=h(t('moderation_customer.cases_empty'))?></p><?php endif; ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th><?=h(t('moderation_admin.case'))?></th><th><?=h(t('moderation_admin.type'))?></th><th><?=h(t('moderation_admin.status'))?></th><th><?=h(t('moderation_admin.date'))?></th></tr></thead>
        <tbody>
          <?php foreach ($cases as $case): ?>
            <tr>
              <td><a href="?id=<?=(int)$case['id']?>"><?=h($case['case_code'])?></a></td>
              <td><?=h($reportTypes[$case['target_type']] ?? t('moderation_admin.target_unknown'))?></td>
              <td><?=h($reportStatuses[$case['status']] ?? t('moderation_admin.status_unknown'))?></td>
              <td><?=h($case['created_at'])?> UTC</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
