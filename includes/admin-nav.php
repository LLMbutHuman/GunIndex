<?php
declare(strict_types=1);
$navUser=current_user($pdo);$permissions=$navUser?rbac_permissions($pdo,(int)$navUser['id']):[];
$adminLinks=[
 '/admin/setup.php'=>['refine.setup_tab','site.manage'],
 '/admin/mail-setup.php'=>['mail_setup.admin_title','site.manage'],
 '/admin/bank.php'=>['bank.admin_title','finance.manage'],
 '/admin/bitcoin.php'=>['bitcoin.admin_title','finance.manage'],
 '/admin/trades.php'=>['trade.admin','reports.review'],
 '/admin/index.php'=>['admin_nav.dashboard','dashboard.read'],'/admin/settings.php'=>['admin_nav.settings','site.manage'],'/admin/geography.php'=>['admin_nav.geography','site.manage'],
 '/admin/categories.php'=>['admin_nav.categories','categories.manage'],'/admin/users.php'=>['admin_nav.users','users.manage'],'/admin/roles.php'=>['admin_nav.roles','roles.manage'],
 '/admin/listings.php'=>['admin_nav.listings','content.moderate'],'/admin/ratings.php'=>['admin_nav.ratings','content.moderate'],'/admin/moderation.php'=>['admin_nav.moderation','reports.read'],
 '/admin/dealers.php'=>['banner_ads.dealers_title','dealers.manage'],'/admin/campaigns.php'=>['banner_ads.admin_title','campaigns.manage'],'/admin/payments.php'=>['finance_admin.payments','finance.manage'],'/admin/finance.php'=>['admin_nav.finance','finance.manage'],
 '/admin/privacy.php'=>['admin_nav.privacy','privacy.review'],'/admin/verifications.php'=>['admin_nav.identity','identity.review'],'/admin/legal.php'=>['admin_nav.legal','legal.manage'],'/admin/logs.php'=>['admin_nav.audit','site.manage']];
if (escrow_simulation_enabled($pdo)) $adminLinks['/admin/escrow.php'] = ['escrow.admin_title','finance.manage'];
$adminPath=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
?>
<aside class="admin-nav panel" aria-label="<?=h(t('admin_nav.title'))?>">
    <p class="eyebrow"><?=h(t('admin_nav.title'))?></p>
    <nav aria-label="<?=h(t('admin_nav.sections'))?>">
    <?php foreach($adminLinks as $linkPath=>[$label,$permission]):
        if(!in_array('*',$permissions,true)&&!in_array($permission,$permissions,true))continue;
    ?>
        <a href="<?=h($linkPath)?>" <?=$linkPath===$adminPath?'aria-current="page"':''?>><?=h(t($label))?></a>
    <?php endforeach; ?>
    </nav>
    <p class="muted"><?=h(t('admin_nav.access'))?></p><a href="/marketplace/index.php"><?=h(t('admin_nav.website'))?></a>
</aside>
