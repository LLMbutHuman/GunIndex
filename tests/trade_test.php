<?php
declare(strict_types=1);
/** Pure contract/security regressions; no database, providers or file writes. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit(2); }
require_once __DIR__.'/../config/i18n.php';
require_once __DIR__.'/../config/trade.php';

function trt_check(bool $ok,string $message):void { if(!$ok)throw new RuntimeException($message); }
function trt_reject(callable $run):void {
    try{$run();}catch(DomainException|InvalidArgumentException $error){return;}
    throw new RuntimeException('Expected invalid contract input to be rejected.');
}
function trt_listing():array {
    return ['id'=>17,'seller_id'=>3,'category_id'=>8,'title'=>'Synthetic folding desk',
        'description'=>'Ordinary synthetic desk, no real customer data.','type'=>'negotiated',
        'price_cents'=>4500,'shipping_cents'=>300,'pickup'=>1,'shipping'=>1,'country_code'=>'DE',
        'zip'=>'10115','city'=>'Synthetic town','condition_label'=>'used','ends_at'=>null,'updated_at'=>'2026-01-01 00:00:00'];
}
function trt_trade(array $changes=[]):array {
    return array_replace(['id'=>19,'seller_id'=>3,'buyer_id'=>5,'status'=>'agreed',
        'seller_confirmed'=>0,'buyer_confirmed'=>0,'requested_by'=>null,'previous_status'=>null],$changes);
}

$name='initialization';$passed=0;
try{
    $tests=[
        'canonical_integer_boundaries_reject_coercion_and_overflow'=>static function():void {
            foreach([1,'1',PHP_INT_MAX,(string)PHP_INT_MAX] as $valid)trt_check(trade_integer($valid)===(int)$valid,'Valid integer rejected.');
            trt_check(trade_integer('0',0,0)===0,'Allowed zero rejected.');
            foreach([0,-1,'01','+1','1.0','1e1',' 1','1 ',true,false,1.0,[],null,'9223372036854775808',str_repeat('9',50)] as $bad)trt_reject(fn()=>trade_integer($bad));
            trt_reject(fn()=>trade_integer(169,1,168));
        },
        'request_and_quote_tokens_require_exact_lowercase_hex'=>static function():void {
            trt_check(trade_hex(str_repeat('a',32))===str_repeat('a',32),'Request key rejected.');
            trt_check(trade_hex(str_repeat('0',64),64)===str_repeat('0',64),'Quote hash rejected.');
            foreach(['',str_repeat('a',31),str_repeat('a',33),str_repeat('A',32),str_repeat('g',32),str_repeat('0',32)."\n",0,[],null] as $bad)trt_reject(fn()=>trade_hex($bad));
        },
        'listing_hash_binds_every_customer_visible_term'=>static function():void {
            $listing=trt_listing();$hash=trade_listing_hash($listing);
            foreach(['id'=>18,'seller_id'=>4,'category_id'=>9,'title'=>'Another desk','description'=>'Another condition',
                'type'=>'fixed','price_cents'=>4501,'shipping_cents'=>301,'pickup'=>0,'shipping'=>0,'country_code'=>'NL',
                'zip'=>'10116','city'=>'Another town','condition_label'=>'new','ends_at'=>'2027-01-01 00:00:00'] as $field=>$value){
                $changed=$listing;$changed[$field]=$value;trt_check(trade_listing_hash($changed)!==$hash,'Changed trade term did not invalidate quote: '.$field);
            }
        },
        'listing_hash_is_stable_across_pdo_integer_types_and_unrelated_timestamps'=>static function():void {
            $listing=trt_listing();$canonical=$listing;
            foreach(['id','seller_id','category_id','price_cents','shipping_cents','pickup','shipping'] as $field)$canonical[$field]=(string)$canonical[$field];
            $canonical['updated_at']='2026-11-15 12:00:00';$canonical=array_reverse($canonical,true);
            trt_check(trade_listing_hash($listing)===trade_listing_hash($canonical),'Unrelated timestamp/PDO ordering changed contract.');
        },
        'delivery_uses_exact_nonnegative_costs_and_enabled_options'=>static function():void {
            $listing=trt_listing();trt_check(trade_delivery($listing,'pickup')===0,'Pickup charged shipping.');
            trt_check(trade_delivery($listing,'shipping')===300,'Shipping cost changed.');
            $listing['shipping_cents']='9999999999';trt_check(trade_delivery($listing,'shipping')===9999999999,'Maximum shipping cost corrupted.');
            foreach([-1,'01',1.1,'10000000000',null] as $bad){$broken=$listing;$broken['shipping_cents']=$bad;if($bad===null)continue;trt_reject(fn()=>trade_delivery($broken,'shipping'));}
            foreach(['pickup','shipping'] as $delivery){$disabled=$listing;$disabled[$delivery]=0;trt_reject(fn()=>trade_delivery($disabled,$delivery));}
            foreach(['','SHIPPING','courier','shipping ' ] as $bad)trt_reject(fn()=>trade_delivery($listing,$bad));
        },
        'idempotency_is_order_independent_but_binds_semantic_request'=>static function():void {
            $input=['delivery'=>'pickup','quote_hash'=>str_repeat('a',64),'request_key'=>str_repeat('b',32),
                'legal_documents'=>[41=>'first',42=>'second'],'csrf_token'=>'old','confirm_purchase'=>'1'];
            $hash=trade_request_hash('purchase',17,null,$input);$retry=array_reverse($input,true);
            $retry['csrf_token']='new';$retry['legal_documents']=[42=>'second',41=>'first'];
            trt_check(trade_request_hash('purchase',17,null,$retry)===$hash,'Equivalent retry did not canonicalize.');
            foreach(['delivery'=>'shipping','quote_hash'=>str_repeat('c',64),'confirm_purchase'=>'0','legal_documents'=>[41=>'different',42=>'second']] as $field=>$value){
                $changed=$input;$changed[$field]=$value;trt_check(trade_request_hash('purchase',17,null,$changed)!==$hash,'Changed retry accepted: '.$field);
            }
            trt_check(trade_request_hash('purchase',18,null,$input)!==$hash,'Request not bound to listing.');
            trt_check(trade_request_hash('purchase',17,21,$input)!==$hash,'Request not bound to offer.');
            trt_check(trade_request_hash('offer',17,null,$input)!==$hash,'Request not bound to operation.');
        },
        'snapshot_preserves_original_text_parties_and_negotiated_amount'=>static function():void {
            $listing=trt_listing();$listing['description']='Synthetic Unicode: Straße, Łódź, <script>not active</script>';
            $snapshot=trade_snapshot($listing,5,'shipping',4200,300);
            trt_check($snapshot['listing_id']===17&&$snapshot['seller_id']===3&&$snapshot['buyer_id']===5,'Snapshot parties incorrect.');
            trt_check($snapshot['description']===$listing['description']&&$snapshot['title']===$listing['title'],'Snapshot changed authored text.');
            trt_check($snapshot['price_cents']===4200&&$snapshot['shipping_cents']===300&&$snapshot['total_cents']===4500&&$snapshot['currency']==='EUR','Negotiated snapshot used list price.');
            $listing['description']='Changed';trt_check($snapshot['description']!==$listing['description'],'Snapshot references mutable input.');
        },
        'offers_require_unexpired_open_matching_listing_and_content'=>static function():void {
            $listing=trt_listing();$offer=['listing_id'=>17,'status'=>'open','expires_at'=>gmdate('Y-m-d H:i:s',time()+3600),'listing_hash'=>trade_listing_hash($listing)];
            trade_offer_live($offer,$listing);
            foreach(['listing_id'=>18,'status'=>'countered','expires_at'=>'2000-01-01 00:00:00','listing_hash'=>str_repeat('0',64)] as $field=>$value){
                $invalid=$offer;$invalid[$field]=$value;trt_reject(fn()=>trade_offer_live($invalid,$listing));
            }
            foreach(['accepted','rejected','withdrawn','expired','OPEN',''] as $status){$invalid=$offer;$invalid['status']=$status;trt_reject(fn()=>trade_offer_live($invalid,$listing));}
        },
        'outsiders_never_receive_party_actions_even_when_marked_staff'=>static function():void {
            foreach(['agreed','dispatched','completed','cancel_requested','cancelled','unknown'] as $status){
                $row=trt_trade(['status'=>$status,'requested_by'=>3]);
                trt_check(trade_allowed_actions($row,7)===[]&&trade_allowed_actions($row,7,true)===[],'Outsider received a party capability.');
            }
        },
        'dispatch_and_receipt_capabilities_follow_roles_and_state'=>static function():void {
            $agreed=trt_trade();trt_check(in_array('dispatch',trade_allowed_actions($agreed,3),true),'Seller cannot dispatch.');
            trt_check(!in_array('dispatch',trade_allowed_actions($agreed,5),true),'Buyer can dispatch.');
            trt_check(!in_array('receive',trade_allowed_actions($agreed,5),true),'Buyer can receive before dispatch.');
            $dispatched=trt_trade(['status'=>'dispatched']);
            trt_check(in_array('receive',trade_allowed_actions($dispatched,5),true),'Buyer cannot confirm delivery.');
            trt_check(!in_array('receive',trade_allowed_actions($dispatched,3),true),'Seller can confirm own delivery.');
            trt_check(!in_array('dispatch',trade_allowed_actions($dispatched,3),true),'Dispatched trade permits redispatch.');
            trt_check(!in_array('receive',trade_allowed_actions(trt_trade(['status'=>'dispatched','buyer_confirmed'=>1]),5),true),'Already confirmed receipt repeats.');
        },
        'external_settlement_confirmation_belongs_only_to_seller'=>static function():void {
            foreach(['agreed','dispatched'] as $status){
                $row=trt_trade(['status'=>$status]);trt_check(in_array('settle',trade_allowed_actions($row,3),true),'Seller cannot report arrangement.');
                trt_check(!in_array('settle',trade_allowed_actions($row,5),true),'Buyer can certify seller arrangement.');
                $row['seller_confirmed']=1;trt_check(!in_array('settle',trade_allowed_actions($row,3),true),'Repeated external confirmation still available.');
            }
        },
        'cancellation_requires_the_other_party_and_preserves_dispute_access'=>static function():void {
            foreach([3=>5,5=>3] as $requester=>$recipient){
                $row=trt_trade(['status'=>'cancel_requested','requested_by'=>$requester,'previous_status'=>'agreed']);
                foreach(['cancel_accept','cancel_decline'] as $action){
                    trt_check(!in_array($action,trade_allowed_actions($row,$requester),true),'Requester can answer own cancellation.');
                    trt_check(in_array($action,trade_allowed_actions($row,$recipient),true),'Counterparty cannot answer cancellation.');
                }
                trt_check(in_array('dispute',trade_allowed_actions($row,$requester),true),'Cancellation hides dispute access.');
            }
        },
        'disputes_require_independent_staff_and_terminal_states_deny_fulfilment'=>static function():void {
            $disputed=trt_trade(['status'=>'disputed']);
            trt_check(trade_allowed_actions($disputed,7,true)===['resolve_complete','resolve_cancel'],'Independent staff cannot resolve.');
            foreach([3,5] as $party){
                trt_check(trade_allowed_actions($disputed,$party,true)===[],'Staff party can adjudicate own dispute.');
                trt_check(trade_allowed_actions(trt_trade(['status'=>'cancelled']),$party)===[],'Cancelled trade permits mutations.');
                trt_check(trade_allowed_actions(trt_trade(['status'=>'completed']),$party)===['dispute'],'Completed trade permits fulfilment mutations.');
            }
        },
        'each_trade_catalog_preserves_keys_and_interpolation'=>static function():void {
            $base=require __DIR__.'/../locales/trade/en.php';
            foreach(['de','en','fr','nl','pl','cs','da','it'] as $locale){
                $catalog=require __DIR__.'/../locales/trade/'.$locale.'.php';
                trt_check(count($catalog)===count($base),'Unexpected trade catalog size: '.$locale);
                foreach($base as $key=>$text){
                    $localized=$catalog[$key]??null;
                    trt_check(is_string($localized)&&trim($localized)!==''&&preg_match('//u',$localized)===1,'Invalid localized entry: '.$locale.' '.$key);
                    preg_match_all('/\{[a-z_]+\}/',$text,$expected);preg_match_all('/\{[a-z_]+\}/',$localized,$actual);
                    sort($expected[0]);sort($actual[0]);
                    trt_check($expected[0]===$actual[0],'Incorrect trade placeholders: '.$locale.' '.$key);
                }
            }
        },
    ];
    foreach($tests as $name=>$run){$run();++$passed;fwrite(STDOUT,'PASS '.$name.PHP_EOL);}
}catch(Throwable $error){fwrite(STDERR,'FAIL '.$name.' ('.get_class($error).' at '.basename($error->getFile()).':'.$error->getLine().'): '.$error->getMessage().PHP_EOL);exit(1);}
fwrite(STDOUT,'Trade pure checks passed: '.$passed.PHP_EOL);
