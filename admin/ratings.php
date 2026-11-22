<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$admin=require_permission($pdo,'content.moderate');$pageTitle=t('trade.admin_ratings_title');$error='';
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (isset($_GET['delete_id'])||isset($_GET['action'])||!in_array($method,['GET','HEAD','POST'],true)) {
 header('Allow: GET, HEAD, POST');
 abort_request(405,t('account.method'));
}
$saved=$method==='GET' && ($_SESSION['moderation_rating_saved']??false)===true;
if ($saved) unset($_SESSION['moderation_rating_saved']);
if ($method==='POST') {
 require_csrf();$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$status=$_POST['status']??null;$reason=$_POST['reason']??null;
 if ($id===false||!is_string($status)||!in_array($status,['active','hidden'],true)||!is_string($reason)||!preg_match('/\A.{10,1000}\z/us',trim($reason))) {http_response_code(422);$error=t('trade.admin_ratings_invalid');}
 else {
  $pdo->beginTransaction();
  try {
   governance_assert_actor($pdo,(int)$admin['id'],'content.moderate');$q=$pdo->prepare('SELECT id,status FROM market_ratings WHERE id=? FOR UPDATE');$q->execute([$id]);$rating=$q->fetch();
   if (!$rating) {$pdo->rollBack();abort_request(404,t('trade.admin_ratings_missing'));}
   if ($rating['status']!==$status) {$pdo->prepare('UPDATE market_ratings SET status=? WHERE id=?')->execute([$status,$id]);audit_log($pdo,(int)$admin['id'],'rating.moderated',$id,['status'=>$status,'reason'=>trim($reason)]);}
   $pdo->commit();$_SESSION['moderation_rating_saved']=true;redirect('/admin/ratings.php');
  } catch (Throwable $exception) {if ($pdo->inTransaction()) $pdo->rollBack();throw $exception;}
 }
}
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]])?:1;$total=(int)$pdo->query('SELECT COUNT(*) FROM market_ratings')->fetchColumn();$page=min($page,max(1,(int)ceil($total/25)));
$q=$pdo->prepare('SELECT r.*,s.username AS sender_name,u.username AS receiver_name,l.title FROM market_ratings r JOIN users s ON s.id=r.sender_id JOIN users u ON u.id=r.receiver_id JOIN market_listings l ON l.id=r.listing_id ORDER BY r.id DESC LIMIT 25 OFFSET ?');$q->bindValue(1,($page-1)*25,PDO::PARAM_INT);$q->execute();$ratings=$q->fetchAll();
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page">
    <div class="page-heading"><h1><?=h($pageTitle)?></h1><p><?=h(t('trade.admin_ratings_help'))?></p></div>
    <div class="admin-layout"><?php include __DIR__.'/../includes/admin-nav.php'; ?>
        <div class="admin-content">
            <?php if ($error !== ''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif; ?>
            <?php if ($saved): ?><p class="notice success" role="status"><?=h(t('trade.admin_ratings_saved'))?></p><?php endif; ?>
            <?php foreach ($ratings as $rating):
                $hidden = $rating['status'] === 'hidden';
                $ratingId = (int)$rating['id'];
            ?>
            <article class="panel">
                <p class="eyebrow"><?=h(t('trade.admin_ratings_id', ['id'=>$ratingId]))?> · <?=h(t($hidden?'trade.admin_ratings_hidden':'trade.admin_ratings_visible'))?></p>
                <h2><?=h($rating['title'])?></h2>
                <p><?=h(t('trade.stars', ['stars'=>(int)$rating['stars']]))?> · <?=h($rating['sender_name'])?> → <?=h($rating['receiver_name'])?></p>
                <blockquote><?=h($rating['comment'])?></blockquote>
                <p class="muted"><?=h($rating['created_at'])?> UTC · <?=h(t('trade.admin_ratings_listing', ['id'=>(int)$rating['listing_id']]))?></p>
                <form method="post" action="/admin/ratings.php">
                    <?=csrf_field()?>
                    <input type="hidden" name="id" value="<?=$ratingId?>">
                    <input type="hidden" name="status" value="<?=$hidden?'active':'hidden'?>">
                    <div class="field">
                        <label for="reason-<?=$ratingId?>"><?=h(t('finance_admin.reason'))?></label>
                        <textarea id="reason-<?=$ratingId?>" name="reason" minlength="10" maxlength="1000" required rows="2"></textarea>
                    </div>
                    <button class="button secondary" type="submit"><?=h(t($hidden?'trade.admin_ratings_show':'trade.admin_ratings_hide'))?></button>
                </form>
            </article>
            <?php endforeach; ?>
            <?php if (!$ratings): ?><section class="panel empty-state"><h2><?=h(t('trade.no_ratings'))?></h2></section><?php endif; ?>
            <nav class="pagination" aria-label="<?=h($pageTitle)?>">
                <?php if ($page>1): ?><a href="?page=<?=$page-1?>">← <?=h(t('Zurück'))?></a><?php endif; ?>
                <span><?=h(t('Seite {page} von {pages}', ['page'=>$page,'pages'=>max(1,(int)ceil($total/25))]))?></span>
                <?php if ($page*25<$total): ?><a href="?page=<?=$page+1?>"><?=h(t('Weiter'))?> →</a><?php endif; ?>
            </nav>
        </div>
    </div>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
