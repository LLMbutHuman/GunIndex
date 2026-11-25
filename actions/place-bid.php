<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo); require_post(); require_csrf();
$id=filter_var($_POST['listing_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$maximum=$_POST['max_bid'] ?? null;
if ($id===false || !is_string($maximum)) abort_request(422,t('auction_actions.bid_data'));
try {
    $result=market_bid($pdo,(int)$id,(int)$user['id'],money_parse($maximum));
    $_SESSION['auction_confirmation']=['listing_id'=>$id,'actor_id'=>(int)$user['id'],'kind'=>'bid'];
    redirect('/marketplace/view-auction.php?id='.(int)$id.'&bid='.($result['leading']?'leading':'outbid'));
} catch (InvalidArgumentException $error) { abort_request(422,$error->getMessage()); }
catch (MarketNotFoundException $error) { abort_request(404,$error->getMessage()); }
catch (MarketAccessException $error) { abort_request(403,$error->getMessage()); }
catch (MarketConflictException $error) { abort_request(409,$error->getMessage()); }
