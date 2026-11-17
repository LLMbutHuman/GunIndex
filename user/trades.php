<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);$actor=(int)$user['id'];
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){header('Allow: GET, HEAD');abort_request(405,t('account.method'));}
$rawPage=$_GET['page']??'1';
if(!is_string($rawPage)||!preg_match('/\A[1-9][0-9]{0,5}\z/',$rawPage))abort_request(400,t('trade.invalid'));
$result=trade_list($pdo,$actor,(int)$rawPage);$page=(int)$result['page'];
$pageTitle=t('trade.title');include __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/trade.css">
<main class="trade">
<div class="trade-heading"><h1><?=h(t('trade.title'))?></h1><a href="/user/dashboard.php"><?=h(t('dashboard.title'))?></a></div>
<p><?=h(t('trade.overview_help'))?></p>
<div class="trade-grid"><section class="panel"><h2><?=h(t('trade.transactions'))?></h2>
<?php if(!$result['trades']):?><p class="trade-empty"><?=h(t('trade.no_transactions'))?></p><?php endif;?>
<div class="trade-stack">
<?php foreach($result['trades'] as $trade):?>
<article class="panel trade-card" data-trade-id="<?=(int)$trade['id']?>"><h3><a href="/user/trade.php?id=<?=(int)$trade['id']?>"><?=h($trade['title'])?></a></h3>
<p class="trade-meta"><?=h(t((int)$trade['buyer_id']===$actor?'trade.role.buyer':'trade.role.seller'))?> · <?=h($trade['reference'])?></p>
<p><span class="trade-status"><?=h(t('trade.status.'.$trade['status']))?></span></p><p><?=h(money_eur((int)$trade['total_cents']))?></p></article>
<?php endforeach;?>
</div>
<nav class="trade-actions" aria-label="<?=h(t('trade.pagination'))?>">
<?php if($page>1):?><a class="button secondary" href="/user/trades.php?page=<?=$page-1?>"><?=h(t('trade.previous'))?></a><?php endif;?>
<?php if(count($result['trades'])>=25):?><a class="button secondary" href="/user/trades.php?page=<?=$page+1?>"><?=h(t('trade.next'))?></a><?php endif;?>
</nav></section>
<section class="panel"><h2><?=h(t('trade.offers'))?></h2>
<?php if(!$result['offers']):?><p class="trade-empty"><?=h(t('trade.no_offers'))?></p><?php endif;?>
<div class="trade-stack"><?php foreach($result['offers'] as $offer):?>
<article class="panel trade-card" data-offer-id="<?=(int)$offer['id']?>"><h3><a href="/user/offers.php?listing_id=<?=(int)$offer['listing_id']?>#offer-<?=(int)$offer['id']?>"><?=h($offer['title'])?></a></h3><p><?=h(money_eur((int)$offer['total_cents']))?> · <?=h(t('trade.delivery.'.$offer['delivery']))?></p><p class="trade-meta"><?=h(t((int)$offer['proposer_id']===$actor?'trade.offer.sent':'trade.offer.received'))?></p><p><?=h(t('trade.expires_at'))?>: <time><?=h($offer['expires_at'])?> UTC</time></p></article>
<?php endforeach;?></div></section></div>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
