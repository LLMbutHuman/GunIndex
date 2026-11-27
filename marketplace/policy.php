<?php
declare(strict_types=1);

require_once __DIR__.'/../config/bootstrap.php';
$sections = [
    'rules'=>'market_policy.rules', 'payments'=>'market_policy.payments',
    'privacy'=>'market_policy.privacy', 'operator'=>'ember.imprint',
];
$section = $_GET['section'] ?? 'rules';
if (!is_string($section) || !isset($sections[$section])) abort_request(404, t('market_policy.missing'));
$pageTitle = t($sections[$section]);
include __DIR__.'/../includes/header.php';
?>
<main class="page-width page">
    <div class="page-heading"><h1><?=h($pageTitle)?></h1></div>
    <div class="policy-layout">
        <nav class="policy-nav" aria-label="<?=h(t('market_policy.sections'))?>">
            <?php foreach ($sections as $sectionCode => $sectionKey):?>
                <a href="/marketplace/policy.php?section=<?=h($sectionCode)?>" <?=$sectionCode === $section ? 'class="active" aria-current="page"' : ''?>><?=h(t($sectionKey))?></a>
            <?php endforeach;?>
            <a href="/marketplace/legal.php"><?=h(t('legal_public.title'))?></a>
        </nav>
        <article class="prose panel">
            <p class="notice"><?=h(t('market_policy.review'))?></p>
            <?php if ($section === 'rules'):?>
                <p><?=h(t('ember.rules_summary'))?></p>
                <p><?=h(t('market_policy.scope'))?></p>
                <h2><?=h(t('market_policy.prohibited'))?></h2>
                <ul>
                    <li><?=h(t('market_policy.regulated'))?></li>
                    <li><?=h(t('market_policy.illegal'))?></li>
                    <li><?=h(t('market_policy.misuse'))?></li>
                </ul>
                <h2><?=h(t('market_policy.auctions'))?></h2>
                <p><?=h(t('market_policy.auction_help'))?></p>
                <h2><?=h(t('market_policy.trade'))?></h2>
                <p><?=h(t('market_policy.trade_help'))?></p>
            <?php elseif ($section === 'payments'):?>
                <p><?=h(t('market_policy.payment_help'))?></p>
                <h2><?=h(t('market_policy.methods'))?></h2>
                <?php foreach (payments_methods(null, $pdo) as $paymentMethod):?>
                    <div class="provider-option">
                        <div><h3><?=h($paymentMethod['label'])?></h3><p><?=h($paymentMethod['reason'])?></p></div>
                        <span class="badge"><?=h(t($paymentMethod['available'] ? 'market_policy.configured' : 'market_policy.unavailable'))?></span>
                    </div>
                <?php endforeach;?>
                <h2><?=h(t('market_policy.fees'))?></h2>
                <p><?=h(t('market_policy.fee_help'))?></p>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th scope="col"><?=h(t('market_policy.service'))?></th><th scope="col"><?=h(t('market_policy.gross'))?></th></tr></thead>
                        <tbody>
                        <?php foreach (['private_fixed','wanted','search_alert','auction_listing','bump_single','bump_pack','category_top','home_top','banner_monthly'] as $serviceCode):
                            $quote = fees_quote($serviceCode, null, $pdo);?>
                            <tr><th scope="row"><?=h(t('market_policy.fee_'.$serviceCode))?></th><td><?=h(money_eur((int)$quote['gross_cents']))?></td></tr>
                        <?php endforeach;?>
                        </tbody>
                    </table>
                </div>
                <p><?=h(t('market_policy.commission', [
                    'cap'=>money_eur((int)fees_quote('auction_success', 9999999999)['gross_cents']),
                    'sale'=>money_eur(100000),
                    'example'=>money_eur((int)fees_quote('auction_success', 100000)['gross_cents']),
                ]))?></p>
            <?php elseif ($section === 'privacy'):?>
                <p><?=h(t('ember.privacy_summary'))?></p>
                <p><?=h(t('market_policy.data'))?></p>
                <p><?=h(t('market_policy.storage'))?></p>
                <p><?=h(t('market_policy.identity'))?></p>
                <p><?=h(t('market_policy.chat'))?></p>
            <?php else:?>
                <?php $operator = site_settings($pdo);
                $contact = $operator['operator_email'] ?: $operator['contact_email'];
                $address = trim($operator['operator_postcode'].' '.$operator['operator_city']);
                $countryLabel = geo_countries()[$operator['operator_country']] ?? $operator['operator_country'];
                if ($operator['operator_legal_name'] !== ''): ?>
                    <address class="operator-address">
                        <strong><?=h($operator['operator_legal_name'])?></strong><br>
                        <?php foreach ([$operator['operator_address_line1'], $operator['operator_address_line2'], $address, $countryLabel] as $line): ?>
                            <?php if ($line !== ''): ?><?=h($line)?><br><?php endif; ?>
                        <?php endforeach; ?>
                    </address>
                <?php endif; ?>
                <?php if ($operator['operator_legal_name'] === '' || $operator['operator_address_line1'] === '' || $operator['operator_city'] === '' || $operator['operator_postcode'] === '' || $contact === ''): ?>
                <p class="notice"><?=h(t('ember.operator_pending'))?></p>
                <?php endif; ?>
                <?php if ($operator['operator_registration'] !== ''): ?><p><?=h(t('admin_settings.field.operator_registration'))?> · <?=h($operator['operator_registration'])?></p><?php endif; ?>
                <?php if ($operator['operator_vat_id'] !== ''): ?><p><?=h(t('admin_settings.field.operator_vat_id'))?> · <?=h($operator['operator_vat_id'])?></p><?php endif; ?>
                <?php if (filter_var($contact, FILTER_VALIDATE_EMAIL)):?>
                    <p><?=h(t('market_policy.contact'))?> <a href="mailto:<?=h($contact)?>"><?=h($contact)?></a></p>
                <?php endif;?>
            <?php endif;?>
        </article>
    </div>
</main>
<?php include __DIR__.'/../includes/footer.php';?>
