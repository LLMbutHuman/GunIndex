<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/catalog.php';
function admin_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function admin_invalid(callable $operation): void {
    try { $operation(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Unsafe setting was accepted.');
}
$tests = [
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
];
foreach ($tests as $name => $operation) { $operation(); fwrite(STDOUT, 'PASS ' . $name . "\n"); }
fwrite(STDOUT, 'Admin checks passed: ' . count($tests) . "\n");
