<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__.'/../config/account_tokens.php';
function al_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$tests = [
    'every_recipient_language_round_trips_with_the_unchanged_token' => static function (): void {
        $token = str_repeat('ab', 32);
        foreach (i18n_locales() as $locale=>$name) {
            parse_str(account_token_query($token, $locale), $query);
            al_assert($query === ['token'=>$token,'locale'=>$locale], 'Unexpected token/link parameters.');
        }
    },
    'unknown_recipient_locales_use_the_mail_template_fallback' => static function (): void {
        foreach ([null, ['fr'], '../../etc/passwd', 'FR', 'fr&token=other', "fr\r\nX-Test: x", 1, ''] as $locale) {
            parse_str(account_token_query(str_repeat('c', 64), $locale), $query);
            al_assert($query === ['token'=>str_repeat('c', 64),'locale'=>'de'], 'Unsupported locale entered a mail link.');
        }
    },
    'token_query_rejects_malformed_capabilities' => static function (): void {
        foreach (['', str_repeat('a', 63), str_repeat('A', 64), str_repeat('a', 64).'&locale=it'] as $token) {
            try { account_token_query($token, 'fr'); }
            catch (InvalidArgumentException) { continue; }
            throw new RuntimeException('Invalid token accepted.');
        }
    },
    'fresh_browser_language_changes_only_the_display_preference' => static function (): void {
        foreach (i18n_locales() as $locale=>$name) {
            $_SESSION = ['csrf_token'=>'synthetic-form-token','market_country'=>'DE'];
            account_apply_link_locale($locale);
            al_assert($_SESSION === ['csrf_token'=>'synthetic-form-token','market_country'=>'DE','locale'=>$locale], 'Unrelated session state changed.');
            al_assert(i18n_locale() === $locale, 'Landing page cannot read the recipient language.');
        }
    },
    'existing_valid_session_choice_takes_precedence' => static function (): void {
        foreach (i18n_locales() as $chosen=>$name) {
            $_SESSION = ['locale'=>$chosen,'csrf_token'=>'synthetic-form-token'];
            foreach (array_keys(i18n_locales()) as $requested) {
                account_apply_link_locale($requested);
                al_assert($_SESSION === ['locale'=>$chosen,'csrf_token'=>'synthetic-form-token'], 'Existing language choice overridden.');
            }
        }
    },
    'legacy_and_invalid_link_hints_have_no_side_effect' => static function (): void {
        foreach ([null, [], ['fr'], 'xx', 'FR', '../../etc/passwd', 'fr&locale=it', "fr\0", 1] as $locale) {
            $_SESSION = ['csrf_token'=>'synthetic-form-token'];
            account_apply_link_locale($locale);
            al_assert($_SESSION === ['csrf_token'=>'synthetic-form-token'] && i18n_locale() === 'de', 'Invalid or absent locale changed legacy behavior.');
        }
    },
    'invalid_existing_preference_is_replaced_only_by_an_allowed_locale' => static function (): void {
        foreach (['invalid', ['de']] as $existing) {
            $_SESSION = ['locale'=>$existing,'csrf_token'=>'synthetic-form-token'];
            account_apply_link_locale('nl');
            al_assert($_SESSION === ['locale'=>'nl','csrf_token'=>'synthetic-form-token'], 'Allowed hint did not recover an invalid preference.');
        }
    },
];
$original = $_SESSION ?? null;
try { foreach ($tests as $name=>$run) { $run(); fwrite(STDOUT, 'PASS '.$name.PHP_EOL); } }
finally { if ($original === null) unset($_SESSION); else $_SESSION = $original; }
fwrite(STDOUT, 'Account link locale checks passed: '.count($tests).PHP_EOL);
