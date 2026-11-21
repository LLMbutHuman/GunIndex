<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';

header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$user = require_login($pdo);
$pageTitle = t('account.privacy_title');
$error = '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    abort_request(405, t('account.method'));
}
if ($method === 'POST') {
    require_csrf();
    auth_rate_limit('privacy-sensitive', (string)$user['id'], 10, 3600);
    try {
        $action = post_string('action');
        $password = post_string('current_password', 1024);
        if ($action === 'export') {
            $stream = privacy_export($pdo, (int)$user['id'], $password);
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="gunindex-meine-daten.json"');
            fpassthru($stream);
            fclose($stream);
            exit;
        }
        if ($action === 'delete') {
            if (post_string('confirm_deactivation') !== '1') throw new InvalidArgumentException(t('account.privacy_confirm_required'));
            privacy_request_deletion($pdo, (int)$user['id'], $password, post_string('reason', 8000));
            auth_clear_session();
            $_SESSION['privacy_deletion_confirmed'] = true;
            redirect('/auth/login.php');
        }
        throw new InvalidArgumentException(t('account.privacy_invalid_action'));
    } catch (InvalidArgumentException | DomainException $failure) {
        http_response_code(422);
        $error = $failure->getMessage();
    }
}
$privacyContact = trim(site_setting($pdo, 'privacy_contact_email', '')) ?: trim(site_setting($pdo, 'contact_email', ''));
include __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <div class="page-heading">
        <h1><?=h($pageTitle)?></h1>
        <p><?=h(t('account.privacy_help'))?></p>
    </div>
    <?php if ($error !== ''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif; ?>
    <section class="panel">
        <h2><?=h(t('account.privacy_export_title'))?></h2>
        <p><?=h(t('account.privacy_export_help'))?></p>
        <form method="post" class="form-grid">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="export">
            <label class="field"><?=h(t('Aktuelles Passwort'))?>
                <input type="password" name="current_password" autocomplete="current-password" maxlength="1024" required>
            </label>
            <button class="button" type="submit"><?=h(t('account.privacy_download'))?></button>
        </form>
    </section>
    <section class="panel">
        <h2><?=h(t('account.privacy_delete_title'))?></h2>
        <p><?=h(t('account.privacy_delete_help'))?></p>
        <form method="post" class="form-grid">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="delete">
            <label class="field"><?=h(t('Aktuelles Passwort'))?>
                <input type="password" name="current_password" autocomplete="current-password" maxlength="1024" required>
            </label>
            <label class="field"><?=h(t('account.privacy_optional_reason'))?>
                <textarea name="reason" maxlength="2000"></textarea>
            </label>
            <label><input type="checkbox" name="confirm_deactivation" value="1" required> <?=h(t('account.privacy_confirm'))?></label>
            <button class="button danger" type="submit"><?=h(t('account.privacy_request_delete'))?></button>
        </form>
    </section>
    <p><?=h($privacyContact !== ''
        ? t('account.privacy_contact', ['address'=>$privacyContact])
        : t('account.privacy_contact_missing'))?></p>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
