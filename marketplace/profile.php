<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$rawId = $_GET['user_id'] ?? null;
$id = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
if ($id === false) { abort_request(404, t('public_profile.missing')); }
$query = $pdo->prepare('SELECT id,username,created_at FROM users WHERE id=? AND is_active=1'); $query->execute([$id]); $seller = $query->fetch();
if (!$seller) { abort_request(404, t('public_profile.missing')); }
$rawPage = $_GET['page'] ?? '1';
$page = is_string($rawPage) ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]) : false;
if ($page === false) { abort_request(422, t('public_profile.invalid_page')); }
$result = market_search($pdo, ['seller_id'=>(string)$id, 'scope'=>'international'], (int)$page);
$items=$result['items'];$total=$result['total'];
$dealer=site_setting($pdo,'dealer_profiles_enabled','1')==='1'?business_dealer_profile($pdo,(int)$id):null;
$avatar=profile_image_revision($pdo,$id,'avatar');
$query = $pdo->prepare("SELECT COUNT(*) AS count,AVG(r.stars) AS average FROM market_ratings r JOIN market_listings l ON l.id=r.listing_id WHERE r.receiver_id=? AND r.status='active' AND l.status IN ('ended','sold') AND (r.trade_id IS NULL OR EXISTS(SELECT 1 FROM market_trades rt WHERE rt.id=r.trade_id AND rt.status='completed'))");
$query->execute([$id]); $summary = $query->fetch();
$query = $pdo->prepare("SELECT r.stars,r.comment,r.created_at,s.username FROM market_ratings r JOIN users s ON s.id=r.sender_id JOIN market_listings l ON l.id=r.listing_id WHERE r.receiver_id=? AND r.status='active' AND l.status IN ('ended','sold') AND (r.trade_id IS NULL OR EXISTS(SELECT 1 FROM market_trades rt WHERE rt.id=r.trade_id AND rt.status='completed')) ORDER BY r.id DESC LIMIT 20");
$query->execute([$id]); $ratings = $query->fetchAll();
$pageTitle = (string)$seller['username']; require __DIR__ . '/../includes/header.php';
?>
<main class="page-width section"><header class="page-heading">
<?php if($avatar!==null): ?><img src="<?=h(profile_image_url($id,'avatar',$avatar))?>" alt="" width="96" height="96" style="object-fit:contain"><?php endif; ?>
<h1><?= h($seller['username']) ?></h1>
<p><?= h(t('public_profile.membership', ['date'=>substr((string)$seller['created_at'], 0, 10), 'count'=>$total])) ?></p>
<?php if ((int)$summary['count'] > 0): ?>
<p><?= h(t('public_profile.rating_summary', ['average'=>number_format((float)$summary['average'], 1, i18n_locale()==='en'?'.':',', ''), 'count'=>(int)$summary['count']])) ?></p>
<?php endif; ?></header>
<?php if($dealer): ?><section class="panel"><p class="eyebrow"><?= h(t('public_profile.dealer')) ?></p>
<?php if($dealer['logo_revision']!==null): ?><img src="<?=h(profile_image_url($id,'logo',$dealer['logo_revision']))?>" alt="" width="120" height="120" style="object-fit:contain"><?php endif; ?>
<h2><?= h($dealer['company_name']) ?></h2><p><?= nl2br(h($dealer['address_text'])) ?> · <?= h(geo_countries()[$dealer['country_code']] ?? $dealer['country_code']) ?></p><p><a href="mailto:<?= h($dealer['contact_email']) ?>"><?= h($dealer['contact_email']) ?></a><?php if($dealer['website']): ?> · <a href="<?= h($dealer['website']) ?>" rel="nofollow noopener noreferrer"><?= h(t('public_profile.website')) ?></a><?php endif; ?></p><details><summary><?= h(t('public_profile.company_info')) ?></summary><p><?= nl2br(h($dealer['legal_notice'])) ?></p></details></section><?php endif; ?>
<section class="section"><h2><?= h(t('public_profile.listings')) ?></h2><?php if ($items): ?><div class="cards"><?php foreach ($items as $item) { ui_listing_card($item); } ?></div><?php else: ?><p class="panel"><?= h(t('public_profile.no_listings')) ?></p><?php endif; ?>
<?php if ($total > 24): ?><nav class="pagination" aria-label="<?= h(t('public_profile.listing_pages')) ?>"><?php if ($page > 1): ?><a class="button secondary" href="?user_id=<?= (int)$id ?>&amp;page=<?= $page - 1 ?>"><?= h(t('Zurück')) ?></a><?php endif; ?><span><?= h(t('public_profile.page', ['page'=>$page])) ?></span><?php if ($page * 24 < $total): ?><a class="button secondary" href="?user_id=<?= (int)$id ?>&amp;page=<?= $page + 1 ?>"><?= h(t('Weiter')) ?></a><?php endif; ?></nav><?php endif; ?></section>
<section class="section"><h2><?= h(t('public_profile.reviews')) ?></h2><?php if ($ratings === []): ?><p class="panel"><?= h(t('public_profile.no_reviews')) ?></p><?php else: ?><?php foreach ($ratings as $rating): ?><article class="panel"><p><?= (int)$rating['stars'] ?> / 5 · <?= h($rating['username']) ?> · <?= h(substr((string)$rating['created_at'], 0, 10)) ?></p><p><?= nl2br(h($rating['comment'])) ?></p></article><?php endforeach; ?><?php endif; ?></section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
