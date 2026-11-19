<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';

header('Cache-Control: private, no-store');
$staff = require_permission($pdo, 'finance.manage');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    abort_request(405, t('bank.method'));
}

$view = ($_GET['view'] ?? '') === 'all' ? 'all' : 'open';
$all = $view === 'all';
$rawPage = $_GET['page'] ?? '';
$page = is_string($rawPage) ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]) : false;
$page = $page === false ? 1 : $page;
$notice = '';
if ($method === 'GET') {
    $notice = $_SESSION['bank_notice'] ?? '';
    unset($_SESSION['bank_notice']);
}
$error = '';
$reason = bank_gate($pdo);

if ($method === 'POST') {
    require_csrf();
    try {
        if ($reason !== null) throw new PaymentUnavailable($reason);
        $action = post_string('action', 16);
        if ($action === 'import') {
            if (post_string('source_confirmed', 1) !== '1') {
                throw new InvalidArgumentException(t('bank.source_confirmation_required'));
            }
            $file = $_FILES['statement'] ?? [];
            $maximum = 5 * 1024 * 1024;
            $name = $file['name'] ?? null;
            $tempPath = $file['tmp_name'] ?? null;
            $size = $file['size'] ?? null;
            if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($name)
                || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'xml'
                || !is_string($tempPath) || !is_uploaded_file($tempPath)
                || !is_int($size) || $size < 1 || $size > $maximum || filesize($tempPath) !== $size) {
                throw new InvalidArgumentException(t('bank.invalid_statement'));
            }
            $xml = file_get_contents($tempPath, false, null, 0, $maximum + 1);
            if (!is_string($xml) || strlen($xml) !== $size) {
                throw new InvalidArgumentException(t('bank.invalid_statement'));
            }
            $result = bank_import($pdo, (int)$staff['id'], $xml);
            $_SESSION['bank_notice'] = t('bank.imported', $result);
            redirect('/admin/bank.php');
        }
        if ($action === 'approve') {
            if (post_string('review_confirmed', 1) !== '1') {
                throw new InvalidArgumentException(t('bank.review_confirmation_required'));
            }
            $entry = filter_var(post_string('entry_id', 20), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($entry === false) throw new InvalidArgumentException(t('bank.conflict'));
            $invoice = bank_approve($pdo, (int)$staff['id'], $entry);
            $_SESSION['bank_notice'] = t('bank.approved', ['invoice' => $invoice]);
            redirect('/admin/bank.php?view=all');
        }
        throw new InvalidArgumentException(t('bank.conflict'));
    } catch (PaymentUnavailable $failure) {
        http_response_code(503);
        $error = $failure->getMessage();
    } catch (PaymentConflict $failure) {
        http_response_code(409);
        $error = $failure->getMessage();
    } catch (InvalidArgumentException|DomainException $failure) {
        http_response_code(422);
        $error = $failure->getMessage();
    } catch (Throwable $failure) {
        http_response_code(503);
        error_log('Bank reconciliation request failed ('.get_class($failure).')');
        $error = t('bank.unavailable');
    }
}

$entries = bank_review_rows($pdo, $page, $all);
$perPage = 25;
$hasNext = count($entries) > $perPage;
$entries = array_slice($entries, 0, $perPage);
$pageTitle = t('bank.admin_title');
$states = ['matched', 'unmatched', 'wrong_amount', 'closed', 'reversal', 'blocked', 'booked'];
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page">
  <div class="page-heading"><h1><?=h($pageTitle)?></h1></div>
  <div class="admin-layout">
    <?php include __DIR__.'/../includes/admin-nav.php';?>
    <div class="admin-content">
      <?php if ($error !== ''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif;?>
      <?php if ($notice !== ''): ?><p class="notice success" role="status"><?=h($notice)?></p><?php endif;?>
      <section class="panel">
        <h2><?=h(t('bank.import_title'))?></h2>
        <?php if ($reason !== null): ?>
          <p><?=h($reason)?></p>
        <?php else: ?>
          <p><?=h(t('bank.import_help'))?></p>
          <form method="post" action="/admin/bank.php" enctype="multipart/form-data" class="form-grid">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="import">
            <label class="field"><?=h(t('bank.statement'))?>
              <input type="file" name="statement" accept=".xml,application/xml,text/xml" required>
            </label>
            <label><input type="checkbox" name="source_confirmed" value="1" required> <?=h(t('bank.source_confirmation'))?></label>
            <button class="button" type="submit"><?=h(t('bank.import_button'))?></button>
          </form>
        <?php endif;?>
      </section>

      <section class="panel">
        <h2><?=h(t('bank.queue_title'))?></h2>
        <nav class="pagination" aria-label="<?=h(t('bank.queue_title'))?>">
          <a href="?view=open"<?=$all ? '' : ' aria-current="page"'?>><?=h(t('bank.filter_open'))?></a>
          <a href="?view=all"<?=$all ? ' aria-current="page"' : ''?>><?=h(t('bank.filter_all'))?></a>
        </nav>
        <?php if (!$entries): ?><p><?=h(t('bank.empty'))?></p><?php endif;?>
        <?php foreach ($entries as $entry):
            $state = in_array($entry['state'], $states, true) ? $entry['state'] : 'blocked';
            $label = t('bank.state_'.$state);
        ?>
          <article class="panel">
            <h3><?=h($entry['payment_reference'] ?? t('bank.no_reference'))?> · <?=h(money_eur((int)$entry['amount_cents']))?></h3>
            <p><strong><?=h($label)?></strong></p>
            <dl>
              <dt><?=h(t('bank.source_reference'))?></dt>
              <dd><code><?=h($entry['source_reference'])?></code></dd>
              <dt><?=h(t('bank.booking_date'))?></dt>
              <dd><time datetime="<?=h($entry['booking_date'])?>"><?=h($entry['booking_date'])?></time></dd>
              <?php if ($entry['invoice_id'] !== null): ?>
                <dt><?=h(t('bank.invoice'))?></dt>
                <dd>#<?=(int)$entry['invoice_id']?></dd>
              <?php endif;?>
            </dl>
            <?php if ($reason === null && $state === 'matched'): ?>
              <form method="post" action="/admin/bank.php?<?=h(http_build_query(['view'=>$view, 'page'=>$page]))?>" class="form-grid">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="entry_id" value="<?=(int)$entry['id']?>">
                <label><input type="checkbox" name="review_confirmed" value="1" required> <?=h(t('bank.review_confirmation'))?></label>
                <button class="button" type="submit"><?=h(t('bank.approve_button'))?></button>
              </form>
            <?php endif;?>
          </article>
        <?php endforeach;?>
        <?php if ($page > 1 || $hasNext): ?>
          <nav class="pagination" aria-label="<?=h(t('bank.pagination'))?>">
            <?php if ($page > 1): ?><a rel="prev" href="?<?=h(http_build_query(['view'=>$view, 'page'=>$page - 1]))?>"><?=h(t('bank.previous'))?></a><?php endif;?>
            <span><?=h(t('bank.page', ['page'=>$page]))?></span>
            <?php if ($hasNext && $page < 10000): ?><a rel="next" href="?<?=h(http_build_query(['view'=>$view, 'page'=>$page + 1]))?>"><?=h(t('bank.next'))?></a><?php endif;?>
          </nav>
        <?php endif;?>
      </section>
    </div>
  </div>
</main>
<?php include __DIR__.'/../includes/footer.php';?>
