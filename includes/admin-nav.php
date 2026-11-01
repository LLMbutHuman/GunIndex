<?php
declare(strict_types=1);
$navUser=current_user($pdo);$permissions=$navUser?rbac_permissions($pdo,(int)$navUser['id']):[];
$adminLinks=[
 '/admin/index.php'=>['Übersicht','dashboard.read'],'/admin/settings.php'=>['Marke & Website','site.manage'],'/admin/geography.php'=>['Länder & Sprachen','site.manage'],
 '/admin/categories.php'=>['Kategorien','categories.manage'],'/admin/users.php'=>['Benutzerkonten','users.manage'],'/admin/roles.php'=>['Rollen & Rechte','roles.manage'],
 '/admin/listings.php'=>['Inserate & Auktionen','content.moderate'],'/admin/ratings.php'=>['Bewertungen','content.moderate'],'/admin/moderation.php'=>['Meldungen & Beschwerden','reports.read'],
 '/admin/campaigns.php'=>['Händler & Werbung','campaigns.manage'],'/admin/payments.php'=>['Zahlungseingänge','finance.manage'],'/admin/finance.php'=>['Rechnungen & Erstattungen','finance.manage'],
 '/admin/privacy.php'=>['Datenschutzanfragen','privacy.review'],'/admin/verifications.php'=>['Identitätsprüfung','identity.review'],'/admin/legal.php'=>['Rechtstexte','legal.manage'],'/admin/logs.php'=>['Änderungsprotokoll','site.manage']];
$adminPath=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);$ordinal=0;
?>
<aside class="admin-nav panel" aria-label="Administration"><p class="eyebrow">Control room</p><nav aria-label="Verwaltungsbereiche">
<?php foreach($adminLinks as $path=>[$label,$permission]):if(!in_array('*',$permissions,true)&&!in_array($permission,$permissions,true))continue;$ordinal++;?><a href="<?=h($path)?>" <?=$path===$adminPath?'aria-current="page"':''?>><span aria-hidden="true"><?=str_pad((string)$ordinal,2,'0',STR_PAD_LEFT)?></span> <?=h(function_exists('t')?t($label):$label)?></a><?php endforeach;?></nav><p class="muted">Aktuelle Berechtigungen, MFA und protokollierte Änderungen.</p><a href="/marketplace/index.php">Zur Website ↗</a></aside>
