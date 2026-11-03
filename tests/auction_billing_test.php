<?php
declare(strict_types=1);
/** Pure auction-fee regressions: no bootstrap, PDO connection, provider or filesystem writes. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(2); }
require_once __DIR__.'/../config/fees.php';
require_once __DIR__.'/../config/consent.php';
require_once __DIR__.'/../config/auction_billing.php';

$auctionBillingPassed = 0;
function auction_billing_test_assert(bool $condition): void {
    if (!$condition) throw new RuntimeException('Auction billing assertion failed.');
}
function auction_billing_test_reject(callable $run, string $expected): void {
    try { $run(); }
    catch (Throwable $error) {
        if ($error instanceof $expected) return;
        throw $error;
    }
    throw new LogicException('Expected auction billing rejection.');
}
function auction_billing_test(string $name, callable $run): void {
    global $auctionBillingPassed;
    $run();
    ++$auctionBillingPassed;
    fwrite(STDOUT, 'PASS '.$name."\n");
}
function auction_billing_test_contract(int $listing = 50): array {
    return [
        'version'=>'auction-billing-1',
        'tariff'=>['listing_cents'=>$listing, 'rates'=>[300,200,100,50], 'cap_cents'=>2990],
    ];
}
function auction_billing_test_quote(): array {
    $body = str_repeat('Synthetic contract document for local tests, not a legal approval. ', 4);
    $documents = [];
    foreach ([41=>'synthetic-terms', 42=>'synthetic-fees'] as $id=>$code) {
        $text = $body.$code;
        $documents[] = [
            'id'=>$id, 'document_code'=>$code, 'version'=>'1.0', 'language'=>'de',
            'title'=>'Synthetic fixture', 'body_text'=>$text, 'content_hash'=>hash('sha256', $text),
        ];
    }
    $payload = auction_billing_test_contract() + [
        'fee_version'=>'synthetic-accepted-version', 'locale'=>'de', 'currency'=>'EUR',
        'buyer'=>['user_id'=>7, 'legal_name'=>'Synthetic buyer', 'address_line1'=>'Synthetic address'],
        'operator'=>['legal_name'=>'Synthetic operator'], 'tax_bps'=>1900,
        'tax_explanation'=>'Synthetic test treatment only.', 'recipient'=>'synthetic@example.invalid',
        'terms'=>'Synthetic terms for the accepted tariff.',
        'documents'=>array_map(static fn(array $document): array => [
            'id'=>$document['id'], 'hash'=>$document['content_hash'], 'language'=>$document['language'],
        ], $documents),
    ];
    return ['payload'=>$payload, 'documents'=>$documents, 'hash'=>hash('sha256', auction_billing_json($payload))];
}
function auction_billing_test_submission(array $quote): array {
    return [
        'auction_fee_action'=>'publish', 'accept_auction_fees'=>'1', 'auction_fee_quote'=>$quote['hash'],
        'legal_confirm'=>'1', 'legal_language'=>$quote['payload']['locale'],
        'legal_documents'=>array_column($quote['documents'], 'content_hash', 'id'),
    ];
}

$originalLocale = $_SESSION['locale'] ?? null;
$hadLocale = array_key_exists('locale', $_SESSION ?? []);
$failed = false;
$name = 'initialization';
try {
    $_SESSION['locale'] = 'de';
    $tests = [
        'only_two_exact_auction_services_are_classified' => static function (): void {
            auction_billing_test_assert(auction_billing_service('auction_listing'));
            auction_billing_test_assert(auction_billing_service('auction_success'));
            foreach (['', 'auction', 'listing', 'success', 'AUCTION_LISTING', 'auction_listing ', ' auction_success',
                'auction_success_refund', "auction_listing\0", 'escrow_preview', 'private_fixed', 'bump_single', 'home_top'] as $service) {
                auction_billing_test_assert(!auction_billing_service($service));
            }
        },
        'listing_fee_uses_the_accepted_cent_amount_including_zero' => static function (): void {
            foreach ([0, 1, 50, 199, 9999999] as $amount) {
                auction_billing_test_assert(auction_billing_amount(auction_billing_test_contract($amount), 'listing', null) === $amount);
            }
        },
        'progressive_commission_has_independent_expected_cent_values' => static function (): void {
            // Independently calculated business examples, not a second copy of the implementation.
            $examples = [1=>0, 16=>0, 17=>1, 49=>1, 50=>2, 9999=>300, 10000=>300,
                10001=>300, 10024=>300, 10025=>301, 49999=>1100, 50000=>1100,
                50001=>1100, 50049=>1100, 50050=>1101, 199999=>2600, 200000=>2600,
                200001=>2600, 200099=>2600, 200100=>2601, 277899=>2989, 277900=>2990,
                278000=>2990, 10000000=>2990, 9999999999=>2990];
            foreach ($examples as $sale=>$expected) {
                auction_billing_test_assert(auction_billing_amount(auction_billing_test_contract(), 'success', $sale) === $expected);
            }
        },
        'commission_is_monotonic_and_never_exceeds_the_accepted_cap' => static function (): void {
            $previous = 0;
            for ($sale = 1; $sale <= 300000; $sale += 137) {
                $amount = auction_billing_amount(auction_billing_test_contract(), 'success', $sale);
                auction_billing_test_assert($amount >= $previous && $amount <= 2990);
                $previous = $amount;
            }
        },
        'wrong_charge_kind_and_missing_or_invalid_sale_are_rejected' => static function (): void {
            foreach ([['listing', 1], ['success', null], ['success', 0], ['success', -1],
                ['success', 10000000000], ['success', PHP_INT_MAX], ['', null], ['listing ', null], ['auction_success', 10000]] as [$kind, $sale]) {
                auction_billing_test_reject(static fn() => auction_billing_amount(auction_billing_test_contract(), $kind, $sale), InvalidArgumentException::class);
            }
        },
        'tariff_version_rates_cap_and_integer_listing_amount_are_strict' => static function (): void {
            $base = auction_billing_test_contract();
            foreach (['version', 'tariff'] as $key) {
                $invalid = $base; unset($invalid[$key]);
                auction_billing_test_reject(static fn() => auction_billing_amount($invalid, 'listing', null), RuntimeException::class);
            }
            foreach (['auction-billing-2', '', 1, null] as $version) {
                $invalid = $base; $invalid['version'] = $version;
                auction_billing_test_reject(static fn() => auction_billing_amount($invalid, 'listing', null), RuntimeException::class);
            }
            foreach (['listing_cents'=>[-1, 10000000, '50', 50.0, null, false],
                'cap_cents'=>[2991, '2990', 2990.0, null],
                'rates'=>[[301,200,100,50], ['300',200,100,50], [300.0,200,100,50], [300,200,100], [300,200,100,50,0], null]] as $field=>$values) {
                foreach ($values as $value) {
                    $invalid = $base; $invalid['tariff'][$field] = $value;
                    auction_billing_test_reject(static fn() => auction_billing_amount($invalid, 'listing', null), RuntimeException::class);
                }
            }
        },
        'accepted_tariff_survives_serialization_and_later_quote_changes' => static function (): void {
            $accepted = auction_billing_test_contract(50);
            $saved = auction_billing_json($accepted);
            $later = $accepted;
            $later['tariff']['listing_cents'] = 900;
            $restored = json_decode($saved, true, 32, JSON_THROW_ON_ERROR);
            auction_billing_test_assert($restored === $accepted);
            auction_billing_test_assert(auction_billing_amount($restored, 'listing', null) === 50);
            auction_billing_test_assert(auction_billing_amount($later, 'listing', null) === 900);
            auction_billing_test_assert(auction_billing_amount($restored, 'success', 50000) === 1100);
            auction_billing_test_assert(auction_billing_json($accepted) === $saved);
        },
        'fee_tax_splits_preserve_every_cent' => static function (): void {
            foreach ([[50,42,8], [300,252,48], [1100,924,176], [2600,2185,415], [2990,2513,477]] as [$gross,$net,$tax]) {
                auction_billing_test_assert(fees_tax_split($gross, 1900, true) === ['net_cents'=>$net,'tax_cents'=>$tax,'gross_cents'=>$gross]);
                auction_billing_test_assert(fees_tax_split($gross, 0, true) === ['net_cents'=>$gross,'tax_cents'=>0,'gross_cents'=>$gross]);
            }
            auction_billing_test_reject(static fn() => fees_tax_split(50, 1900, false), InvalidArgumentException::class);
        },
        'contract_json_preserves_unicode_and_rejects_nonrepresentable_data' => static function (): void {
            $value = ['text'=>'Prüfung / Łódź / Česká', 'cents'=>9999999999];
            $json = auction_billing_json($value);
            auction_billing_test_assert(str_contains($json, $value['text']));
            auction_billing_test_assert(json_decode($json, true, 32, JSON_THROW_ON_ERROR) === $value);
            foreach ([['text'=>"\xC3\x28"], ['amount'=>INF], ['amount'=>NAN]] as $invalid) {
                auction_billing_test_reject(static fn() => auction_billing_json($invalid), JsonException::class);
            }
        },
        'explicit_paid_publication_accepts_the_exact_quote_and_documents' => static function (): void {
            $quote = auction_billing_test_quote();
            auction_billing_validate_quote($quote, auction_billing_test_submission($quote));
        },
        'ordinary_save_or_missing_submit_action_cannot_accept_paid_publication' => static function (): void {
            $quote = auction_billing_test_quote();
            $input = auction_billing_test_submission($quote);
            unset($input['auction_fee_action']);
            auction_billing_test_reject(static fn() => auction_billing_validate_quote($quote, $input), InvalidArgumentException::class);
            foreach (['save', 'draft', '', 'PUBLISH', ['publish'], true] as $action) {
                $input = auction_billing_test_submission($quote); $input['auction_fee_action'] = $action;
                auction_billing_test_reject(static fn() => auction_billing_validate_quote($quote, $input), InvalidArgumentException::class);
            }
        },
        'fee_checkbox_and_quote_hash_require_exact_scalar_values' => static function (): void {
            $quote = auction_billing_test_quote();
            foreach ([null, '', '0', 1, true, ['1']] as $accepted) {
                $input = auction_billing_test_submission($quote); $input['accept_auction_fees'] = $accepted;
                auction_billing_test_reject(static fn() => auction_billing_validate_quote($quote, $input), InvalidArgumentException::class);
            }
            foreach ([null, '', str_repeat('0',64), $quote['hash'].'0', [$quote['hash']], true] as $hash) {
                $input = auction_billing_test_submission($quote); $input['auction_fee_quote'] = $hash;
                auction_billing_test_reject(static fn() => auction_billing_validate_quote($quote, $input), InvalidArgumentException::class);
            }
        },
        'changed_price_recipient_billing_tax_language_or_terms_invalidates_old_quote' => static function (): void {
            $quote = auction_billing_test_quote();
            $input = auction_billing_test_submission($quote);
            $changes = [
                static function (array &$p): void { $p['tariff']['listing_cents'] = 51; },
                static function (array &$p): void { $p['recipient'] = 'changed@example.invalid'; },
                static function (array &$p): void { $p['buyer']['address_line1'] = 'Changed synthetic address'; },
                static function (array &$p): void { $p['buyer']['user_id'] = 8; },
                static function (array &$p): void { $p['operator']['legal_name'] = 'Changed synthetic operator'; },
                static function (array &$p): void { $p['tax_bps'] = 0; },
                static function (array &$p): void { $p['locale'] = 'en'; },
                static function (array &$p): void { $p['terms'] .= ' Changed.'; },
                static function (array &$p): void { $p['documents'][0]['id'] = 43; },
            ];
            foreach ($changes as $change) {
                $changed = $quote; $change($changed['payload']);
                $changed['hash'] = hash('sha256', auction_billing_json($changed['payload']));
                auction_billing_test_assert($changed['hash'] !== $quote['hash']);
                auction_billing_test_reject(static fn() => auction_billing_validate_quote($changed, $input), InvalidArgumentException::class);
            }
        },
        'fee_confirmation_does_not_replace_explicit_legal_consent' => static function (): void {
            $quote = auction_billing_test_quote();
            foreach (['legal_confirm', 'legal_language', 'legal_documents'] as $field) {
                $input = auction_billing_test_submission($quote); unset($input[$field]);
                auction_billing_test_reject(static fn() => auction_billing_validate_quote($quote, $input), InvalidArgumentException::class);
            }
            $input = auction_billing_test_submission($quote); $input['legal_language'] = 'en';
            auction_billing_test_reject(static fn() => auction_billing_validate_quote($quote, $input), InvalidArgumentException::class);
        },
        'legal_document_set_versions_and_hashes_cannot_be_substituted' => static function (): void {
            $quote = auction_billing_test_quote();
            $valid = auction_billing_test_submission($quote);
            $variants = [[], [41=>$valid['legal_documents'][41]],
                [41=>$valid['legal_documents'][41],43=>$valid['legal_documents'][42]],
                [41=>str_repeat('0',64),42=>$valid['legal_documents'][42]],
                [41=>[$valid['legal_documents'][41]],42=>$valid['legal_documents'][42]],
                $valid['legal_documents'] + [43=>str_repeat('a',64)]];
            foreach ($variants as $documents) {
                $input = $valid; $input['legal_documents'] = $documents;
                auction_billing_test_reject(static fn() => auction_billing_validate_quote($quote, $input), InvalidArgumentException::class);
            }
        },
        'tampered_approved_document_body_fails_even_with_valid_quote_hash' => static function (): void {
            $quote = auction_billing_test_quote();
            $input = auction_billing_test_submission($quote);
            $quote['documents'][0]['body_text'] .= ' Altered after approval.';
            auction_billing_test_reject(static fn() => auction_billing_validate_quote($quote, $input), DomainException::class);
        },
    ];
    foreach ($tests as $name=>$run) auction_billing_test($name, $run);
} catch (Throwable $error) {
    // Keep payloads, addresses, consent contents and any future configuration out of logs.
    fwrite(STDERR, 'FAIL '.$name.' ('.get_class($error).' at '.basename($error->getFile()).':'.$error->getLine().")\n");
    $failed = true;
} finally {
    if ($hadLocale) $_SESSION['locale'] = $originalLocale;
    else unset($_SESSION['locale']);
}
if ($failed) exit(1);
fwrite(STDOUT, 'Auction billing pure checks passed: '.$auctionBillingPassed."\n");
