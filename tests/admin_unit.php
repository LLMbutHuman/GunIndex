<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/catalog.php';
require_once __DIR__ . '/../config/governance.php';
function admin_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function admin_invalid(callable $operation): void {
    try { $operation(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Unsafe setting was accepted.');
}
$tests = [
    'settings_forms_have_matching_localized_fields_and_errors' => static function (): void {
        $base = require __DIR__ . '/../locales/admin_settings/de.php';
        admin_assert(count($base) === 70, 'Settings catalogue coverage changed.');
        foreach (i18n_locales() as $locale=>$label) {
            $catalog = require __DIR__ . '/../locales/admin_settings/' . $locale . '.php';
            $dictionary = i18n_dictionary($locale);
            admin_assert(array_keys($base) === array_keys($catalog), 'Settings translation keys differ.');
            foreach ($base as $key=>$text) {
                preg_match_all('/\{[a-z_]+\}/', $text, $sourceParameters);
                preg_match_all('/\{[a-z_]+\}/', $catalog[$key], $targetParameters);
                admin_assert($sourceParameters === $targetParameters && $dictionary[$key] === $catalog[$key], 'Settings translation is incomplete.');
            }
            foreach (site_setting_definitions() as $definition) {
                admin_assert(isset($dictionary[$definition['label']]), 'Undefined settings field label.');
            }
        }
    },
    'active_parent_controls_child' => static function (): void {
        $rows = [1 => ['parent_id' => null, 'name' => 'Outdoor', 'is_active' => 1], 2 => ['parent_id' => 1, 'name' => 'Camping', 'is_active' => 1]];
        admin_assert(catalog_row_allowed($rows, 2), 'Ordinary active child must be available.');
        $rows[1]['is_active'] = 0;
        admin_assert(!catalog_row_allowed($rows, 2), 'Inactive ancestors must disable descendants.');
        admin_assert(!catalog_row_allowed($rows, 42), 'Missing categories must be denied.');
    },
    'corrupt_cycle_and_orphan_fail_closed' => static function (): void {
        $rows = [1 => ['parent_id' => 2, 'name' => 'Outdoor', 'is_active' => 1], 2 => ['parent_id' => 1, 'name' => 'Camping', 'is_active' => 1]];
        admin_assert(!catalog_row_allowed($rows, 1), 'Category cycles must fail closed.');
        $rows[1]['parent_id'] = 77;
        admin_assert(!catalog_row_allowed($rows, 1), 'Orphan categories must fail closed.');
    },
    'explicit_restricted_names_do_not_block_household_words' => static function (): void {
        admin_assert(!catalog_text_allowed('Waffen und Munition'), 'Restricted category terms must be rejected.');
        admin_assert(!catalog_text_allowed('Camping PISTOLE'), 'Case changes cannot bypass platform rules.');
        admin_assert(catalog_text_allowed('Waffeleisen und Glockenspiel'), 'Ordinary household words must remain available.');
        admin_assert(catalog_text_allowed('Patronen für Tintenstrahldrucker'), 'Printer supplies must remain available.');
        admin_assert(!catalog_text_allowed('Patronen für Drucker und Munition'), 'Printer supplies must not override other restrictions.');
    },
    'color_and_scalar_injection_rejected' => static function (): void {
        admin_assert(site_setting_validate('accent', '#ABCDEF') === '#abcdef', 'Colors must normalize safely.');
        foreach (['red', '#fff', '#123456; background:url(https://example.invalid)', ['#123456']] as $value) admin_invalid(static fn() => site_setting_validate('accent', $value));
    },
    'names_are_plain_bounded_valid_utf8' => static function (): void {
        admin_assert(site_setting_validate('brand_name', '  Märkte & Dinge  ') === 'Märkte & Dinge', 'Unicode brands must survive validation.');
        foreach (["Name\nHeader", str_repeat('x', 61), "\xff", []] as $value) admin_invalid(static fn() => site_setting_validate('brand_name', $value));
    },
    'no_editable_secret_or_float_money' => static function (): void {
        admin_assert(site_setting_validate('fee_bump_single_cents', '199') === '199', 'Cent amounts remain exact strings.');
        foreach (['1.99', '1e2', '-1', '10000000', '0199'] as $value) admin_invalid(static fn() => site_setting_validate('fee_bump_single_cents', $value));
        admin_invalid(static fn() => site_setting_validate('PAYPAL_CLIENT_SECRET', 'not-a-secret'));
        admin_invalid(static fn() => site_setting_validate('registration_enabled', 'true'));
    },
    'revision_is_order_independent_and_change_sensitive' => static function (): void {
        admin_assert(site_settings_revision(['brand_name' => 'A', 'accent' => '#000000']) === site_settings_revision(['accent' => '#000000', 'brand_name' => 'A']), 'Order must not cause a false edit conflict.');
        admin_assert(site_settings_revision(['brand_name' => 'A']) !== site_settings_revision(['brand_name' => 'B']), 'Changed values must invalidate old revisions.');
    },
    'role_matrix_rejects_unknown_permissions_and_self_escalation' => static function (): void {
        $roles = array_map(static fn(array $role): array => $role['permissions'], governance_roles());
        $form = array_map(static fn(array $permissions): array => array_merge([''], $permissions), $roles);
        admin_assert(governance_role_policy_changes($roles,$form,['*'],[]) === [], 'Existing defaults were changed.');
        $form['moderator'][] = 'finance.manage';
        admin_assert(governance_role_policy_changes($roles,$form,['*'],[]) === ['moderator'=>['added'=>['finance.manage'],'removed'=>[]]], 'A single permission must be independently editable.');
        foreach ([[['roles.manage','finance.manage'],['moderator']], [['roles.manage'],[]]] as [$permissions,$actorRoles]) {
            try { governance_role_policy_changes($roles,$form,$permissions,$actorRoles); }
            catch (DomainException) { continue; }
            throw new RuntimeException('The actor exceeded the permitted role editing scope.');
        }
        $form['moderator'][] = '*';
        admin_invalid(static fn() => governance_role_policy_changes($roles,$form,['*'],[]));
        unset($form['finance']);
        admin_invalid(static fn() => governance_role_policy_changes($roles,$form,['*'],[]));
        admin_assert(governance_role_mfa_recent(['auth_started_at'=>1000],1900), 'A recent MFA session was rejected.');
        admin_assert(!governance_role_mfa_recent(['auth_started_at'=>1000],1901)
            && !governance_role_mfa_recent(['auth_started_at'=>1001],1000), 'Expired or future MFA evidence was accepted.');
        foreach (array_keys(i18n_locales()) as $locale) {
            $dictionary = i18n_dictionary($locale);
            foreach (governance_permissions() as $permission) {
                admin_assert(isset($dictionary['admin_roles.permission_'.str_replace('.','_',$permission)]), 'Missing permission label in '.$locale);
            }
        }
    },
];
foreach ($tests as $name => $operation) { $operation(); fwrite(STDOUT, 'PASS ' . $name . "\n"); }
fwrite(STDOUT, 'Admin checks passed: ' . count($tests) . "\n");
