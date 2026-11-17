<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_permission($pdo,'reports.review');$actor=(int)$user['id'];
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','HEAD','POST'],true)){header('Allow: GET, HEAD, POST');abort_request(405,t('account.method'));}
$error='';$trade=null;
if($method==='POST'){
    require_csrf();
    try {
        $id=trade_integer($_POST['id']??null);$action=market_input($_POST,'action');
        if(!in_array($action,['resolve_complete','resolve_cancel'],true))throw new InvalidArgumentException(t('trade.invalid'));
        trade_transition($pdo,$actor,$id,$action,$_POST);redirect('/admin/trades.php?id='.$id);
    } catch(DomainException|InvalidArgumentException|MarketAccessException|MarketConflictException $e){http_response_code(422);$error=$e->getMessage();}
}
try {if(isset($_GET['id']))$trade=trade_details($pdo,trade_row($pdo,trade_integer($_GET['id'])),$actor);}
catch(DomainException|InvalidArgumentException $e){abort_request(404,t('trade.unavailable'));}
$q=$pdo->query("SELECT id,reference,updated_at FROM market_trades WHERE status='disputed' ORDER BY updated_at,id LIMIT 100");$disputes=$q->fetchAll();
$pageTitle=t('trade.admin');include __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/trade.css">
<main class="page trade"><h1><?=h(t('trade.admin'))?></h1><p><?=h(t('trade.admin_help'))?></p>
<?php if($error!==''):?><p role="alert" class="notice notice-error"><?=h($error)?></p><?php endif;?>
<nav class="trade-actions"><?php foreach($disputes as $row):?><a class="button secondary" href="?id=<?=(int)$row['id']?>"><?=h($row['reference'])?></a><?php endforeach;?></nav>
<?php if($trade):?><section class="panel"><h2><?=h($trade['reference'])?> · <?=h($trade['snapshot']['title'])?></h2>
<p><?=h(t('trade.status.'.$trade['status']))?> · <?=h(money_eur((int)$trade['total_cents']))?></p>
<p><?=h(t('trade.role.buyer'))?> #<?=(int)$trade['buyer_id']?> · <?=h(t('trade.role.seller'))?> #<?=(int)$trade['seller_id']?></p>
<?php foreach($trade['events'] as $event):?><article class="panel"><p><?=h($event['created_at'])?> UTC · #<?=(int)$event['actor_id']?> · <?=h(t('trade.action.'.$event['action']))?></p><div class="trade-text"><?=h($event['details'])?></div></article><?php endforeach;?>
<?php foreach(trade_allowed_actions($trade,$actor,true) as $action):?><form method="post">
<?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$trade['id']?>"><input type="hidden" name="version" value="<?=(int)$trade['version']?>"><input type="hidden" name="request_key" value="<?=h(bin2hex(random_bytes(16)))?>">
<label><?=h(t('trade.reason'))?><textarea name="details" minlength="10" maxlength="1000" required></textarea></label>
<label class="check"><input type="checkbox" name="confirm_action" value="1" required><?=h(t('trade.action.'.$action))?></label>
<button class="button" name="action" value="<?=h($action)?>"><?=h(t('trade.action.'.$action))?></button></form><?php endforeach;?></section><?php endif;?>
</main><?php include __DIR__.'/../includes/footer.php'; ?>
