<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/governance.php';

header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$admin = require_permission($pdo,'reports.read');
$pageTitle = t('moderation_admin.title');
$error = '';
$evidence = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    try {
        $action = post_string('action');
        $id = (int)post_string('case_id');
        if ($action === 'evidence') {
            governance_assert_actor($pdo,(int)$admin['id'],'reports.read');
            $reason = trim(post_string('reason',1000));
            if (mb_strlen($reason) < 10) throw new InvalidArgumentException(t('moderation_admin.error_evidence_reason'));
            $query = $pdo->prepare('SELECT evidence_cipher FROM market_reports WHERE id=?');
            $query->execute([$id]);
            $cipher = $query->fetchColumn();
            if (!$cipher) throw new DomainException(t('moderation_admin.error_no_evidence'));
            audit_log($pdo,(int)$admin['id'],'report.evidence_viewed',$id,['reason'=>$reason]);
            $evidence = crypto_decrypt('report',$cipher);
        } else {
            governance_decide($pdo,(int)$admin['id'],$id,$action,post_string('reason',16000));
            redirect('/admin/moderation.php?id='.$id);
        }
    } catch (InvalidArgumentException|DomainException $failure) {
        http_response_code(422);
        $message = $failure->getMessage();
        // Map fixed service diagnostics here so other governance callers retain their wording.
        $key = match ($message) {
            'Entscheidung und nachvollziehbare Begründung fehlen.' => 'moderation_admin.error_decision',
            'Fall nicht gefunden.' => 'moderation_admin.error_missing_case',
            'Der Fall muss zuerst dir zugewiesen werden.' => 'moderation_admin.error_assignment',
            'Es liegt keine offene Beschwerde vor.' => 'moderation_admin.error_no_appeal',
            'Der Fall wurde bereits entschieden.' => 'moderation_admin.error_closed',
            'Angebot nicht gefunden.' => 'moderation_admin.error_missing_listing',
            'Dieses Ziel kann hier nicht pauschal ausgeblendet werden.' => 'moderation_admin.error_hide_target',
            'Ein Administrationskonto wird hier nicht gesperrt.' => 'moderation_admin.error_restrict_account',
            'Die erforderliche Berechtigung fehlt.' => 'moderation_admin.error_permission',
            'Eine aktuelle Mehrfaktoranmeldung ist erforderlich.' => 'moderation_admin.error_mfa',
            default => null,
        };
        $error = $key === null ? $message : t($key);
    }
}

$selected = null;
$raw = $_GET['id'] ?? $_POST['case_id'] ?? null;
if (is_string($raw) && ctype_digit($raw)) {
    $query = $pdo->prepare('SELECT * FROM market_reports WHERE id=?');
    $query->execute([(int)$raw]);
    $selected = $query->fetch();
}
$cases = $pdo->query("SELECT id,case_code,target_type,status,assigned_to,created_at FROM market_reports ORDER BY CASE status WHEN 'appealed' THEN 0 WHEN 'open' THEN 1 WHEN 'reviewing' THEN 2 ELSE 3 END,id DESC LIMIT 100")->fetchAll();

$moderationTypes = [
    'listing'=>t('moderation_admin.target_listing'),
    'user'=>t('moderation_admin.target_user'),
    'message'=>t('moderation_admin.target_message'),
    'rating'=>t('moderation_admin.target_rating'),
    'advertisement'=>t('moderation_admin.target_advertisement'),
];
$moderationStatuses = [
    'open'=>t('moderation_admin.status_open'),
    'reviewing'=>t('moderation_admin.status_reviewing'),
    'resolved'=>t('moderation_admin.status_resolved'),
    'appealed'=>t('moderation_admin.status_appealed'),
];
$moderationActions = [
    'assign'=>t('moderation_admin.action_assign'),
    'dismiss'=>t('moderation_admin.action_dismiss'),
    'warn'=>t('moderation_admin.action_warn'),
    'hide'=>t('moderation_admin.action_hide'),
    'restrict'=>t('moderation_admin.action_restrict'),
    'appeal_upheld'=>t('moderation_admin.action_appeal_upheld'),
    'appeal_reconsider'=>t('moderation_admin.action_appeal_reconsider'),
];
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page">
  <div class="page-heading">
    <p class="eyebrow"><?=h(t('moderation_admin.eyebrow'))?></p>
    <h1><?=h(t('moderation_admin.heading'))?></h1>
    <p><?=h(t('moderation_admin.intro'))?></p>
  </div>
  <div class="admin-layout">
    <?php include __DIR__.'/../includes/admin-nav.php'; ?>
    <div class="admin-content">
      <?php if ($error !== ''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif; ?>
      <?php if ($selected): ?>
        <section class="panel">
          <h2><?=h($selected['case_code'])?></h2>
          <p><?=h($moderationTypes[$selected['target_type']] ?? t('moderation_admin.target_unknown'))?> #<?=(int)$selected['target_id']?>
            · <?=h($moderationStatuses[$selected['status']] ?? t('moderation_admin.status_unknown'))?>
            · <?=h($selected['assigned_to'] === null ? t('moderation_admin.unassigned') : t('moderation_admin.assigned',['id'=>(int)$selected['assigned_to']]))?></p>
          <h3><?=h(t('moderation_admin.report_reason'))?></h3>
          <p><?=nl2br(h($selected['reason']))?></p>
          <?php if ($selected['appeal_text']): ?>
            <h3><?=h(t('moderation_admin.appeal'))?></h3><p><?=nl2br(h($selected['appeal_text']))?></p>
          <?php endif; ?>
          <?php if ($selected['decision_reason']): ?>
            <h3><?=h(t('moderation_admin.previous_decision'))?></h3><p><?=nl2br(h($selected['decision_reason']))?></p>
          <?php endif; ?>
          <form method="post" class="form-grid">
            <?=csrf_field()?>
            <input type="hidden" name="case_id" value="<?=(int)$selected['id']?>">
            <label class="field"><?=h(t('moderation_admin.action'))?>
              <select name="action">
                <?php foreach ($moderationActions as $moderationAction=>$moderationLabel): ?>
                  <option value="<?=h($moderationAction)?>"><?=h($moderationLabel)?></option>
                <?php endforeach; ?>
                <?php if ($selected['evidence_cipher']): ?><option value="evidence"><?=h(t('moderation_admin.action_evidence'))?></option><?php endif; ?>
              </select>
            </label>
            <label class="field"><?=h(t('moderation_admin.decision_reason'))?><textarea name="reason" minlength="10" maxlength="4000" required></textarea></label>
            <button class="button" type="submit"><?=h(t('moderation_admin.save'))?></button>
          </form>
          <p><?=h(t('moderation_admin.auction_notice'))?></p>
          <?php if ($evidence !== ''): ?>
            <div class="notice"><h3><?=h(t('moderation_admin.evidence_heading'))?></h3><p><?=nl2br(h($evidence))?></p></div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
      <section class="panel">
        <h2><?=h(t('moderation_admin.queue'))?></h2>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th><?=h(t('moderation_admin.case'))?></th><th><?=h(t('moderation_admin.type'))?></th><th><?=h(t('moderation_admin.status'))?></th><th><?=h(t('moderation_admin.date'))?></th></tr></thead>
            <tbody>
              <?php foreach ($cases as $case): ?>
                <tr>
                  <td><a href="?id=<?=(int)$case['id']?>"><?=h($case['case_code'])?></a></td>
                  <td><?=h($moderationTypes[$case['target_type']] ?? t('moderation_admin.target_unknown'))?></td>
                  <td><?=h($moderationStatuses[$case['status']] ?? t('moderation_admin.status_unknown'))?></td>
                  <td><?=h($case['created_at'])?> UTC</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (!$cases): ?><p><?=h(t('moderation_admin.empty'))?></p><?php endif; ?>
      </section>
    </div>
  </div>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
