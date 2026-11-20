<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';

header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$accountConsentUser = (int)require_login($pdo)['id'];
$accountConsentMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($accountConsentMethod, ['GET','HEAD','POST'], true)) {
    header('Allow: GET, HEAD, POST');
    abort_request(405, t('account.method'));
}
if ($accountConsentMethod === 'POST') require_csrf();

$accountConsentReturn = consent_account_return($accountConsentMethod === 'POST' ? ($_POST['return'] ?? null) : ($_GET['return'] ?? null));
$accountConsentLocale = i18n_locale();
$accountConsentError = '';
$accountConsentAvailable = true;
$accountConsentDocuments = [];
try {
    if ($accountConsentMethod === 'POST') {
        try {
            consent_account_submit($pdo, $accountConsentUser, $accountConsentLocale, $_POST);
            redirect($accountConsentReturn);
        } catch (InvalidArgumentException $failure) {
            $missingConfirmation = $failure->getMessage() === t('consent.confirm');
            http_response_code($missingConfirmation ? 422 : 409);
            $accountConsentError = t($missingConfirmation ? 'consent.confirm' : 'consent.changed');
        }
    }
    $accountConsentDocuments = consent_account_pending($pdo, $accountConsentUser, $accountConsentLocale);
} catch (DomainException|InvalidArgumentException) {
    $accountConsentAvailable = false;
} catch (Throwable $failure) {
    $accountConsentAvailable = false;
    error_log('Account consent could not be processed ('.get_class($failure).')');
}

if (!$accountConsentAvailable) {
    http_response_code(503);
    $accountConsentError = t('account_consent.unavailable');
}

$pageTitle = t('account_consent.title');
include __DIR__.'/../includes/header.php';
?>
<main class="page" id="main-content">
  <div class="page-heading">
    <h1><?=h($pageTitle)?></h1>
    <p><?=h(t('account_consent.intro'))?></p>
  </div>

  <section class="panel">
    <?php if ($accountConsentError !== ''): ?><p class="notice error" role="alert"><?=h($accountConsentError)?></p><?php endif;?>
    <?php if ($accountConsentAvailable && $accountConsentDocuments): ?>
      <form method="post" action="/user/account-consent.php" class="form-grid">
        <?=csrf_field()?>
        <input type="hidden" name="return" value="<?=h($accountConsentReturn)?>">
        <?php consent_form($accountConsentDocuments, $accountConsentLocale); ?>
        <button class="button" type="submit"><?=h(t('account_consent.accept'))?></button>
      </form>
    <?php elseif ($accountConsentAvailable): ?>
      <p><?=h(t('account_consent.current'))?></p>
      <a class="button" href="<?=h($accountConsentReturn)?>"><?=h(t('Weiter'))?></a>
    <?php endif;?>
  </section>

  <nav class="button-row" aria-label="<?=h(t('account_consent.other_options'))?>">
    <a href="/user/dashboard.php"><?=h(t('account_consent.account'))?></a>
    <a href="/user/invoices.php"><?=h(t('account_consent.receipts'))?></a>
    <a href="/marketplace/policy.php?section=operator"><?=h(t('account_consent.support'))?></a>
  </nav>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
