<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__ . '/../config/fees.php';
require_once __DIR__ . '/../config/payments.php';

$passed = 0;
function same(mixed $expected, mixed $actual): void { if ($expected !== $actual) throw new RuntimeException('Assertion failed.'); }
function rejected(callable $run, string $class = Throwable::class): void
{
    try { $run(); } catch (Throwable $error) { if ($error instanceof $class) return; throw new RuntimeException('Unexpected error class.'); }
    throw new RuntimeException('Expected rejection.');
}
function test_payment(string $name, callable $run): void
{
    global $passed;
    try { $run(); ++$passed; echo 'PASS ' . $name . "\n"; }
    catch (Throwable) { fwrite(STDERR, 'FAIL ' . $name . "\n"); exit(1); }
}
function test_config(array $replace = []): array
{
    return array_replace(['enabled'=>true,'mode'=>'sandbox','scope'=>'general_only','account_type'=>'business','billing_approved'=>true,'live_approved'=>false,'client_id'=>'synthetic-client','client_secret'=>'synthetic-secret','merchant_id'=>'SYNTHETICMERCH','webhook_id'=>'SYNTHETICHOOK','app_url'=>'https://market.example.invalid'], $replace);
}
function test_invoice(): array { return ['id'=>42,'invoice_number'=>'GI-42','gross_cents'=>1650,'currency'=>'EUR','status'=>'issued']; }
function test_order(bool $completed = false): array
{
    $order = ['id'=>'ORDER123456','intent'=>'CAPTURE','status'=>$completed?'COMPLETED':'APPROVED','purchase_units'=>[['reference_id'=>'invoice:42','custom_id'=>'42','invoice_id'=>'GI-42','payee'=>['merchant_id'=>'SYNTHETICMERCH'],'amount'=>['currency_code'=>'EUR','value'=>'16.50']]],'links'=>[['rel'=>'payer-action','href'=>'https://www.sandbox.paypal.com/checkoutnow?token=ORDER123456']]];
    if ($completed) $order['purchase_units'][0]['payments'] = ['captures'=>[['id'=>'CAPTURE123456','status'=>'COMPLETED','final_capture'=>true,'amount'=>['currency_code'=>'EUR','value'=>'16.50']]]];
    return $order;
}
function test_client(array &$calls, callable $response): MarketPayPal
{
    return new MarketPayPal(test_config(), static function($method,$url,$headers,$body) use (&$calls,$response): array {
        $calls[] = [$method,$url,$headers,$body];
        if (str_ends_with($url,'/v1/oauth2/token')) return ['status'=>200,'body'=>['access_token'=>'synthetic-access-token']];
        return $response($method,$url,$headers,$body);
    });
}

test_payment('progressive_tiers_rounding_and_cap', static function(): void {
    foreach ([0=>0,17=>1,10000=>300,50000=>1100,100000=>1600,200000=>2600,300000=>2990,9999999999=>2990] as $sale=>$fee) same($fee,fees_quote('auction_success',$sale)['gross_cents']);
    rejected(static fn()=>fees_quote('auction_success',-1),InvalidArgumentException::class);
});
test_payment('fixed_free_and_paid_prices_are_exact', static function(): void {
    foreach (['private_fixed'=>0,'wanted'=>0,'search_alert'=>0,'auction_listing'=>50,'bump_single'=>199,'bump_pack'=>499,'category_top'=>990,'home_top'=>1990,'banner_monthly'=>12900] as $service=>$cents) same($cents,fees_quote($service)['gross_cents']);
    rejected(static fn()=>fees_quote('other'),InvalidArgumentException::class);
});
test_payment('fee_version_deterministic_and_escrow_unavailable', static function(): void {
    same(fees_quote('bump_single')['version'],fees_quote('bump_single')['version']);
    same(249,fees_quote('escrow_preview',100)['gross_cents']); same(3990,fees_quote('escrow_preview',9999999999)['gross_cents']); same(false,fees_quote('escrow_preview',10000)['available']);
});
test_payment('tax_split_requires_review_and_preserves_total', static function(): void {
    rejected(static fn()=>fees_tax_split(199,1900,false),InvalidArgumentException::class);
    $split=fees_tax_split(199,1900,true);same(199,$split['net_cents']+$split['tax_cents']);same(167,$split['net_cents']);
});
test_payment('personal_account_not_misrepresented_as_merchant', static function(): void {
    same(null,payments_paypal_reason(test_config()));
    same(false,payments_methods(test_config(['account_type'=>'personal']))['paypal']['available']);
    rejected(static fn()=>payments_assert_provider('paypal',test_config(['account_type'=>'personal'])),PaymentUnavailable::class);
});
test_payment('disabled_bank_bitcoin_mock_cannot_be_enabled', static function(): void {
    foreach (['bank','bitcoin','mock','wire'] as $name) rejected(static fn()=>payments_assert_provider($name,test_config()),PaymentUnavailable::class);
});
test_payment('live_scope_billing_and_credentials_gates', static function(): void {
    foreach ([['enabled'=>false],['scope'=>'other'],['billing_approved'=>false],['mode'=>'live'],['client_secret'=>''],['webhook_id'=>''],['mode'=>'unsupported']] as $changes) same(false,payments_methods(test_config($changes))['paypal']['available']);
    same(true,payments_methods(test_config(['mode'=>'live','live_approved'=>true]))['paypal']['available']);
});
test_payment('callback_origin_refuses_credentials_and_public_http', static function(): void {
    foreach (['https://user@market.example.invalid','https://user:pass@market.example.invalid','http://market.example.invalid','https://market.example.invalid/?x=1','https://market.example.invalid/path','https://market.example.invalid/#x'] as $url) rejected(static fn()=>payments_origin(test_config(['app_url'=>$url])),PaymentUnavailable::class);
    same('http://127.0.0.1:8081',payments_origin(test_config(['app_url'=>'http://127.0.0.1:8081'])));
});
test_payment('provider_amounts_refuse_float_exponents_currency', static function(): void {
    same(1,payments_api_cents(['currency_code'=>'EUR','value'=>'0.01'])); same('16.50',payments_decimal(1650));
    foreach ([['currency_code'=>'USD','value'=>'16.50'],['currency_code'=>'EUR','value'=>16.5],['currency_code'=>'EUR','value'=>'1e3'],['currency_code'=>'EUR','value'=>'16,50']] as $amount) rejected(static fn()=>payments_api_cents($amount),PaymentConflict::class);
});
test_payment('checkout_payload_and_idempotency_are_server_owned', static function(): void {
    $calls=[];$client=test_client($calls,static fn()=>['status'=>201,'body'=>test_order()]);
    $client->create(test_invoice(),'1234567890abcdef1234567890abcdef');
    $request=$calls[1];same('https://api-m.sandbox.paypal.com/v2/checkout/orders',$request[1]);
    $body=json_decode($request[3],true,64,JSON_THROW_ON_ERROR);same('16.50',$body['purchase_units'][0]['amount']['value']);same('GI-42',$body['purchase_units'][0]['invoice_id']);same('SYNTHETICMERCH',$body['purchase_units'][0]['payee']['merchant_id']);
    same(true,in_array('PayPal-Request-Id: 1234567890abcdef1234567890abcdef',$request[2],true));
});
test_payment('approval_redirect_only_official_mode_and_token', static function(): void {
    $client=new MarketPayPal(test_config(),static fn()=>[]);same('https://www.sandbox.paypal.com/checkoutnow?token=ORDER123456',$client->approval(test_order()));
    foreach (['https://evil.example/checkoutnow?token=ORDER123456','https://www.paypal.com/checkoutnow?token=ORDER123456','https://www.sandbox.paypal.com/checkoutnow?token=OTHERID','https://user@www.sandbox.paypal.com/checkoutnow?token=ORDER123456'] as $href) { $order=test_order();$order['links'][0]['href']=$href;rejected(static fn()=>$client->approval($order),PaymentConflict::class); }
});
test_payment('approved_order_checked_before_capture', static function(): void {
    $client=new MarketPayPal(test_config(),static fn()=>[]);same(null,$client->validate(test_order(),test_invoice(),'ORDER123456',false));
    foreach (['amount','merchant','invoice','reference','custom','status'] as $kind) {
        $bad=test_order();
        if($kind==='amount')$bad['purchase_units'][0]['amount']['value']='1.00';
        if($kind==='merchant')$bad['purchase_units'][0]['payee']['merchant_id']='OTHER';
        if($kind==='invoice')$bad['purchase_units'][0]['invoice_id']='OTHER';
        if($kind==='reference')$bad['purchase_units'][0]['reference_id']='invoice:99';
        if($kind==='custom')$bad['purchase_units'][0]['custom_id']='99';
        if($kind==='status')$bad['status']='CREATED';
        rejected(static fn()=>$client->validate($bad,test_invoice(),'ORDER123456',false),PaymentConflict::class);
    }
});
test_payment('completed_capture_must_be_unique_final_and_exact', static function(): void {
    $client=new MarketPayPal(test_config(),static fn()=>[]);same('CAPTURE123456',$client->validate(test_order(true),test_invoice(),'ORDER123456',true));
    $bad=test_order(true);$bad['purchase_units'][0]['payments']['captures'][0]['amount']['value']='1.00';rejected(static fn()=>$client->validate($bad,test_invoice(),'ORDER123456',true),PaymentConflict::class);
    $bad=test_order(true);$bad['purchase_units'][0]['payments']['captures'][]=$bad['purchase_units'][0]['payments']['captures'][0];rejected(static fn()=>$client->validate($bad,test_invoice(),'ORDER123456',true),PaymentConflict::class);
    $bad=test_order(true);$bad['purchase_units'][0]['payments']['captures'][0]['final_capture']=false;rejected(static fn()=>$client->validate($bad,test_invoice(),'ORDER123456',true),PaymentConflict::class);
});
test_payment('certificate_ssrf_rejected_without_provider_call', static function(): void {
    $calls=[];$client=test_client($calls,static fn()=>['status'=>200,'body'=>['verification_status'=>'SUCCESS']]);
    $headers=['paypal-auth-algo'=>'SHA256withRSA','paypal-cert-url'=>'https://evil.example/v1/notifications/certs/ABC','paypal-transmission-id'=>'SYNTHETIC','paypal-transmission-sig'=>'SYNTHETIC','paypal-transmission-time'=>'2026-10-31T00:00:00Z'];
    same(false,$client->verifyWebhook($headers,['id'=>'SYNTHETIC']));same(0,count($calls));same(false,$client->verifyWebhook([],[]));
});
test_payment('official_webhook_verifier_controls_acceptance', static function(): void {
    $headers=['paypal-auth-algo'=>'SHA256withRSA','paypal-cert-url'=>'https://api.sandbox.paypal.com/v1/notifications/certs/ABC','paypal-transmission-id'=>'SYNTHETIC','paypal-transmission-sig'=>'SYNTHETIC','paypal-transmission-time'=>'2026-10-31T00:00:00Z'];
    foreach (['SUCCESS'=>true,'FAILURE'=>false] as $status=>$expected) { $calls=[];$client=test_client($calls,static fn()=>['status'=>200,'body'=>['verification_status'=>$status]]);same($expected,$client->verifyWebhook($headers,['id'=>'EVENT123']));same('https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature',$calls[1][1]); }
});
test_payment('refund_adapter_exact_and_idempotent', static function(): void {
    $calls=[];$client=test_client($calls,static fn()=>['status'=>201,'body'=>['id'=>'REFUND123456','status'=>'COMPLETED']]);
    $client->refund('CAPTURE123456',199,'1234567890abcdef-refund');same('https://api-m.sandbox.paypal.com/v2/payments/captures/CAPTURE123456/refund',$calls[1][1]);same('1.99',json_decode($calls[1][3],true)['amount']['value']);
});
test_payment('remote_transport_failure_never_means_paid', static function(): void {
    $client=new MarketPayPal(test_config(),static fn()=>['status'=>500,'body'=>[]]);rejected(static fn()=>$client->order('ORDER123456'),PaymentUnavailable::class);
});
test_payment('invalid_order_reference_never_reaches_network', static function(): void {
    $called=false;$client=new MarketPayPal(test_config(),static function()use(&$called){$called=true;return[];});rejected(static fn()=>$client->order('../../secrets'),PaymentConflict::class);same(false,$called);
});
test_payment('terminal_invoice_not_payable', static function(): void {
    foreach (['draft','cancelled','refunded','paid','failed'] as $state) { $invoice=test_invoice();$invoice['status']=$state;rejected(static fn()=>payments_assert_invoice($invoice),PaymentConflict::class); }
    payments_assert_invoice(test_invoice());
});
echo 'Payment unit checks passed: ' . $passed . "\n";
