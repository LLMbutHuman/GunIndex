<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';

header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$admin = require_permission($pdo, 'privacy.review');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    abort_request(405, t('account.method'));
}

$pageTitle = t('admin_privacy.title');
$error = '';
$saved = $method === 'GET' && ($_SESSION['privacy_review_saved'] ?? false) === true;
if ($saved) unset($_SESSION['privacy_review_saved']);
if ($method === 'POST') {
    require_csrf();
    try {
        privacy_review_deletion($pdo, (int)$admin['id'], (int)post_string('request_id'),
            post_string('decision'), post_string('reason', 16000), ($_POST['retention_confirmed'] ?? '') === '1');
        $_SESSION['privacy_review_saved'] = true;
        redirect('/admin/privacy.php');
    } catch (InvalidArgumentException | DomainException $failure) {
        http_response_code(422);
        $error = $failure->getMessage();
    }
}

$requests = $pdo->query("SELECT r.*,u.username FROM market_deletion_requests r JOIN users u ON u.id=r.user_id
    ORDER BY CASE r.status WHEN 'pending_review' THEN 0 WHEN 'waiting_retention' THEN 1 ELSE 2 END,r.id DESC LIMIT 100")->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<main class="container admin-page">
    <div class="page-heading">
        <h1><?=h($pageTitle)?></h1>
        <p><?=h(t('admin_privacy.help'))?></p>
    </div>
    <div class="admin-layout">
        <?php include __DIR__ . '/../includes/admin-nav.php'; ?>
        <div class="admin-content">
            <?php if ($error !== ''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif; ?>
            <?php if ($saved): ?><p class="notice success" role="status"><?=h(t('admin_privacy.saved'))?></p><?php endif; ?>
            <?php foreach ($requests as $request):
                $status = match ($request['status']) {
                    'pending_review' => t('admin_privacy.pending_review'),
                    'waiting_retention' => t('admin_privacy.waiting_retention'),
                    'anonymized' => t('admin_privacy.anonymized'),
                    default => t('admin_privacy.unknown_status'),
                };
            ?>
            <section class="panel">
                <h2><?=h(t('admin_privacy.request', ['id'=>(int)$request['id'], 'name'=>$request['username']]))?></h2>
                <p><?=h($status)?> · <?=h($request['created_at'])?> UTC</p>
                <p><?=nl2br(h($request['request_reason']))?></p>
                <?php if ($request['review_reason']): ?><p><?=nl2br(h($request['review_reason']))?></p><?php endif; ?>
                <?php if ($request['status'] !== 'anonymized'): ?>
                <form method="post" class="form-grid">
                    <?=csrf_field()?>
                    <input type="hidden" name="request_id" value="<?=(int)$request['id']?>">
                    <label class="field"><?=h(t('admin_privacy.decision'))?>
                        <select name="decision">
                            <option value="waiting_retention"><?=h(t('admin_privacy.defer'))?></option>
                            <option value="anonymized"><?=h(t('admin_privacy.anonymize'))?></option>
                        </select>
                    </label>
                    <label class="field"><?=h(t('admin_privacy.reason'))?>
                        <textarea name="reason" minlength="20" maxlength="4000" required></textarea>
                    </label>
                    <label><input type="checkbox" name="retention_confirmed" value="1"> <?=h(t('admin_privacy.confirm'))?></label>
                    <button class="button" type="submit"><?=h(t('admin_privacy.submit'))?></button>
                </form>
                <?php endif; ?>
            </section>
            <?php endforeach; ?>
            <?php if (!$requests): ?><section class="panel"><p><?=h(t('admin_privacy.empty'))?></p></section><?php endif; ?>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
