<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/editorial.php';

$countries = geo_active_countries($pdo);
$country = $_GET['country'] ?? geo_country();
$status = $_GET['status'] ?? '';
if (!is_string($country) || !isset($countries[$country]) || !is_string($status)
    || ($status !== '' && !in_array($status, editorial_statuses(), true))) abort_request(400, t('classification.search_invalid'));
$reviews = editorial_reviews($pdo, $country);
$source = editorial_sources()[$country] ?? null;
$pageTitle = t('ember.editorial_title');
$pageDescription = t('ember.editorial_description');
$seoFiltered = array_key_exists('country', $_GET) || array_key_exists('status', $_GET);
$path = seo_localized_path('/fachwissen', i18n_locale());
include __DIR__.'/../includes/header.php';
?>
<main class="page-width page editorial-page">
    <div class="page-heading"><h1><?=h($pageTitle)?></h1><p><?=h($pageDescription)?></p></div>
    <p class="notice editorial-notice"><?=h(t('ember.editorial_only'))?></p>
    <form class="panel editorial-filters" action="<?=h($path)?>" method="get">
        <label class="field"><?=h(t('Land'))?><select name="country"><?php foreach ($countries as $code => $label): ?>
            <option value="<?=h($code)?>" <?=$code === $country ? 'selected' : ''?>><?=h($label)?></option>
        <?php endforeach; ?></select></label>
        <label class="field"><?=h(t('ember.status'))?><select name="status">
            <option value=""><?=h(t('ember.all_statuses'))?></option>
            <?php foreach (editorial_statuses() as $option): ?><option value="<?=h($option)?>" <?=$status === $option ? 'selected' : ''?>><?=h(t('ember.status_'.$option))?></option><?php endforeach; ?>
        </select></label>
        <div class="actions"><button class="button" type="submit"><?=h(t('Anzeigen'))?></button>
            <?php if ($seoFiltered): ?><a class="button secondary" href="<?=h($path)?>"><?=h(t('Filter zurücksetzen'))?></a><?php endif; ?></div>
    </form>
    <?php if ($source !== null): ?><p class="editorial-source"><a href="<?=h($source)?>" rel="noreferrer"><?=h(t('ember.official_source', ['country'=>$countries[$country]]))?></a></p><?php endif; ?>
    <div class="editorial-topics">
        <?php $matched = false; foreach (editorial_topics() as $topic):
            $review = $reviews[$topic] ?? null;
            $current = editorial_review_status($review, $country);
            if ($status !== '' && $current !== $status) continue;
            $matched = true;
        ?>
        <article class="panel editorial-topic" id="<?=h($topic)?>">
            <h2><?=h(t('ember.topic_'.$topic))?></h2>
            <p><?=h(t('ember.about_'.$topic))?></p>
            <p><span class="badge muted"><?=h(t('ember.status_'.$current))?></span></p>
            <p><?=h(t('ember.help_'.$current))?></p>
            <?php if ($current !== 'pending'): ?>
            <details><summary><?=h(t('ember.legal_details'))?></summary>
                <dl><dt><?=h(t('Land'))?></dt><dd><?=h($countries[$country])?></dd>
                    <dt><?=h(t('ember.scope'))?></dt><dd><?=h($review['scope_note'])?></dd>
                    <dt><?=h(t('ember.reference'))?></dt><dd><?=h($review['legal_reference'])?></dd>
                    <dt><?=h(t('ember.reviewed_on'))?></dt><dd><time datetime="<?=h(substr($review['reviewed_at'], 0, 10))?>"><?=h(substr($review['reviewed_at'], 0, 10))?></time></dd></dl>
            </details>
            <?php endif; ?>
        </article>
        <?php endforeach; if (!$matched): ?><p class="panel"><?=h(t('ember.no_reviews'))?></p><?php endif; ?>
    </div>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
