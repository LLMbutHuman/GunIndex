<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/editorial.php';

header('Cache-Control: private, no-store, max-age=0');
$admin = require_permission($pdo, 'legal.manage');
$countries = geo_active_countries($pdo);
$country = $_GET['country'] ?? geo_country();
$topic = $_GET['topic'] ?? 'legal-basics';
if (!is_string($country) || !isset($countries[$country]) || !is_string($topic) || !in_array($topic, editorial_topics(), true)) {
    abort_request(400, t('ember.invalid_review'));
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','HEAD','POST'], true)) {
    header('Allow: GET, HEAD, POST');
    abort_request(405, t('account.method'));
}
$editorUrl = '/admin/editorial.php?' . http_build_query(['country'=>$country,'topic'=>$topic]);
$error = '';
if ($method === 'POST') {
    require_csrf();
    try {
        editorial_review_save($pdo, (int)$admin['id'], $_SESSION, $topic, $country, post_string('revision', 20), $_POST);
        $_SESSION['editorial_review_saved'] = true;
        redirect($editorUrl);
    } catch (InvalidArgumentException | DomainException $failure) {
        http_response_code(422);
        $error = $failure->getMessage();
    }
}
$saved = $_SESSION['editorial_review_saved'] ?? false;
unset($_SESSION['editorial_review_saved']);
$review = editorial_reviews($pdo, $country)[$topic] ?? null;
$storedStatus = $review['status_code'] ?? 'pending';
$source = editorial_sources()[$country] ?? null;
$pageTitle = t('ember.editorial_admin');
include __DIR__.'/../includes/header.php';
?>
<main class="container admin-page">
    <div class="page-heading"><h1><?=h($pageTitle)?></h1><p><?=h(t('ember.editorial_only'))?></p></div>
    <div class="admin-layout">
        <?php include __DIR__.'/../includes/admin-nav.php'; ?>
        <div class="admin-content">
            <?php if ($error !== ''): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif; ?>
            <?php if ($saved): ?><p class="notice success" role="status"><?=h(t('ember.review_saved'))?></p><?php endif; ?>
            <form class="panel editorial-filters" method="get" action="/admin/editorial.php">
                <label class="field"><?=h(t('Land'))?><select name="country"><?php foreach ($countries as $code => $label): ?>
                    <option value="<?=h($code)?>" <?=$code === $country ? 'selected' : ''?>><?=h($label)?></option>
                <?php endforeach; ?></select></label>
                <label class="field"><?=h(t('ember.topic'))?><select name="topic"><?php foreach (editorial_topics() as $option): ?>
                    <option value="<?=h($option)?>" <?=$option === $topic ? 'selected' : ''?>><?=h(t('ember.topic_'.$option))?></option>
                <?php endforeach; ?></select></label>
                <button class="button secondary" type="submit"><?=h(t('Anzeigen'))?></button>
            </form>
            <form class="panel editorial-review" method="post" action="<?=h($editorUrl)?>">
                <?=csrf_field()?><input type="hidden" name="revision" value="<?=h((string)($review['revision'] ?? 0))?>">
                <h2><?=h(t('ember.topic_'.$topic))?> · <?=h($countries[$country])?></h2>
                <p><?=h(t('ember.status'))?> · <strong><?=h(t('ember.status_'.editorial_review_status($review, $country)))?></strong></p>
                <?php if ($source !== null): ?><p><a class="text-link" href="<?=h($source)?>" rel="noreferrer"><?=h(t('ember.official_source', ['country'=>$countries[$country]]))?></a></p>
                <?php else: ?><p class="notice"><?=h(t('ember.no_source'))?></p><?php endif; ?>
                <label class="field"><?=h(t('ember.status'))?><select name="status"><?php foreach ($source === null ? ['pending'] : editorial_statuses() as $option): ?>
                    <option value="<?=h($option)?>" <?=$option === $storedStatus ? 'selected' : ''?>><?=h(t('ember.status_'.$option))?></option>
                <?php endforeach; ?></select></label>
                <?php if ($source !== null): ?>
                <label class="field"><?=h(t('ember.reference'))?><input name="reference" maxlength="255" value="<?=h($review['legal_reference'] ?? '')?>"></label>
                <label class="field"><?=h(t('ember.scope'))?><textarea name="scope_note" maxlength="2000" rows="3"><?=h($review['scope_note'] ?? '')?></textarea></label>
                <label class="checkbox"><input type="checkbox" name="review_confirmed" value="1"> <?=h(t('ember.review_confirmation'))?></label>
                <?php endif; ?>
                <div><button class="button" type="submit"><?=h(t('admin_settings.save'))?></button></div>
            </form>
        </div>
    </div>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
