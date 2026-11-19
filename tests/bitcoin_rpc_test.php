<?php
declare(strict_types=1);

if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
require_once __DIR__.'/../config/bitcoin_rpc.php';

const BTC_TEST_ADDRESS='bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4';
const BTC_TEST_SCRIPT='0014751e76e8199196d454941c45d1b3a323f1433bd6';
const BTC_TEST_REFERENCE='GI-BTC-1234567890ABCDEF12345678';
const BTC_TEST_TIP='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const BTC_TEST_BLOCK='bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const BTC_TEST_TX='cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
const BTC_TEST_TIME=1795046400;

function btc_test_require(bool $condition): void
{
    if (!$condition) throw new RuntimeException('Unexpected Bitcoin RPC test result.');
}

function btc_test_rejected(callable $operation,string $class): void
{
    try { $operation(); }
    catch (Throwable $error) { if ($error instanceof $class) return; throw $error; }
    throw new RuntimeException('Invalid Bitcoin RPC state accepted.');
}

function btc_test_config(array $changes=[]): array
{
    return array_replace(['rpc_url'=>'https://node.example.invalid:8332','rpc_user'=>'__cookie__',
        'rpc_password'=>'synthetic-rpc-password','wallet'=>'gunindex-watch','chain'=>'main'],$changes);
}

function btc_test_address_info(array $changes=[]): array
{
    return array_replace(['address'=>BTC_TEST_ADDRESS,'scriptPubKey'=>BTC_TEST_SCRIPT,'ismine'=>true,
        'ischange'=>false,'iswitness'=>true,'witness_version'=>0,'iswatchonly'=>false,
        'labels'=>[BTC_TEST_REFERENCE]],$changes);
}

function btc_test_tally(array $changes=[]): array
{
    return [array_replace(['address'=>BTC_TEST_ADDRESS,'label'=>BTC_TEST_REFERENCE,'amount'=>0.00000003,
        'confirmations'=>2,'txids'=>[BTC_TEST_TX,BTC_TEST_TX]],$changes)];
}

function btc_test_transaction(array $changes=[]): array
{
    return array_replace([
        'txid'=>BTC_TEST_TX,'confirmations'=>2,'timereceived'=>BTC_TEST_TIME,'blockhash'=>BTC_TEST_BLOCK,'blockheight'=>99,
        'lastprocessedblock'=>['hash'=>BTC_TEST_TIP,'height'=>100],'walletconflicts'=>[],'mempoolconflicts'=>[],
        'details'=>[
            ['address'=>BTC_TEST_ADDRESS,'category'=>'receive','amount'=>0.00000002,'vout'=>4,'abandoned'=>false],
            ['address'=>'not-the-receiving-address','category'=>'receive','amount'=>3.0,'vout'=>5,'abandoned'=>false],
            ['address'=>BTC_TEST_ADDRESS,'category'=>'receive','amount'=>0.00000001,'vout'=>1,'abandoned'=>false],
        ],
    ],$changes);
}

/**
 * Fixtures return explicit JSON-RPC reply members, or reserved raw HTTP status/body overrides.
 * The spy records every request so a safe-looking return cannot hide an extra operation.
 */
function btc_test_client(array &$calls,array $fixtures=[],array $config=[]): MarketBitcoin
{
    $handlers=array_replace([
        'getblockchaininfo'=>['result'=>['chain'=>'main','blocks'=>100,'headers'=>100,'bestblockhash'=>BTC_TEST_TIP,'initialblockdownload'=>false]],
        'getwalletinfo'=>['result'=>['walletname'=>'gunindex-watch','descriptors'=>true,'private_keys_enabled'=>false,
            'external_signer'=>false,'scanning'=>false,'lastprocessedblock'=>['hash'=>BTC_TEST_TIP,'height'=>100]]],
        'getaddressesbylabel'=>['result'=>[BTC_TEST_ADDRESS=>['purpose'=>'receive']]],
        'getnewaddress'=>['result'=>BTC_TEST_ADDRESS],
        'getaddressinfo'=>['result'=>btc_test_address_info()],
        'listreceivedbyaddress'=>['result'=>btc_test_tally()],
        'gettransaction'=>['result'=>btc_test_transaction()],
        'getblockheader'=>['result'=>['hash'=>BTC_TEST_BLOCK,'height'=>99,'confirmations'=>2]],
    ],$fixtures);
    $seen=[];
    return new MarketBitcoin(btc_test_config($config),static function(string $url,array $headers,string $body)use(&$calls,&$seen,$handlers):array{
        $request=json_decode($body,true,8,JSON_THROW_ON_ERROR);
        $method=$request['method'];
        $seen[$method]=($seen[$method]??0)+1;
        $calls[]=[$method,$request['params'],$url,$headers];
        $fixture=$handlers[$method]??throw new RuntimeException('Unexpected Bitcoin RPC operation.');
        if (is_callable($fixture)) $fixture=$fixture($seen[$method]);
        $status=$fixture['status']??200;
        if (isset($fixture['body'])) return ['status'=>$status,'body'=>$fixture['body']];
        unset($fixture['status']);
        return ['status'=>$status,'body'=>json_encode($fixture+['jsonrpc'=>'2.0','id'=>$request['id']],JSON_THROW_ON_ERROR)];
    });
}

function btc_test_methods(array $calls): array
{
    return array_column($calls,0);
}

$tests=[
    'fixed vault origin and exact wallet endpoint'=>static function():void{
        foreach (['http://node.example.invalid','http://localhost','http://127.0.0.2','https://user:pass@node.example.invalid',
            'https://node.example.invalid/rpc','https://node.example.invalid/?anything','https://node.example.invalid/#anything',
            'ftp://127.0.0.1','https://node.example.invalid\path'] as $origin) {
            btc_test_rejected(fn()=>new MarketBitcoin(btc_test_config(['rpc_url'=>$origin])),PaymentUnavailable::class);
        }
        foreach ([['wallet'=>'../other'],['wallet'=>'wallet/path'],['chain'=>'signet'],['rpc_user'=>'user:name'],['rpc_password'=>"bad\r\nvalue"]] as $config) {
            btc_test_rejected(fn()=>new MarketBitcoin(btc_test_config($config)),PaymentUnavailable::class);
        }
        $calls=[];
        btc_test_client($calls,[],['rpc_url'=>'http://[::1]:18443/'])->assertReady();
        btc_test_require(array_column($calls,2)===['http://[::1]:18443/','http://[::1]:18443/wallet/gunindex-watch']);
        btc_test_require(in_array('Authorization: Basic '.base64_encode('__cookie__:synthetic-rpc-password'),$calls[0][3],true));
    },
    'network and synced watch-only wallet gates'=>static function():void{
        $node=['chain'=>'main','blocks'=>100,'headers'=>100,'bestblockhash'=>BTC_TEST_TIP,'initialblockdownload'=>false];
        $wallet=['walletname'=>'gunindex-watch','descriptors'=>true,'private_keys_enabled'=>false,'external_signer'=>false,
            'scanning'=>false,'lastprocessedblock'=>['hash'=>BTC_TEST_TIP,'height'=>100]];
        foreach ([['chain'=>'regtest'],['headers'=>101],['initialblockdownload'=>true]] as $changes) {
            $calls=[];
            $client=btc_test_client($calls,['getblockchaininfo'=>['result'=>array_replace($node,$changes)]]);
            btc_test_rejected(fn()=>$client->assertReady(),PaymentUnavailable::class);
        }
        foreach ([['private_keys_enabled'=>true],['descriptors'=>false],['external_signer'=>true],['walletname'=>'other'],
            ['scanning'=>['progress'=>0.1]],['lastprocessedblock'=>['hash'=>BTC_TEST_BLOCK,'height'=>100]]] as $changes) {
            $calls=[];
            $client=btc_test_client($calls,['getwalletinfo'=>['result'=>array_replace($wallet,$changes)]]);
            btc_test_rejected(fn()=>$client->assertReady(),PaymentUnavailable::class);
        }
    },
    'allocation uses new bech32 address only for the missing label code'=>static function():void{
        $calls=[];
        $client=btc_test_client($calls,['getaddressesbylabel'=>static fn(int $call):array=>$call===1
            ?['error'=>['code'=>-11,'message'=>'No matching label']]
            :['result'=>[BTC_TEST_ADDRESS=>['purpose'=>'receive']]]]);
        btc_test_require($client->address(BTC_TEST_REFERENCE)===BTC_TEST_ADDRESS);
        btc_test_require($client->address(BTC_TEST_REFERENCE)===BTC_TEST_ADDRESS);
        $allocations=array_values(array_filter($calls,static fn(array $call):bool=>$call[0]==='getnewaddress'));
        btc_test_require(count($allocations)===1&&$allocations[0][1]===[BTC_TEST_REFERENCE,'bech32']);
        foreach ([
            ['error'=>['code'=>-5,'message'=>'Other wallet error']],
            ['error'=>['code'=>-11],'status'=>500],
            ['error'=>['code'=>-11],'result'=>null],
        ] as $fixture) {
            $calls=[];
            $client=btc_test_client($calls,['getaddressesbylabel'=>$fixture]);
            btc_test_rejected(fn()=>$client->address(BTC_TEST_REFERENCE),PaymentUnavailable::class);
            btc_test_require(!in_array('getnewaddress',btc_test_methods($calls),true));
        }
    },
    'allocated address proves network script ownership and label'=>static function():void{
        foreach ([['scriptPubKey'=>'0014'.str_repeat('0',40)],['ismine'=>false],['ischange'=>true],['labels'=>['other']]] as $changes) {
            $calls=[];
            $client=btc_test_client($calls,['getaddressinfo'=>['result'=>btc_test_address_info($changes)]]);
            btc_test_rejected(fn()=>$client->address(BTC_TEST_REFERENCE),PaymentUnavailable::class);
        }
        $calls=[];
        $badChecksum=substr(BTC_TEST_ADDRESS,0,-1).'5';
        $client=btc_test_client($calls,['getaddressesbylabel'=>['result'=>[$badChecksum=>['purpose'=>'receive']]]]);
        btc_test_rejected(fn()=>$client->address(BTC_TEST_REFERENCE),PaymentUnavailable::class);
        $calls=[];
        $client=btc_test_client($calls,['getaddressesbylabel'=>['result'=>[BTC_TEST_ADDRESS=>['purpose'=>'send']]]]);
        btc_test_rejected(fn()=>$client->address(BTC_TEST_REFERENCE),PaymentUnavailable::class);
    },
    'same transaction can contain two independently verified satoshi outputs'=>static function():void{
        $calls=[];
        $result=btc_test_client($calls)->receipts(BTC_TEST_ADDRESS);
        $expected=[
            ['txid'=>BTC_TEST_TX,'vout'=>1,'satoshis'=>1,'confirmations'=>2,'block_hash'=>BTC_TEST_BLOCK,'received_at'=>BTC_TEST_TIME],
            ['txid'=>BTC_TEST_TX,'vout'=>4,'satoshis'=>2,'confirmations'=>2,'block_hash'=>BTC_TEST_BLOCK,'received_at'=>BTC_TEST_TIME],
        ];
        btc_test_require($result===$expected);
        $counts=array_count_values(btc_test_methods($calls));
        btc_test_require($counts['gettransaction']===1&&$counts['getblockheader']===1&&$counts['listreceivedbyaddress']===2);
    },
    'known transaction remains visible without depending on address tally'=>static function():void{
        $callWalletTx=static function():array{
            $transaction=btc_test_transaction(['confirmations'=>0]);
            unset($transaction['blockhash'],$transaction['blockheight']);
            return ['result'=>$transaction];
        };
        $calls=[];
        $client=btc_test_client($calls,['gettransaction'=>$callWalletTx,'listreceivedbyaddress'=>['status'=>500,'body'=>'']]);
        $result=$client->receipts(BTC_TEST_ADDRESS,BTC_TEST_TX);
        btc_test_require(count($result)===2&&$result[0]['confirmations']===0&&$result[0]['block_hash']===null);
        btc_test_require(!in_array('listreceivedbyaddress',btc_test_methods($calls),true));
        $calls=[];
        $missing=btc_test_client($calls,['gettransaction'=>['error'=>['code'=>-5,'message'=>'Not in wallet']]]);
        btc_test_require($missing->receipts(BTC_TEST_ADDRESS,BTC_TEST_TX)===[]);
        $unreachable=btc_test_client($calls,['gettransaction'=>['error'=>['code'=>-5],'status'=>503]]);
        btc_test_rejected(fn()=>$unreachable->receipts(BTC_TEST_ADDRESS,BTC_TEST_TX),PaymentUnavailable::class);
    },
    'coinbase conflicts and abandoned transfers do not count'=>static function():void{
        foreach ([['generated'=>true],['confirmations'=>-1],['walletconflicts'=>[BTC_TEST_BLOCK]],
            ['mempoolconflicts'=>[BTC_TEST_BLOCK]],['details'=>[
                ['address'=>BTC_TEST_ADDRESS,'category'=>'receive','amount'=>0.00000003,'vout'=>4,'abandoned'=>true],
            ]]] as $changes) {
            $calls=[];
            $client=btc_test_client($calls,['gettransaction'=>['result'=>btc_test_transaction($changes)]]);
            btc_test_require($client->receipts(BTC_TEST_ADDRESS)===[]);
            btc_test_require(!in_array('getblockheader',btc_test_methods($calls),true));
        }
    },
    'stale chain amount and oversized address tally fail closed'=>static function():void{
        $calls=[];
        $client=btc_test_client($calls,['getblockheader'=>['result'=>['hash'=>BTC_TEST_BLOCK,'height'=>99,'confirmations'=>-1]]]);
        btc_test_rejected(fn()=>$client->receipts(BTC_TEST_ADDRESS),PaymentUnavailable::class);
        $calls=[];
        $client=btc_test_client($calls,['listreceivedbyaddress'=>['result'=>btc_test_tally(['txids'=>array_fill(0,11,BTC_TEST_TX)])]]);
        btc_test_rejected(fn()=>$client->receipts(BTC_TEST_ADDRESS),PaymentConflict::class);
        btc_test_require(!in_array('gettransaction',btc_test_methods($calls),true));
        $calls=[];
        $client=btc_test_client($calls,['listreceivedbyaddress'=>static fn(int $call)=>[
            'result'=>btc_test_tally($call===1?[]:['amount'=>0.00000004]),
        ]]);
        btc_test_rejected(fn()=>$client->receipts(BTC_TEST_ADDRESS),PaymentUnavailable::class);
        $calls=[];
        $client=btc_test_client($calls,['gettransaction'=>['result'=>btc_test_transaction([
            'lastprocessedblock'=>['hash'=>BTC_TEST_BLOCK,'height'=>99],
        ])]]);
        btc_test_rejected(fn()=>$client->receipts(BTC_TEST_ADDRESS),PaymentUnavailable::class);
    },
    'BTC conversion stays exact near the monetary limit'=>static function():void{
        $row=['address'=>BTC_TEST_ADDRESS,'category'=>'receive','amount'=>20999999.99999999,'vout'=>3,'abandoned'=>false];
        $calls=[];
        $client=btc_test_client($calls,['gettransaction'=>['result'=>btc_test_transaction(['details'=>[$row]])]]);
        btc_test_require($client->receipts(BTC_TEST_ADDRESS,BTC_TEST_TX)[0]['satoshis']===2099999999999999);
        foreach ([0.000000009,'0.01',21000001,-0.1] as $invalid) {
            $row['amount']=$invalid;
            $client=btc_test_client($calls,['gettransaction'=>['result'=>btc_test_transaction(['details'=>[$row]])]]);
            btc_test_rejected(fn()=>$client->receipts(BTC_TEST_ADDRESS,BTC_TEST_TX),PaymentUnavailable::class);
        }
    },
    'strict RPC envelope and body limits'=>static function():void{
        foreach ([
            ['id'=>'another-request','result'=>[]],['jsonrpc'=>'1.0','result'=>[]],
            ['status'=>301,'body'=>'redirect'],['body'=>'{'],['body'=>str_repeat('x',2*1024*1024+1)],
            ['result'=>null],
        ] as $invalid) {
            $calls=[];
            $client=btc_test_client($calls,['getblockchaininfo'=>$invalid]);
            btc_test_rejected(fn()=>$client->assertReady(),PaymentUnavailable::class);
        }
    },
];

foreach ($tests as $name=>$test) {
    $test();
    fwrite(STDOUT,'PASS '.$name.PHP_EOL);
}
fwrite(STDOUT,'Bitcoin RPC checks passed: '.count($tests).PHP_EOL);
