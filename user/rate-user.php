<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';$user=require_login($pdo);$id=filter_var($_GET['listing_id']??null,FILTER_VALIDATE_INT);$listing=$id?market_find($pdo,(int)$id,true):null;
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
if(!$listing||$listing['type']!=='auction'||!in_array($listing['status'],['ended','sold'],true)||!$listing['highest_bidder_id'])abort_request(404,t('trade.auction_rating_missing'));
$seller=(int)$listing['seller_id'];$winner=(int)$listing['highest_bidder_id'];$actor=(int)$user['id'];
if($seller===$winner||!in_array($actor,[$seller,$winner],true))abort_request(403,t('trade.auction_rating_parties'));
$recipient=$actor===$seller?$winner:$seller;$error='';$success=false;$comment='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 require_csrf();
 try{$stars=filter_var($_POST['stars']??null,FILTER_VALIDATE_INT);$comment=trim(post_string('comment',3000));if(!$stars||$stars<1||$stars>5||!preg_match('//u',$comment))throw new InvalidArgumentException(t('trade.auction_rating_invalid'));
 $pdo->prepare('INSERT INTO market_ratings(listing_id,sender_id,receiver_id,stars,comment) VALUES (?,?,?,?,?)')->execute([$id,$actor,$recipient,$stars,$comment]);$success=true;
 }catch(InvalidArgumentException $e){http_response_code(422);$error=$e->getMessage();}catch(PDOException $e){if($e->getCode()!=='23000')throw$e;http_response_code(409);$error=t('trade.duplicate');}
}
$pageTitle=t('trade.auction_rating_title');include __DIR__.'/../includes/header.php';?>
<main class="container"><section class="panel"><h1><?=h($pageTitle)?></h1><p><?=h($listing['title'])?></p><?php if($error):?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?><?php if($success):?><p class="notice success" role="status"><?=h(t('trade.auction_rating_saved'))?></p><?php else:?><form method="post" class="form-grid"><?=csrf_field()?><label class="field"><?=h(t('trade.rating_score'))?><select name="stars"><?php for($n=5;$n>=1;$n--):?><option value="<?=$n?>"><?=h(t('trade.stars',['stars'=>$n]))?></option><?php endfor;?></select></label><label class="field"><?=h(t('trade.auction_rating_comment'))?><textarea name="comment" maxlength="3000"><?=h($comment)?></textarea></label><button class="button"><?=h(t('trade.rate'))?></button></form><?php endif;?></section></main><?php include __DIR__.'/../includes/footer.php';?>
