<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__ . '/../config/i18n.php';

function ip_country_assert(bool $condition, string $reason): void
{
    if (!$condition) throw new RuntimeException($reason);
}

$data = dirname(__DIR__) . '/config/ip-country/';
ip_country_assert(hash_file('sha256', $data . 'ipv4.bin') === '835d091f789ee85a215ff79e73793e1eb1d657a09084a1164abaff2bd02adb19', 'IPv4 database checksum changed.');
ip_country_assert(hash_file('sha256', $data . 'ipv6.bin') === '4dbdfe0fa43bef49b4f3459d3294c70d605ab356c5df65602643bfe61788d83c', 'IPv6 database checksum changed.');

foreach (['80.12.0.20'=>'FR', '145.220.26.200'=>'NL', '2003:10::1'=>'DE', '2a01:e0a::1'=>'FR', '::ffff:80.12.0.20'=>'FR'] as $address => $country) {
    ip_country_assert(ip_country_lookup(['REMOTE_ADDR'=>$address]) === $country, 'Known IPv4 or IPv6 country differs from the source.');
}
foreach (['127.0.0.1', '10.0.0.1', '100.64.1.1', '192.0.2.1', '::1', 'fc00::1', 'fe80::1', '2001:db8::1', '::ffff:10.0.0.1', '80.12.0.20,192.168.1.1', '', "80.12.0.20\0invalid", []] as $address) {
    ip_country_assert(ip_country_lookup(['REMOTE_ADDR'=>$address]) === null, 'Private or malformed address was used.');
}
$spoofed = ['HTTP_X_FORWARDED_FOR'=>'80.12.0.20', 'HTTP_X_REAL_IP'=>'80.12.0.20', 'HTTP_CF_CONNECTING_IP'=>'80.12.0.20', 'HTTP_CF_IPCOUNTRY'=>'FR', 'HTTP_FORWARDED'=>'for=80.12.0.20'];
ip_country_assert(ip_country_lookup($spoofed + ['REMOTE_ADDR'=>'127.0.0.1']) === null, 'A client header bypassed the socket peer.');
ip_country_assert(ip_country_lookup($spoofed + ['REMOTE_ADDR'=>'145.220.26.200']) === 'NL', 'A client header replaced the socket peer.');

$active = ['AT', 'DE', 'FR', 'NL'];
$server = ['HTTP_ACCEPT_LANGUAGE'=>'en-GB;q=0.5, fr-FR, de-AT;q=0.9', 'REMOTE_ADDR'=>'145.220.26.200'];
$saved = ['locale'=>'it', 'market_country'=>'AT'];
ip_country_assert(i18n_visitor_defaults($server, $saved, $active) === ['locale'=>'it', 'country'=>'AT', 'ip_attribution'=>false], 'Browser or IP replaced a saved preference.');
ip_country_assert(i18n_visitor_defaults($server, $saved, $active, 'en') === ['locale'=>'en', 'country'=>'AT', 'ip_attribution'=>false], 'Public path language is not stable.');
ip_country_assert(i18n_visitor_defaults($server, [], $active) === ['locale'=>'fr', 'country'=>'NL', 'ip_attribution'=>true], 'Country must follow the local address and language the browser preference.');
ip_country_assert(i18n_visitor_defaults(['HTTP_ACCEPT_LANGUAGE'=>'fr-FR', 'REMOTE_ADDR'=>'127.0.0.1'], [], $active)
    === ['locale'=>'fr', 'country'=>'FR', 'ip_attribution'=>false], 'A browser region must still be usable for a local test connection.');
ip_country_assert(i18n_visitor_defaults(['HTTP_ACCEPT_LANGUAGE'=>'fr, nl;q=0.7', 'REMOTE_ADDR'=>'145.220.26.200'], [], $active)
    === ['locale'=>'fr', 'country'=>'NL', 'ip_attribution'=>true], 'A browser language without a region did not preserve the local country hint.');
ip_country_assert(i18n_visitor_defaults(['HTTP_ACCEPT_LANGUAGE'=>'fr;q=0, en;q=bad, pt-BR', 'REMOTE_ADDR'=>'2a01:e0a::1'], [], $active)
    === ['locale'=>'fr', 'country'=>'FR', 'ip_attribution'=>true], 'Valid IPv6 hint did not succeed unavailable browser preferences.');
ip_country_assert(i18n_visitor_defaults(['REMOTE_ADDR'=>'80.12.0.20'], [], ['NL'])
    === ['locale'=>'fr', 'country'=>'NL', 'ip_attribution'=>true], 'An inactive country became the market selection.');
ip_country_assert(i18n_visitor_defaults($spoofed + ['REMOTE_ADDR'=>'127.0.0.1'], [], $active)
    === ['locale'=>'de', 'country'=>'DE', 'ip_attribution'=>false], 'Private address did not use the fallback.');

echo "Verified country data, IPv4 and IPv6 ranges, rejected headers and addresses, and visitor preference priority.\n";
