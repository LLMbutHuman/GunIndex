<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$admin=require_admin($pdo);$pageTitle='Bewertungen moderieren';$error='';
if (isset($_GET['delete_id'])||isset($_GET['action'])) abort_request(405,'Moderation erfordert POST und CSRF.');
if ($_SERVER['REQUEST_METHOD']==='POST') {
 require_csrf();$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$status=$_POST['status']??null;$reason=$_POST['reason']??null;
 if ($id===false||!is_string($status)||!in_array($status,['active','hidden'],true)||!is_string($reason)||!preg_match('/\A.{10,1000}\z/us',trim($reason))) {http_response_code(422);$error='Bitte gib eine gültige Bewertung, einen Status und eine Begründung mit 10 bis 1000 Zeichen an.';}
 else {
  $pdo->beginTransaction();
  try {
   settings_assert_admin($pdo,(int)$admin['id']);$q=$pdo->prepare('SELECT id,status FROM market_ratings WHERE id=? FOR UPDATE');$q->execute([$id]);$rating=$q->fetch();
   if (!$rating) {$pdo->rollBack();abort_request(404,'Bewertung nicht gefunden.');}
   if ($rating['status']!==$status) {$pdo->prepare('UPDATE market_ratings SET status=? WHERE id=?')->execute([$status,$id]);audit_log($pdo,(int)$admin['id'],'rating.moderated',$id,['status'=>$status,'reason'=>trim($reason)]);}
   $pdo->commit();redirect('/admin/ratings.php?saved=1');
  } catch (Throwable $exception) {if ($pdo->inTransaction()) $pdo->rollBack();throw $exception;}
 }
}
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]])?:1;$total=(int)$pdo->query('SELECT COUNT(*) FROM market_ratings')->fetchColumn();$page=min($page,max(1,(int)ceil($total/25)));
$q=$pdo->prepare('SELECT r.*,s.username AS sender_name,u.username AS receiver_name,l.title FROM market_ratings r JOIN users s ON s.id=r.sender_id JOIN users u ON u.id=r.receiver_id JOIN market_listings l ON l.id=r.listing_id ORDER BY r.id DESC LIMIT 25 OFFSET ?');$q->bindValue(1,($page-1)*25,PDO::PARAM_INT);$q->execute();$ratings=$q->fetchAll();
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page"><div class="page-heading"><div><p class="eyebrow">Vertrauen nachvollziehbar halten</p><h1>Bewertungen moderieren.</h1><p>Ein- und Ausblenden mit dokumentierter Begründung. Keine physische Löschung.</p></div></div><div class="admin-layout"><?php include __DIR__.'/../includes/admin-nav.php'; ?><div class="admin-content"><?php if ($error!==''): ?><p class="notice error" role="alert"><?= h($error) ?></p><?php endif; ?><?php if (($_GET['saved']??'')==='1'): ?><p class="notice success" role="status">Moderationsentscheidung gespeichert.</p><?php endif; ?><?php foreach ($ratings as $rating): ?><article class="panel"><p class="eyebrow">Bewertung #<?= (int)$rating['id'] ?> · <?= h($rating['status']==='hidden'?'Ausgeblendet':'Sichtbar') ?></p><h2><?= h($rating['title']) ?></h2><p><?= (int)$rating['stars'] ?> / 5 · <?= h($rating['sender_name']) ?> → <?= h($rating['receiver_name']) ?></p><blockquote><?= h($rating['comment']) ?></blockquote><p class="muted"><?= h($rating['created_at']) ?> UTC · Inserat #<?= (int)$rating['listing_id'] ?></p><form method="post" action="/admin/ratings.php"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$rating['id'] ?>"><input type="hidden" name="status" value="<?= $rating['status']==='hidden'?'active':'hidden' ?>"><div class="field"><label for="reason-<?= (int)$rating['id'] ?>">Begründung</label><textarea id="reason-<?= (int)$rating['id'] ?>" name="reason" minlength="10" maxlength="1000" required rows="2"></textarea></div><button class="button secondary" type="submit"><?= $rating['status']==='hidden'?'Wieder einblenden':'Ausblenden' ?></button></form></article><?php endforeach; ?><?php if ($ratings===[]): ?><section class="panel empty-state"><h2>Noch keine Bewertungen.</h2><p>Sobald abgeschlossene allgemeine Transaktionen bewertet werden, erscheinen sie hier.</p></section><?php endif; ?><nav class="pagination" aria-label="Bewertungsseiten"><?php if ($page>1): ?><a href="?page=<?= $page-1 ?>">← Zurück</a><?php endif; ?><span>Seite <?= $page ?> von <?= max(1,(int)ceil($total/25)) ?></span><?php if ($page*25<$total): ?><a href="?page=<?= $page+1 ?>">Weiter →</a><?php endif; ?></nav></div></div></main>
<?php include __DIR__.'/../includes/footer.php'; ?>
