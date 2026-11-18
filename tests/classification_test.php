<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(2); }

function c_check(bool $condition, string $failure): void
{
    if (!$condition) throw new RuntimeException($failure);
}

function c_reject(callable $attempt): void
{
    try { $attempt(); }
    catch (InvalidArgumentException|DomainException|PDOException) { return; }
    throw new RuntimeException('Invalid classification operation accepted.');
}

function c_fixture(PDO $pdo): array
{
    $tag = bin2hex(random_bytes(9));
    $password = 'Synthetic classification '.bin2hex(random_bytes(18));
    $users=[];
    foreach (['admin','seller'] as $role) {
        $username='class_'.$role.'_'.$tag;
        $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code,is_admin) VALUES (?,?,?,'Synthetic classification test','10115','Berlin','DE',?)")
            ->execute([$username,$username.'@example.invalid',auth_hash($password),(int)($role==='admin')]);
        $users[$role]=['id'=>(int)$pdo->lastInsertId(),'username'=>$username,'password'=>$password];
    }
    $categories=[];
    foreach (['main','other'] as $type) {
        $name='Synthetic desks '.$type.' '.$tag;
        $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')->execute([$name,'classification-'.$type.'-'.$tag]);
        $categories[$type]=(int)$pdo->lastInsertId();
    }
    $attributes=[];
    foreach ([['width','Width','number','cm','','1'],['finish','Finish','choice','',"Oak\nMaple",'0'],['folds','Folding','boolean','','','0'],['model','Model','text','','','0']] as [$code,$label,$kind,$unit,$choices,$required]) {
        $attributes[$code]=classification_admin_save($pdo,$users['admin']['id'],$categories['main'],0,compact('code','label','kind','unit','choices')+['is_required'=>$required,'is_active'=>'1']);
    }
    return compact('tag','users','categories','attributes');
}

$mode = $argv[1] ?? '';
if ($mode === '') {
    require_once __DIR__.'/../config/i18n.php';
    require_once __DIR__.'/../config/catalog.php';
    require_once __DIR__.'/../config/classification.php';
    require_once __DIR__.'/../config/market_search_text.php';
    c_check(classification_number('-1,065')==='-1.065'&&classification_number('-0,0')==='0.000'&&classification_number('999999999.999')==='999999999.999','Decimal normalization lost precision.');
    foreach (['1e3','1.1234','1000000000','01','+1',1,[],null] as $bad) c_reject(fn()=>classification_number($bad));
    $definition=classification_definition(['code'=>'finish','label'=>'Finish','kind'=>'choice','choices'=>"Oak\r\nMaple"]);
    c_check($definition['choices']===['Oak','Maple'],'Choice parsing failed.');
    foreach (["Oak\nOak","Oak\n\nMaple",'Oak',"Oak\nMunition"] as $bad) c_reject(fn()=>classification_definition(['code'=>'finish','label'=>'Finish','kind'=>'choice','choices'=>$bad]));
    $defs=[1=>['kind'=>'number','label'=>'Width','is_required'=>1],2=>['kind'=>'boolean','label'=>'Folding','is_required'=>0],3=>$definition+['is_required'=>0]];
    c_check(classification_values($defs,[2=>'0',1=>'1,5',3=>'Maple'],true)===[1=>'1.500',2=>'0',3=>'Maple'],'Typed values or false boolean lost.');
    c_check(classification_values($defs,[],false)===[],'Missing draft attribute rejected.');
    foreach ([[],[7=>'99'],[1=>[]],[1=>'1',2=>'off'],[1=>'2',3=>'Unknown']] as $bad) c_reject(fn()=>classification_values($defs,$bad,true));
    [$sql,$params]=classification_search($defs,['attribute_min'=>[1=>'7.2'],'attribute_max'=>[1=>'9'],'attributes'=>[2=>'0',3=>'Maple']]);
    c_check(count($sql)===4&&$params===[2,'0',3,'Maple',1,'7.200',1,'9.000'],'Combined filters changed prepared values.');
    c_reject(fn()=>classification_search($defs,['attribute_min'=>[2=>'0']]));
    c_reject(fn()=>classification_search($defs,['attributes'=>[3=>['Maple']]]));
    [$terms,$bindings,$score,$rankBindings]=market_text_search('Oak 20%_!');
    c_check(count($terms)===2&&$bindings===['%Oak%','%Oak%','%20!%!_!!%','%20!%!_!!%']&&count($rankBindings)===6,
        'Text search did not bind terms and escape literal wildcard characters.');
    c_reject(fn()=>market_text_search("invalid\0word"));
    c_reject(fn()=>market_text_search(implode(' ',range(1,13))));
    echo "Classification pure checks passed.\n";
    exit;
}

if (!in_array($mode,['--database','--browser-fixture'],true)||PHP_OS_FAMILY==='Windows'||getenv('DB_DATABASE')!=='gunindex_rebuild_test') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='gunindex_rebuild_test') exit(2);
$fixture=c_fixture($pdo);
if ($mode==='--browser-fixture') { echo json_encode($fixture,JSON_THROW_ON_ERROR); exit; }
['users'=>$users,'tag'=>$tag,'categories'=>$categories,'attributes'=>$attributes]=$fixture;
$seller=$users['seller']['id']; $admin=$users['admin']['id'];
$category=$categories['main']; $other=$categories['other'];
$width=$attributes['width'];$finish=$attributes['finish'];$folds=$attributes['folds'];
$state=['is_required'=>'0','is_active'=>'0'];
c_reject(fn()=>classification_admin_save($pdo,$seller,$category,$width,$state));
c_reject(fn()=>classification_admin_save($pdo,$admin,$other,$width,$state));
c_check(isset(classification_definitions($pdo,$category)[$width]),'Unprivileged update changed definition.');

$listing=['title'=>'Classification desk '.$tag,'description'=>'An ordinary synthetic folding desk for the classification regression.',
    'category_id'=>(string)$category,'type'=>'fixed','status'=>'active','price'=>'23.50','zip'=>'10115','city'=>'Berlin',
    'country_code'=>'DE','condition_label'=>'used','pickup'=>'1','accept_rules'=>'1'];
$draft=market_save_listing($pdo,$seller,array_replace($listing,['status'=>'draft']));
c_reject(fn()=>market_transition($pdo,$draft,$seller,'publish'));
c_reject(fn()=>market_save_listing($pdo,$seller,$listing));
$priceAttributes=['attributes'=>[$width=>'51,125',$finish=>'Oak',$folds=>'0']];
$published=market_save_listing($pdo,$seller,$listing+$priceAttributes,[],$draft);
c_check($draft===$published,'Draft ID changed during publication.');
$legacy=market_save_listing($pdo,$seller,array_replace($listing,['category_id'=>(string)$other]));
$filters=['category_id'=>(string)$category,'scope'=>'country','country_code'=>'DE','q'=>$tag,'min_price'=>'23',
    'attributes'=>[$finish=>'Oak',$folds=>'0'],'attribute_min'=>[$width=>'51.125'],'attribute_max'=>[$width=>'52']];
c_check(array_column(market_search($pdo,$filters)['items'],'id')===[$published],'Combined attribute, price, text and market filters broke.');
$prefix='Indexed test '.$tag;
$exact=market_save_listing($pdo,$seller,array_replace($listing,['title'=>$prefix])+$priceAttributes);
$body=market_save_listing($pdo,$seller,array_replace($listing,['title'=>'Independent table '.$tag,'description'=>'This synthetic item mentions: '.$prefix.'.'])+$priceAttributes);
$archived=market_save_listing($pdo,$seller,array_replace($listing,['title'=>$prefix.' hidden'])+$priceAttributes);
market_transition($pdo,$archived,$seller,'archive');
$ranked=market_search($pdo,array_replace($filters,['q'=>$prefix,'sort'=>'relevance']));
c_check(array_column($ranked['items'],'id')===[$exact,$body],'Exact title does not outrank a description match.');
c_check(market_suggestions($pdo,$prefix,['country_code'=>'DE','category_id'=>(string)$category])===[$prefix],'Prefix suggestions included a nonpublic or nonmatching title.');
c_check(market_suggestions($pdo,$prefix,['country_code'=>'DE','category_id'=>(string)$other])===[],'Suggestions leaked from another category.');
c_check(market_suggestions($pdo,$prefix.'%',['country_code'=>'DE'])===[],'Unescaped prefix wildcard suggested a title.');
$filters['attribute_min'][$width]='51.126';
c_check(market_search($pdo,$filters)['total']===0,'Numeric range rounded unexpectedly.');
c_reject(fn()=>market_search($pdo,array_replace($filters,['category_id'=>(string)$other])));
c_reject(fn()=>market_save_listing($pdo,$seller,array_replace($listing,['category_id'=>(string)$other])+$priceAttributes));
c_reject(fn()=>$pdo->prepare('INSERT INTO market_listing_attributes(listing_id,category_id,attribute_id,number_value) VALUES (?,?,?,?)')->execute([$legacy,$other,$width,'1']));

classification_admin_save($pdo,$admin,$category,$finish,$state);
c_check(!in_array($finish,array_column(classification_entries($pdo,$published),'id'),true),'Inactive field still presented.');
c_reject(fn()=>market_search($pdo,$filters));
classification_admin_save($pdo,$admin,$category,$finish,['is_required'=>'0','is_active'=>'1']);
c_check(in_array($finish,array_column(classification_entries($pdo,$published),'id'),true),'Archived values did not survive reactivation.');

market_transition($pdo,$published,$seller,'archive');
$copy=relisting_create_draft($pdo,$seller,$published);
c_check(classification_entries($pdo,$copy)===classification_entries($pdo,$published),'Relisting lost classification.');
market_save_listing($pdo,$seller,array_replace($listing,['category_id'=>(string)$other,'status'=>'draft']),[],$copy);
c_check(classification_entries($pdo,$copy,false)===[]&&count(classification_entries($pdo,$published))===3,'Category change left mismatched values or changed the source.');
echo "Classification database checks passed: access, draft/publication, combined filters, foreign keys, deactivation and relisting.\n";
