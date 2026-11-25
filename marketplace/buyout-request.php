<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo); require_post(); require_csrf();
$id=filter_var($_POST['listing_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if ($id===false) abort_request(422,t('auction_actions.order_data'));
try {
    market_buy_now($pdo,(int)$id,(int)$user['id']);
    $_SESSION['auction_confirmation']=['listing_id'=>$id,'actor_id'=>(int)$user['id'],'kind'=>'buyout'];
    redirect('/marketplace/view-auction.php?id='.(int)$id);
} catch (MarketNotFoundException $error) { abort_request(404,$error->getMessage()); }
catch (MarketAccessException $error) { abort_request(403,$error->getMessage()); }
catch (MarketConflictException $error) { abort_request(409,$error->getMessage()); }
