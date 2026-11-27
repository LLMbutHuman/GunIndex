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
    if ($action === 'save_step') {
        $step = $_POST['step'] ?? null;
        if (!is_string($step) || !in_array($step, array_column(setup_steps([]), 'id'), true)) abort_request(422, t('account.invalid_input'));
        setup_wizard_checkpoint($pdo, $actor, $step);
        redirect('/admin/setup.php?step='.rawurlencode($step).'#setup-'.$step);
    } elseif ($action === 'run_checks') {
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
    setup_wizard_checkpoint($pdo, $actor, 'acceptance');
    redirect('/admin/setup.php?step=acceptance#installation');
}
$activationFailed = ($_SESSION['installation_activation_failed'] ?? false) === true;
unset($_SESSION['installation_activation_failed']);
$facts = setup_collect($pdo, $actor);
$steps = setup_steps($facts);
$labels = setup_status_labels();
$checkpoint = setup_wizard_checkpoint($pdo, $actor);
$activeStep = setup_wizard_pick($steps, $_GET['step'] ?? null, $checkpoint);
$production = getenv('APP_ENV', true) === 'production';
$commands = setup_wizard_commands($production);
$completed = count(array_filter($steps, static fn(array $step): bool => in_array($step['status'], ['configured','tested'], true)));
$stepUrl = static fn(string $step): string => '/admin/setup.php?step='.rawurlencode($step).'#setup-'.$step;
$actorPermissions = rbac_permissions($pdo, $actor);
$checkTopics = ['database'=>'acceptance', 'https'=>'https', 'runtime'=>'security', 'storage'=>'storage',
    'workers'=>'workers', 'operator'=>'operator', 'catalog'=>'geography', 'mail'=>'mail', 'legal'=>'legal',
    'recovery'=>'backups', 'payment_gates'=>'providers'];
$pageTitle = t('setup.ui.title');
include __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/admin-setup.css">
<script src="/assets/js/admin-setup.js" defer></script>
<main class="container" id="setup-wizard">
    <div class="page-heading"><p class="eyebrow"><?=h(t('setup.ui.eyebrow'))?></p><h1><?=h(t('setup.ui.heading'))?></h1><p><?=h(t('setup.ui.intro'))?></p></div>
    <div class="setup-layout">
    <aside class="setup-sidebar">
        <div class="panel"><a href="/admin/index.php"><?=h(t('setup.wizard.dashboard'))?></a>
            <p class="notice"><?=h(t($production?'setup.wizard.production':'setup.wizard.test'))?></p>
            <p><?=h(t('setup.wizard.progress', ['completed'=>$completed,'total'=>count($steps)]))?></p>
            <a href="<?=h($stepUrl('acceptance'))?>"><?=h(t('setup.launch.title'))?></a>
        </div>
        <details class="panel setup-step-list"><summary><?=h(t('setup.ui.nav'))?></summary>
            <nav aria-label="<?=h(t('setup.ui.nav'))?>"><ol>
                <?php foreach ($steps as $step): ?><li>
                    <a href="<?=h($stepUrl($step['id']))?>"<?=$step['id']===$activeStep?' aria-current="step"':''?>><?=h($step['title'])?></a>
                    <span class="setup-step-state"><?=h($labels[$step['status']])?></span>
                </li><?php endforeach; ?>
            </ol></nav>
        </details>
    </aside>
    <div class="setup-main">
        <section class="panel" id="installation" data-installation-state="<?=h($state)?>"<?=$activeStep==='acceptance'?'':' hidden'?>>
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
                        <?php if (!$passed && isset($checkTopics[$check])): ?><a href="<?=h($stepUrl($checkTopics[$check]))?>"><?=h(t('setup.check.'.$check))?></a>
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
        <?php foreach ($steps as $index=>$step): ?>
            <section class="panel" id="setup-<?=h($step['id'])?>" aria-labelledby="heading-<?=h($step['id'])?>"<?=$step['id']===$activeStep?'':' hidden'?>>
                <p class="eyebrow"><?=h(t('setup.ui.step', ['current'=>$index+1,'total'=>count($steps)]))?></p>
                <h2 id="heading-<?=h($step['id'])?>"><?=h($step['title'])?></h2>
                <p class="notice<?=$step['status']==='error'?' error':''?>"><strong><?=h($labels[$step['status']])?></strong></p>
                <?php if (isset($step['providers'])): ?><ul><?php foreach ($step['providers'] as $provider=>$status): ?>
                    <li data-provider="<?=h($provider)?>" data-status="<?=h($status)?>"><?=h(t('setup.provider.'.$provider))?> · <?=h($labels[$status])?></li>
                <?php endforeach; ?></ul><?php endif; ?>
                <p><?=h($step['detail'])?></p><p><strong><?=h(t('setup.ui.next'))?></strong> <?=h($step['next'])?></p>
                <?php if ($step['href'] !== null && (in_array('*',$actorPermissions,true)||in_array($step['permission'],$actorPermissions,true))): ?>
                    <p><a class="button secondary" href="<?=h($step['href'])?>"><?=h(t('setup.ui.settings', ['title'=>$step['title']]))?></a></p>
                <?php endif; ?>
                <?php if (isset($commands[$step['id']])): ?>
                    <p><?=h(t($production?'setup.wizard.server_terminal':'setup.wizard.test_terminal'))?></p>
                    <?php foreach ($commands[$step['id']] as $command): ?>
                        <div class="setup-command">
                            <pre><code><?=h($command)?></code></pre>
                            <button type="button" class="button secondary" data-setup-copy hidden><?=h(t('setup.wizard.copy'))?></button>
                            <span role="status" data-copied="<?=h(t('setup.wizard.copied'))?>" data-copy-failed="<?=h(t('setup.wizard.copy_failed'))?>"></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <p class="muted"><?=h(t('setup.ui.checked_at', ['time'=>$facts['checked_at']]))?> · <a href="<?=h($stepUrl($step['id']))?>"><?=h(t('setup.ui.refresh'))?></a></p>
                <form method="post" action="/admin/setup.php" class="setup-navigation">
                    <?=csrf_field()?><input type="hidden" name="action" value="save_step">
                    <?php if ($index>0): ?><button class="button secondary" name="step" value="<?=h($steps[$index-1]['id'])?>"><?=h(t('setup.wizard.back'))?></button><?php endif; ?>
                    <button class="button secondary" name="step" value="<?=h($step['id'])?>"><?=h(t('setup.wizard.resume_here'))?></button>
                    <?php if ($index+1<count($steps)): ?><button class="button" name="step" value="<?=h($steps[$index+1]['id'])?>"><?=h(t('setup.wizard.next'))?></button><?php endif; ?>
                </form>
            </section>
        <?php endforeach; ?>
    </div></div>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
