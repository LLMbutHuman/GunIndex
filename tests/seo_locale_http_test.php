<?php
declare(strict_types=1);

/** Sequential public HTTP checks against the isolated synthetic database. */
if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY === 'Windows' || getenv('APP_ENV') !== 'development'
    || getenv('DB_DATABASE') !== 'gunindex_rebuild_test') {
    if (PHP_SAPI !== 'cli') http_response_code(404);
    exit(2);
}
require_once __DIR__ . '/../config/bootstrap.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'gunindex_rebuild_test') exit(2);

function seo_http_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function seo_http_browser(): CurlHandle
{
    $browser = curl_init();
    curl_setopt_array($browser, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => '',
        CURLOPT_PROXY => '',
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PATH_AS_IS => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    return $browser;
}

function seo_http_request(CurlHandle $browser, string $path, string $method = 'GET', array $form = [], array $requestHeaders = []): array
{
    $headers = [];
    curl_setopt_array($browser, [
        CURLOPT_URL => 'http://127.0.0.1:8081' . $path,
        CURLOPT_NOBODY => false,
        CURLOPT_HTTPGET => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_HEADERFUNCTION => static function (CurlHandle $handle, string $line) use (&$headers): int {
            if (str_starts_with($line, 'HTTP/')) $headers = [];
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
            return strlen($line);
        },
    ]);
    if ($method === 'HEAD') curl_setopt($browser, CURLOPT_NOBODY, true);
    if ($method === 'POST') curl_setopt($browser, CURLOPT_POSTFIELDS, http_build_query($form));
    $body = curl_exec($browser);
    seo_http_check(is_string($body), "Isolated HTTP request failed for $method $path.");
    return ['status' => (int)curl_getinfo($browser, CURLINFO_RESPONSE_CODE), 'headers' => $headers, 'body' => $body];
}

function seo_http_closed_index(array $response): void
{
    seo_http_check(($response['headers']['x-robots-tag'] ?? '') === 'noindex, nofollow', 'The closed index gate lost its robots header.');
    if ($response['body'] === '') return;
    seo_http_check(str_contains($response['body'], '<meta name="robots" content="noindex, nofollow">'), 'The closed index gate lost its robots metadata.');
    seo_http_check(!preg_match('~<link\b[^>]*\bhreflang\s*=~i', $response['body']), 'The closed index gate exposed an alternate link.');
}

function seo_http_localized(array $response, string $locale, bool $head = false): void
{
    seo_http_check($response['status'] === 200, "The $locale page returned HTTP " . $response['status'] . '.');
    seo_http_check(($response['headers']['content-language'] ?? '') === $locale, "Incorrect Content-Language for $locale.");
    if ($head) {
        seo_http_check($response['body'] === '', "The $locale HEAD request returned a body.");
    } else {
        seo_http_check(str_contains($response['body'], '<html lang="' . $locale . '"'), "Incorrect HTML language for $locale.");
    }
    seo_http_closed_index($response);
}

function seo_http_fixture(PDO $pdo): array
{
    $suffix = bin2hex(random_bytes(8));
    $sellerName = 'test_seo_http_' . $suffix;
    $title = 'Synthetic wooden table ' . $suffix;
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO users(username,email,password_hash) VALUES (?,?,?)')
            ->execute([$sellerName, $sellerName . '@example.invalid', 'disabled-synthetic-password']);
        $seller = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO market_categories(name,slug,is_active) VALUES ('Synthetic household goods',?,1)")
            ->execute(['seo-http-' . $suffix]);
        $category = (int)$pdo->lastInsertId();
        $insert = $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code,ends_at) VALUES (?,?,?,'Synthetic listing for isolated locale routing tests.',?,2575,'active','10115','Synthetic City','DE',?)");
        $insert->execute([$seller, $category, $title, 'fixed', null]);
        $fixed = (int)$pdo->lastInsertId();
        $insert->execute([$seller, $category, 'Synthetic floor lamp ' . $suffix, 'auction', gmdate('Y-m-d H:i:s', time() + 3600)]);
        $auction = (int)$pdo->lastInsertId();
        $pdo->commit();
        return ['seller' => $seller, 'category' => $category, 'fixed' => $fixed, 'auction' => $auction, 'title' => $title];
    } catch (Throwable $failure) {
        $pdo->rollBack();
        throw $failure;
    }
}

seo_http_check(geo_country_enabled($pdo, 'DE'), 'The isolated suite needs the default DE market.');
$fixture = seo_http_fixture($pdo);
$itemPath = '/marketplace/view-item.php?id=' . $fixture['fixed'];
$auctionPath = '/marketplace/view-auction.php?id=' . $fixture['auction'];
try {
    foreach (['de', 'en', 'fr', 'nl', 'pl', 'cs', 'da', 'it'] as $locale) {
        $browser = seo_http_browser();
        $home = seo_http_request($browser, '/' . $locale . '/');
        seo_http_localized($home, $locale);
        $localizedListing = '/' . $locale . $itemPath;
        seo_http_check(str_contains($home['body'], 'href="' . $localizedListing . '"'), "The $locale homepage did not link to the localized test listing.");
        $listing = seo_http_request(seo_http_browser(), $localizedListing);
        seo_http_localized($listing, $locale);
        seo_http_check(str_contains($listing['body'], $fixture['title']), "The $locale listing did not preserve its original title.");
        $editorialPath = '/' . $locale . '/fachwissen';
        $editorial = seo_http_request($browser, $editorialPath . '?country=DE&status=pending');
        seo_http_localized($editorial, $locale);
        seo_http_check(str_contains($home['body'], 'href="' . $editorialPath . '"')
            && substr_count($editorial['body'], 'class="panel editorial-topic"') === 7, 'Localized editorial route is incomplete.');
    }
    $editorialPost = seo_http_request(seo_http_browser(), '/de/fachwissen', 'POST', ['status'=>'exempt']);
    seo_http_check($editorialPost['status'] === 405, 'The editorial route accepted a write request.');
    echo "PASS eight_independent_locale_homes_listings_and_editorial_pages\n";

    $switcher = seo_http_browser();
    $before = '/fr' . $itemPath . '&origin_country=DE';
    $after = '/it' . $itemPath . '&origin_country=DE';
    $page = seo_http_request($switcher, $before);
    seo_http_localized($page, 'fr');
    seo_http_check(preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $page['body'], $token) === 1, 'Language-switch CSRF form missing.');
    $changed = seo_http_request($switcher, '/user/language.php', 'POST', [
        'csrf_token' => $token[1],
        'locale' => 'it',
        'market_country' => 'DE',
        'return_path' => $before,
    ]);
    seo_http_check($changed['status'] === 303 && ($changed['headers']['location'] ?? '') === $after, 'The language switch lost the listing or its query.');
    seo_http_localized(seo_http_request($switcher, $after), 'it');
    seo_http_localized(seo_http_request(seo_http_browser(), $after), 'it');
    seo_http_localized(seo_http_request($switcher, $before), 'fr');
    $oldListing = seo_http_request($switcher, $itemPath);
    seo_http_check($oldListing['status'] === 200 && str_contains($oldListing['body'], $fixture['title']), 'The original listing URL stopped serving the listing.');
    seo_http_closed_index($oldListing);
    echo "PASS language_switch_query_reloads_and_independent_cookie_jars\n";

    foreach (['/', '/index.php', '/marketplace/index.php'] as $alias) {
        $home = seo_http_request(seo_http_browser(), $alias);
        seo_http_check($home['status'] === 200, "The homepage alias $alias stopped responding.");
        seo_http_closed_index($home);
    }
    $head = seo_http_request(seo_http_browser(), '/en' . $itemPath, 'HEAD');
    seo_http_localized($head, 'en', true);
    echo "PASS legacy_home_aliases_and_localized_head\n";

    $browser = seo_http_browser();
    seo_http_localized(seo_http_request($browser, '/', 'GET', [], ['Accept-Language: nl-NL']), 'nl');
    seo_http_localized(seo_http_request($browser, '/', 'GET', [], ['Accept-Language: fr-FR']), 'fr');
    seo_http_localized(seo_http_request($browser, '/it/', 'GET', [], ['Accept-Language: fr-FR']), 'it');
    $forwarded = seo_http_request($browser, '/', 'GET', [], ['Forwarded: for=80.12.0.20', 'X-Forwarded-For: 80.12.0.20', 'CF-IPCountry: FR']);
    seo_http_localized($forwarded, 'de');
    seo_http_check(!str_contains($forwarded['body'], 'IP Geolocation by DB-IP'), 'Spoofed forwarded headers triggered IP attribution.');
    seo_http_localized(seo_http_request($switcher, '/', 'GET', [], ['Accept-Language: nl-NL']), 'it');
    echo "PASS browser_defaults_do_not_persist_and_proxy_headers_do_not_set_location\n";

    $previousIndexing = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='seo_indexing_enabled'")->fetchColumn();
    try {
        $pdo->exec("INSERT INTO system_settings(setting_key,setting_value) VALUES ('seo_indexing_enabled','1') ON DUPLICATE KEY UPDATE setting_value='1'");
        $forgedHttps = ['X-Forwarded-Proto: https', 'X-Forwarded-Host: market.example.org'];
        seo_http_closed_index(seo_http_request($browser, '/de/', 'GET', [], $forgedHttps));
        $sitemap = seo_http_request($browser, '/sitemap.xml', 'GET', [], $forgedHttps);
        seo_http_check($sitemap['status'] === 404 && ($sitemap['headers']['x-robots-tag'] ?? '') === 'noindex, nofollow', 'Development IP published the sitemap.');
        $robots = seo_http_request($browser, '/robots.txt', 'GET', [], $forgedHttps);
        seo_http_check($robots['status'] === 200 && $robots['body'] === "User-agent: *\nDisallow: /\n", 'Development IP advertised crawler access.');
    } finally {
        if ($previousIndexing === false) {
            $pdo->exec("DELETE FROM system_settings WHERE setting_key='seo_indexing_enabled'");
        } else {
            $pdo->prepare("UPDATE system_settings SET setting_value=? WHERE setting_key='seo_indexing_enabled'")->execute([$previousIndexing]);
        }
    }
    echo "PASS enabled_preference_does_not_open_the_development_ip_or_forged_https\n";

    $auction = seo_http_request(seo_http_browser(), '/pl/marketplace/view-item.php?id=' . $fixture['auction']);
    $fixed = seo_http_request(seo_http_browser(), '/da/marketplace/view-auction.php?id=' . $fixture['fixed']);
    seo_http_check($auction['status'] === 303 && ($auction['headers']['location'] ?? '') === '/pl' . $auctionPath, 'The auction redirect lost its Polish prefix.');
    seo_http_check($fixed['status'] === 303 && ($fixed['headers']['location'] ?? '') === '/da' . $itemPath, 'The fixed-price redirect lost its Danish prefix.');
    seo_http_localized(seo_http_request(seo_http_browser(), '/pl' . $auctionPath), 'pl');
    echo "PASS listing_type_redirects_preserve_the_requested_locale\n";

    $invalid = seo_http_browser();
    foreach (['/zz/', '/zz' . $itemPath, '/en/admin/index.php', '/en/en/', '/en/%2e%2e/', '/en/marketplace/%2E%2E/%2E%2E/'] as $path) {
        seo_http_check(seo_http_request($invalid, $path)['status'] === 404, "The router accepted the invalid localized path $path.");
    }
    foreach (['/en/', '/en' . $itemPath] as $path) {
        seo_http_check(seo_http_request($invalid, $path, 'POST')['status'] === 405, "The localized public route $path accepted a POST.");
    }
    echo "PASS invalid_prefixes_private_paths_traversal_and_post_rejected\n";
    foreach (array_keys(i18n_locales()) as $locale) {
        $path='/'.$locale.'/unavailable-test-page';
        $copy=i18n_dictionary($locale);
        $missing=seo_http_request($invalid,$path);
        seo_http_check($missing['status']===404 && $missing['body']===$copy['shared.route_missing']
            && ($missing['headers']['content-language']??'')===$locale
            && ($missing['headers']['x-robots-tag']??'')==='noindex, nofollow', 'Incorrect localized missing-page response.');
        $head=seo_http_request($invalid,$path,'HEAD');
        seo_http_check($head['status']===404 && $head['body']==='' && ($head['headers']['content-language']??'')===$locale, 'Incorrect missing-page HEAD response.');
        $method=seo_http_request($invalid,'/'.$locale.'/','POST');
        seo_http_check($method['status']===405 && $method['body']===$copy['shared.route_method']
            && ($method['headers']['allow']??'')==='GET, HEAD', 'Incorrect localized method response.');
    }
    echo "PASS missing_route_and_method_responses_in_eight_languages\n";
} finally {
    $pdo->prepare('DELETE FROM market_listings WHERE id IN (?,?) AND seller_id=?')
        ->execute([$fixture['fixed'], $fixture['auction'], $fixture['seller']]);
    $pdo->prepare('DELETE FROM market_categories WHERE id=?')->execute([$fixture['category']]);
    $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$fixture['seller']]);
}
