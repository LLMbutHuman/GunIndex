<?php
declare(strict_types=1);
require_once __DIR__ . '/ip-country.php';

function i18n_locales():array {return ['de'=>'Deutsch','en'=>'English','fr'=>'Français','nl'=>'Nederlands','pl'=>'Polski','cs'=>'Čeština','da'=>'Dansk','it'=>'Italiano'];}
function i18n_locale(): string
{
    if (defined('GUNINDEX_PUBLIC_LOCALE')) return GUNINDEX_PUBLIC_LOCALE;
    $saved = $_SESSION['locale'] ?? null;
    if (is_string($saved) && isset(i18n_locales()[$saved])) return $saved;
    return defined('GUNINDEX_VISITOR_LOCALE') ? GUNINDEX_VISITOR_LOCALE : 'de';
}

function i18n_browser_preference(mixed $header): array
{
    $preference = ['locale'=>null, 'country'=>null];
    if (!is_string($header) || strlen($header) > 1024) return $preference;
    $locales = i18n_locales();
    $highest = 0.0;
    foreach (explode(',', $header) as $entry) {
        if (!preg_match('/\A([a-z]{2,3})((?:-[a-z0-9]{1,8}){0,4})(?:\s*;\s*q=(0(?:\.[0-9]{0,3})?|1(?:\.0{0,3})?))?\z/i', trim($entry), $parts)) continue;
        $locale = strtolower($parts[1]);
        $weight = isset($parts[3]) ? (float)$parts[3] : 1.0;
        if (!isset($locales[$locale]) || $weight <= $highest) continue;
        $country = null;
        if (preg_match('/\A(?:-[a-z]{4})?-([a-z]{2})(?:-|\z)/i', $parts[2], $region)) $country = strtoupper($region[1]);
        $preference = ['locale'=>$locale, 'country'=>$country];
        $highest = $weight;
    }
    return $preference;
}

/** Automatic hints never become saved browser or account preferences. */
function i18n_visitor_defaults(array $server, array $saved, array $activeCountries, ?string $pathLocale = null): array
{
    $locales = i18n_locales();
    $browser = i18n_browser_preference($server['HTTP_ACCEPT_LANGUAGE'] ?? null);
    $savedLocale = $saved['locale'] ?? null;
    $savedCountry = $saved['market_country'] ?? null;
    $locale = isset($locales[$pathLocale ?? '']) ? $pathLocale
        : (is_string($savedLocale) && isset($locales[$savedLocale]) ? $savedLocale : $browser['locale']);
    $country = is_string($savedCountry) && in_array($savedCountry, $activeCountries, true) ? $savedCountry : null;
    $ipCountry = $locale === null || $country === null ? ip_country_lookup($server) : null;
    $fromIp = $ipCountry !== null && ($locale === null || ($country === null && in_array($ipCountry, $activeCountries, true)));
    $locale ??= ip_country_locales()[$ipCountry ?? ''] ?? 'de';
    $country ??= in_array($ipCountry, $activeCountries, true) ? $ipCountry
        : (in_array($browser['country'], $activeCountries, true) ? $browser['country']
            : (in_array('DE', $activeCountries, true) ? 'DE' : ($activeCountries[0] ?? 'DE')));
    return ['locale'=>$locale, 'country'=>$country, 'ip_attribution'=>$fromIp];
}

function i18n_initialize_visitor(PDO $pdo): void
{
    if (defined('GUNINDEX_VISITOR_LOCALE')) return;
    $defaults = i18n_visitor_defaults($_SERVER, $_SESSION ?? [], array_keys(geo_active_countries($pdo)),
        defined('GUNINDEX_PUBLIC_LOCALE') ? GUNINDEX_PUBLIC_LOCALE : null);
    define('GUNINDEX_VISITOR_LOCALE', $defaults['locale']);
    define('GUNINDEX_VISITOR_MARKET_COUNTRY', $defaults['country']);
    define('GUNINDEX_VISITOR_IP_ATTRIBUTION', $defaults['ip_attribution']);
}

function i18n_ip_attribution_needed(): bool
{
    return defined('GUNINDEX_VISITOR_IP_ATTRIBUTION') && GUNINDEX_VISITOR_IP_ATTRIBUTION;
}
function i18n_dictionary(?string $locale=null):array {
    static $cache=[];$locale=$locale??i18n_locale();if(!isset(i18n_locales()[$locale]))$locale='de';
    if(!isset($cache[$locale])){
        $path=dirname(__DIR__).'/locales/'.$locale.'.php';$loaded=is_file($path)?require $path:[];$cache[$locale]=is_array($loaded)?$loaded:[];
        $cache[$locale] = array_replace($cache[$locale], require dirname(__DIR__).'/locales/carbon_ember/'.$locale.'.php');
        // Explicit trusted modules only; never discover PHP catalogs from user-controlled paths.
        foreach (['setup','personal','consent','account','shared','dashboard','auction_billing','auction_correction','maintenance','relisting','e2ee','trade','classification','listing_draft','listing_alert','sessions','user_security','profile_images','paypal_refunds','bank','bitcoin','mail_setup','escrow','dealer_team','dealer_stock','dealer_api','dealer_checkout','dealer_subscription','account_consent','payment_customer','finance_customer','billing_customer','finance_admin','promotion','admin_nav','admin_audit','admin_privacy','admin_settings','saved_search','legal_ui','wanted','admin_overview','identity','admin_listings','admin_users','admin_campaigns','market_policy','dealer_dashboard','listing_validation','auction_actions','banner_ads','marketplace_refinements','admin_roles_matrix','setup_wizard'] as $moduleName) {
            $modulePath=dirname(__DIR__).'/locales/'.$moduleName.'/'.$locale.'.php';
            $module=is_file($modulePath)?require $modulePath:[];
            if(is_array($module))$cache[$locale]=array_replace($cache[$locale],$module);
        }
    }return $cache[$locale];
}
/** Explicit interface strings only. Never call this on listing descriptions or messages. */
function t(string $key,array $parameters=[]):string {
    $dictionary=i18n_dictionary();$fallback=i18n_dictionary('de');$translated=$dictionary[$key]??$fallback[$key]??$key;
    foreach($parameters as $name=>$value)if(is_scalar($value))$translated=str_replace('{'.$name.'}',(string)$value,$translated);
    return $translated;
}

function i18n_number_separators(string $locale, int|float $whole): array
{
    $group = match ($locale) {
        'en' => ',',
        'fr' => "\u{202F}",
        'cs', 'pl' => "\u{00A0}",
        default => '.',
    };
    if (in_array($locale, ['pl', 'it'], true) && $whole < 10000) $group = '';
    return [$locale === 'en' ? '.' : ',', $group];
}
function i18n_setting(PDO $pdo,string $key,string $default=''):string {
    if(!in_array($key,['hero_title','hero_text','tagline'],true))return site_setting($pdo,$key,$default);
    $locale=i18n_locale();
    if($locale!=='de'){$q=$pdo->prepare('SELECT content FROM market_content_translations WHERE setting_key=? AND locale=?');$q->execute([$key,$locale]);$localized=$q->fetchColumn();if(is_string($localized)&&$localized!=='')return $localized;}
    $value=site_setting($pdo,$key,$default);$definition=site_setting_definitions()[$key]??null;
    // A custom operator text remains its authored language until a translation is entered.
    return $definition&&$value===$definition['default']?t($definition['translation'] ?? $value):$value;
}
function i18n_category(PDO $pdo,array $category):string {
    $locale=i18n_locale();if($locale==='de')return(string)($category['name']??$category['category_name']??'');
    static $cache=null;$cache??=new WeakMap();$all=$cache[$pdo]??[];
    if(!isset($all[$locale])){$q=$pdo->prepare('SELECT category_id,name FROM market_category_translations WHERE locale=? LIMIT 1001');$q->execute([$locale]);$all[$locale]=$q->fetchAll(PDO::FETCH_KEY_PAIR);$cache[$pdo]=$all;}
    $id=(int)($category['category_id']??$category['id']??0);
    return $all[$locale][$id]??t((string)($category['name']??$category['category_name']??''));
}

/** Recipient-language system text; never changes the acting browser session. */
function i18n_user_text(PDO $pdo,int $userId,string $key,array $parameters=[]):string {
    $q=$pdo->prepare('SELECT preferred_locale FROM users WHERE id=?');$q->execute([$userId]);$locale=$q->fetchColumn();
    if(!is_string($locale)||!isset(i18n_locales()[$locale]))$locale='de';
    $dictionary=i18n_dictionary($locale);$fallback=i18n_dictionary('de');$translated=$dictionary[$key]??$fallback[$key]??$key;
    foreach($parameters as $name=>$value)if(is_scalar($value))$translated=str_replace('{'.$name.'}',(string)$value,$translated);
    return $translated;
}
