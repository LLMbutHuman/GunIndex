<?php
declare(strict_types=1);

function public_route_error(int $status,string $path):never
{
    require_once dirname(__DIR__).'/config/i18n.php';
    $language='de';
    if(preg_match('~\A/([a-z]{2})(?:/|\z)~',$path,$match)&&isset(i18n_locales()[$match[1]]))$language=$match[1];
    $copy=i18n_dictionary($language);
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Language: '.$language);
    header('X-Robots-Tag: noindex, nofollow');
    if($status===405)header('Allow: GET, HEAD');
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')echo $copy[$status===405?'shared.route_method':'shared.route_missing'];
    exit;
}

$root=dirname(__DIR__);$path=rawurldecode(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
if(str_contains($path,"\0")||str_contains($path,'\\')||preg_match('~(?:^|/)\.\.?(/|$)~',$path))public_route_error(404,$path);
if (preg_match('~\A/[a-z]{2}/~', $path)) {
    require_once $root . '/config/seo.php';
    $publicRoute = seo_public_route($path);
    if ($publicRoute !== null) {
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET','HEAD'], true)) {
            public_route_error(405,$path);
        }
        define('GUNINDEX_PUBLIC_LOCALE', $publicRoute['locale']);
        $path = $publicRoute['path'];
    }
}
$allowed=['marketplace/index.php','marketplace/search.php','marketplace/view-item.php','marketplace/view-auction.php','marketplace/image.php','marketplace/profile.php','marketplace/policy.php','marketplace/buyout-request.php','auth/login.php','auth/register.php','auth/logout.php','auth/mfa.php','user/watchlist.php','user/toggle-watchlist.php','user/messages.php','user/chat.php','user/rate-user.php','admin/ratings.php','admin/listings.php','user/dashboard.php','user/profile.php','user/create-listing.php','user/edit-listing.php','user/delete-listing.php','user/payments.php','user/billing.php','admin/payments.php','actions/place-bid.php','actions/manage-auction.php','actions/payment-checkout.php','actions/payment-return.php','actions/payment-webhook.php','admin/index.php','admin/settings.php','admin/categories.php','admin/users.php','admin/logs.php'];
$allowed=array_merge($allowed,['auth/forgot-password.php', 'auth/reset-password.php', 'auth/verify-email.php', 'user/notifications.php', 'user/notification-settings.php', 'user/language.php', 'user/wanted.php', 'user/saved-searches.php', 'user/promotions.php', 'user/dealer.php', 'user/privacy.php', 'user/verification.php', 'user/reports.php', 'user/invoices.php', 'user/billing-details.php', 'user/chat-updates.php', 'admin/geography.php', 'admin/campaigns.php', 'admin/roles.php', 'admin/legal.php', 'admin/verifications.php', 'admin/privacy.php', 'admin/moderation.php', 'admin/finance.php', 'marketplace/wanted.php', 'marketplace/legal.php', 'marketplace/report.php', 'marketplace/verification-document.php', 'marketplace/banner.php', 'marketplace/banner-click.php']);
$allowed=array_merge($allowed,['admin/setup.php','admin/mail-setup.php','user/listing-image.php','user/personal.php','user/relist.php','marketplace/sitemap.php','marketplace/robots.php']);
$allowed=array_merge($allowed,['user/secure-chat.php','user/e2ee-api.php']);
$allowed=array_merge($allowed,['user/checkout.php','user/offers.php','user/trade.php','user/trades.php','user/extend-listing.php','admin/trades.php']);
$allowed=array_merge($allowed,['marketplace/category-fields.php','marketplace/suggest.php','admin/category-attributes.php']);
$allowed=array_merge($allowed,['user/listing-draft.php','user/listing-draft-image.php']);
$allowed[]='user/sessions.php';
$allowed=array_merge($allowed,['user/security.php','auth/user-factor.php']);
$allowed=array_merge($allowed,['user/passkeys.php','auth/passkey.php']);
$allowed=array_merge($allowed,['user/profile-image.php','marketplace/profile-image.php']);
$allowed[]='admin/bank.php';
$allowed[]='admin/bitcoin.php';
$allowed=array_merge($allowed,['user/escrow.php','admin/escrow.php']);
$allowed=array_merge($allowed,['user/dealer-team.php','user/dealer-stock.php']);
$allowed=array_merge($allowed,['user/dealer-api-keys.php','api/dealer/v1.php']);
$allowed=array_merge($allowed,['user/dealer-subscription.php','user/dealer-stock-export.php']);
$allowed[]='user/account-consent.php';
$allowed=array_merge($allowed,['admin/dealers.php','user/banner-advertising.php']);
$allowed[]='admin/editorial.php';
if ($path === '/fachwissen') {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET','HEAD'], true)) public_route_error(405,$path);
    $allowed[]='marketplace/editorial.php';
}
$route=match($path){'/', '/index.php'=>'marketplace/index.php','/fachwissen'=>'marketplace/editorial.php','/robots.txt'=>'marketplace/robots.php','/sitemap.xml'=>'marketplace/sitemap.php',default=>ltrim($path,'/')};
$static=['/assets/css/international.css'=>'text/css; charset=utf-8','/assets/js/chat.js'=>'application/javascript; charset=utf-8','/assets/css/app.css'=>'text/css; charset=utf-8','/assets/js/app.js'=>'application/javascript; charset=utf-8','/assets/img/no-image.png'=>'image/png'];
$static+=['/assets/css/e2ee.css'=>'text/css; charset=utf-8','/assets/js/e2ee-ui.mjs'=>'application/javascript; charset=utf-8','/assets/js/e2ee-crypto.mjs'=>'application/javascript; charset=utf-8','/assets/vendor/openpgp/openpgp.min.mjs'=>'application/javascript; charset=utf-8'];
$static['/assets/css/trade.css']='text/css; charset=utf-8';
$static['/assets/js/listing-draft.js']='application/javascript; charset=utf-8';
$static['/assets/js/passkeys.js']='application/javascript; charset=utf-8';
$static['/assets/js/admin-setup.js']='application/javascript; charset=utf-8';
$static['/assets/css/admin-setup.css']='text/css; charset=utf-8';
$static['/assets/css/banner-advertising.css']='text/css; charset=utf-8';
if (isset($static[$path]) && is_file($root.$path)) {
    $method=$_SERVER['REQUEST_METHOD']??'GET';
    if (!in_array($method,['GET','HEAD'],true)) public_route_error(405,$path);
    header('Content-Type: '.$static[$path]);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=300');
    if ($method==='GET') readfile($root.$path);
    exit;
}
if(!in_array($route,$allowed,true)||!is_file($root.'/'.$route))public_route_error(404,$path);
if ($route === 'api/dealer/v1.php') define('GUNINDEX_STATELESS_REQUEST', true);
require_once $root.'/config/bootstrap.php';
require_once $root.'/config/setup.php';
setup_enforce_installation_http($pdo,$route);
maintenance_enforce_http($pdo,$route);
consent_account_gate_http($pdo,$route);
if (in_array($route,['marketplace/index.php','marketplace/search.php','marketplace/view-item.php','marketplace/view-auction.php',
    'marketplace/profile.php','marketplace/policy.php','marketplace/legal.php','marketplace/editorial.php','marketplace/wanted.php','auth/login.php','auth/register.php'],true)) {
    i18n_initialize_visitor($pdo);
}
require $root.'/'.$route;
