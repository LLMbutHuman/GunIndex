<?php
declare(strict_types=1);
require_once __DIR__ . '/i18n.php';

/** Locale prefixes apply exclusively to these public document routes. */
function seo_public_route(string $path): ?array
{
    if (!preg_match('~\A/([a-z]{2})(/|/marketplace/view-(?:item|auction)\.php)\z~', $path, $matches)
        || !isset(i18n_locales()[$matches[1]])) return null;
    return ['locale'=>$matches[1], 'path'=>$matches[2]];
}

function seo_localized_path(string $path, string $locale): string
{
    if (!isset(i18n_locales()[$locale]) || ($path !== '/' && !preg_match('~\A/marketplace/view-(?:item|auction)\.php\?id=[1-9][0-9]{0,18}\z~', $path))) {
        throw new InvalidArgumentException('Invalid public localized path.');
    }
    return '/' . $locale . $path;
}

function seo_switch_path(string $return, string $locale): string
{
    $parts = parse_url($return);
    if (!is_array($parts) || !isset($parts['path']) || !isset(i18n_locales()[$locale])) return $return;
    $path = rawurldecode($parts['path']);
    $route = seo_public_route($path);
    if ($route !== null) $path = $route['path'];
    if (in_array($path, ['/', '/index.php', '/marketplace/index.php'], true)) $path = '/';
    if (!in_array($path, ['/', '/marketplace/view-item.php', '/marketplace/view-auction.php'], true)) return $return;
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    return '/' . $locale . $path . $query . $fragment;
}

function seo_alternates(string $origin, string $path): array
{
    $links = [];
    foreach (array_keys(i18n_locales()) as $locale) $links[$locale] = $origin . seo_localized_path($path, $locale);
    $links['x-default'] = $links['de'];
    return $links;
}

/** Only a configured HTTPS origin is trusted; never derive URLs from Host headers. */
function seo_origin(?string $configured = null): ?string
{
    $value = $configured ?? (string)getenv('APP_URL');
    if ($value === '' || strlen($value) > 300 || preg_match('/[\x00-\x20\x7f\\\\]/', $value)) return null;
    $parts = parse_url($value);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user'], $parts['pass'])
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
        || !in_array($parts['path'] ?? '', ['', '/'], true) || (isset($parts['port']) && $parts['port'] !== 443)) return null;
    $host = strtolower($parts['host'] ?? '');
    if (!preg_match('/\.[a-z]{2,63}\z/', $host) || !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
        || preg_match('/\.(?:localhost|test|invalid|example|local|internal)\z/', $host)
        || preg_match('/\A(?:local|localhost|dev|development|test|testing|stage|staging|preview|demo|sandbox)[.-]/', $host)) return null;
    return 'https://' . $host;
}

/** Index only HTTPS requests on the configured production hostname. */
function seo_request_origin(array $server, ?string $configured = null, ?string $environment = null, ?string $database = null): ?string
{
    $origin = seo_origin($configured);
    $environment ??= (string)getenv('APP_ENV');
    $database ??= (string)getenv('DB_DATABASE');
    $host = $server['HTTP_HOST'] ?? null;
    if ($origin === null || $environment !== 'production' || $database === '' || str_ends_with(strtolower($database), '_test')
        || ($server['HTTPS'] ?? '') !== 'on' || !is_string($host)) return null;
    $configuredHost = parse_url($origin, PHP_URL_HOST);
    return in_array(strtolower($host), [$configuredHost, $configuredHost . ':443'], true) ? $origin : null;
}

function seo_gate(array $settings, ?string $origin): bool
{
    return $origin !== null && ($settings['seo_indexing_enabled'] ?? '0') === '1'
        && ($settings['maintenance_enabled'] ?? '0') === '0';
}

function seo_enabled(PDO $pdo): bool
{
    return seo_gate(site_settings($pdo), seo_request_origin($_SERVER)) && setup_installation_state($pdo) !== 'pending';
}

function seo_listing_path(array $listing): ?string
{
    $id = $listing['id'] ?? null;
    if ((!is_int($id) && !is_string($id)) || !preg_match('/\A[1-9][0-9]{0,18}\z/', (string)$id)
        || !in_array($listing['type'] ?? null, ['fixed', 'auction', 'negotiated'], true)) return null;
    return '/marketplace/view-' . ($listing['type'] === 'auction' ? 'auction' : 'item') . '.php?id=' . $id;
}

/** Caller must first establish public visibility through market_find or sitemap SQL. */
function seo_listing_current(array $listing, ?int $now = null): bool
{
    if (seo_listing_path($listing) === null || ($listing['status'] ?? '') !== 'active'
        || !catalog_text_allowed(($listing['title'] ?? '') . ' ' . ($listing['description'] ?? ''))) return false;
    if ($listing['type'] === 'auction' || ($listing['ends_at']??null)!==null) {
        $end = $listing['ends_at'] ?? null;
        if (!is_string($end) || !preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $end)) return false;
        $timestamp = strtotime($end . ' UTC');
        if ($timestamp === false || $timestamp <= ($now ?? time())) return false;
    }
    return true;
}

function seo_product_data(array $listing, string $origin, ?string $locale = null): ?array
{
    if (seo_origin($origin) !== $origin || !seo_listing_current($listing)) return null;
    $path = seo_listing_path($listing);
    $url = $origin . ($locale === null ? $path : seo_localized_path($path, $locale));
    $product = ['@context' => 'https://schema.org', '@type' => 'Product', 'url' => $url,
        'name' => (string)$listing['title'], 'description' => (string)$listing['description']];
    $condition = ['new' => 'NewCondition', 'like_new' => 'UsedCondition', 'used' => 'UsedCondition'][$listing['condition_label'] ?? ''] ?? null;
    if ($condition !== null) $product['itemCondition'] = 'https://schema.org/' . $condition;
    $images = min(5, max(0, (int)($listing['image_count'] ?? 0)));
    if ($images > 0) {
        $product['image'] = [];
        for ($i = 0; $i < $images; $i++) $product['image'][] = $origin . '/marketplace/image.php?id=' . $listing['id'] . '&position=' . $i . '&size=large';
    }
    // A current bid is not an available fixed sale price. No auction Offer is invented.
    if ($listing['type'] === 'fixed' && isset($listing['price_cents'])
        && preg_match('/\A[1-9][0-9]{0,9}\z/', (string)$listing['price_cents'])) {
        $cents = (int)$listing['price_cents'];
        $product['offers'] = ['@type' => 'Offer', 'url' => $url, 'priceCurrency' => 'EUR',
            'price' => intdiv($cents, 100) . '.' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT),
            'availability' => ($listing['stock_available'] ?? true) === false ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock'];
    }
    return $product;
}

function seo_json(array $data): string
{
    return json_encode($data, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

function seo_description(string $description): string
{
    $description = trim(preg_replace('/\s+/u', ' ', $description) ?? '');
    preg_match('/\A.{0,160}/u', $description, $excerpt);
    return $excerpt[0] ?? '';
}

function seo_listing_breadcrumbs(array $listing, string $origin, string $locale, string $canonical): array
{
    $dictionary = i18n_dictionary($locale);
    $items = [['name'=>$dictionary['Entdecken'] ?? 'Entdecken', 'item'=>$origin . seo_localized_path('/', $locale)]];
    $category = $listing['category_id'] ?? null;
    $categoryName = $listing['seo_category_name'] ?? null;
    if ((is_int($category) || is_string($category)) && preg_match('/\A[1-9][0-9]{0,9}\z/', (string)$category)
        && is_string($categoryName) && $categoryName !== '') {
        $items[] = ['name'=>$categoryName, 'item'=>$origin . '/marketplace/search.php?category_id=' . $category];
    }
    $items[] = ['name'=>(string)$listing['title'], 'item'=>$canonical];
    $elements = [];
    foreach ($items as $position => $item) $elements[] = ['@type'=>'ListItem', 'position'=>$position + 1] + $item;
    return ['@context'=>'https://schema.org', '@type'=>'BreadcrumbList', 'itemListElement'=>$elements];
}

function seo_page(array $settings, string $requestPath, ?array $listing = null, ?string $configured = null, string $locale = 'de'): array
{
    $origin = seo_origin($configured);
    $result = ['index'=>false, 'canonical'=>null, 'structured'=>null, 'alternates'=>[], 'breadcrumbs'=>null];
    if (!seo_gate($settings, $origin)) return $result;
    $route = seo_public_route($requestPath);
    if ($route !== null) {
        $locale = $route['locale'];
        $requestPath = $route['path'];
    }
    if (!isset(i18n_locales()[$locale])) return $result;
    if (in_array($requestPath, ['/', '/index.php', '/marketplace/index.php'], true)) {
        $alternates = seo_alternates($origin, '/');
        return ['index'=>true, 'canonical'=>$alternates[$locale], 'structured'=>null, 'alternates'=>$alternates, 'breadcrumbs'=>null];
    }
    if ($listing !== null && seo_listing_current($listing)) {
        $path = seo_listing_path($listing);
        if ($requestPath === parse_url($path, PHP_URL_PATH)) {
            $alternates = seo_alternates($origin, $path);
            $canonical = $alternates[$locale];
            return ['index'=>true, 'canonical'=>$canonical, 'structured'=>seo_product_data($listing, $origin, $locale),
                'alternates'=>$alternates, 'breadcrumbs'=>seo_listing_breadcrumbs($listing, $origin, $locale, $canonical)];
        }
    }
    return $result;
}

function seo_robots(bool $enabled, ?string $origin): string
{
    if (!$enabled || $origin === null || seo_origin($origin) !== $origin) return "User-agent: *\nDisallow: /\n";
    return "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /user/\nDisallow: /auth/\nDisallow: /actions/\nDisallow: /marketplace/search.php\nSitemap: " . $origin . "/sitemap.xml\n";
}

function seo_sitemap_page(mixed $value): ?int
{
    if ($value === null) return null;
    if (!is_string($value) || !preg_match('/\A[1-9][0-9]{0,4}\z/', $value) || (int)$value > 50000) throw new InvalidArgumentException('Invalid sitemap page.');
    return (int)$value;
}

/** Values originate only in the new public general-goods catalog. */
function seo_sitemap_condition(PDO $pdo): array
{
    $categories = market_category_ids($pdo);
    if (!$categories) return ['1=0', []];
    $sql = "l.status='active' AND l.type IN ('fixed','auction','negotiated') AND u.is_active=1 AND mc.is_active=1"
        . " AND ((l.type<>'auction' AND l.ends_at IS NULL) OR l.ends_at>UTC_TIMESTAMP())"
        . ' AND l.category_id IN (' . implode(',', array_fill(0, count($categories), '?')) . ')';
    return [$sql, $categories];
}

function seo_sitemap_rows(PDO $pdo, ?int $page): array
{
    [$condition, $parameters] = seo_sitemap_condition($pdo);
    $from = ' FROM market_listings l JOIN users u ON u.id=l.seller_id JOIN market_countries mc ON mc.code=l.country_code WHERE ' . $condition;
    $count = $pdo->prepare('SELECT COUNT(*)' . $from);
    $count->execute($parameters);
    $total = (int)$count->fetchColumn();
    $pages = max(1, (int)ceil($total / 1000));
    if ($pages > 50000) throw new RuntimeException('Sitemap partition limit exceeded.');
    if ($page === null) return ['pages' => $pages, 'items' => []];
    if ($page < 1 || $page > $pages) throw new OutOfBoundsException('Sitemap page not found.');
    $query = $pdo->prepare('SELECT l.id,l.type,l.status,l.title,l.description,l.ends_at,l.updated_at' . $from . ' ORDER BY l.id LIMIT 1000 OFFSET ' . (($page - 1) * 1000));
    $query->execute($parameters);
    return ['pages' => $pages, 'items' => array_values(array_filter($query->fetchAll(), 'seo_listing_current'))];
}

function seo_xml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

function seo_sitemap_entries(string $origin, string $path, ?string $updatedAt = null): string
{
    $alternates = seo_alternates($origin, $path);
    $links = '';
    foreach ($alternates as $locale=>$url) {
        $links .= '<xhtml:link rel="alternate" hreflang="' . $locale . '" href="' . seo_xml($url) . '" />';
    }
    unset($alternates['x-default']);
    $entries = '';
    foreach ($alternates as $url) {
        $entries .= '<url><loc>' . seo_xml($url) . '</loc>' . ($updatedAt === null ? '' : '<lastmod>' . seo_xml($updatedAt) . '</lastmod>') . $links . '</url>';
    }
    return $entries;
}

function seo_sitemap_document(string $origin, ?int $page, array $data): string
{
    if (seo_origin($origin) !== $origin) throw new InvalidArgumentException('Invalid sitemap origin.');
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    if ($page === null) {
        $pages = $data['pages'] ?? 0;
        if (!is_int($pages) || $pages < 1 || $pages > 50000) throw new InvalidArgumentException('Invalid sitemap partitions.');
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        for ($i = 1; $i <= $pages; $i++) $xml .= '<sitemap><loc>' . seo_xml($origin . '/sitemap.xml?page=' . $i) . '</loc></sitemap>';
        return $xml . '</sitemapindex>';
    }
    if ($page < 1 || $page > 50000 || !is_array($data['items'] ?? null) || count($data['items']) > 1000) throw new InvalidArgumentException('Invalid sitemap rows.');
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
    if ($page === 1) $xml .= seo_sitemap_entries($origin, '/');
    foreach ($data['items'] as $listing) {
        if (!seo_listing_current($listing)) continue;
        $updated = $listing['updated_at'] ?? '';
        $lastmod = is_string($updated) && preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $updated) && strtotime($updated . ' UTC') !== false
            ? str_replace(' ', 'T', $updated) . 'Z' : null;
        $xml .= seo_sitemap_entries($origin, seo_listing_path($listing), $lastmod);
    }
    return $xml . '</urlset>';
}
