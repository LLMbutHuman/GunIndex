<?php
declare(strict_types=1);
function i18n_locales():array {return ['de'=>'Deutsch','en'=>'English','fr'=>'Français','nl'=>'Nederlands','pl'=>'Polski','cs'=>'Čeština','da'=>'Dansk','it'=>'Italiano'];}
function i18n_locale():string {$locale=$_SESSION['locale']??'de';return is_string($locale)&&isset(i18n_locales()[$locale])?$locale:'de';}
function i18n_dictionary(?string $locale=null):array {
    static $cache=[];$locale=$locale??i18n_locale();if(!isset(i18n_locales()[$locale]))$locale='de';
    if(!isset($cache[$locale])){
        $path=dirname(__DIR__).'/locales/'.$locale.'.php';$loaded=is_file($path)?require $path:[];$cache[$locale]=is_array($loaded)?$loaded:[];
        // Explicit trusted modules only; never discover PHP catalogs from user-controlled paths.
        foreach (['setup','personal','consent','account','shared'] as $moduleName) {
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
function i18n_setting(PDO $pdo,string $key,string $default=''):string {
    if(!in_array($key,['hero_title','hero_text','tagline'],true))return site_setting($pdo,$key,$default);
    $locale=i18n_locale();
    if($locale!=='de'){$q=$pdo->prepare('SELECT content FROM market_content_translations WHERE setting_key=? AND locale=?');$q->execute([$key,$locale]);$localized=$q->fetchColumn();if(is_string($localized)&&$localized!=='')return $localized;}
    $value=site_setting($pdo,$key,$default);$definition=site_setting_definitions()[$key]??null;
    // A custom operator text remains its authored language until a translation is entered.
    return $definition&&$value===$definition['default']?t($value):$value;
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
