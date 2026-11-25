<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit(2); }

function draft_check(bool $test,string $message): void
{
    if (!$test) throw new RuntimeException($message);
}
function draft_reject(callable $test,string $expected): void
{
    try { $test(); }
    catch (Throwable $e) { if ($e instanceof $expected) return; throw $e; }
    throw new RuntimeException('Expected rejection: '.$expected);
}
function draft_fixture(PDO $pdo): array
{
    $tag=bin2hex(random_bytes(8));
    $users=[];
    foreach (['seller','other'] as $role) {
        $username='draft_'.$role.'_'.$tag;
        $password='Synthetic draft '.bin2hex(random_bytes(16));
        $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code) VALUES (?,?,?,'Synthetic draft test','10115','Berlin','DE')")
            ->execute([$username,$username.'@example.invalid',auth_hash($password)]);
        $users[$role]=['id'=>(int)$pdo->lastInsertId(),'username'=>$username,'password'=>$password];
    }
    $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')->execute(['Synthetic homeware '.$tag,'draft-homeware-'.$tag]);
    return ['tag'=>$tag,'users'=>$users,'category'=>(int)$pdo->lastInsertId()];
}

$mode=$argv[1]??'';
if ($mode==='') {
    require_once __DIR__.'/../config/i18n.php';
    require_once __DIR__.'/../config/image_order.php';
    require_once __DIR__.'/../config/market.php';
    require_once __DIR__.'/../config/listing_drafts.php';
    $payload=listing_draft_payload(['title'=>'Desk','attributes'=>[4=>'5'],'accept_rules'=>'1','accept_auction_fees'=>'1','legal_confirm'=>'1','csrf_token'=>'secret']);
    foreach (['accept_rules','accept_auction_fees','legal_confirm','csrf_token'] as $key) draft_check(!array_key_exists($key,$payload),'Acceptance or CSRF was stored.');
    draft_check($payload['title']==='Desk'&&$payload['attributes']===[4=>'5']&&$payload['pickup']==='0','Incomplete values not preserved.');
    $description = str_repeat('Ł', 11000);
    draft_check(listing_draft_payload(['description'=>$description])['description']===$description,'Multibyte description was rejected below the character limit.');
    draft_reject(fn()=>listing_draft_payload(['description'=>str_repeat('a',20001)]),InvalidArgumentException::class);
    draft_reject(fn()=>listing_draft_payload(['description'=>str_repeat('🪑',17000)]),InvalidArgumentException::class);
    draft_reject(fn()=>listing_draft_payload(['title'=>['Desk']]),InvalidArgumentException::class);
    draft_reject(fn()=>listing_draft_payload(['description'=>"bad\0value"]),InvalidArgumentException::class);
    draft_reject(fn()=>listing_draft_payload(['attributes'=>['foo'=>'x']]),InvalidArgumentException::class);
    draft_reject(fn()=>listing_draft_id('9999999999999999999'),InvalidArgumentException::class);
    $base=listing_draft_base(null,[]);
    listing_draft_guard(null,$base,['draft_revision'=>'0','draft_base'=>$base]);
    draft_reject(fn()=>listing_draft_guard(null,$base,['draft_revision'=>'0','draft_base'=>str_repeat('1',64)]),MarketConflictException::class);
    draft_check(listing_draft_base(['id'=>1],[['id'=>2,'position'=>0]])!==listing_draft_base(['id'=>1],[['id'=>3,'position'=>0]]),'Base hash ignores changed image manifest.');
    echo "Listing draft pure checks passed.\n";
    exit;
}

if (!in_array($mode,['--database','--browser-fixture'],true)||PHP_OS_FAMILY==='Windows'||getenv('DB_DATABASE')!=='gunindex_rebuild_test') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='gunindex_rebuild_test') exit(2);
$fixture=draft_fixture($pdo);
if ($mode==='--browser-fixture') { echo json_encode($fixture,JSON_THROW_ON_ERROR); exit; }
['tag'=>$tag,'users'=>$users,'category'=>$category]=$fixture;
$seller=$users['seller']['id'];$other=$users['other']['id'];
$empty=['draft_revision'=>'0','draft_base'=>listing_draft_base(null,[])];
$saved=listing_draft_save($pdo,$seller,0,$empty+['title'=>'T','accept_rules'=>'1']);
draft_reject(fn()=>listing_draft_save($pdo,$seller,0,$empty+['title'=>'Captured later']),MarketConflictException::class);
$stored=listing_draft_workspace($pdo,$seller,0);
draft_check($stored['payload']['title']==='T'&&!isset($stored['payload']['accept_rules']),'Stale write changed the draft or stored consent.');
draft_check(listing_draft_workspace($pdo,$other,0)===null,'Another owner can read this workspace.');
$export=privacy_export($pdo,$seller,$users['seller']['password']);
$ownerData=json_decode(stream_get_contents($export),true,64,JSON_THROW_ON_ERROR);fclose($export);
draft_check(($ownerData['listing_drafts'][0]['payload']['title']??null)==='T'&&!isset($ownerData['listing_drafts'][0]['revision']),
    'Owner export missed the draft or included its private revision.');
$q=$pdo->prepare('SELECT COUNT(*) FROM market_listings WHERE seller_id=?');$q->execute([$seller]);
draft_check((int)$q->fetchColumn()===0,'Draft autosave created a market listing.');
$values=['title'=>'Draft test desk '.$tag,'description'=>'Synthetic desk from a private workspace, no real inventory.',
    'category_id'=>(string)$category,'type'=>'fixed','status'=>'active','price'=>'5.50','zip'=>'10115','city'=>'Berlin',
    'country_code'=>'DE','condition_label'=>'used','pickup'=>'1','draft_revision'=>$saved['revision'],'draft_base'=>$saved['base']];
draft_reject(fn()=>market_save_listing($pdo,$seller,$values),InvalidArgumentException::class);
draft_check(listing_draft_workspace($pdo,$seller,0)['revision']===$saved['revision'],'Validation failure consumed the draft.');
$id=market_save_listing($pdo,$seller,$values+['accept_rules'=>'1']);
draft_reject(fn()=>market_save_listing($pdo,$seller,$values+['accept_rules'=>'1']),MarketConflictException::class);
draft_check(listing_draft_workspace($pdo,$seller,0)['payload']===null,'Published workspace still contains data.');
draft_check(market_find($pdo,$id)['title']===$values['title'],'Explicit publication did not create the listing.');

$source=listing_draft_source($pdo,$seller,$id);
$edit=['draft_revision'=>'0','draft_base'=>listing_draft_base($source,[])];
draft_reject(fn()=>listing_draft_save($pdo,$other,$id,$edit+['title'=>'Foreign edit']),MarketNotFoundException::class);
$edited=listing_draft_save($pdo,$seller,$id,$edit+['title'=>'Unpublished private edit']);
draft_check(market_find($pdo,$id)['title']===$values['title'],'Autosave changed a published listing.');
$pdo->prepare('UPDATE market_listings SET title=? WHERE id=?')->execute(['Updated source listing '.$tag,$id]);
$stale=['draft_revision'=>$edited['revision'],'draft_base'=>listing_draft_base(listing_draft_source($pdo,$seller,$id),[])];
draft_reject(fn()=>listing_draft_save($pdo,$seller,$id,$stale+['title'=>'Old draft']),MarketConflictException::class);
listing_draft_save($pdo,$seller,$id,$stale,[],true);
draft_check(listing_draft_workspace($pdo,$seller,$id)['payload']===null,'Discard did not release the workspace.');
echo "Listing draft database checks passed: private scope, stale revision, publication, retry and discard.\n";
