<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';

header('Cache-Control: private, no-store, max-age=0');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$user = require_login($pdo);
$owner = (int)$user['id'];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    abort_request(405, t('account.method'));
}
if ($method === 'POST') require_csrf();

$scopeLabels = [
    'inventory.read' => 'dealer_api.inventory_read',
    'inventory.write' => 'dealer_api.inventory_write',
    'orders.read' => 'dealer_api.orders_read',
];
try {
    $keys = dealer_api_keys($pdo, $owner);
    $dealerApiPublished = business_dealer_profile($pdo, $owner) !== null;
} catch (DomainException $failure) {
    if ($failure->getMessage() === t('dealer_api.forbidden')) abort_request(403, t('dealer_api.forbidden'));
    abort_request(404, t('dealer_api.unavailable'));
} catch (Throwable $failure) {
    error_log('Dealer API key list failed ('.get_class($failure).')');
    abort_request(503, t('dealer_api.unavailable'));
}
if (!$dealerApiPublished && !$keys) abort_request(403, t('dealer_api.forbidden'));
$dealerApiCanIssue = $dealerApiPublished && dealer_api_available();

$secret = null;
$error = '';
if ($method === 'POST') {
    try {
        $action = post_string('action', 16);
        if ($action === 'revoke') {
            dealer_api_revoke($pdo, $owner, trade_integer($_POST['key_id'] ?? null));
            $_SESSION['dealer_api_key_revoked'] = true;
            redirect('/user/dealer-api-keys.php');
        }
        if ($action !== 'issue') throw new InvalidArgumentException(t('dealer_api.invalid'));
        if (!$dealerApiCanIssue) throw new DomainException(t('dealer_api.unavailable'));

        auth_rate_limit('dealer-api-key-create', (string)$owner, 5, 3600);
        $scopes = $_POST['scopes'] ?? [];
        if (!is_array($scopes) || !array_is_list($scopes) || $scopes === [] || count($scopes) > count($scopeLabels)) {
            throw new InvalidArgumentException(t('dealer_api.invalid'));
        }
        foreach ($scopes as $scope) {
            if (!is_string($scope) || !isset($scopeLabels[$scope])) throw new InvalidArgumentException(t('dealer_api.invalid'));
        }
        $secret = dealer_api_issue($pdo, $owner, post_string('label', 320), $scopes, post_string('password', 1024), post_string('request_key', 32));
        http_response_code(201);
    } catch (MarketAccessException) {
        http_response_code(403);
        $error = t('dealer_api.forbidden');
    } catch (InvalidArgumentException) {
        http_response_code(422);
        $error = t('dealer_api.invalid');
    } catch (DomainException $failure) {
        [$status, $error] = match ($failure->getMessage()) {
            t('dealer_api.forbidden') => [403, t('dealer_api.forbidden')],
            t('dealer_api.unavailable') => [404, t('dealer_api.unavailable')],
            t('dealer_api.duplicate') => [409, t('dealer_api.duplicate')],
            default => [422, t('dealer_api.invalid')],
        };
        http_response_code($status);
    } catch (Throwable $failure) {
        error_log('Dealer API key update failed ('.get_class($failure).')');
        abort_request(503, t('dealer_api.unavailable'));
    }
}

$revoked = $method === 'GET' && !empty($_SESSION['dealer_api_key_revoked']);
if ($method === 'GET') unset($_SESSION['dealer_api_key_revoked']);
$pageTitle = t('dealer_api.title');
include __DIR__.'/../includes/header.php';
?>
<main class="page" id="main-content">
  <div class="page-heading">
    <h1><?=h($pageTitle)?></h1>
    <div class="button-row">
      <a href="/user/dealer.php"><?=h(t('dashboard.dealer'))?></a>
      <a href="/user/dealer-stock.php"><?=h(t('dealer_stock.title'))?></a>
    </div>
  </div>

  <?php if ($secret !== null): ?>
    <section class="panel" aria-labelledby="dealer-api-issued">
      <h2 id="dealer-api-issued"><?=h(t('dealer_api.issued'))?></h2>
      <p><?=h(t('dealer_api.secret_help'))?></p>
      <p><?=h(t('dealer_api.auth_help'))?> <code>Authorization: Bearer &lt;token&gt;</code></p>
      <label class="field">
        <?=h(t('dealer_api.secret'))?>
        <textarea id="dealer-api-token" rows="2" dir="ltr" readonly spellcheck="false" autocomplete="off"><?=h($secret)?></textarea>
      </label>
      <a class="button" href="/user/dealer-api-keys.php"><?=h(t('dealer_api.back'))?></a>
    </section>
  <?php else: ?>
    <?php if ($revoked): ?><p class="notice success" role="status"><?=h(t('dealer_api.revoked'))?></p><?php endif;?>
    <?php if ($error !== ''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?>

    <?php if ($dealerApiCanIssue): ?>
    <section class="panel" aria-labelledby="dealer-api-issue">
      <h2 id="dealer-api-issue"><?=h(t('dealer_api.issue_title'))?></h2>
      <p><?=h(t('dealer_api.issue_help'))?></p>
      <p><?=h(t('dealer_api.endpoint'))?> <code>/api/dealer/v1.php</code></p>
      <form method="post" action="/user/dealer-api-keys.php" class="form-grid">
        <?=csrf_field()?>
        <input type="hidden" name="request_key" value="<?=h(bin2hex(random_bytes(16)))?>">
        <label class="field">
          <?=h(t('dealer_api.label'))?>
          <input name="label" maxlength="80" required>
        </label>
        <label class="field">
          <?=h(t('Aktuelles Passwort'))?>
          <input name="password" type="password" autocomplete="current-password" maxlength="1024" required>
        </label>
        <fieldset class="full">
          <legend><?=h(t('dealer_api.scopes'))?></legend>
          <?php foreach ($scopeLabels as $scope => $labelKey): ?>
            <p><label><input type="checkbox" name="scopes[]" value="<?=h($scope)?>"> <?=h(t($labelKey))?></label></p>
          <?php endforeach;?>
        </fieldset>
        <button class="button" type="submit" name="action" value="issue"><?=h(t('dealer_api.issue'))?></button>
      </form>
    </section>
    <?php else: ?><p class="notice"><?=h(t('dealer_api.unavailable'))?></p><?php endif;?>

    <section aria-labelledby="dealer-api-keys">
      <h2 id="dealer-api-keys"><?=h(t('dealer_api.keys'))?></h2>
      <?php if (!$keys): ?><p><?=h(t('dealer_api.empty'))?></p><?php endif;?>
      <?php foreach ($keys as $key): ?>
        <?php
        $active = $key['revoked_at'] === null && strtotime($key['expires_at'].' UTC') > time();
        $statusKey = $active ? 'dealer_team.status_active' : ($key['revoked_at'] === null ? 'dealer_team.status_expired' : 'dealer_team.status_revoked');
        $permissionNames = [];
        foreach ($key['scopes'] as $scope) {
            if (isset($scopeLabels[$scope])) $permissionNames[] = t($scopeLabels[$scope]);
        }
        ?>
        <article class="panel" data-dealer-api-key="<?=(int)$key['id']?>">
          <h3><?=h($key['label'])?></h3>
          <dl class="detail-facts">
            <div><dt><?=h(t('dealer_team.status'))?></dt><dd><?=h(t($statusKey))?></dd></div>
            <div><dt><?=h(t('dealer_api.scopes'))?></dt><dd><?=h(implode(', ', $permissionNames))?></dd></div>
            <div><dt><?=h(t('dealer_api.created'))?></dt><dd><time datetime="<?=h(str_replace(' ', 'T', $key['created_at']).'Z')?>"><?=h($key['created_at'])?> UTC</time></dd></div>
            <div><dt><?=h(t('dealer_team.expires'))?></dt><dd><time datetime="<?=h(str_replace(' ', 'T', $key['expires_at']).'Z')?>"><?=h($key['expires_at'])?> UTC</time></dd></div>
          </dl>
          <?php if ($active): ?>
            <form method="post" action="/user/dealer-api-keys.php">
              <?=csrf_field()?>
              <input type="hidden" name="key_id" value="<?=(int)$key['id']?>">
              <button class="button secondary" type="submit" name="action" value="revoke"><?=h(t('dealer_api.revoke'))?></button>
            </form>
          <?php endif;?>
        </article>
      <?php endforeach;?>
    </section>
  <?php endif;?>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
