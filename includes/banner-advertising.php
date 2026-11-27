<?php
declare(strict_types=1);

function banner_ads_picture(array $campaign, string $source, string $still): void
{
    if ($campaign['creative_format'] === 'text') return;
    ?><picture><?php if ($campaign['creative_format'] === 'gif'): ?>
        <source media="(prefers-reduced-motion: reduce)" srcset="<?=h($still)?>">
    <?php endif; ?>
        <img src="<?=h($source)?>" alt="<?=h($campaign['overlay_text'] === '' ? $campaign['title'] : '')?>" loading="lazy" width="600" height="240">
    </picture><?php
}

function banner_ads_creative(array $campaign, string $source, string $still): void
{
    ?><div class="banner-ad-creative banner-ad-<?=h($campaign['overlay_style'])?>">
    <?php banner_ads_picture($campaign,$source,$still); ?>
    <?php if ($campaign['overlay_text'] !== ''): ?><span class="banner-ad-overlay"><?=h($campaign['overlay_text'])?></span><?php endif; ?>
    </div><?php
}

function banner_ads_render(PDO $pdo, string $placement, string $country, ?int $category = null): void
{
    if (site_setting($pdo,'business_promotions_enabled','0') !== '1') return;
    $items = banner_ads_public($pdo,$placement,$country,$category);
    if (count($items) < 3) {
        foreach (business_banners($pdo,$country,$category,3-count($items)) as $legacy) {
            try { banner_ads_url($legacy['target_url']); } catch (InvalidArgumentException) { continue; }
            $items[] = $legacy + ['placement'=>'legacy','creative_format'=>'webp','overlay_style'=>'plain','overlay_text'=>''];
            if (count($items) === 3) break;
        }
    }
    if (!$items) return;
    ?><section class="page-width banner-ads" aria-label="<?=h(t('banner_ads.ad'))?>">
        <div class="banner-ad-grid">
            <?php foreach ($items as $campaign):
                $param = ['id'=>(int)$campaign['id'],'country'=>$country];
                if ($campaign['placement'] !== 'legacy') $param['placement'] = $placement;
                $query = http_build_query($param);
                $source = '/marketplace/banner.php?'.$query;
                $still = $source.'&still=1';
                $id = (int)$campaign['id']; ?>
                <article class="banner-ad-card">
                    <a class="banner-ad-link" href="<?=h('/marketplace/banner-click.php?'.$query)?>" rel="nofollow sponsored" aria-label="<?=h(t('banner_ads.ad').' · '.$campaign['title'])?>">
                        <?php banner_ads_creative($campaign,$source,$still); ?>
                        <?php if ($campaign['creative_format'] === 'text'): ?><img class="banner-ad-pixel" src="<?=h($source)?>" alt="" width="1" height="1" loading="lazy"><?php endif; ?>
                    </a>
                    <div class="banner-ad-caption"><span><?=h(t('banner_ads.ad'))?> · <?=h($campaign['title'])?></span>
                        <a href="/marketplace/report.php?type=advertisement&amp;id=<?=$id?>"><?=h(t('banner_ads.report'))?></a></div>
                </article>
            <?php endforeach; ?>
        </div>
    </section><?php
}

function banner_ads_status_label(string $state): string
{
    return match ($state) {
        'all' => t('admin_listings.all'),
        'awaiting_payment','awaiting_review','scheduled','removed' => t('banner_ads.status_'.$state),
        default => business_state_label($state),
    };
}

function banner_ads_details(array $campaign): void
{
    ?><dl>
        <?php if ($campaign['placement']!=='legacy'): ?><dt><?=h(t('banner_ads.placement'))?></dt><dd><?=h(t('banner_ads.'.$campaign['placement']))?></dd><?php endif; ?>
        <?php if ($campaign['term_days']!==null): ?><dt><?=h(t('banner_ads.term'))?></dt><dd><?=h(t('banner_ads.days',['days'=>(int)$campaign['term_days']]))?></dd><?php endif; ?>
        <?php if ($campaign['category_id']!==null): ?><dt><?=h(t('Kategorie'))?></dt><dd><?=h(t('admin_campaigns.category',['id'=>(int)$campaign['category_id']]))?></dd><?php endif; ?>
        <dt><?=h(t('dealer_dashboard.target_url'))?></dt><dd class="banner-ad-url"><?=h($campaign['target_url'])?></dd>
        <?php if ($campaign['invoice_status']!==null): ?>
            <dt><?=h(t('payment_customer.status'))?></dt><dd><?=h(payments_status_label($campaign['invoice_status']).' · '.money_eur((int)$campaign['gross_cents']).' · '.$campaign['invoice_number'])?></dd>
        <?php elseif ($campaign['agreed_gross_cents']!==null): ?>
            <dt><?=h(t('Betrag'))?></dt><dd><?=h(money_eur((int)$campaign['agreed_gross_cents']))?></dd>
        <?php endif; ?>
        <?php if (($start=$campaign['starts_at']??$campaign['scheduled_from'])!==null): ?><dt><?=h(t('banner_ads.starts'))?></dt><dd><?=h($start)?></dd><?php endif; ?>
        <?php if (($end=$campaign['ends_at']??$campaign['scheduled_until'])!==null): ?><dt><?=h(t('banner_ads.ends'))?></dt><dd><?=h($end)?></dd><?php endif; ?>
    </dl>
    <p><?=h(t('dealer_dashboard.campaign_metrics',['impressions'=>(int)$campaign['impressions'],'clicks'=>(int)$campaign['clicks']]))?></p><?php
}

function banner_ads_preview(array $campaign, string $source): void
{
    ?><div class="banner-preview"><figure>
        <?php banner_ads_creative($campaign,$source,$source.'&still=1'); ?>
        <figcaption><?=h(t('banner_ads.preview_desktop'))?></figcaption>
    </figure><figure class="banner-preview-narrow">
        <?php banner_ads_creative($campaign,$source,$source.'&still=1'); ?>
        <figcaption><?=h(t('banner_ads.preview_mobile'))?></figcaption>
    </figure></div><?php
}

function banner_ads_fields(PDO $pdo, array $values = [], bool $operator = false): void
{
    $value = static fn(string $key,string $fallback=''): string => (string)($values[$key]??$fallback);
    $countries = geo_countries();
    $categories = catalog_categories($pdo);
    ?><label class="field"><?=h(t('Titel'))?><input name="title" value="<?=h($value('title'))?>" minlength="5" maxlength="120" required></label>
    <label class="field"><?=h(t('dealer_dashboard.target_url'))?><input name="target_url" value="<?=h($value('target_url'))?>" type="url" maxlength="1000" placeholder="https://" required></label>
    <label class="field"><?=h(t('dealer_dashboard.target_market'))?><select name="country_code" required>
    <?php foreach ($countries as $code=>$label): if (!business_country_enabled($pdo,$code)) continue; ?>
        <option value="<?=h($code)?>" <?=($value('country_code','DE')===$code)?'selected':''?>><?=h($label)?></option>
    <?php endforeach; ?></select></label>
    <label class="field"><?=h(t('Kategorie'))?><select name="category_id"><option value=""><?=h(t('banner_ads.all_categories'))?></option>
    <?php foreach ($categories as $category): ?><option value="<?=(int)$category['id']?>" <?=($value('category_id')===(string)$category['id'])?'selected':''?>><?=h(i18n_category($pdo,$category))?></option><?php endforeach; ?>
    </select></label>
    <label class="field"><?=h(t('dealer_dashboard.banner_image'))?><input type="file" name="creative" accept="image/jpeg,image/png,image/webp,image/gif"><small><?=h(t('banner_ads.media_help'))?></small></label>
    <label class="field"><?=h(t('banner_ads.overlay'))?><input name="overlay_text" value="<?=h($value('overlay_text'))?>" maxlength="120"></label>
    <label class="field"><?=h(t('banner_ads.template'))?><select name="overlay_style">
    <?php foreach (['plain','dark','light'] as $style): ?><option value="<?=h($style)?>" <?=($value('overlay_style')===$style)?'selected':''?>><?=h(t('banner_ads.style_'.$style))?></option><?php endforeach; ?>
    </select></label>
    <?php if ($operator): ?>
        <label class="field"><?=h(t('banner_ads.placement'))?><select name="placement"><?php foreach (BANNER_AD_PLACEMENTS as $place): ?><option value="<?=h($place)?>" <?=($value('placement')===$place)?'selected':''?>><?=h(t('banner_ads.'.$place))?></option><?php endforeach; ?></select></label>
        <label class="field"><?=h(t('banner_ads.priority'))?><input name="priority" type="number" min="0" max="9999" value="<?=h($value('priority','0'))?>" required></label>
        <label class="field"><?=h(t('banner_ads.starts'))?><input name="scheduled_from" type="datetime-local" value="<?=h(str_replace(' ','T',substr($value('scheduled_from',gmdate('Y-m-d H:i')),0,16)))?>" required></label>
        <label class="field"><?=h(t('banner_ads.ends'))?><input name="scheduled_until" type="datetime-local" value="<?=h(str_replace(' ','T',substr($value('scheduled_until',gmdate('Y-m-d H:i',time()+604800)),0,16)))?>" required></label>
    <?php endif;
}
