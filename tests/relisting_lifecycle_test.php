<?php
declare(strict_types=1);
/** Pure lifecycle regressions: no runtime bootstrap, database, files or provider calls. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit(2); }
require_once __DIR__.'/../config/i18n.php';
require_once __DIR__.'/../config/relisting.php';

function rlc_check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function rlc_reject(callable $operation): void {
    try { $operation(); } catch (DomainException) { return; }
    throw new RuntimeException('Invalid lifecycle input was accepted.');
}
function rlc_listing(array $changes=[]): array {
    return array_replace(['id'=>51,'seller_id'=>7,'category_id'=>4,'title'=>'Synthetic table','description'=>'Ordinary synthetic table.',
        'type'=>'fixed','status'=>'active','price_cents'=>1000,'buyout_cents'=>null,'shipping_cents'=>500,
        'zip'=>'10115','city'=>'Synthetic city','country_code'=>'DE','latitude'=>'52.532300','longitude'=>'13.384600',
        'pickup'=>1,'shipping'=>1,'condition_label'=>'used','ends_at'=>'2026-03-28 12:00:00','updated_at'=>'2026-03-01 12:00:00',
        'highest_bidder_id'=>null,'current_bid_cents'=>null],$changes);
}
$now=strtotime('2026-03-27 12:00:00 UTC');
$tests=[
    'closed_general_sources_keep_their_own_eligibility'=>static function() use ($now): void {
        foreach (['fixed','negotiated'] as $type) foreach (['ended','sold','archived'] as $status) {
            $listing=rlc_listing(['type'=>$type,'status'=>$status,'ends_at'=>'2026-03-26 12:00:00']);
            rlc_check(relisting_general_eligible($listing,[],false,$now),'Closed general listing rejected.');
            rlc_check(!relisting_source_eligible($listing,[],false,$now),'General listing gained auction eligibility.');
        }
    },
    'unfinished_sources_and_future_expiry_are_rejected'=>static function() use ($now): void {
        foreach (['draft','active','paused','review','unknown'] as $status) rlc_check(!relisting_general_eligible(rlc_listing(['status'=>$status]),[],false,$now),'Unfinished source accepted.');
        foreach ([null,'','2026-02-30 12:00:00','2026-03-28 12:00:00'] as $end)
            rlc_check(!relisting_general_eligible(rlc_listing(['status'=>'ended','ends_at'=>$end]),[],false,$now),'Invalid or future close accepted.');
        rlc_check(relisting_general_eligible(rlc_listing(['status'=>'ended','ends_at'=>'2026-03-27 12:00:00']),[],false,$now),'Exact closed boundary rejected.');
    },
    'general_copies_reject_every_trace_of_auction_history'=>static function() use ($now): void {
        $listing=rlc_listing(['status'=>'archived']);
        rlc_check(!relisting_general_eligible($listing,[['event_type'=>'close_no_bid']],false,$now),'Auction close history accepted.');
        rlc_check(!relisting_general_eligible($listing,[],true,$now),'Proxy history accepted.');
        foreach (['highest_bidder_id','current_bid_cents'] as $field) {
            rlc_check(!relisting_general_eligible(array_replace($listing,[$field=>0]),[],false,$now),'Non-null bidding state accepted.');
            $missing=$listing; unset($missing[$field]); rlc_check(!relisting_general_eligible($missing,[],false,$now),'Missing bidding state accepted.');
        }
    },
    'general_copies_require_canonical_owned_row_identifiers'=>static function() use ($now): void {
        foreach (['id','seller_id'] as $field) foreach ([0,-1,'01','1e2',true,[],null] as $value)
            rlc_check(!relisting_general_eligible(rlc_listing(['status'=>'archived',$field=>$value]),[],false,$now),'Invalid ID accepted.');
        foreach (['auction','unknown',''] as $type) rlc_check(!relisting_general_eligible(rlc_listing(['status'=>'archived','type'=>$type]),[],false,$now),'Unsupported general type accepted.');
    },
    'extension_has_bounded_choices_per_listing_kind'=>static function(): void {
        rlc_check(relisting_extension_days('auction')===[1,3,7],'Auction limits changed.');
        foreach (['fixed','negotiated'] as $type) rlc_check(relisting_extension_days($type)===[7,14,30],'General limits changed.');
        rlc_check(relisting_extension_days('unknown')===[],'Unsupported type has extension choices.');
    },
    'only_future_active_bid_free_listings_can_extend'=>static function() use ($now): void {
        foreach (['fixed','negotiated','auction'] as $type) rlc_check(relisting_extension_eligible(rlc_listing(['type'=>$type]),false,$now),'Eligible extension rejected.');
        foreach (['draft','paused','ended','sold','archived','review'] as $status) rlc_check(!relisting_extension_eligible(rlc_listing(['status'=>$status]),false,$now),'Inactive extension accepted.');
        foreach ([null,'','2026-03-27 12:00:00','2026-03-26 12:00:00','2026-03-32 12:00:00'] as $end)
            rlc_check(!relisting_extension_eligible(rlc_listing(['ends_at'=>$end]),false,$now),'Expired/unlimited/invalid deadline accepted.');
        rlc_check(!relisting_extension_eligible(rlc_listing(),true,$now),'Bid history accepted for extension.');
        foreach (['highest_bidder_id','current_bid_cents'] as $field) rlc_check(!relisting_extension_eligible(rlc_listing([$field=>1]),false,$now),'Bid marker accepted.');
    },
    'extension_uses_utc_days_and_the_original_deadline'=>static function() use ($now): void {
        $timezone=date_default_timezone_get(); date_default_timezone_set('Europe/Berlin');
        try {
            rlc_check(relisting_extension_deadline(rlc_listing(),7,$now)==='2026-04-04 12:00:00','DST or request time altered the extension.');
            rlc_check(relisting_extension_deadline(rlc_listing(['type'=>'auction']),1,$now)==='2026-03-29 12:00:00','Auction extension did not preserve UTC hour.');
        } finally { date_default_timezone_set($timezone); }
    },
    'extension_rejects_unsupported_periods_and_datetime_overflow'=>static function() use ($now): void {
        foreach ([-1,0,1,3,15,31,PHP_INT_MAX] as $days) rlc_reject(fn()=>relisting_extension_deadline(rlc_listing(),$days,$now));
        rlc_reject(fn()=>relisting_extension_deadline(rlc_listing(['type'=>'auction']),14,$now));
        rlc_reject(fn()=>relisting_extension_deadline(rlc_listing(['ends_at'=>'9999-12-30 12:00:00']),7,$now));
    },
    'confirmation_hash_binds_product_price_delivery_and_deadline'=>static function(): void {
        $listing=rlc_listing(); $hash=relisting_extension_hash($listing);
        rlc_check((bool)preg_match('/\A[a-f0-9]{64}\z/',$hash),'Invalid hash format.');
        foreach (['title'=>'Changed','price_cents'=>1001,'shipping_cents'=>501,'ends_at'=>'2026-03-29 12:00:00',
            'seller_id'=>8,'status'=>'sold','type'=>'negotiated','description'=>'Changed text','pickup'=>0,'category_id'=>5] as $field=>$value)
            rlc_check(relisting_extension_hash(array_replace($listing,[$field=>$value]))!==$hash,'Confirmation missed changed field '.$field);
        rlc_check(relisting_extension_hash(array_replace($listing,['id'=>'51','price_cents'=>'1000']))===$hash,'PDO integer representations changed canonical hash.');
    },
    'confirmation_rejects_missing_and_nested_data'=>static function(): void {
        foreach (['shipping_cents','ends_at','highest_bidder_id','title'] as $field) {
            $listing=rlc_listing(); unset($listing[$field]); rlc_reject(fn()=>relisting_extension_hash($listing));
            rlc_reject(fn()=>relisting_extension_hash(rlc_listing([$field=>[]])));
        }
    },
    'all_eight_catalogs_cover_lifecycle_and_keep_placeholders'=>static function(): void {
        $base=require __DIR__.'/../locales/relisting/de.php';
        foreach (array_keys(i18n_locales()) as $locale) {
            $dictionary=require __DIR__.'/../locales/relisting/'.$locale.'.php';
            rlc_check(array_keys($dictionary)===array_keys($base),'Lifecycle key mismatch in '.$locale);
            foreach ($base as $key=>$value) {
                rlc_check(is_string($dictionary[$key])&&trim($dictionary[$key])!==''&&$dictionary[$key]!==$key,'Missing text in '.$locale.':'.$key);
                preg_match_all('/\{[a-z_]+\}/',$value,$expected); preg_match_all('/\{[a-z_]+\}/',$dictionary[$key],$actual);
                sort($expected[0]); sort($actual[0]); rlc_check($expected[0]===$actual[0],'Placeholder mismatch in '.$locale.':'.$key);
            }
        }
    },
];
$passed=0;
foreach ($tests as $name=>$test) {
    try { $test(); ++$passed; fwrite(STDOUT,'PASS '.$name.PHP_EOL); }
    catch (Throwable $error) { fwrite(STDERR,'FAIL '.$name.': '.$error->getMessage().PHP_EOL); exit(1); }
}
fwrite(STDOUT,'Listing lifecycle pure checks passed: '.$passed.PHP_EOL);
