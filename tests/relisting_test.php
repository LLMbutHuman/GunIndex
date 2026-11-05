<?php
declare(strict_types=1);
/** Pure domain/quote regressions: no PDO connection, bootstrap, provider or file writes. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit(2); }
require_once __DIR__.'/../config/fees.php';
require_once __DIR__.'/../config/money.php';
require_once __DIR__.'/../config/consent.php';
require_once __DIR__.'/../config/auction_billing.php';
require_once __DIR__.'/../config/relisting.php';

function rel_test_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function rel_test_reject(callable $run, string $expected=DomainException::class): void
{
    try { $run(); } catch (Throwable $error) { if ($error instanceof $expected) return; throw $error; }
    throw new RuntimeException('Expected relisting rejection did not occur.');
}
function rel_test_source(): array
{
    return ['id'=>10,'seller_id'=>7,'type'=>'auction','status'=>'ended','ends_at'=>'2026-01-01 12:00:00',
        'highest_bidder_id'=>null,'current_bid_cents'=>null];
}
function rel_test_events(): array
{
    return [['event_type'=>'close_no_bid','idempotency_key'=>'close-10','user_id'=>null,'amount_cents'=>null]];
}
function rel_test_link(): array
{
    return ['root_listing_id'=>10,'parent_listing_id'=>10,'child_listing_id'=>11,'seller_id'=>7,'published_at'=>null];
}
function rel_test_chain(): array
{
    return ['root_listing_id'=>10,'seller_id'=>7,'free_listing_id'=>null,'free_used_at'=>null];
}
function rel_test_context(bool $available=true): array
{
    if ($available) return relisting_context_values(rel_test_link(),rel_test_chain(),7);
    $link=rel_test_link(); $link['parent_listing_id']=11; $link['child_listing_id']=12;
    $chain=rel_test_chain(); $chain['free_listing_id']=11; $chain['free_used_at']='2026-01-02 12:00:00';
    return relisting_context_values($link,$chain,7);
}
function rel_test_quote(int $listingFee=50, string $locale='de'): array
{
    $documents=[];
    foreach ([41=>'terms',42=>'fees'] as $id=>$kind) {
        $body=str_repeat('Synthetic relisting consent, no actual legal approval. ',5).$kind;
        $documents[]=['id'=>$id,'document_code'=>'synthetic-'.$kind,'version'=>'1.0','language'=>$locale,
            'title'=>'Synthetic '.$kind,'body_text'=>$body,'content_hash'=>hash('sha256',$body)];
    }
    $payload=['version'=>'auction-billing-1','fee_version'=>'ab1-synthetic',
        'tariff'=>['listing_cents'=>$listingFee,'rates'=>[300,200,100,50],'cap_cents'=>2990],
        'buyer'=>['user_id'=>7,'legal_name'=>'Synthetic Käufer Łódź','address_line1'=>'Synthetic Straße 1'],
        'operator'=>['legal_name'=>'Synthetic operator','email'=>'operator@example.invalid'],
        'tax_bps'=>1900,'tax_explanation'=>'Synthetic treatment only.','currency'=>'EUR','locale'=>$locale,
        'recipient'=>'merchant@example.invalid','labels'=>['listing'=>'Synthetic listing fee','success'=>'Synthetic success fee'],
        'terms'=>'Synthetic original conditions.',
        'documents'=>array_map(static fn(array $doc):array=>['id'=>$doc['id'],'hash'=>$doc['content_hash'],'language'=>$doc['language']],$documents)];
    return ['payload'=>$payload,'documents'=>$documents,'hash'=>hash('sha256',auction_billing_json($payload))];
}
function rel_test_submission(array $quote): array
{
    return ['auction_fee_action'=>'publish','accept_auction_fees'=>'1','auction_fee_quote'=>$quote['hash'],
        'legal_confirm'=>'1','legal_language'=>$quote['payload']['locale'],
        'legal_documents'=>array_column($quote['documents'],'content_hash','id')];
}
function rel_test_images(int $count=2): array
{
    $rows=[];
    for ($position=0;$position<$count;$position++) $rows[]=['storage_key'=>str_repeat(dechex($position+1),48),
        'position'=>$position,'width'=>1600,'height'=>900];
    return $rows;
}

$hadLocale=array_key_exists('locale',$_SESSION??[]); $originalLocale=$_SESSION['locale']??null;
$passed=0; $failure=null; $name='initialization';
try {
    $_SESSION['locale']='de'; $now=1769947200; // 2026-02-01 12:00:00 UTC.
    $tests=[
        'database_integer_boundaries_reject_coercion_and_overflow'=>static function(): void {
            foreach ([1,'1',PHP_INT_MAX,(string)PHP_INT_MAX] as $value)
                rel_test_check(relisting_integer($value)===(int)$value,'Valid canonical integer rejected.');
            rel_test_check(relisting_integer('0',0,4)===0,'Position zero must be valid.');
            foreach ([0,-1,'01','+1','1.0','1e1',' 1','1 ',true,false,1.0,[],null,'9223372036854775808','99999999999999999999'] as $bad)
                rel_test_reject(fn()=>relisting_integer($bad));
            rel_test_reject(fn()=>relisting_integer(5,0,4));
        },
        'only_a_real_completed_unsold_auction_is_eligible'=>static function() use($now): void {
            rel_test_check(relisting_source_eligible(rel_test_source(),rel_test_events(),false,$now),'Genuine no-bid completion rejected.');
            $source=rel_test_source(); $source['id']='10';
            rel_test_check(relisting_source_eligible($source,rel_test_events(),false,$now),'Canonical DB ID string rejected.');
            foreach (['draft','active','paused','sold','review','archived','ENDED'] as $status) {
                $source=rel_test_source(); $source['status']=$status;
                rel_test_check(!relisting_source_eligible($source,rel_test_events(),false,$now),'Invalid source status accepted.');
            }
            $source=rel_test_source(); $source['type']='fixed';
            rel_test_check(!relisting_source_eligible($source,rel_test_events(),false,$now),'Fixed listing accepted by auction-only relisting.');
        },
        'proxy_winner_or_price_evidence_blocks_unsold_relisting'=>static function() use($now): void {
            rel_test_check(!relisting_source_eligible(rel_test_source(),rel_test_events(),true,$now),'Existing proxy bid was ignored.');
            foreach (['highest_bidder_id','current_bid_cents'] as $field) {
                foreach ([0,'0',1,1000,''] as $value) {
                    $source=rel_test_source(); $source[$field]=$value;
                    rel_test_check(!relisting_source_eligible($source,rel_test_events(),false,$now),'Non-null sale evidence was accepted.');
                }
                $source=rel_test_source(); unset($source[$field]);
                rel_test_check(!relisting_source_eligible($source,rel_test_events(),false,$now),'Missing sale evidence field was accepted.');
            }
        },
        'close_event_must_be_unique_and_bound_to_the_exact_source'=>static function() use($now): void {
            $source=rel_test_source();
            foreach ([[],array_merge(rel_test_events(),rel_test_events()),[null]] as $events)
                rel_test_check(!relisting_source_eligible($source,$events,false,$now),'Missing/duplicate close proof accepted.');
            foreach (['bid','buyout','close','close_review',''] as $kind) {
                $events=rel_test_events(); $events[0]['event_type']=$kind;
                rel_test_check(!relisting_source_eligible($source,$events,false,$now),'Wrong historical event accepted.');
            }
            foreach (['idempotency_key'=>'close-11','user_id'=>7,'amount_cents'=>0] as $field=>$value) {
                $events=rel_test_events(); $events[0][$field]=$value;
                rel_test_check(!relisting_source_eligible($source,$events,false,$now),'Altered close proof accepted.');
            }
            foreach (['idempotency_key','user_id','amount_cents'] as $field) {
                $events=rel_test_events(); unset($events[0][$field]);
                rel_test_check(!relisting_source_eligible($source,$events,false,$now),'Incomplete close proof accepted.');
            }
        },
        'expired_timestamp_is_strict_utc_not_a_normalized_or_future_date'=>static function() use($now): void {
            foreach ([null,'','2026-02-30 12:00:00','2026-01-01 24:00:00',"2026-01-01 12:00:00\0",
                '2026-01-01T12:00:00Z','2026-01-01 12:00:00+01:00','2026-1-1 12:00:00','2027-01-01 12:00:00'] as $value) {
                $source=rel_test_source(); $source['ends_at']=$value;
                rel_test_check(!relisting_source_eligible($source,rel_test_events(),false,$now),'Invalid/future close time accepted.');
            }
            $timezone=date_default_timezone_get(); date_default_timezone_set('Pacific/Chatham');
            try { rel_test_check(relisting_timestamp('1970-01-01 00:00:00')===0,'Database date was not interpreted as UTC.'); }
            finally { date_default_timezone_set($timezone); }
            rel_test_check(relisting_timestamp('2024-02-29 00:00:00')!==null&&relisting_timestamp('2025-02-29 00:00:00')===null,'Leap day validation failed.');
        },
        'lineage_context_normalizes_ids_and_preserves_unused_entitlement'=>static function(): void {
            $link=rel_test_link(); $chain=rel_test_chain();
            foreach (['root_listing_id','parent_listing_id','child_listing_id','seller_id'] as $key) $link[$key]=(string)$link[$key];
            $chain['root_listing_id']='10'; $chain['seller_id']='7';
            $context=relisting_context_values($link,$chain,7);
            rel_test_check($context===['root_listing_id'=>10,'parent_listing_id'=>10,'child_listing_id'=>11,'seller_id'=>7,
                'published_at'=>null,'free_available'=>true,'consumed_by'=>null],'Lineage normalized incorrectly.');
        },
        'lineage_context_rejects_wrong_owner_cycles_and_inconsistent_claim'=>static function(): void {
            rel_test_reject(fn()=>relisting_context_values(rel_test_link(),rel_test_chain(),8));
            foreach (['root_listing_id'=>12,'parent_listing_id'=>11,'child_listing_id'=>10,'seller_id'=>8] as $key=>$value) {
                $link=rel_test_link(); $link[$key]=$value;
                rel_test_reject(fn()=>relisting_context_values($link,rel_test_chain(),7));
            }
            foreach ([['root_listing_id'=>9],['seller_id'=>8],['free_listing_id'=>11],['free_used_at'=>'2026-01-02 12:00:00'],
                ['free_listing_id'=>10,'free_used_at'=>'2026-01-02 12:00:00'],
                ['free_listing_id'=>11,'free_used_at'=>'invalid'],
                ['free_listing_id'=>11,'free_used_at'=>'2026-01-02 12:00:00']] as $changes) {
                $chain=array_replace(rel_test_chain(),$changes);
                rel_test_reject(fn()=>relisting_context_values(rel_test_link(),$chain,7));
            }
            $link=rel_test_link(); $link['published_at']='not a timestamp';
            rel_test_reject(fn()=>relisting_context_values($link,rel_test_chain(),7));
        },
        'used_entitlement_remains_used_on_later_descendants'=>static function(): void {
            $context=rel_test_context(false);
            rel_test_check($context['root_listing_id']===10&&$context['parent_listing_id']===11&&$context['child_listing_id']===12
                &&$context['consumed_by']===11&&!$context['free_available'],'A descendant reset the claim.');
            $link=rel_test_link(); $link['published_at']='2026-01-02 12:00:00';
            $chain=rel_test_chain(); $chain['free_listing_id']=12; $chain['free_used_at']='2026-01-04 12:00:00';
            rel_test_check(!relisting_context_values($link,$chain,7)['free_available'],'Reading an earlier published member reset a later claim.');
        },
        'valid_image_manifest_is_bounded_ordered_and_does_not_mutate_source'=>static function(): void {
            rel_test_check(relisting_image_manifest([])===[],'Image-free source should be supported.');
            $images=rel_test_images(5); $saved=$images;
            foreach ($images as &$image) { $image['position']=(string)$image['position']; $image['width']=(string)$image['width']; } unset($image);
            rel_test_check(relisting_image_manifest($images)===$saved,'Valid image manifest did not normalize correctly.');
            rel_test_check($images[0]['width']==='1600','Manifest validation mutated its source argument.');
        },
        'image_manifest_rejects_traversal_duplicates_holes_and_unbounded_dimensions'=>static function(): void {
            rel_test_reject(fn()=>relisting_image_manifest(rel_test_images(6)));
            $images=rel_test_images(); $images[1]['storage_key']=$images[0]['storage_key']; rel_test_reject(fn()=>relisting_image_manifest($images));
            $images=rel_test_images(); unset($images[0]); rel_test_reject(fn()=>relisting_image_manifest($images));
            foreach (['../'.str_repeat('a',45),str_repeat('A',48),str_repeat('a',47),str_repeat('a',49),str_repeat('g',48),"\0".str_repeat('a',47)] as $key) {
                $images=rel_test_images(1); $images[0]['storage_key']=$key; rel_test_reject(fn()=>relisting_image_manifest($images));
            }
            foreach (['position'=>[1,-1,'00',false],'width'=>[0,1601,1.0,'1e3'],'height'=>[0,1601,null,[]]] as $field=>$values) {
                foreach ($values as $value) { $images=rel_test_images(1); $images[0][$field]=$value; rel_test_reject(fn()=>relisting_image_manifest($images)); }
            }
        },
        'positive_listing_fee_is_explicitly_discounted_and_quote_is_rebound'=>static function(): void {
            $original=rel_test_quote(); $saved=$original; $quote=relisting_quote_apply($original,rel_test_context()); $payload=$quote['payload'];
            rel_test_check($payload['tariff']['listing_cents']===0,'Free relist did not remove the listing fee.');
            rel_test_check($payload['relisting']===['version'=>'relisting-1','root_listing_id'=>10,'parent_listing_id'=>10,'child_listing_id'=>11,
                'original_listing_cents'=>50,'discount_cents'=>50,'free_applied'=>true],'Discount was not explicitly bound to its source and amount.');
            rel_test_check($quote['hash']!==$saved['hash']&&$quote['hash']===hash('sha256',auction_billing_json($payload)),'Discount quote hash is stale.');
            rel_test_check($payload['fee_version']!==$saved['payload']['fee_version']&&strlen($payload['fee_version'])<=32,'Discount tariff version is absent or exceeds schema.');
            rel_test_check(str_contains($payload['terms'],money_eur(0)),'Terms still show a positive listing price.');
            rel_test_check($original===$saved,'Quote transformation mutated the original offer.');
            rel_test_check(relisting_quote_claim($payload,rel_test_context()),'Valid positive-price discount was not claimable.');
        },
        'free_listing_never_removes_or_changes_success_commission'=>static function(): void {
            $quote=relisting_quote_apply(rel_test_quote(),rel_test_context());
            foreach ([10000=>300,50000=>1100,200000=>2600,10000000=>2990] as $sale=>$expected)
                rel_test_check(auction_billing_amount($quote['payload'],'success',$sale)===$expected,'Free relist changed independently calculated success commission.');
            rel_test_check($quote['payload']['tariff']['rates']===[300,200,100,50]&&$quote['payload']['tariff']['cap_cents']===2990,'Success tariff changed.');
        },
        'already_zero_base_fee_does_not_claim_or_advertise_a_discount'=>static function(): void {
            $context=rel_test_context(); $quote=relisting_quote_apply(rel_test_quote(0),$context);
            rel_test_check($quote['payload']['tariff']['listing_cents']===0&&!$quote['payload']['relisting']['free_applied']
                &&$quote['payload']['relisting']['discount_cents']===0&&!relisting_quote_claim($quote['payload'],$context),'A zero base fee consumed the entitlement.');
            rel_test_check($context['free_available']&&$context['consumed_by']===null,'Zero-price calculation changed claim state.');
            rel_test_check(!str_contains($quote['payload']['terms'],relisting_quote_text('de','relisting.free_label')),'Zero base fee advertised a used discount.');
        },
        'used_claim_charges_current_listing_fee_without_a_second_discount'=>static function(): void {
            $context=rel_test_context(false); $quote=relisting_quote_apply(rel_test_quote(199),$context);
            rel_test_check($quote['payload']['tariff']['listing_cents']===199&&!$quote['payload']['relisting']['free_applied']
                &&$quote['payload']['relisting']['discount_cents']===0&&!relisting_quote_claim($quote['payload'],$context),'Used lineage was given another discount.');
        },
        'quote_preserves_legal_versions_invoice_identity_and_user_authored_text'=>static function(): void {
            $original=rel_test_quote(); $quote=relisting_quote_apply($original,rel_test_context());
            foreach (['buyer','operator','tax_bps','tax_explanation','recipient','currency','locale','documents','labels'] as $field)
                rel_test_check($quote['payload'][$field]===$original['payload'][$field],'Discount altered frozen field '.$field.'.');
            rel_test_check($quote['documents']===$original['documents'],'Discount replaced legal documents.');
        },
        'ordinary_quote_or_another_child_quote_cannot_accept_discounted_publication'=>static function(): void {
            $normal=rel_test_quote(); $discount=relisting_quote_apply($normal,rel_test_context());
            rel_test_reject(fn()=>auction_billing_validate_quote($discount,rel_test_submission($normal)),InvalidArgumentException::class);
            $context=rel_test_context(); $context['root_listing_id']=20; $context['parent_listing_id']=20; $context['child_listing_id']=21;
            $other=relisting_quote_apply($normal,$context);
            rel_test_check($other['hash']!==$discount['hash'],'Different lineage shared an accepted quote hash.');
            rel_test_reject(fn()=>auction_billing_validate_quote($other,rel_test_submission($discount)),InvalidArgumentException::class);
            auction_billing_validate_quote($discount,rel_test_submission($discount));
        },
        'discount_requires_the_explicit_action_and_exact_legal_consent'=>static function(): void {
            $quote=relisting_quote_apply(rel_test_quote(),rel_test_context());
            foreach (['auction_fee_action','accept_auction_fees','auction_fee_quote','legal_confirm','legal_language','legal_documents'] as $key) {
                $input=rel_test_submission($quote); unset($input[$key]);
                rel_test_reject(fn()=>auction_billing_validate_quote($quote,$input),InvalidArgumentException::class);
            }
            $input=rel_test_submission($quote); $input['legal_documents'][41]=str_repeat('0',64);
            rel_test_reject(fn()=>auction_billing_validate_quote($quote,$input),InvalidArgumentException::class);
        },
        'invalid_quote_hash_owner_currency_or_repeat_transformation_is_rejected'=>static function(): void {
            $context=rel_test_context();
            $bad=rel_test_quote(); $bad['hash']=str_repeat('0',64); rel_test_reject(fn()=>relisting_quote_apply($bad,$context));
            foreach (['buyer'=>['user_id'=>8],'currency'=>'USD','locale'=>'unknown'] as $field=>$value) {
                $bad=rel_test_quote(); $bad['payload'][$field]=$value; $bad['hash']=hash('sha256',auction_billing_json($bad['payload']));
                rel_test_reject(fn()=>relisting_quote_apply($bad,$context));
            }
            $once=relisting_quote_apply(rel_test_quote(),$context);
            rel_test_reject(fn()=>relisting_quote_apply($once,$context));
            $bad=rel_test_quote(); $bad['payload']['tariff']['rates']=[0,0,0,0]; $bad['hash']=hash('sha256',auction_billing_json($bad['payload']));
            rel_test_reject(fn()=>relisting_quote_apply($bad,$context),RuntimeException::class);
        },
        'published_or_inconsistent_context_cannot_generate_a_fresh_claim'=>static function(): void {
            foreach ([['published_at'=>'2026-01-02 12:00:00'],['seller_id'=>8],['parent_listing_id'=>11],['root_listing_id'=>0],
                ['free_available'=>'1'],['free_available'=>false],['consumed_by'=>11],['free_available'=>false,'consumed_by'=>11]] as $changes) {
                $context=array_replace(rel_test_context(),$changes);
                rel_test_reject(fn()=>relisting_quote_apply(rel_test_quote(),$context));
            }
        },
        'claim_rejects_altered_ids_discount_amounts_tariffs_and_version'=>static function(): void {
            $context=rel_test_context(); $payload=relisting_quote_apply(rel_test_quote(),$context)['payload'];
            foreach (['version'=>'other','root_listing_id'=>20,'parent_listing_id'=>9,'child_listing_id'=>12,
                'original_listing_cents'=>51,'discount_cents'=>49,'free_applied'=>false] as $field=>$value) {
                $bad=$payload; $bad['relisting'][$field]=$value;
                rel_test_reject(fn()=>relisting_quote_claim($bad,$context));
            }
            foreach (['root_listing_id','parent_listing_id','child_listing_id','original_listing_cents','discount_cents','free_applied'] as $field) {
                $bad=$payload; unset($bad['relisting'][$field]); rel_test_reject(fn()=>relisting_quote_claim($bad,$context));
            }
            $bad=$payload; $bad['tariff']['listing_cents']=1; rel_test_reject(fn()=>relisting_quote_claim($bad,$context));
            $bad=$payload; $bad['fee_version']='forged'; rel_test_reject(fn()=>relisting_quote_claim($bad,$context));
            $bad=$payload; $bad['buyer']['user_id']=8; rel_test_reject(fn()=>relisting_quote_claim($bad,$context));
        },
        'a_claim_used_after_quote_display_invalidates_the_old_acceptance'=>static function(): void {
            $context=rel_test_context(); $context['parent_listing_id']=11; $context['child_listing_id']=12;
            $quote=relisting_quote_apply(rel_test_quote(),$context);
            $current=$context; $current['consumed_by']=11; $current['free_available']=false;
            rel_test_reject(fn()=>relisting_quote_claim($quote['payload'],$current));
            $fresh=relisting_quote_apply(rel_test_quote(),$current);
            rel_test_check($fresh['payload']['tariff']['listing_cents']===50&&$fresh['hash']!==$quote['hash'],'Changed claim did not change the payable quote.');
            rel_test_reject(fn()=>auction_billing_validate_quote($fresh,rel_test_submission($quote)),InvalidArgumentException::class);
        },
        'terms_follow_frozen_quote_language_without_changing_browser_locale'=>static function(): void {
            $_SESSION['locale']='de'; $quote=relisting_quote_apply(rel_test_quote(50,'en'),rel_test_context());
            $expected=relisting_quote_text('en','auction_billing.tariff',['listing'=>money_eur(0)]).' '.relisting_quote_text('en','auction_billing.accept');
            rel_test_check(str_starts_with($quote['payload']['terms'],$expected),'Contract terms were written in the acting browser language.');
            rel_test_check(i18n_locale()==='de'&&$quote['payload']['locale']==='en','Transform changed browser or contract locale.');
            rel_test_check($quote['payload']['buyer']['legal_name']==='Synthetic Käufer Łódź','User identity text was translated.');
        },
    ];
    foreach ($tests as $name=>$run) { $run(); ++$passed; fwrite(STDOUT,'PASS '.$name.PHP_EOL); }
} catch (Throwable $error) { $failure=$error; }
finally { if ($hadLocale) $_SESSION['locale']=$originalLocale; else unset($_SESSION['locale']); }
if ($failure!==null) {
    fwrite(STDERR,'FAIL '.$name.' ('.get_class($failure).' at '.basename($failure->getFile()).':'.$failure->getLine().'): '.$failure->getMessage().PHP_EOL);
    exit(1);
}
fwrite(STDOUT,'Relisting pure checks passed: '.$passed.PHP_EOL);
