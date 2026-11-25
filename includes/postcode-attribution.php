<?php
declare(strict_types=1);
foreach (array_unique(geo_postcode_attributions($pdo, $postcodeSourceCountries), SORT_REGULAR) as $postcodeLinks):
    $postcodeAnchors = [];
    foreach ($postcodeLinks as $sourceName => $sourceUrl) {
        $postcodeAnchors[] = '<a href="'.h($sourceUrl).'">'.h($sourceName).'</a>';
    }
?>
<p class="help"><?=implode(' · ', $postcodeAnchors)?> · <?=h(t('market_search.derived_points'))?></p>
<?php endforeach;?>
