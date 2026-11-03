<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__.'/../config/maintenance.php';
function mt_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$enabled = ['maintenance_enabled'=>'1'];
$now = 1800000000;
$user = ['id'=>42,'is_active'=>1,'is_admin'=>1,'auth_version'=>3,'mfa_secret'=>'synthetic-encrypted-secret'];
$session = ['user_id'=>42,'auth_version'=>3,'admin_mfa_user_id'=>42,'auth_started_at'=>$now - 100,'auth_last_seen'=>$now - 10];
$tests = [
    'maintenance_is_off_unless_explicitly_enabled' => static function () use ($enabled): void {
        foreach ([[], ['maintenance_enabled'=>'0'], ['maintenance_enabled'=>null], ['maintenance_enabled'=>1], ['maintenance_enabled'=>true], ['maintenance_enabled'=>['1']], ['maintenance_enabled'=>'on']] as $settings) {
            mt_assert(!maintenance_enabled($settings), 'Unexpected activation.');
            mt_assert(maintenance_allows_request($settings, 'actions/place-bid.php', 'POST'), 'Disabled gate interrupted a route.');
        }
        mt_assert(maintenance_enabled($enabled), 'Explicit setting was ignored.');
    },
    'marketplace_reads_and_writes_are_blocked_even_for_administrators' => static function () use ($enabled): void {
        foreach (['marketplace/index.php','marketplace/search.php','marketplace/buyout-request.php','actions/place-bid.php','actions/payment-checkout.php','actions/payment-return.php','user/edit-listing.php','user/language.php','auth/register.php','auth/reset-password.php','user/chat-updates.php'] as $route) {
            foreach (['GET','HEAD','POST','PUT','DELETE','OPTIONS'] as $method) {
                mt_assert(!maintenance_allows_request($enabled, $route, $method), 'Public route escaped the gate.');
                mt_assert(!maintenance_allows_request($enabled, $route, $method, true), 'Administrator could trade through the gate.');
            }
        }
    },
    'only_exact_authentication_routes_have_limited_public_access' => static function () use ($enabled): void {
        foreach (['auth/login.php','auth/mfa.php','auth/logout.php'] as $route) {
            foreach (['GET','HEAD','POST'] as $method) mt_assert(maintenance_allows_request($enabled, $route, $method), 'Recovery authentication was blocked.');
            foreach (['DELETE','PUT','get','',"GET\r\nPOST"] as $method) mt_assert(!maintenance_allows_request($enabled, $route, $method), 'Unsupported auth method allowed.');
        }
        foreach (['/auth/login.php','auth/login.php?next=admin','auth//login.php','AUTH/login.php','auth/login.php/extra','auth/../auth/login.php','auth%2flogin.php'] as $route) {
            mt_assert(!maintenance_allows_request($enabled, $route, 'GET'), 'Noncanonical auth route allowed.');
        }
    },
    'payment_reconciliation_exception_is_exact_and_post_only' => static function () use ($enabled): void {
        mt_assert(maintenance_allows_request($enabled, 'actions/payment-webhook.php', 'POST'), 'Existing provider reconciliation blocked.');
        foreach (['GET','HEAD','OPTIONS','PUT','post'] as $method) mt_assert(!maintenance_allows_request($enabled, 'actions/payment-webhook.php', $method), 'Webhook method bypass.');
        foreach (['actions/payment-webhook.php/extra','actions/payment-webhook.php?verified=1','actions/payment-return.php','actions/payment-checkout.php'] as $route) {
            mt_assert(!maintenance_allows_request($enabled, $route, 'POST'), 'Other payment route bypassed maintenance.');
        }
    },
    'administration_requires_the_explicit_authorized_actor' => static function () use ($enabled): void {
        foreach (['admin/index.php','admin/settings.php','admin/setup.php','admin/geography.php'] as $route) {
            foreach (['GET','HEAD','POST'] as $method) {
                mt_assert(!maintenance_allows_request($enabled, $route, $method), 'Unauthenticated admin route allowed.');
                mt_assert(maintenance_allows_request($enabled, $route, $method, true), 'Authorized administration blocked.');
            }
            mt_assert(!maintenance_allows_request($enabled, $route, 'DELETE', true), 'Unexpected admin method allowed.');
        }
        foreach (['admin/../actions/place-bid.php','admin/./settings.php','admin//settings.php','admin\\settings.php','admin/settings.php/extra','admin/settings.php?bypass=1','/admin/settings.php','administrator/settings.php',"admin/settings.php\0"] as $route) {
            mt_assert(!maintenance_allows_request($enabled, $route, 'GET', true), 'Malformed route bypassed administration gate.');
        }
    },
    'active_recent_mfa_and_site_management_permission_are_all_required' => static function () use ($user,$session,$now): void {
        mt_assert(maintenance_admin_session($user, $session, true, $now), 'Valid administrator denied.');
        mt_assert(maintenance_admin_session(array_replace($user,['is_admin'=>0]), $session, true, $now), 'Valid delegated site administrator denied.');
        mt_assert(!maintenance_admin_session($user, $session, false, $now), 'Permission was not checked.');
        mt_assert(!maintenance_admin_session(null, $session, true, $now), 'Missing current user accepted.');
        foreach (['is_active'=>0,'mfa_secret'=>'','id'=>0,'auth_version'=>4] as $field=>$invalid) {
            mt_assert(!maintenance_admin_session(array_replace($user,[$field=>$invalid]), $session, true, $now), 'Invalid user accepted: '.$field);
        }
        foreach (['user_id'=>43,'auth_version'=>2,'admin_mfa_user_id'=>43] as $field=>$invalid) {
            mt_assert(!maintenance_admin_session($user, array_replace($session,[$field=>$invalid]), true, $now), 'Invalid session accepted: '.$field);
            $missing = $session; unset($missing[$field]);
            mt_assert(!maintenance_admin_session($user, $missing, true, $now), 'Missing session claim accepted: '.$field);
        }
    },
    'expired_future_and_malformed_sessions_fail_closed' => static function () use ($user,$session,$now): void {
        foreach ([['auth_started_at'=>$now-43201],['auth_last_seen'=>$now-1801],['auth_started_at'=>$now+1],['auth_last_seen'=>$now+1],['auth_started_at'=>0],['auth_started_at'=>[]],['auth_last_seen'=>(string)$now],['user_id'=>'42'],['admin_mfa_user_id'=>'42']] as $change) {
            mt_assert(!maintenance_admin_session($user, array_replace($session,$change), true, $now), 'Invalid session time/type accepted.');
        }
        foreach ([['id'=>[]],['id'=>'42x'],['auth_version'=>[]],['auth_version'=>'3x'],['mfa_secret'=>true],['is_active'=>true]] as $change) {
            mt_assert(!maintenance_admin_session(array_replace($user,$change), $session, true, $now), 'Malformed user fact accepted.');
        }
        mt_assert(maintenance_admin_session(array_replace($user,['id'=>'42','auth_version'=>'3','is_active'=>'1']), $session, true, $now), 'Valid database string fields denied.');
        mt_assert(maintenance_admin_session($user, array_replace($session,['auth_started_at'=>$now-43200,'auth_last_seen'=>$now-1800]), true, $now), 'Session boundaries disagree with existing authentication.');
    },
    'response_is_temporary_uncacheable_and_has_no_external_dependencies' => static function (): void {
        $response = maintenance_response('en');
        mt_assert($response['status'] === 503 && $response['headers']['Retry-After'] === '300', 'Maintenance must return temporary unavailability.');
        mt_assert($response['headers']['Cache-Control'] === 'no-store, max-age=0', 'Maintenance response may be cached.');
        mt_assert($response['headers']['X-Robots-Tag'] === 'noindex, nofollow', 'Maintenance page may be indexed.');
        mt_assert(str_contains($response['headers']['Content-Security-Policy'], "default-src 'none'"), 'Maintenance CSP missing.');
        mt_assert(!str_contains($response['body'],'<script') && !str_contains($response['body'],'http://') && !str_contains($response['body'],'https://'), 'External or active content present.');
        mt_assert(str_contains($response['body'],'name="viewport"') && str_contains($response['body'],'<main>') && str_contains($response['body'],'<h1>'), 'Responsive accessible page structure missing.');
    },
    'head_returns_identical_headers_without_a_response_body' => static function (): void {
        $get = maintenance_response('fr'); $head = maintenance_response('fr','HEAD');
        mt_assert($head['status'] === $get['status'] && $head['headers'] === $get['headers'] && $head['body'] === '', 'HEAD does not preserve HTTP semantics.');
    },
    'all_eight_locales_have_complete_valid_catalogs_and_rendered_text' => static function (): void {
        $base = maintenance_dictionary('de');
        mt_assert(count($base) === 6 && count(i18n_locales()) === 8, 'Unexpected language coverage.');
        foreach (i18n_locales() as $locale=>$name) {
            $catalog = maintenance_dictionary($locale); $response = maintenance_response($locale);
            mt_assert(array_keys($catalog) === array_keys($base), 'Translation keys differ in '.$locale);
            mt_assert($response['headers']['Content-Language'] === $locale && str_contains($response['body'],'<html lang="'.$locale.'">'), 'Incorrect response language.');
            foreach ($base as $key=>$source) {
                $text = $catalog[$key];
                mt_assert(str_starts_with($key,'maintenance.') && is_string($text) && trim($text) !== '' && preg_match('//u',$text) === 1, 'Invalid localized string.');
                preg_match_all('/\{[a-z_]+\}/',$source,$expected); preg_match_all('/\{[a-z_]+\}/',$text,$actual);
                sort($expected[0]); sort($actual[0]);
                mt_assert($expected[0] === $actual[0], 'Translation placeholder mismatch.');
                mt_assert(str_contains($response['body'],htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')), 'Localized text was not rendered: '.$key);
            }
        }
    },
    'untrusted_locale_values_never_become_paths_markup_or_headers' => static function (): void {
        $fallback = maintenance_response('de');
        foreach ([null,[],['fr'],'../../config/db','EN',"en\r\nX-Test: injected",'<script>',1,'en?x=1'] as $locale) {
            mt_assert(maintenance_response($locale) === $fallback, 'Untrusted locale influenced the response.');
        }
    },
    'response_does_not_read_or_modify_private_request_and_session_data' => static function (): void {
        $previousSession = $_SESSION ?? null; $previousGet = $_GET;
        try {
            $_SESSION = ['locale'=>'it','private'=>'synthetic-private-sentinel','user_id'=>42];
            $_GET = ['secret'=>'synthetic-private-sentinel','bypass'=>'1'];
            $response = maintenance_response('it');
            mt_assert(!str_contains(json_encode($response,JSON_THROW_ON_ERROR),'synthetic-private-sentinel'), 'Private data leaked.');
            mt_assert($_SESSION === ['locale'=>'it','private'=>'synthetic-private-sentinel','user_id'=>42] && $_GET === ['secret'=>'synthetic-private-sentinel','bypass'=>'1'], 'Rendering mutated request/session state.');
        } finally { if ($previousSession === null) unset($_SESSION); else $_SESSION = $previousSession; $_GET = $previousGet; }
    },
];
foreach ($tests as $name=>$run) { $run(); fwrite(STDOUT,'PASS '.$name.PHP_EOL); }
fwrite(STDOUT,'Maintenance checks passed: '.count($tests).PHP_EOL);
