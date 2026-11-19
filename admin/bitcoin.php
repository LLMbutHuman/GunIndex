<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';

header('Cache-Control: private, no-store');
require_permission($pdo, 'finance.manage');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'HEAD') {
    header('Allow: GET, HEAD');
    abort_request(405, t('bitcoin.method'));
}

$view = ($_GET['view'] ?? '') === 'all' ? 'all' : 'open';
$all = $view === 'all';
$rawPage = $_GET['page'] ?? '';
$page = is_string($rawPage) ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]) : false;
$page = $page === false ? 1 : $page;
$perPage = 25;
$entries = bitcoin_review_rows($pdo, $page, $all);
$hasNext = count($entries) > $perPage;
$entries = array_slice($entries, 0, $perPage);
$reason = bitcoin_gate($pdo);
$pageTitle = t('bitcoin.admin_title');
$statuses = ['allocating', 'pending', 'confirmed', 'expired', 'review'];
$reviewReasons = ['multiple', 'amount', 'late', 'reorg', 'conflict', 'cancelled'];
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page">
  <div class="page-heading"><h1><?=h($pageTitle)?></h1></div>
  <div class="admin-layout">
    <?php include __DIR__.'/../includes/admin-nav.php';?>
    <div class="admin-content">
      <section class="panel">
        <p><?=h(t('bitcoin.admin_help'))?></p>
        <?php if ($reason !== null): ?><p class="notice"><?=h($reason)?></p><?php endif;?>
        <nav class="pagination" aria-label="<?=h(t('bitcoin.filters'))?>">
          <a href="?view=open"<?=$all ? '' : ' aria-current="page"'?>><?=h(t('bitcoin.filter_open'))?></a>
          <a href="?view=all"<?=$all ? ' aria-current="page"' : ''?>><?=h(t('bitcoin.filter_all'))?></a>
        </nav>
        <?php if (!$entries): ?><p><?=h(t('bitcoin.empty'))?></p><?php endif;?>
        <?php foreach ($entries as $entry):
            $status = in_array($entry['status'], $statuses, true) ? $entry['status'] : 'review';
            $reviewReason = $entry['review_reason'];
            $txid = $entry['txid'];
            $vout = $entry['vout'];
        ?>
          <article class="panel">
            <h2><?=h($entry['reference'])?> · <?=h(bitcoin_decimal((int)$entry['amount_sats']))?> BTC</h2>
            <p><strong><?=h(t('bitcoin.status_'.$status))?></strong></p>
            <?php if ($entry['chain'] === 'regtest'): ?><p><?=h(t('bitcoin.regtest'))?></p><?php endif;?>
            <?php if ($reviewReason !== null): ?>
              <p><?=h(in_array($reviewReason, $reviewReasons, true) ? t('bitcoin.reason_'.$reviewReason) : t('bitcoin.review_required'))?></p>
            <?php endif;?>
            <dl>
              <dt><?=h(t('bitcoin.invoice'))?></dt>
              <dd>#<?=(int)$entry['invoice_id']?></dd>
              <dt><?=h(t('bitcoin.network'))?></dt>
              <dd><?=h(t($entry['chain'] === 'main' ? 'bitcoin.chain_main' : 'bitcoin.chain_regtest'))?></dd>
              <?php if ($entry['address'] !== null): ?>
                <dt><?=h(t('bitcoin.address'))?></dt>
                <dd><code style="overflow-wrap:anywhere"><?=h($entry['address'])?></code></dd>
              <?php endif;?>
              <?php if ($txid !== null): ?>
                <dt><?=h(t($vout === null ? 'bitcoin.txid' : 'bitcoin.outpoint'))?></dt>
                <dd><code style="overflow-wrap:anywhere"><?=h($vout === null ? $txid : $txid.':'.$vout)?></code></dd>
              <?php endif;?>
              <?php if ($entry['confirmations'] !== null): ?>
                <dt><?=h(t('bitcoin.confirmations'))?></dt>
                <dd><?=(int)$entry['confirmations']?></dd>
              <?php endif;?>
              <dt><?=h(t('bitcoin.deadline'))?></dt>
              <dd><time datetime="<?=h(str_replace(' ', 'T', $entry['expires_at']).'Z')?>"><?=h($entry['expires_at'])?> UTC</time></dd>
              <dt><?=h(t('bitcoin.updated'))?></dt>
              <dd><time datetime="<?=h(str_replace(' ', 'T', $entry['updated_at']).'Z')?>"><?=h($entry['updated_at'])?> UTC</time></dd>
            </dl>
          </article>
        <?php endforeach;?>
        <?php if ($page > 1 || $hasNext): ?>
          <nav class="pagination" aria-label="<?=h(t('bitcoin.pagination'))?>">
            <?php if ($page > 1): ?><a rel="prev" href="?<?=h(http_build_query(['view'=>$view, 'page'=>$page - 1]))?>"><?=h(t('bitcoin.previous'))?></a><?php endif;?>
            <span><?=h(t('bitcoin.page', ['page'=>$page]))?></span>
            <?php if ($hasNext && $page < 10000): ?><a rel="next" href="?<?=h(http_build_query(['view'=>$view, 'page'=>$page + 1]))?>"><?=h(t('bitcoin.next'))?></a><?php endif;?>
          </nav>
        <?php endif;?>
      </section>
    </div>
  </div>
</main>
<?php include __DIR__.'/../includes/footer.php';?>
