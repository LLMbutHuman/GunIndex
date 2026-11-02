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
        setup_assert(count($base) === 77, 'Unexpected setup catalog coverage.');
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
        preg_match_all("/t\\('(setup\\.[a-z_.]+)'/", $source, $matches);
        setup_assert(count($matches[1]) >= 10, 'Expected localized UI labels missing.');
        $previous = $_SESSION['locale'] ?? null;
        try {
            foreach (i18n_locales() as $locale=>$label) {
                $_SESSION['locale'] = $locale;
                foreach ($matches[1] as $key) setup_assert(isset(i18n_dictionary()[$key]), 'Untranslated template key.');
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
    'configured_payments_do_not_imply_provider_acceptance' => static function (): void {
        $facts = ['manual_recipient'=>true,'manual_enabled'=>true,'manual_ready'=>true,'paypal_enabled'=>true,'paypal_ready'=>true];
        setup_assert(setup_step($facts, 'paypal')['status'] === 'external', 'Manual configuration proves too much.');
        setup_assert(setup_step($facts, 'providers')['status'] === 'external', 'Credentials became provider acceptance.');
        $facts['manual_enabled'] = false;
        setup_assert(setup_step($facts, 'paypal')['status'] === 'disabled', 'Disabled flag ignored.');
    },
    'partial_geo_and_tax_coverage_is_visible' => static function (): void {
        setup_assert(setup_step(['countries'=>11,'countries_without_geo'=>1], 'geography')['status'] === 'missing', 'Partial geo coverage hidden.');
        setup_assert(setup_step(['countries'=>11,'countries_without_geo'=>0], 'geography')['status'] === 'configured', 'Present country sources not recognized.');
        setup_assert(setup_step(['finance_enabled'=>true,'operator'=>true,'countries_without_tax'=>1], 'finance')['status'] === 'error', 'Missing tax country accepted.');
    },
    'heartbeat_freshness_failure_and_future_are_checked' => static function (): void {
        setup_assert(setup_heartbeat('990', null, 1000, 120), 'Fresh heartbeat not recognized.');
        foreach ([['800',null],['1001',null],['990','990'],['990','995'],['990','invalid'],['x',null],[null,null]] as [$ok,$failed]) {
            setup_assert(!setup_heartbeat($ok, $failed, 1000, 120), 'Invalid or failing heartbeat accepted.');
        }
        setup_assert(setup_heartbeat('990', '980', 1000, 120), 'Older failure obscures newer success.');
    },
    'all_workers_required' => static function (): void {
        $workers = array_fill_keys(['auction','mail','discovery','business','privacy'], true);
        setup_assert(setup_step(['workers'=>$workers], 'workers')['status'] === 'tested', 'Complete heartbeat set not recognized.');
        unset($workers['privacy']);
        setup_assert(setup_step(['workers'=>$workers], 'workers')['status'] === 'error', 'Missing worker accepted.');
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
];
foreach ($tests as $name=>$test) { $test(); fwrite(STDOUT, 'PASS '.$name.PHP_EOL); }
fwrite(STDOUT, 'Setup checks passed: '.count($tests).PHP_EOL);
