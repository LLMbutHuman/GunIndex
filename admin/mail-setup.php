<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';

header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$admin = require_permission($pdo, 'site.manage');
$actor = (int)$admin['id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    abort_request(405, t('account.method'));
}

$notice = '';
if ($method === 'GET') {
    $notice = $_SESSION['mail_setup_notice'] ?? '';
    unset($_SESSION['mail_setup_notice']);
}
$error = '';
$dns = null;
if ($method === 'POST') {
    require_csrf();
    $action = '';
    try {
        $action = post_string('action', 16);
        if ($action === 'save') {
            $fields = [];
            foreach (['mode', 'from', 'host', 'port', 'username', 'password', 'selector', 'revision'] as $name) {
                $fields[$name] = post_string($name);
            }
            mail_setup_save($pdo, $actor, $fields);
            $_SESSION['mail_setup_notice'] = t('mail_setup.saved');
            redirect('/admin/mail-setup.php');
        } elseif ($action === 'dns') {
            $dns = mail_setup_dns($pdo);
        } elseif ($action === 'test') {
            if (post_string('test_confirmed', 1) !== '1') {
                throw new InvalidArgumentException(t('mail_setup.test_confirmation_required'));
            }
            mail_setup_test($pdo, $actor);
            $_SESSION['mail_setup_notice'] = t('mail_setup.test_queued');
            redirect('/admin/mail-setup.php#mail-test');
        } else {
            throw new InvalidArgumentException(t('mail_setup.invalid_config'));
        }
    } catch (Throwable $failure) {
        $message = $failure->getMessage();
        if ($message === t('mail_setup.stale')) {
            http_response_code(409);
            $error = t('mail_setup.stale');
        } elseif ($message === t('account.rate_limit')) {
            http_response_code(429);
            $error = t('account.rate_limit');
        } elseif (in_array($message, [t('mail_setup.not_ready'), t('mail_setup.test_confirmation_required')], true)) {
            http_response_code(422);
            $error = $message;
        } elseif ($failure instanceof InvalidArgumentException || $failure instanceof DomainException) {
            http_response_code(422);
            $error = t($action === 'test' ? 'mail_setup.send_failed' : 'mail_setup.invalid_config');
        } else {
            http_response_code(503);
            $error = t(match ($action) {
                'test' => 'mail_setup.send_failed',
                'dns' => 'mail_setup.dns_failed',
                default => 'mail_setup.unavailable',
            });
            error_log('Mail setup request failed ('.get_class($failure).')');
        }
    }
}

try {
    $settings = mail_setup_view($pdo);
    $transportReason = mail_transport_reason($pdo);
    $test = mail_setup_test_status($pdo, $actor);
} catch (Throwable $failure) {
    error_log('Mail setup could not be read ('.get_class($failure).')');
    abort_request(503, t('mail_setup.unavailable'));
}
$transportLabel = match ($transportReason) {
    null => 'mail_setup.ready',
    'Mail delivery is not configured.' => 'mail_setup.disabled',
    'A valid sender address is required.' => 'mail_setup.sender_required',
    'SMTP transport dependencies are unavailable.' => 'mail_setup.smtp_unavailable',
    'Local sendmail transport is unavailable.' => 'mail_setup.sendmail_unavailable',
    'Test transport is restricted to the isolated development database.' => 'mail_setup.test_unavailable',
    default => 'mail_setup.not_ready',
};
$modes = ['off', 'sendmail', 'smtp'];
$dnsStatuses = ['ok', 'missing', 'invalid', 'error'];
$testStatuses = ['pending', 'processing', 'delivered', 'failed', 'expired', 'cancelled'];
$pageTitle = t('mail_setup.admin_title');
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page">
  <div class="page-heading"><h1><?=h($pageTitle)?></h1></div>
  <div class="admin-layout">
    <?php include __DIR__.'/../includes/admin-nav.php';?>
    <div class="admin-content">
      <?php if ($error !== ''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?>
      <?php if ($notice !== ''): ?><p class="notice success" role="status"><?=h($notice)?></p><?php endif;?>

      <section class="panel">
        <h2><?=h(t('mail_setup.transport'))?></h2>
        <p><?=h(t($transportLabel))?></p>
        <form method="post" action="/admin/mail-setup.php" class="form-grid">
          <?=csrf_field()?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="revision" value="<?=h($settings['revision'])?>">
          <label class="field"><?=h(t('mail_setup.mode'))?>
            <select name="mode" required>
              <?php foreach ($modes as $mode): ?>
                <option value="<?=h($mode)?>"<?=$settings['mode'] === $mode ? ' selected' : ''?>><?=h(t('mail_setup.mode_'.$mode))?></option>
              <?php endforeach;?>
            </select>
          </label>
          <label class="field"><?=h(t('mail_setup.sender'))?>
            <input name="from" type="email" value="<?=h($settings['from'])?>" maxlength="190" autocomplete="off">
          </label>
          <label class="field"><?=h(t('mail_setup.selector'))?>
            <input name="selector" type="text" value="<?=h($settings['selector'])?>" maxlength="63" autocomplete="off" autocapitalize="none" spellcheck="false">
          </label>
          <p class="span-2"><?=h(t('mail_setup.relay_help'))?></p>
          <label class="field"><?=h(t('mail_setup.host'))?>
            <input name="host" type="text" value="<?=h($settings['host'])?>" maxlength="253" autocomplete="off" autocapitalize="none" spellcheck="false">
          </label>
          <label class="field"><?=h(t('mail_setup.port'))?>
            <select name="port">
              <option value="587"<?= (string)$settings['port'] === '465' ? '' : ' selected'?>>587 (STARTTLS)</option>
              <option value="465"<?= (string)$settings['port'] === '465' ? ' selected' : ''?>>465 (TLS)</option>
            </select>
          </label>
          <label class="field"><?=h(t('mail_setup.username'))?>
            <input name="username" type="text" value="<?=h($settings['username'])?>" autocomplete="off" autocapitalize="none" spellcheck="false">
          </label>
          <label class="field"><?=h(t('mail_setup.password'))?>
            <input name="password" type="password" autocomplete="new-password" aria-describedby="mail-password-help">
            <small id="mail-password-help"><?=h(t($settings['password_set'] ? 'mail_setup.password_keep' : 'mail_setup.password_unset'))?></small>
          </label>
          <button class="button" type="submit"><?=h(t('mail_setup.save'))?></button>
        </form>
      </section>

      <section class="panel" id="mail-dns">
        <h2><?=h(t('mail_setup.dns'))?></h2>
        <p><?=h(t('mail_setup.dns_help'))?></p>
        <form method="post" action="/admin/mail-setup.php#mail-dns">
          <?=csrf_field()?>
          <input type="hidden" name="action" value="dns">
          <button class="button secondary" type="submit"><?=h(t('mail_setup.dns_button'))?></button>
        </form>
        <?php if ($dns !== null): ?>
          <dl aria-live="polite">
            <dt><?=h(t('mail_setup.domain'))?></dt>
            <dd><?=h($dns['domain'])?></dd>
            <?php foreach (['spf' => 'SPF', 'dkim' => 'DKIM', 'dmarc' => 'DMARC'] as $name => $label):
                $status = in_array($dns[$name] ?? null, $dnsStatuses, true) ? $dns[$name] : 'error';
            ?>
              <dt><?=h($label)?></dt>
              <dd><?=h(t('mail_setup.dns_'.$status))?></dd>
            <?php endforeach;?>
          </dl>
        <?php endif;?>
      </section>

      <section class="panel" id="mail-test">
        <h2><?=h(t('mail_setup.test'))?></h2>
        <p><?=h(t('mail_setup.test_help'))?></p>
        <?php if ($test !== null):
            $status = in_array($test['status'], $testStatuses, true) ? $test['status'] : 'unknown';
            $synthetic = (bool)($test['synthetic'] ?? false);
            $statusLabel = $status === 'delivered' && $synthetic ? 'test_simulated' : 'test_'.$status;
        ?>
          <?php if ($synthetic): ?><p><?=h(t('mail_setup.test_synthetic'))?></p><?php endif;?>
          <dl>
            <dt><?=h(t('mail_setup.test_status'))?></dt>
            <dd><?=h(t('mail_setup.'.$statusLabel))?></dd>
            <dt><?=h(t('mail_setup.test_created'))?></dt>
            <dd><time datetime="<?=h(str_replace(' ', 'T', $test['created_at']).'Z')?>"><?=h($test['created_at'])?> UTC</time></dd>
            <?php if ((int)($test['attempts'] ?? 0) > 0): ?>
              <dt><?=h(t('mail_setup.test_attempts'))?></dt>
              <dd><?=(int)$test['attempts']?></dd>
            <?php endif;?>
          </dl>
          <?php if ($test['retrying'] ?? false): ?><p><?=h(t('mail_setup.test_retrying'))?></p><?php endif;?>
          <a href="/admin/mail-setup.php#mail-test"><?=h(t('mail_setup.refresh'))?></a>
        <?php endif;?>
        <?php if ($transportReason === null): ?>
          <form method="post" action="/admin/mail-setup.php#mail-test" class="form-grid">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="test">
            <label><input type="checkbox" name="test_confirmed" value="1" required> <?=h(t('mail_setup.test_confirmation', ['email' => $admin['email']]))?></label>
            <button class="button" type="submit"><?=h(t('mail_setup.test_button'))?></button>
          </form>
        <?php endif;?>
      </section>
    </div>
  </div>
</main>
<?php include __DIR__.'/../includes/footer.php';?>
