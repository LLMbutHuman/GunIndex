<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__.'/../config/setup.php';
function setup_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function setup_step(array $facts, string $id): array {
    foreach (setup_steps($facts) as $step) if ($step['id'] === $id) return $step;
    throw new RuntimeException('Missing step');
}
$tests = [
    'eight_complete_catalogs_with_placeholder_parity' => static function (): void {
        $base = require __DIR__.'/../locales/setup/de.php';
        setup_assert(count($base) === 103, 'Unexpected setup catalog coverage.');
        foreach (i18n_locales() as $locale=>$label) {
            $catalog = require __DIR__.'/../locales/setup/'.$locale.'.php';
            setup_assert(array_keys($catalog) === array_keys($base), 'Missing or unexpected setup translation in '.$locale);
            foreach ($base as $key=>$source) {
                setup_assert(str_starts_with($key, 'setup.'), 'Module key must be namespaced.');
                setup_assert(is_string($catalog[$key]) && trim($catalog[$key]) !== '' && preg_match('//u', $catalog[$key]) === 1, 'Invalid translation in '.$locale);
                preg_match_all('/\{[a-z_]+\}/', $source, $expected);
                preg_match_all('/\{[a-z_]+\}/', $catalog[$key], $actual);
                sort($expected[0]); sort($actual[0]);
                setup_assert($expected[0] === $actual[0], 'Changed translation parameters in '.$locale.' '.$key);
                setup_assert((i18n_dictionary($locale)[$key] ?? null) === $catalog[$key], 'Explicit module was not loaded.');
            }
        }
    },
    'all_locales_render_every_step_without_fallback_or_unbound_parameters' => static function (): void {
        $previous = $_SESSION['locale'] ?? null;
        $facts = ['countries'=>11,'countries_without_geo'=>2,'categories'=>123,'tax_countries'=>4,'legal_approved'=>6,'database'=>true];
        $baseline = null;
        try {
            foreach (i18n_locales() as $locale=>$label) {
                $_SESSION['locale'] = $locale;
                $catalog = require __DIR__.'/../locales/setup/'.$locale.'.php';
                $steps = setup_steps($facts);
                $statuses = array_column($steps, 'status', 'id');
                $baseline ??= $statuses;
                setup_assert($statuses === $baseline, 'Language changed readiness semantics.');
                foreach ($steps as $step) {
                    setup_assert($step['title'] === $catalog['setup.'.$step['id'].'.title'], 'Title silently fell back.');
                    setup_assert($step['next'] === $catalog['setup.'.$step['id'].'.next'], 'Instruction silently fell back.');
                    setup_assert(!preg_match('/setup\.[a-z_.]+|\{[a-z_]+\}/', $step['detail']), 'Unresolved detail text.');
                }
                foreach (setup_status_labels() as $key=>$value) setup_assert($value === $catalog['setup.status.'.$key], 'Status label not localized.');
                setup_assert(str_contains(setup_step($facts, 'geography')['detail'], '11'), 'Country count lost.');
                setup_assert(str_contains(setup_step($facts, 'acceptance')['detail'], $catalog['setup.yes']), 'Reachability not localized.');
                setup_assert(str_contains(setup_step([], 'geography')['detail'], $catalog['setup.unknown']), 'Unknown result not localized.');
            }
        } finally { if ($previous === null) unset($_SESSION['locale']); else $_SESSION['locale'] = $previous; }
    },
    'template_keys_exist_and_parameters_preserve_operator_text' => static function (): void {
        $source = file_get_contents(__DIR__.'/../admin/setup.php');
        preg_match_all("/t\\('(setup\\.[a-z_.]+)'\\s*(?=,|\\))/", $source, $matches);
        setup_assert(count($matches[1]) >= 10, 'Expected localized UI labels missing.');
        $previous = $_SESSION['locale'] ?? null;
        try {
            foreach (i18n_locales() as $locale=>$label) {
                $_SESSION['locale'] = $locale;
                foreach ($matches[1] as $key) setup_assert(isset(i18n_dictionary()[$key]), 'Untranslated template key.');
                foreach (array_keys(setup_step([], 'providers')['providers']) as $provider) {
                    $key='setup.provider.'.$provider;
                    setup_assert(isset(i18n_dictionary($locale)[$key]), 'Untranslated provider label.');
                }
                $text = 'Operator authored <text> & name';
                setup_assert(str_contains(t('setup.ui.settings', ['title'=>$text]), $text), 'Operator parameter was translated or changed.');
                $step = t('setup.ui.step', ['current'=>2,'total'=>18]);
                setup_assert(str_contains($step, '2') && str_contains($step, '18') && !str_contains($step, '{'), 'Step label lost values.');
            }
        } finally { if ($previous === null) unset($_SESSION['locale']); else $_SESSION['locale'] = $previous; }
    },
    'missing_evidence_never_passes' => static function (): void {
        $steps = setup_steps([]);
        setup_assert(count($steps) === 18, 'All eighteen topics must be covered.');
        setup_assert(count(array_unique(array_column($steps, 'id'))) === 18, 'Duplicate step identifiers.');
        foreach ($steps as $step) {
            setup_assert(isset(setup_status_labels()[$step['status']]), 'Unknown status.');
            setup_assert(!in_array($step['status'], ['configured','tested'], true), 'Missing evidence became success.');
            setup_assert($step['next'] !== '', 'Actionable next step missing.');
        }
    },
    'test_mail_never_proves_delivery' => static function (): void {
        setup_assert(setup_step(['mail_transport'=>'test','mail_ready'=>true], 'mail')['status'] === 'disabled', 'Test transport exposed as production mail.');
        setup_assert(setup_step(['mail_transport'=>'sendmail','mail_ready'=>true], 'mail')['status'] === 'external', 'MTA configuration became inbox proof.');
        setup_assert(setup_step(['mail_transport'=>'sendmail','mail_ready'=>true,'mail_failed'=>1], 'mail')['status'] === 'error', 'Mail failure was hidden.');
    },
    'backup_status_requires_actual_local_evidence' => static function (): void {
        $file = tempnam(sys_get_temp_dir(), 'setup-op-');
        if ($file === false) throw new RuntimeException('Test fixture could not be created.');
        try {
            $now = 1800000000;
            file_put_contents($file, ($now + 1)."\n");
            setup_assert(setup_verified_operation($file, $now) === null, 'Future proof was accepted.');
            file_put_contents($file, "invalid\n");
            setup_assert(setup_verified_operation($file, $now) === null, 'Invalid proof was accepted.');
            file_put_contents($file, $now."\n");
            $backup = setup_verified_operation($file, $now);
            setup_assert($backup === gmdate('c', $now), 'Verified local timestamp was rejected.');
            $facts = ['backup_verified_at'=>$backup];
            setup_assert(setup_step($facts, 'backups')['status'] === 'configured', 'Backup alone was treated as a restore.');
            $facts['restore_verified_at'] = $backup;
            $step = setup_step($facts, 'backups');
            setup_assert($step['status'] === 'tested' && str_contains($step['detail'], $backup), 'Verified recovery was not shown.');
            $offsite = gmdate('c', $now-60);
            $facts['offsite_verified_at'] = $offsite;
            setup_assert(str_contains(setup_step($facts, 'backups')['detail'], $offsite), 'Verified SFTP copy was not shown separately.');
        } finally { unlink($file); }
    },
    'configured_payments_do_not_imply_provider_acceptance' => static function (): void {
        $facts = ['manual_recipient'=>true,'manual_enabled'=>true,'manual_ready'=>true,'paypal_enabled'=>true,'paypal_ready'=>true];
        setup_assert(setup_step($facts, 'paypal')['status'] === 'external', 'Manual configuration proves too much.');
        setup_assert(setup_step($facts, 'providers')['status'] === 'external', 'Credentials became provider acceptance.');
        $facts['manual_enabled'] = false;
        setup_assert(setup_step($facts, 'paypal')['status'] === 'disabled', 'Disabled flag ignored.');
    },
    'provider_states_are_independent_and_do_not_enable_escrow' => static function (): void {
        $facts=['paypal_enabled'=>true,'paypal_ready'=>true,'paypal_refund_enabled'=>true,'paypal_refund_ready'=>false,
            'bank_enabled'=>true,'bank_ready'=>true,'bitcoin_enabled'=>false,'bitcoin_ready'=>true,'escrow_ready'=>true];
        $step=setup_step($facts,'providers');
        setup_assert($step['status']==='error'&&$step['providers']===['paypal'=>'external','paypal_refund'=>'error','bank'=>'external','bitcoin'=>'disabled','escrow'=>'disabled'], 'Provider states were conflated.');
    },
    'partial_geo_and_tax_coverage_is_visible' => static function (): void {
        setup_assert(setup_step(['countries'=>11,'countries_without_geo'=>1], 'geography')['status'] === 'missing', 'Partial geo coverage hidden.');
        setup_assert(setup_step(['countries'=>11,'countries_without_geo'=>0], 'geography')['status'] === 'configured', 'Present country sources not recognized.');
        setup_assert(setup_step(['finance_enabled'=>true,'operator'=>true,'countries_without_tax'=>1], 'finance')['status'] === 'error', 'Missing tax country accepted.');
    },
    'heartbeat_freshness_failure_and_future_are_checked' => static function (): void {
        setup_assert(worker_heartbeat_status('990', null, 1000, 120)['ready'], 'Fresh heartbeat not recognized.');
        foreach ([['800',null],['1001',null],['990','990'],['990','995'],['990','1001'],['990','invalid'],['x',null],[null,null]] as [$ok,$failed]) {
            setup_assert(!worker_heartbeat_status($ok, $failed, 1000, 120)['ready'], 'Invalid or failing heartbeat accepted.');
        }
        setup_assert(worker_heartbeat_status('990', '980', 1000, 120)['ready'], 'Older failure obscures newer success.');
    },
    'all_workers_required' => static function (): void {
        $workers = array_fill_keys(array_keys(worker_heartbeat_limits()), true);
        setup_assert(setup_step(['workers'=>$workers], 'workers')['status'] === 'tested', 'Complete heartbeat set not recognized.');
        unset($workers['paypal-refund']);
        setup_assert(setup_step(['workers'=>$workers], 'workers')['status'] === 'error', 'Missing worker accepted.');
    },
    'public_launch_gate_keeps_only_login_and_permitted_admin_routes' => static function (): void {
        foreach (['auth/login.php', 'auth/mfa.php', 'auth/logout.php', 'admin/setup.php'] as $route) {
            setup_assert(setup_installation_route_allowed($route, 'GET'), 'Administrator sign-in path is blocked.');
        }
        setup_assert(!setup_installation_route_allowed('admin/settings.php', 'POST'), 'Anonymous setup bypass.');
        setup_assert(setup_installation_route_allowed('admin/settings.php', 'POST', true), 'Administrator cannot complete settings.');
        foreach (['auth/register.php', 'marketplace/index.php', 'marketplace/sitemap.php', 'actions/payment-webhook.php', 'api/dealer/v1.php'] as $route) {
            setup_assert(!setup_installation_route_allowed($route, 'GET', true) && !setup_installation_route_allowed($route, 'POST', true),
                'Public or provider route open before launch.');
        }
    },
    'positive_launch_evidence_is_complete_current_and_independent' => static function (): void {
        $facts = array_fill_keys(['database', 'https', 'secure_cookies', 'https_request', 'runtime_probe', 'crypto_probe', 'storage_probe', 'maintenance_clear',
            'brand', 'operator', 'mail_ready', 'legal_enabled', 'legal_binding'], true);
        $facts += ['query_errors'=>[], 'workers'=>array_fill_keys(array_keys(worker_heartbeat_limits()), true), 'countries'=>1,
            'countries_without_geo'=>0, 'categories'=>5, 'mail_transport'=>'smtp', 'mail_failed'=>0, 'legal_approved'=>8,
            'backup_verified_at'=>'backup', 'restore_verified_at'=>'restore'];
        $now = 1800000000;
        $result = ['time'=>$now, 'checks'=>setup_launch_checks($facts)];
        setup_assert(setup_recent_success($result, $now), 'Complete readiness failed.');
        foreach ($result['checks'] as $key=>$passed) {
            setup_assert($passed, 'Positive evidence failed for '.$key);
            foreach (i18n_locales() as $locale=>$label) {
                setup_assert(isset(i18n_dictionary($locale)['setup.check.'.$key]), 'Launch check not translated.');
            }
            $modified = $result;
            $modified['checks'][$key] = false;
            setup_assert(!setup_recent_success($modified, $now), 'Failed check accepted for '.$key);
        }
        setup_assert(!setup_recent_success($result, $now-1) && !setup_recent_success($result, $now+901), 'Invalid timestamp accepted.');
        setup_assert(!setup_recent_success(['time'=>$now, 'checks'=>['fake'=>true]], $now), 'Incomplete forged result accepted.');
        foreach (['mail_transport'=>'test', 'legal_enabled'=>false, 'paypal_enabled'=>true, 'manual_enabled'=>true, 'restore_verified_at'=>null] as $key=>$value) {
            $result['checks'] = setup_launch_checks(array_replace($facts, [$key=>$value]));
            setup_assert(setup_recent_success($result, $now), 'An unrelated setting blocked technical launch: '.$key);
        }
        $result['checks'] = setup_launch_checks(array_replace($facts, ['operator'=>false,'brand'=>false]));
        setup_assert(setup_recent_success($result,$now),'Pending operator details blocked technical launch.');
    },
    'no_secret_values_or_claimed_final_acceptance' => static function (): void {
        $facts = ['APP_KEY'=>'synthetic-secret-marker','operator_email'=>'private@example.invalid','database'=>true,'query_errors'=>[]];
        $json = json_encode(setup_steps($facts), JSON_THROW_ON_ERROR);
        setup_assert(!str_contains($json,'synthetic-secret-marker') && !str_contains($json,'private@example.invalid'), 'Arbitrary facts leaked.');
        setup_assert(setup_step($facts, 'acceptance')['status'] === 'external', 'Reachable database became release acceptance.');
        $facts['query_errors'] = ['categories'];
        setup_assert(setup_step($facts, 'acceptance')['status'] === 'error', 'Failed diagnostic hidden.');
    },
    'safe_links_and_private_storage_boundary' => static function (): void {
        foreach (setup_steps([]) as $step) if ($step['href'] !== null) setup_assert(preg_match('~\A/admin/[a-z-]+\.php\z~', $step['href']) === 1, 'Unexpected action URL.');
        $public = dirname(__DIR__).'/public';
        setup_assert(!setup_private_directory($public, $public), 'Webroot accepted as private storage.');
        setup_assert(!setup_private_directory($public.'/missing-synthetic', $public), 'Missing storage accepted.');
    },
    'wizard_only_resumes_known_steps_and_commands_match_environment' => static function (): void {
        $steps = [['id'=>'brand','status'=>'configured'],['id'=>'operator','status'=>'missing'],['id'=>'acceptance','status'=>'external']];
        setup_assert(setup_wizard_pick($steps,null,null)==='operator','Resume ignored the first unresolved setup item.');
        setup_assert(setup_wizard_pick($steps,null,'brand')==='brand','Stored step was not restored.');
        setup_assert(setup_wizard_pick($steps,'acceptance','brand')==='acceptance','Selected setup step did not take precedence.');
        setup_assert(setup_wizard_pick($steps,['invalid'],'unrecognized')==='operator','Invalid checkpoint did not fall back.');
        setup_assert(isset(setup_wizard_commands(false)['tests']) && !isset(setup_wizard_commands(true)['tests']), 'Sandbox commands leaked into production.');
        foreach (setup_wizard_commands(true) as $commands) foreach ($commands as $command) {
            setup_assert(preg_match('~\Asudo [a-zA-Z0-9. /-]+\z~',$command)===1,'Production commands cannot include shell syntax or injected values.');
        }
        $base = require __DIR__.'/../locales/setup_wizard/de.php';
        foreach (array_keys(i18n_locales()) as $locale) {
            $catalog = require __DIR__.'/../locales/setup_wizard/'.$locale.'.php';
            setup_assert(array_keys($catalog)===array_keys($base) && array_intersect_key(i18n_dictionary($locale),$catalog)===$catalog,
                'Invalid setup wizard translation for '.$locale);
        }
    },
];
foreach ($tests as $name=>$test) { $test(); fwrite(STDOUT, 'PASS '.$name.PHP_EOL); }
fwrite(STDOUT, 'Setup checks passed: '.count($tests).PHP_EOL);
