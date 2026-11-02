<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(2);}
require_once __DIR__.'/../config/i18n.php';
require_once __DIR__.'/../config/personal.php';
$personalChecks=0;
function personal_check(bool $ok):void {global $personalChecks;if(!$ok)throw new RuntimeException('Personal list assertion failed.');$personalChecks++;}
$s=[];$now=100000;
$item=['id'=>1,'status'=>'active','type'=>'fixed','title'=>'Never store this'];
personal_record_view($s,1,$item,$now);personal_check($s['personal_lists']['recent']===[]);
personal_change($s,1,'enable',null,$now);personal_record_view($s,1,$item,$now);personal_check($s['personal_lists']['recent']===[1=>$now]);
for($id=2;$id<=23;$id++) personal_record_view($s,1,array_replace($item,['id'=>$id]),$now);
personal_check(count($s['personal_lists']['recent'])===20&&!isset($s['personal_lists']['recent'][1]));
personal_record_view($s,1,array_replace($item,['id'=>10]),$now+1);personal_check(array_key_first($s['personal_lists']['recent'])===10&&count($s['personal_lists']['recent'])===20);
personal_change($s,1,'disable',null,$now);personal_check($s['personal_lists']['recent']===[]&&!$s['personal_lists']['recent_enabled']);
for($id=1;$id<=4;$id++)personal_change($s,1,'add',$id,$now);
personal_change($s,1,'add',4,$now);personal_check(count($s['personal_lists']['compare'])===4);
try{personal_change($s,1,'add',5,$now);throw new LogicException('Capacity bypass');}catch(InvalidArgumentException){personal_check(true);}
personal_change($s,1,'remove',2,$now);personal_change($s,1,'add',5,$now);personal_check(!isset($s['personal_lists']['compare'][2])&&isset($s['personal_lists']['compare'][5]));
personal_state($s,1,$now+21600);personal_check($s['personal_lists']['compare']===[]);
personal_change($s,1,'enable',null,$now+21600);personal_state($s,1,$now+43200);personal_check(!$s['personal_lists']['recent_enabled']);
personal_change($s,1,'add',7,$now+43200);personal_state($s,2,$now+43200);personal_check($s['personal_lists']['compare']===[]&&$s['personal_lists']['user_id']===2);
personal_check(!str_contains(json_encode($s,JSON_THROW_ON_ERROR),'Never store this'));
personal_check(!personal_current(array_replace($item,['status'=>'draft']))&&!personal_current(['type'=>'auction','status'=>'active','ends_at'=>'2000-01-01 00:00:00']));
try{personal_change($s,2,'unknown');throw new LogicException('Unknown action accepted');}catch(InvalidArgumentException){personal_check(true);}
require_once __DIR__.'/../config/auth.php';
$_SESSION=['personal_lists'=>$s['personal_lists'],'locale'=>'en'];auth_clear_session();
personal_check(!isset($_SESSION['personal_lists'])&&$_SESSION['locale']==='en');
echo "Personal list checks passed: ".$personalChecks."\n";

if (in_array('--database',$argv,true)) {
    if (PHP_SAPI!=='cli'||getenv('DB_DATABASE')!=='gunindex_rebuild_test') exit(2);
    require_once __DIR__.'/../config/bootstrap.php';
    if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='gunindex_rebuild_test') exit(2);
    $pdo->beginTransaction();
    try {
        $suffix=bin2hex(random_bytes(8));
        $pdo->prepare('INSERT INTO users(username,email,password_hash) VALUES (?,?,?)')->execute(['personal_'.$suffix,'personal_'.$suffix.'@example.invalid','synthetic-disabled-password']);
        $seller=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')->execute(['Synthetic personal list','personal-'.$suffix]);$category=(int)$pdo->lastInsertId();
        $pdo->exec("UPDATE market_countries SET is_active=1 WHERE code='DE'");
        $insert=$pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code,ends_at) VALUES (?,?,'Synthetic comparison table','Synthetic ordinary goods for session visibility tests.',?,1000,?,'00001','Synthetic','DE',?)");
        $ids=[];
        foreach([['fixed','active',null],['fixed','draft',null],['auction','active','2000-01-01 00:00:00']] as $fields){$insert->execute(array_merge([$seller,$category],$fields));$ids[]=(int)$pdo->lastInsertId();}
        $newState=static function()use($seller,$ids):array{$session=[];foreach($ids as $id)personal_change($session,$seller,'add',$id);return $session;};
        $session=$newState();$items=personal_items($pdo,$session,$seller,'compare');
        personal_check(count($items)===1&&(int)$items[0]['id']===$ids[0]&&count($session['personal_lists']['compare'])===1);
        $pdo->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$seller]);$session=$newState();personal_check(personal_items($pdo,$session,$seller,'compare')===[]);
        $pdo->prepare('UPDATE users SET is_active=1 WHERE id=?')->execute([$seller]);
        $pdo->prepare('UPDATE market_categories SET is_active=0 WHERE id=?')->execute([$category]);$session=$newState();personal_check(personal_items($pdo,$session,$seller,'compare')===[]);
        $pdo->prepare('UPDATE market_categories SET is_active=1 WHERE id=?')->execute([$category]);
        $pdo->exec("UPDATE market_countries SET is_active=0 WHERE code='DE'");$session=$newState();personal_check(personal_items($pdo,$session,$seller,'compare')===[]);
        echo "Personal list DB checks passed: 4\n";
    } finally {if($pdo->inTransaction())$pdo->rollBack();}
}
