<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
if (current_user($pdo) === null) {
    $_SESSION['open_setup_after_login'] = true;
    redirect('/auth/login.php');
}
$admin = require_permission($pdo, 'site.manage');
require_once __DIR__.'/../config/setup.php';
$actor = (int)$admin['id'];
$state = setup_installation_state($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    abort_request(405, t('account.method'));
}
$lastCheck = $_SESSION['installation_check_result'] ?? null;
if ($method === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? null;
    if ($action === 'run_checks') {
        $_SESSION['installation_check_result'] = setup_run_checks($pdo, $actor);
    } elseif ($action === 'activate') {
        if (($_POST['confirm'] ?? null) !== '1') abort_request(422, t('setup.launch.confirm'));
        if ($state !== 'pending' || !setup_recent_success($lastCheck, time())) abort_request(409, t('setup.launch.recheck'));
        if (!setup_activate($pdo, $actor)) {
            $_SESSION['installation_check_result'] = setup_run_checks($pdo, $actor);
            $_SESSION['installation_activation_failed'] = true;
        } else {
            unset($_SESSION['installation_check_result']);
        }
    } else {
        abort_request(422, t('account.invalid_input'));
    }
    redirect('/admin/setup.php#installation');
}
$activationFailed = ($_SESSION['installation_activation_failed'] ?? false) === true;
unset($_SESSION['installation_activation_failed']);
$facts = setup_collect($pdo, $actor);
$steps = setup_steps($facts);
$labels = setup_status_labels();
$checkTopics = ['database'=>'acceptance', 'https'=>'https', 'runtime'=>'security', 'storage'=>'storage',
    'workers'=>'workers', 'operator'=>'operator', 'catalog'=>'geography', 'mail'=>'mail', 'legal'=>'legal',
    'recovery'=>'backups', 'payment_gates'=>'providers'];
$pageTitle = t('setup.ui.title');
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page">
    <div class="page-heading"><p class="eyebrow"><?=h(t('setup.ui.eyebrow'))?></p><h1><?=h(t('setup.ui.heading'))?></h1><p><?=h(t('setup.ui.intro'))?></p></div>
    <div class="admin-layout"><?php include __DIR__.'/../includes/admin-nav.php'; ?><div class="admin-content">
        <section class="panel" id="installation" data-installation-state="<?=h($state)?>">
            <h2><?=h(t('setup.launch.title'))?></h2>
            <p><?=h(t('setup.launch.'.$state))?></p>
            <?php if ($activationFailed): ?><p class="notice error" role="alert"><?=h(t('setup.launch.recheck'))?></p><?php endif; ?>
            <form method="post" action="/admin/setup.php"><?=csrf_field()?>
                <button class="button secondary" name="action" value="run_checks"><?=h(t('setup.launch.run'))?></button>
            </form>
            <?php if (is_array($lastCheck)): ?>
                <p><?=h(t('setup.ui.checked_at', ['time'=>gmdate('c', $lastCheck['time'])]))?></p>
                <ul><?php foreach ($lastCheck['checks'] as $check=>$passed): ?>
                    <li data-installation-check="<?=h($check)?>" data-status="<?=$passed?'passed':'failed'?>">
                        <?php if (!$passed && isset($checkTopics[$check])): ?><a href="#setup-<?=h($checkTopics[$check])?>"><?=h(t('setup.check.'.$check))?></a>
                        <?php elseif (!$passed && $check === 'maintenance'): ?><a href="/admin/settings.php"><?=h(t('setup.check.maintenance'))?></a>
                        <?php else: ?><?=h(t('setup.check.'.$check))?><?php endif; ?>
                        · <strong><?=h(t($passed?'setup.launch.passed':'setup.launch.failed'))?></strong>
                    </li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
            <?php if ($state === 'pending' && setup_recent_success($lastCheck, time())): ?>
                <form method="post" action="/admin/setup.php" class="form-grid"><?=csrf_field()?>
                    <label class="checkbox"><input name="confirm" type="checkbox" value="1" required> <?=h(t('setup.launch.confirm'))?></label>
                    <button class="button" name="action" value="activate"><?=h(t('setup.launch.open'))?></button>
                </form>
            <?php endif; ?>
        </section>
        <section class="panel"><h2><?=h(t('setup.ui.current'))?></h2><p><?=h(t('setup.ui.checked_at', ['time'=>$facts['checked_at']]))?></p><p><?=h(t('setup.ui.status_help'))?></p><a class="button secondary" href="/admin/setup.php"><?=h(t('setup.ui.refresh'))?></a></section>
        <nav class="panel" aria-label="<?=h(t('setup.ui.nav'))?>"><ol><?php foreach ($steps as $step): ?><li><a href="#setup-<?=h($step['id'])?>"><?=h($step['title'])?></a> · <?=h($labels[$step['status']])?></li><?php endforeach; ?></ol></nav>
        <?php foreach ($steps as $index=>$step): ?>
            <section class="panel" id="setup-<?=h($step['id'])?>" aria-labelledby="heading-<?=h($step['id'])?>">
                <p class="eyebrow"><?=h(t('setup.ui.step', ['current'=>$index+1,'total'=>count($steps)]))?></p>
                <h2 id="heading-<?=h($step['id'])?>"><?=h($step['title'])?></h2>
                <p class="notice<?=$step['status']==='error'?' error':''?>"><strong><?=h($labels[$step['status']])?></strong></p>
                <?php if (isset($step['providers'])): ?><ul><?php foreach ($step['providers'] as $provider=>$status): ?>
                    <li data-provider="<?=h($provider)?>" data-status="<?=h($status)?>"><?=h(t('setup.provider.'.$provider))?> · <?=h($labels[$status])?></li>
                <?php endforeach; ?></ul><?php endif; ?>
                <p><?=h($step['detail'])?></p><p><strong><?=h(t('setup.ui.next'))?></strong> <?=h($step['next'])?></p>
                <?php if ($step['href'] !== null && rbac_has_permission($pdo, (int)$admin['id'], $step['permission'])): ?><a class="button secondary" href="<?=h($step['href'])?>"><?=h(t('setup.ui.settings', ['title'=>$step['title']]))?></a><?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div></div>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
