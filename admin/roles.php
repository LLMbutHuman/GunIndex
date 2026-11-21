<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/governance.php';

header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$admin = require_permission($pdo,'roles.manage');
$pageTitle = t('admin_roles.title');
$error = '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    require_csrf();
    try {
        $action = post_string('action');
        if (!in_array($action,['grant','revoke'],true)) throw new InvalidArgumentException(t('admin_roles.error_action'));
        governance_role_change($pdo,(int)$admin['id'],(int)post_string('user_id'),post_string('role'),$action==='grant');
        $_SESSION['admin_roles_saved'] = true;
        redirect('/admin/roles.php');
    } catch (InvalidArgumentException|DomainException $failure) {
        http_response_code(422);
        $message = $failure->getMessage();
        $key = match ($message) {
            'Unbekannte Rolle.' => 'admin_roles.error_role',
            'Das eigene oder ein initiales Administrationskonto wird hier nicht verändert.' => 'admin_roles.error_protected_account',
            'Das Zielkonto benötigt vor einer Personalrolle eine eingerichtete Zwei-Faktor-Anmeldung.' => 'admin_roles.error_target_mfa',
            'Die erforderliche Berechtigung fehlt.' => 'admin_roles.error_permission',
            'Eine aktuelle Mehrfaktoranmeldung ist erforderlich.' => 'admin_roles.error_current_mfa',
            default => null,
        };
        $error = $key === null ? $message : t($key);
    }
}
$rolesSaved = $method === 'GET' && ($_SESSION['admin_roles_saved'] ?? false) === true;
if ($rolesSaved) unset($_SESSION['admin_roles_saved']);

$roles = governance_roles();
$roleLabel = static function(string $role):string {
    $key = 'admin_roles.role_'.$role;
    $label = t($key);
    return $label === $key ? $role : $label;
};
$permissionLabel = static function(string $permission):string {
    $key = 'admin_roles.permission_'.str_replace('.','_',$permission);
    $label = t($key);
    return $label === $key ? $permission : $label;
};
$users = $pdo->query('SELECT id,username,is_admin,is_active,mfa_secret IS NOT NULL AS has_mfa FROM users ORDER BY id DESC LIMIT 100')->fetchAll();
$grants = $pdo->query('SELECT g.user_id,g.role_code,u.username FROM market_role_grants g JOIN users u ON u.id=g.user_id ORDER BY u.username,g.role_code LIMIT 500')->fetchAll();
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page">
  <div class="page-heading">
    <h1><?=h(t('admin_roles.heading'))?></h1>
    <p><?=h(t('admin_roles.intro'))?></p>
  </div>
  <div class="admin-layout">
    <?php include __DIR__.'/../includes/admin-nav.php'; ?>
    <div class="admin-content">
      <?php if ($error !== ''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif; ?>
      <?php if ($rolesSaved): ?><p class="notice success" role="status"><?=h(t('admin_roles.saved'))?></p><?php endif; ?>
      <section class="panel">
        <h2><?=h(t('admin_roles.manage'))?></h2>
        <form method="post" class="form-grid">
          <?=csrf_field()?>
          <label class="field"><?=h(t('admin_roles.user_id'))?><input name="user_id" inputmode="numeric" pattern="[0-9]+" required></label>
          <label class="field"><?=h(t('admin_roles.role'))?>
            <select name="role">
              <?php foreach ($roles as $roleCode=>$role): ?><option value="<?=h($roleCode)?>"><?=h($roleLabel($roleCode))?></option><?php endforeach; ?>
            </select>
          </label>
          <label class="field"><?=h(t('admin_roles.action'))?>
            <select name="action"><option value="grant"><?=h(t('admin_roles.grant'))?></option><option value="revoke"><?=h(t('admin_roles.revoke'))?></option></select>
          </label>
          <button class="button" type="submit"><?=h(t('admin_roles.change'))?></button>
        </form>
        <p><?=h(t('admin_roles.protected_accounts'))?></p>
      </section>
      <section class="panel">
        <h2><?=h(t('admin_roles.permissions'))?></h2>
        <?php foreach ($roles as $roleCode=>$role): ?>
          <details>
            <summary><?=h($roleLabel($roleCode))?></summary>
            <ul>
              <?php foreach ($role['permissions'] as $permission): ?>
                <li><?=h($permissionLabel($permission))?> · <code><?=h($permission)?></code></li>
              <?php endforeach; ?>
            </ul>
          </details>
        <?php endforeach; ?>
      </section>
      <section class="panel">
        <h2><?=h(t('admin_roles.assignments'))?></h2>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th><?=h(t('admin_roles.account'))?></th><th><?=h(t('admin_roles.role'))?></th></tr></thead>
          <tbody>
            <?php foreach ($grants as $grant): ?>
              <tr><td><?=h($grant['username'])?> #<?=(int)$grant['user_id']?></td><td><?=h($roleLabel($grant['role_code']))?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      </section>
      <section class="panel">
        <h2><?=h(t('admin_roles.recent_accounts'))?></h2>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>ID</th><th><?=h(t('admin_roles.name'))?></th><th>MFA</th><th><?=h(t('admin_roles.status'))?></th></tr></thead>
          <tbody>
            <?php foreach ($users as $row): ?>
              <tr>
                <td><?=(int)$row['id']?></td><td><?=h($row['username'])?></td>
                <td><?=h(t($row['has_mfa'] ? 'admin_roles.mfa_present' : 'admin_roles.mfa_missing'))?></td>
                <td><?=h(t($row['is_active'] ? 'admin_roles.active' : 'admin_roles.disabled'))?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      </section>
    </div>
  </div>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
