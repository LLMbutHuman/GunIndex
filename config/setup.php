<?php
declare(strict_types=1);
require_once __DIR__.'/i18n.php';
require_once __DIR__.'/worker_health.php';

/** Read-only setup diagnostics. Results contain booleans/counts, never secrets. */
function setup_status_labels(): array
{
    $labels = [];
    foreach (['configured','tested','missing','external','disabled','error'] as $status) $labels[$status] = t('setup.status.'.$status);
    return $labels;
}

/** realpath resolves symlinked ancestors; lexical prefix alone is insufficient. */
function setup_private_directory(string $directory, string $webroot): bool
{
    $real = realpath($directory);
    $public = realpath($webroot);
    if ($real === false || $public === false || !is_dir($real) || is_link($directory)
        || !is_readable($real) || !is_writable($real)) return false;
    $normalize = static fn(string $path): string => rtrim(str_replace('\\', '/', $path), '/');
    $real = $normalize($real);
    $public = $normalize($public);
    if (PHP_OS_FAMILY === 'Windows') { $real = strtolower($real); $public = strtolower($public); }
    if ($real === $public || str_starts_with($real, $public . '/')) return false;
    // Windows ACLs require an independent check; POSIX mode bits cannot certify them.
    if (PHP_OS_FAMILY === 'Windows') return false;
    $permissions = @fileperms($directory);
    return $permissions !== false && ($permissions & 0077) === 0;
}

function setup_verified_operation(string $path, int $now): ?string
{
    if (!is_file($path) || is_link($path)) return null;
    $stamp = @file_get_contents($path, false, null, 0, 32);
    if (!is_string($stamp) || !preg_match('/\A[1-9][0-9]{0,10}\n\z/', $stamp) || (int)$stamp > $now) return null;
    return gmdate('c', (int)$stamp);
}

function setup_collect(PDO $pdo, int $actor): array
{
    governance_assert_actor($pdo, $actor, 'site.manage');
    $settings = site_settings($pdo, true);
    $paypal=payments_config();$bank=bank_config();$bitcoin=bitcoin_config();
    $facts = ['checked_at'=>gmdate('c'), 'database'=>false, 'query_errors'=>[],
        'brand'=>trim($settings['brand_name'] ?? '') !== '' && filter_var($settings['contact_email'] ?? '', FILTER_VALIDATE_EMAIL) !== false,
        'operator'=>false, 'origin'=>false, 'https'=>false, 'mail_transport'=>'off',
        'mail_ready'=>false, 'keys'=>false, 'secure_cookies'=>(bool)(session_get_cookie_params()['secure'] ?? false),
        'manual_enabled'=>($settings['manual_paypal_enabled'] ?? '0') === '1',
        'manual_recipient'=>filter_var($settings['manual_paypal_recipient'] ?? '', FILTER_VALIDATE_EMAIL) !== false,
        'manual_ready'=>manual_payment_reason($pdo) === null,
        'paypal_enabled'=>$paypal['enabled'], 'paypal_ready'=>payments_paypal_reason($paypal) === null,
        'paypal_refund_enabled'=>$paypal['refunds_enabled'], 'paypal_refund_ready'=>paypal_refund_gate($pdo,$paypal) === null,
        'bank_enabled'=>$bank['enabled'], 'bank_ready'=>bank_gate($pdo,$bank) === null,
        'bitcoin_enabled'=>$bitcoin['enabled'], 'bitcoin_ready'=>bitcoin_gate($pdo,$bitcoin,true) === null,
        'finance_enabled'=>($settings['finance_enabled'] ?? '0') === '1',
        'legal_enabled'=>($settings['legal_publication_enabled'] ?? '0') === '1',
        'legal_binding'=>false,
        'storage'=>true, 'workers'=>[]];
    try { $facts['database'] = (int)$pdo->query('SELECT 1')->fetchColumn() === 1; } catch (Throwable) {}
    if($facts['legal_enabled']){
        try{
            $ready=true;
            foreach(['registration','checkout'] as $scope)foreach(array_keys(i18n_locales()) as $locale){if(consent_documents($pdo,$scope,$locale)===[])$ready=false;}
            $facts['legal_binding']=$ready;
        }catch(Throwable){}
    }
    try { finance_operator($pdo); $facts['operator'] = true; } catch (Throwable) {}
    try { $origin = mail_origin(); $facts['origin'] = true; $facts['https'] = str_starts_with($origin, 'https://'); } catch (Throwable) {}
    try {
        $transport = mail_runtime_config($pdo);
        $facts['mail_transport'] = $transport['mode'];
        $facts['mail_ready'] = mail_transport_reason($pdo, $transport) === null;
    } catch (Throwable) {}
    try { crypto_master_keys(); $facts['keys'] = extension_loaded('sodium'); } catch (Throwable) {}
    $queries = [
        'countries'=>'SELECT COUNT(*) FROM market_countries WHERE is_active=1',
        'countries_without_geo'=>'SELECT COUNT(*) FROM market_countries c WHERE c.is_active=1 AND NOT EXISTS (SELECT 1 FROM geo_locations l JOIN geo_imports i ON i.id=l.import_id WHERE l.country_code=c.code AND i.is_active=1 AND i.license_reviewed=1)',
        'categories'=>'SELECT COUNT(*) FROM market_categories WHERE is_active=1',
        'tax_countries'=>'SELECT COUNT(*) FROM market_tax_rules',
        'countries_without_tax'=>'SELECT COUNT(*) FROM market_countries c WHERE c.is_active=1 AND NOT EXISTS (SELECT 1 FROM market_tax_rules t WHERE t.country_code=c.code)',
        'legal_approved'=>"SELECT COUNT(*) FROM market_legal_documents WHERE status='approved' AND approved_at IS NOT NULL AND effective_at<=UTC_TIMESTAMP()",
        'mail_failed'=>"SELECT COUNT(*) FROM market_mail_queue WHERE status='failed'",
    ];
    foreach ($queries as $key => $sql) {
        try { $facts[$key] = (int)$pdo->query($sql)->fetchColumn(); }
        catch (Throwable) { $facts[$key] = null; $facts['query_errors'][] = $key; }
    }
    $webroot = dirname(__DIR__).'/public';
    foreach ([getenv('AUTH_RATE_LIMIT_DIR') ?: '/var/lib/gunindex/limits',
        getenv('GENERAL_IMAGE_DIR') ?: '/var/lib/gunindex/images',
        getenv('GENERAL_PRIVATE_DIR') ?: '/var/lib/gunindex/private'] as $directory) {
        if (!setup_private_directory($directory, $webroot)) $facts['storage'] = false;
    }
    $now = time();
    $facts['backup_verified_at'] = setup_verified_operation('/run/gunindex-ops/backup.verified', $now);
    $facts['restore_verified_at'] = setup_verified_operation('/run/gunindex-ops/restore.verified', $now);
    foreach (worker_heartbeat_limits() as $worker=>$maximum) {
        $base = '/var/lib/gunindex/heartbeats/'.$worker;
        $facts['workers'][$worker] = worker_heartbeat_status(worker_read_heartbeat($base.'.ok'), worker_read_heartbeat($base.'.failed'), $now, $maximum)['ready'];
    }
    return $facts;
}

/** Pure presentation model: missing evidence never becomes a successful test. */
function setup_steps(array $facts): array
{
    $yes = static fn(string $key): bool => ($facts[$key] ?? false) === true;
    $count = static fn(string $key): ?int => isset($facts[$key]) && is_int($facts[$key]) && $facts[$key] >= 0 ? $facts[$key] : null;
    $steps = [];
    $add = static function (string $id, string $status, array $parameters = [], ?string $href = null, string $permission = 'site.manage') use (&$steps): void {
        $title = t('setup.'.$id.'.title');
        $detail = t('setup.'.$id.'.detail', $parameters);
        $next = t('setup.'.$id.'.next');
        $steps[] = compact('id','title','status','detail','next','href','permission');
    };
    $add('brand', $yes('brand')?'configured':'missing', [], '/admin/settings.php');
    $add('operator', $yes('operator')?'configured':'missing', [], '/admin/settings.php');
    $add('https', $yes('origin')?($yes('https')?'external':'disabled'):'missing');

    $transport = $facts['mail_transport'] ?? 'none';
    $mailStatus = $transport === 'test' ? 'disabled' : ($yes('mail_ready') && in_array($transport, ['sendmail', 'smtp'], true) ? 'external' : 'missing');
    if (($count('mail_failed') ?? 0) > 0) $mailStatus = 'error';
    $add('mail', $mailStatus, [], '/admin/mail-setup.php');

    $geoStatus = $count('countries') === null || $count('countries_without_geo') === null ? 'error'
        : (($count('countries') > 0 && $count('countries_without_geo') === 0) ? 'configured' : 'missing');
    $add('geography', $geoStatus, ['countries'=>$count('countries') ?? t('setup.unknown'), 'missing'=>$count('countries_without_geo') ?? t('setup.unknown')], '/admin/geography.php');
    $add('categories', $count('categories') === null?'error':($count('categories')>0?'configured':'missing'),
        ['categories'=>$count('categories') ?? t('setup.unknown')], '/admin/categories.php', 'categories.manage');
    $add('fees', 'external', [], '/admin/settings.php');

    $manual = !$yes('manual_recipient') ? 'missing' : (!$yes('manual_enabled') ? 'disabled' : ($yes('manual_ready') ? 'external' : 'error'));
    $add('paypal', $manual, [], '/admin/settings.php');
    $providerStates=[];
    foreach (['paypal','paypal_refund','bank','bitcoin'] as $provider) {
        $providerStates[$provider]=!$yes($provider.'_enabled')?'disabled':($yes($provider.'_ready')?'external':'error');
    }
    $providerStates['escrow']='disabled';
    $providerStatus=in_array('error',$providerStates,true)?'error':(in_array('external',$providerStates,true)?'external':'disabled');
    $add('providers', $providerStatus, [], '/admin/payments.php', 'finance.manage');
    $steps[array_key_last($steps)]['providers']=$providerStates;
    $finance = !$yes('finance_enabled') ? 'disabled'
        : (!$yes('operator') || $count('countries_without_tax') === null || $count('countries_without_tax') > 0 ? 'error' : 'external');
    $add('finance', $finance, ['rules'=>$count('tax_countries') ?? t('setup.unknown')], '/admin/finance.php', 'finance.manage');
    $add('legal', !$yes('legal_enabled')?'disabled':(!$yes('legal_binding')?'missing':(($count('legal_approved') ?? 0)>0?'external':'error')),
        ['documents'=>$count('legal_approved') ?? t('setup.unknown')], '/admin/legal.php', 'legal.manage');
    $add('security', $yes('keys')&&$yes('secure_cookies')?'configured':'error', [], '/admin/users.php', 'users.manage');
    $add('storage', $yes('storage')?'configured':'error');

    $workers = $facts['workers'] ?? [];
    $workerReady = true;
    foreach (worker_heartbeat_limits() as $worker=>$maximum) if (($workers[$worker] ?? false) !== true) $workerReady = false;
    $add('workers', $workerReady?'tested':'error');
    $backup = $facts['backup_verified_at'] ?? null;
    $restore = $facts['restore_verified_at'] ?? null;
    $add('backups', $backup !== null && $restore !== null ? 'tested' : ($backup !== null ? 'configured' : 'external'),
        ['backup'=>$backup ?? t('setup.unknown'), 'restore'=>$restore ?? t('setup.unknown')]);
    $add('monitoring', 'external');
    $add('tests', 'external');
    $add('acceptance', $yes('database')&&empty($facts['query_errors'])?'external':'error', ['reachable'=>t($yes('database')?'setup.yes':'setup.no')]);
    return $steps;
}
