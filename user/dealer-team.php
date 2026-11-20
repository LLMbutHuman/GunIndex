<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';

header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$user = require_login($pdo);
$actor = (int)$user['id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    abort_request(405, t('account.method'));
}

$roles = ['viewer'=>'dealer_team.role_viewer', 'inventory'=>'dealer_team.role_inventory', 'operations'=>'dealer_team.role_operations'];
$statuses = [
    'invited'=>'dealer_team.status_invited',
    'active'=>'dealer_team.status_active',
    'revoked'=>'dealer_team.status_revoked',
    'declined'=>'dealer_team.status_declined',
    'expired'=>'dealer_team.status_expired',
];
$error = '';
if ($method === 'POST') {
    require_csrf();
    try {
        $action = post_string('action', 16);
        switch ($action) {
            case 'invite':
                dealer_team_invite($pdo, $actor, post_string('username', 50), post_string('role', 16));
                break;
            case 'role':
            case 'revoke':
                $member = trade_integer($_POST['member_id'] ?? null);
                $version = trade_integer($_POST['version'] ?? null);
                dealer_team_owner_change($pdo, $actor, $member, $version, $action, $action === 'role' ? post_string('role', 16) : null);
                break;
            case 'accept':
            case 'decline':
                $owner = trade_integer($_POST['owner_id'] ?? null);
                $version = trade_integer($_POST['version'] ?? null);
                dealer_team_respond($pdo, $actor, $owner, $version, $action === 'accept');
                break;
            default:
                throw new InvalidArgumentException();
        }
        $_SESSION['dealer_team_saved'] = true;
        redirect('/user/dealer-team.php');
    } catch (InvalidArgumentException) {
        http_response_code(422);
        $error = t('dealer_team.invalid');
    } catch (MarketAccessException) {
        http_response_code(403);
        $error = t('dealer_team.forbidden');
    } catch (DomainException $failure) {
        [$status, $error] = match ($failure->getMessage()) {
            t('dealer_team.changed') => [409, t('dealer_team.changed')],
            t('dealer_team.forbidden') => [403, t('dealer_team.forbidden')],
            t('dealer_team.unavailable') => [404, t('dealer_team.unavailable')],
            default => [422, t('dealer_team.invalid')],
        };
        http_response_code($status);
    } catch (Throwable $failure) {
        error_log('Dealer team update failed ('.get_class($failure).')');
        abort_request(503, t('dealer_team.unavailable'));
    }
}

try {
    $view = dealer_team_view($pdo, $actor);
} catch (Throwable $failure) {
    error_log('Dealer team could not be read ('.get_class($failure).')');
    abort_request(503, t('dealer_team.unavailable'));
}
$saved = $method === 'GET' && !empty($_SESSION['dealer_team_saved']);
if ($saved) unset($_SESSION['dealer_team_saved']);
$pageTitle = t('dealer_team.title');
include __DIR__.'/../includes/header.php';
?>
<main class="page" id="main-content">
  <div class="page-heading">
    <h1><?=h($pageTitle)?></h1>
    <p><?=h(t('dealer_team.intro'))?></p>
    <div class="button-row"><a href="/user/dashboard.php"><?=h(t('Zum Dashboard'))?></a><a href="/user/dealer-stock.php"><?=h(t('dealer_stock.title'))?></a></div>
  </div>
  <?php if ($saved): ?><p class="notice success" role="status"><?=h(t('dealer_team.saved'))?></p><?php endif;?>
  <?php if ($error !== ''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?>

  <?php if ($view['owned']): ?>
    <section class="panel" aria-labelledby="team-invite">
      <h2 id="team-invite"><?=h(t('dealer_team.invite_title'))?></h2>
      <p><?=h(t('dealer_team.invite_help'))?></p>
      <form method="post" action="/user/dealer-team.php" class="form-grid">
        <?=csrf_field()?>
        <label class="field">
          <?=h(t('dealer_team.username'))?>
          <input name="username" minlength="3" maxlength="50" autocomplete="off" required>
        </label>
        <label class="field">
          <?=h(t('dealer_team.role'))?>
          <select name="role" aria-describedby="team-role-help" required>
            <?php foreach ($roles as $role => $label): ?><option value="<?=h($role)?>"><?=h(t($label))?></option><?php endforeach;?>
          </select>
        </label>
        <p id="team-role-help"><?=h(t('dealer_team.role_help'))?></p>
        <button class="button" type="submit" name="action" value="invite"><?=h(t('dealer_team.invite'))?></button>
      </form>
    </section>

    <section aria-labelledby="team-members">
      <h2 id="team-members"><?=h(t('dealer_team.owner_title'))?></h2>
      <?php if (!$view['members']): ?><p><?=h(t('dealer_team.members_empty'))?></p><?php endif;?>
      <?php foreach ($view['members'] as $member): ?>
        <article class="panel" data-dealer-member="<?=(int)$member['member_id']?>" data-dealer-status="<?=h($member['status'])?>">
          <h3><?=h($member['username'])?></h3>
          <dl class="detail-facts">
            <div><dt><?=h(t('dealer_team.role'))?></dt><dd><?=h(t($roles[$member['role']] ?? 'dealer_team.status_unknown'))?></dd></div>
            <div><dt><?=h(t('dealer_team.status'))?></dt><dd><?=h(t($statuses[$member['status']] ?? 'dealer_team.status_unknown'))?></dd></div>
            <?php if ($member['status'] === 'invited' && $member['expires_at'] !== null): ?>
              <div><dt><?=h(t('dealer_team.expires'))?></dt><dd><time datetime="<?=h(str_replace(' ', 'T', $member['expires_at']).'Z')?>"><?=h($member['expires_at'])?> UTC</time></dd></div>
            <?php endif;?>
          </dl>
          <?php if (in_array($member['status'], ['invited','active'], true)): ?>
            <form method="post" action="/user/dealer-team.php" class="form-grid">
              <?=csrf_field()?>
              <input type="hidden" name="member_id" value="<?=(int)$member['member_id']?>">
              <input type="hidden" name="version" value="<?=(int)$member['version']?>">
              <?php if ($member['status'] === 'active'): ?>
                <label class="field">
                  <?=h(t('dealer_team.role'))?>
                  <select name="role" aria-describedby="team-role-help" required>
                    <?php foreach ($roles as $role => $label): ?>
                      <option value="<?=h($role)?>"<?= $role === $member['role'] ? ' selected' : ''?>><?=h(t($label))?></option>
                    <?php endforeach;?>
                  </select>
                </label>
              <?php endif;?>
              <div class="button-row">
                <?php if ($member['status'] === 'active'): ?><button class="button secondary" type="submit" name="action" value="role"><?=h(t('dealer_team.change_role'))?></button><?php endif;?>
                <button class="button secondary" type="submit" name="action" value="revoke"><?=h(t($member['status'] === 'invited' ? 'dealer_team.withdraw' : 'dealer_team.revoke'))?></button>
              </div>
            </form>
          <?php endif;?>
        </article>
      <?php endforeach;?>
    </section>
  <?php endif;?>

  <section aria-labelledby="team-memberships">
    <h2 id="team-memberships"><?=h(t('dealer_team.teams_title'))?></h2>
    <?php if (!$view['teams']): ?><p><?=h(t('dealer_team.teams_empty'))?></p><?php endif;?>
    <?php foreach ($view['teams'] as $team): ?>
      <article class="panel" data-dealer-owner="<?=(int)$team['owner_id']?>" data-dealer-status="<?=h($team['status'])?>">
        <h3><?=h($team['company_name'])?></h3>
        <dl class="detail-facts">
          <div><dt><?=h(t('dealer_team.role'))?></dt><dd><?=h(t($roles[$team['role']] ?? 'dealer_team.status_unknown'))?></dd></div>
          <div><dt><?=h(t('dealer_team.status'))?></dt><dd><?=h(t($statuses[$team['status']] ?? 'dealer_team.status_unknown'))?></dd></div>
          <?php if ($team['status'] === 'invited' && $team['expires_at'] !== null): ?>
            <div><dt><?=h(t('dealer_team.expires'))?></dt><dd><time datetime="<?=h(str_replace(' ', 'T', $team['expires_at']).'Z')?>"><?=h($team['expires_at'])?> UTC</time></dd></div>
          <?php endif;?>
        </dl>
        <?php if ($team['status'] === 'invited'): ?>
          <p><?=h(t('dealer_team.respond_help'))?></p>
          <form method="post" action="/user/dealer-team.php" class="button-row">
            <?=csrf_field()?>
            <input type="hidden" name="owner_id" value="<?=(int)$team['owner_id']?>">
            <input type="hidden" name="version" value="<?=(int)$team['version']?>">
            <button class="button" type="submit" name="action" value="accept"><?=h(t('dealer_team.accept'))?></button>
            <button class="button secondary" type="submit" name="action" value="decline"><?=h(t('dealer_team.decline'))?></button>
          </form>
        <?php endif;?>
      </article>
    <?php endforeach;?>
  </section>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
