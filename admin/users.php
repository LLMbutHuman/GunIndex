<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$admin = require_permission($pdo,'users.manage');
header('Cache-Control: private, no-store');
$pageTitle = t('admin_nav.users');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','HEAD','POST'], true) || isset($_GET['action']) || isset($_GET['delete_id'])) {
 header('Allow: GET, HEAD, POST');
 abort_request(405, t('admin_users.method'));
}
if ($method === 'POST') {
 require_csrf();
 $id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
 if ($id === false || ($_POST['action'] ?? null) !== 'revoke_sessions') abort_request(422, t('admin_users.invalid_action'));
 if ($id === (int)$admin['id']) abort_request(422, t('admin_users.own_sessions'));
 $pdo->beginTransaction();
 try {
  governance_assert_actor($pdo,(int)$admin['id'],'users.manage');
  $statement = $pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
  $statement->execute([$id]);
  if (!$statement->fetchColumn()) {
   $pdo->rollBack();
   abort_request(404, t('admin_users.missing'));
  }
  $pdo->prepare('UPDATE users SET auth_version=auth_version+1 WHERE id=?')->execute([$id]);
  audit_log($pdo, (int)$admin['id'], 'user.sessions_revoked', $id);
  $pdo->commit();
  $_SESSION['admin_users_revoked'] = true;
  redirect('/admin/users.php');
 } catch (Throwable $exception) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  throw $exception;
 }
}
$sessionsRevoked = $method === 'GET' && ($_SESSION['admin_users_revoked'] ?? false) === true;
if ($sessionsRevoked) unset($_SESSION['admin_users_revoked']);
$page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]) ?: 1;
$total = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$page = min($page, max(1, (int)ceil($total / 30)));
$statement = $pdo->prepare('SELECT id, username, email, city, is_admin, created_at FROM users ORDER BY id DESC LIMIT 30 OFFSET ?');
$statement->bindValue(1, ($page - 1) * 30, PDO::PARAM_INT);
$statement->execute();
$users = $statement->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<main class="container admin-page">
    <div class="page-heading">
        <h1><?=h($pageTitle)?></h1>
        <p><?=h(t('admin_users.total', ['count'=>$total]))?></p>
    </div>
    <div class="admin-layout">
        <?php include __DIR__.'/../includes/admin-nav.php';?>
        <div class="admin-content">
            <?php if ($sessionsRevoked):?><p class="notice success" role="status"><?=h(t('admin_users.revoked'))?></p><?php endif;?>
            <section class="panel">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th scope="col"><?=h(t('identity.account'))?></th><th scope="col"><?=h(t('admin_users.contact'))?></th><th scope="col"><?=h(t('admin_users.account_type'))?></th><th scope="col"><?=h(t('admin_users.since'))?></th><th scope="col"><?=h(t('dashboard.actions'))?></th></tr></thead>
                        <tbody>
                        <?php foreach ($users as $account):?>
                            <tr>
                                <th scope="row"><strong><?=h($account['username'])?></strong><br><span class="muted">#<?=(int)$account['id']?></span></th>
                                <td><?=h($account['email'])?><br><span class="muted"><?=h($account['city'])?></span></td>
                                <td><?=h(t($account['is_admin'] ? 'admin_nav.title' : 'admin_users.member'))?></td>
                                <td><?=h($account['created_at'])?></td>
                                <td>
                                    <?php if ((int)$account['id'] !== (int)$admin['id']):?>
                                        <form method="post" action="/admin/users.php">
                                            <?=csrf_field()?>
                                            <input type="hidden" name="action" value="revoke_sessions">
                                            <input type="hidden" name="user_id" value="<?=(int)$account['id']?>">
                                            <button class="button secondary small" type="submit" aria-label="<?=h(t('admin_users.revoke_account', ['name'=>$account['username']]))?>"><?=h(t('admin_users.revoke'))?></button>
                                        </form>
                                    <?php else:?>
                                        <a href="/user/profile.php"><?=h(t('admin_users.own_profile'))?></a>
                                    <?php endif;?>
                                </td>
                            </tr>
                        <?php endforeach;?>
                        <?php if ($users === []):?><tr><td colspan="5"><?=h(t('admin_users.empty'))?></td></tr><?php endif;?>
                        </tbody>
                    </table>
                </div>
            </section>
            <nav class="pagination" aria-label="<?=h(t('dashboard.pages'))?>">
                <?php if ($page > 1):?><a href="?page=<?=$page-1?>">← <?=h(t('Zurück'))?></a><?php endif;?>
                <span><?=h(t('admin_audit.page', ['page'=>$page, 'total'=>max(1,(int)ceil($total/30))]))?></span>
                <?php if ($page*30 < $total):?><a href="?page=<?=$page+1?>"><?=h(t('Weiter'))?> →</a><?php endif;?>
            </nav>
        </div>
    </div>
</main>
<?php include __DIR__.'/../includes/footer.php';?>
