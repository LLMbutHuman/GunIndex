<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user = require_login($pdo);
$actor = (int)$user['id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method,['GET','HEAD','POST'],true)) {
    header('Allow: GET, HEAD, POST');
    abort_request(405,t('account.method'));
}
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$id = filter_var($_GET['listing_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$listing = $id ? market_find($pdo,$id,true) : null;
if (!$listing || $listing['type']!=='auction' || !in_array($listing['status'],['ended','sold'],true)) abort_request(404,t('trade.auction_rating_missing'));
if (trade_auction_rating_recipient($pdo,$listing,$actor)===null) abort_request(403,t('trade.auction_rating_parties'));
$error = '';
$comment = '';
$saved = false;
if ($method==='POST') {
    require_csrf();
    try {
        $stars = filter_var($_POST['stars'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>5]]);
        if (!$stars) throw new InvalidArgumentException(t('trade.auction_rating_invalid'));
        $comment = post_string('comment',3000);
        trade_rate_auction($pdo,$actor,$id,$stars,$comment);
        $saved = true;
    } catch (InvalidArgumentException $exception) {
        http_response_code(422); $error=$exception->getMessage();
    } catch (MarketConflictException $exception) {
        http_response_code(409); $error=$exception->getMessage();
    } catch (MarketAccessException $exception) {
        http_response_code(403); $error=$exception->getMessage();
    }
}
$query = $pdo->prepare('SELECT 1 FROM market_ratings WHERE listing_id=? AND sender_id=?');
$query->execute([$id,$actor]);
$rated = $query->fetchColumn()!==false;
$pageTitle = t('trade.auction_rating_title');
include __DIR__.'/../includes/header.php';
?>
<main class="container"><section class="panel">
    <h1><?=h($pageTitle)?></h1><p><?=h($listing['title'])?></p>
    <?php if ($error): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif; ?>
    <?php if ($saved): ?><p class="notice success" role="status"><?=h(t('trade.auction_rating_saved'))?></p><?php endif; ?>
    <?php if ($rated && !$saved && !$error): ?><p class="notice"><?=h(t('trade.duplicate'))?></p><?php endif; ?>
    <?php if (!$rated): ?><form method="post" class="form-grid">
        <?=csrf_field()?><label class="field"><?=h(t('trade.rating_score'))?><select name="stars" required>
            <option value=""><?=h(t('trade.choose'))?></option>
            <?php for ($n=5;$n>=1;$n--): ?><option value="<?=$n?>"><?=h(t('trade.stars',['stars'=>$n]))?></option><?php endfor; ?>
        </select></label>
        <label class="field"><?=h(t('trade.auction_rating_comment'))?><textarea name="comment" maxlength="3000"><?=h($comment)?></textarea></label>
        <button class="button" type="submit"><?=h(t('trade.rate'))?></button>
    </form><?php endif; ?>
</section></main>
<?php include __DIR__.'/../includes/footer.php'; ?>
