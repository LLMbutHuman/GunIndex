<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit(2); }
function sec_check(bool $ok,string $reason): void { if (!$ok) throw new RuntimeException($reason); }
function sec_reject(callable $test): void
{
    try { $test(); } catch (DomainException|InvalidArgumentException) { return; }
    throw new RuntimeException('Invalid security command accepted.');
}
$mode=$argv[1]??'';
if ($mode==='') {
    require_once __DIR__.'/../config/auth.php';
    require_once __DIR__.'/../config/user_security.php';
    require_once __DIR__.'/../config/passkeys.php';
    $key=random_bytes(32);$encoded=passkey_encode($key);
    sec_check(strlen($encoded)===43&&passkey_decode($encoded,32)===$key,'Base64url round trip failed.');
    foreach (['A','AB','A=','<>',[],null] as $bad) sec_reject(fn()=>passkey_decode($bad,32));
    $origin='https://market.example.com';
    sec_check(passkey_config($origin.'/')===['origin'=>$origin,'rp'=>'market.example.com'],'Wrong canonical origin.');
    foreach (['http://market.example.com','https://u:p@market.example.com','https://market.example.com/path','https://127.0.0.1','https://market.example.com.'] as $bad) sec_check(passkey_config($bad)===null,'Invalid configured origin accepted.');
    $client=['origin'=>$origin,'type'=>'webauthn.get','challenge'=>$encoded,'crossOrigin'=>false];
    passkey_client_data(json_encode($client,JSON_THROW_ON_ERROR),$origin,'webauthn.get');
    foreach ([['origin'=>'https://other-market.example.com'],['origin'=>$origin.':444'],['type'=>'webauthn.create'],['crossOrigin'=>true],['crossOrigin'=>null],['topOrigin'=>$origin]] as $mutated) {
        sec_reject(fn()=>passkey_client_data(json_encode(array_replace($client,$mutated),JSON_THROW_ON_ERROR),$origin,'webauthn.get'));
    }
    $config=passkey_config($origin);
    $_SESSION['passkey_ceremony']=['mode'=>'create','origin'=>$origin,'rp'=>$config['rp'],'until'=>time()+100];
    sec_reject(fn()=>passkey_consume($config,'get'));
    sec_reject(fn()=>passkey_consume($config,'create'));
    sec_check(user_totp_recovery_hash('AAAAaaaa-00000000-12345678-90AbCDDe')===hash('sha256','aaaaaaaa000000001234567890abcdde'),'Recovery normalization failed.');
    echo "User security pure checks passed.\n";
    exit;
}
if (!in_array($mode,['--database','--browser-fixture'],true)||PHP_OS_FAMILY==='Windows'||getenv('DB_DATABASE')!=='gunindex_rebuild_test') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='gunindex_rebuild_test') exit(2);
$username='security_'.bin2hex(random_bytes(8));$password='Synthetic security '.bin2hex(random_bytes(16));
$q=$pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code) VALUES (?,?,?,'Synthetic security test','10115','Berlin','DE')");
$q->execute([$username,$username.'@example.invalid',auth_hash($password)]);$id=(int)$pdo->lastInsertId();
if ($mode==='--browser-fixture') { echo json_encode(compact('username','password'),JSON_THROW_ON_ERROR); exit; }
$q=$pdo->prepare('SELECT * FROM users WHERE id=?');$q->execute([$id]);$viewer=$q->fetch();
auth_issue_session($pdo,$viewer);
sec_reject(fn()=>user_totp_begin($pdo,$viewer,'incorrect synthetic password'));
user_totp_begin($pdo,$viewer,$password);
$pending=user_totp_pending($viewer);
sec_check($pending&&user_totp_state($pdo,$id)===null,'Unconfirmed enrollment became a factor.');
$secret=crypto_decrypt('user_totp',$pending['cipher']);
$otp=auth_totp($secret,intdiv(time(),30));sodium_memzero($secret);
$codes=user_totp_change($pdo,$viewer,'enable','',$otp);
sec_check(count(array_unique($codes))===8&&user_totp_state($pdo,$id)!==null,'Confirmed enrollment did not generate codes.');
$q->execute([$id]);$viewer=$q->fetch();
sec_check(user_security_primary($pdo,$viewer)==='/auth/user-factor.php'&&current_user($pdo)===null,'Primary credential bypassed second factor.');
sec_reject(fn()=>user_security_complete($pdo,$otp));
user_security_complete($pdo,$codes[0]);
sec_check((int)current_user($pdo)['id']===$id,'Recovery did not authenticate the owner.');
user_security_primary($pdo,$viewer);
sec_reject(fn()=>user_security_complete($pdo,$codes[0]));
user_security_complete($pdo,$codes[1]);
$newCodes=user_totp_change($pdo,$viewer,'rotate',$password,$codes[2]);
$q->execute([$id]);$viewer=$q->fetch();
sec_reject(fn()=>user_totp_change($pdo,$viewer,'disable',$password,$codes[3]));
sec_check(user_totp_change($pdo,$viewer,'disable',$password,$newCodes[0])===[]&&user_totp_state($pdo,$id)===null,'Fresh recovery did not authorize factor removal.');
$q->execute([$id]);$viewer=$q->fetch();
sec_check(user_security_primary($pdo,$viewer)==='/user/dashboard.php'&&current_user($pdo)!==null,'Disabled factor still blocked the account.');
echo "User security database checks passed: enrollment, one-time recovery, rotation, version guards and removal.\n";
