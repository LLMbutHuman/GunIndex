<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit(2); }

function session_check(bool $valid,string $message): void { if (!$valid) throw new RuntimeException($message); }
function session_fixture(PDO $pdo): array
{
    $users=[];
    foreach (['owner','other'] as $role) {
        $username='session_'.$role.'_'.bin2hex(random_bytes(8));
        $password='Synthetic session '.bin2hex(random_bytes(16));
        $q=$pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code) VALUES (?,?,?,'Synthetic session test','10115','Berlin','DE')");
        $q->execute([$username,$username.'@example.invalid',auth_hash($password)]);
        $users[$role]=['id'=>(int)$pdo->lastInsertId(),'username'=>$username,'password'=>$password];
    }
    return $users;
}

$mode=$argv[1]??'';
if ($mode==='') {
    require_once __DIR__.'/../config/auth_sessions.php';
    session_check(auth_session_device('Windows Chrome/122.0 Safari/737 Edg/122.0')===['Edge','Windows'],'Edge labels changed.');
    session_check(auth_session_device('Android Linux Chrome/122.0 Safari/737')===['Chrome','Android'],'Android labels changed.');
    session_check(auth_session_device('iPhone iOS FxiOS/130 Safari/737')===['Firefox','iOS'],'Mobile browser labels changed.');
    session_check(auth_session_device('unknown <img>')===['unknown','unknown'],'Unrecognized request metadata was retained.');
    $token=str_repeat('a',64);
    session_check(auth_session_key($token)===hash('sha256',$token)&&auth_session_key(str_repeat('A',64))===null&&auth_session_key([])===null,'Malformed or raw session token accepted.');
    echo "Auth session pure checks passed.\n";
    exit;
}
if (!in_array($mode,['--database','--browser-fixture'],true)||PHP_OS_FAMILY==='Windows'||getenv('DB_DATABASE')!=='gunindex_rebuild_test') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='gunindex_rebuild_test') exit(2);
$users=session_fixture($pdo);
if ($mode==='--browser-fixture') { echo json_encode($users,JSON_THROW_ON_ERROR); exit; }
$find=$pdo->prepare('SELECT * FROM users WHERE id=?');
$find->execute([$users['owner']['id']]);$owner=$find->fetch();
$find->execute([$users['other']['id']]);$other=$find->fetch();
$_SERVER['HTTP_USER_AGENT']='Windows Firefox/124.0 Synthetic test only';
auth_issue_session($pdo,$owner);
$first=$_SESSION;
auth_issue_session($pdo,$owner);
$second=$_SESSION;
auth_issue_session($pdo,$other);
$foreign=$_SESSION;
$firstHash=auth_session_key($first['auth_session_key']);
$secondHash=auth_session_key($second['auth_session_key']);
$foreignHash=auth_session_key($foreign['auth_session_key']);
session_check($firstHash!==$secondHash,'Independent sessions shared a token.');
$records=auth_session_list($pdo,(int)$owner['id'],(int)$owner['auth_version'],$firstHash);
session_check(count($records)===2&&(int)$records[0]['current_session']===1&&$records[0]['browser']==='Firefox','Session overview missing or mislabeled.');
$foreignRecords=auth_session_list($pdo,(int)$other['id'],(int)$other['auth_version'],$foreignHash);
session_check(count($foreignRecords)===1&&auth_session_revoke($pdo,(int)$owner['id'],(int)$foreignRecords[0]['id'],$firstHash)===null,'Cross-account revocation was possible.');
session_check(auth_session_revoke($pdo,(int)$owner['id'],(int)$records[1]['id'],$firstHash)===false,'Noncurrent revocation returned the wrong result.');
$_SESSION=$second;
session_check(current_user($pdo)===null,'Revoked cookie was still authenticated.');
$_SESSION=$first;
session_check((int)current_user($pdo)['id']===(int)$owner['id'],'Revoking another session ended the current one.');
$sessionStatement=$pdo->prepare('SELECT token_hash,expires_at FROM market_auth_sessions WHERE token_hash=?');
$sessionStatement->execute([$firstHash]);$stored=$sessionStatement->fetch();
session_check($stored['token_hash']!==$first['auth_session_key'],'Session token persisted directly in the registry.');
$export=privacy_export($pdo,(int)$owner['id'],$users['owner']['password']);
$data=json_decode(stream_get_contents($export),true,64,JSON_THROW_ON_ERROR);fclose($export);
session_check(count($data['sign_in_sessions'])===2&&!isset($data['sign_in_sessions'][0]['token_hash']),'Owner export exposed tokens or another account.');

unset($_SESSION['auth_session_key']);
$_SESSION['auth_started_at']-=60;
$legacyStart=$_SESSION['auth_started_at'];
session_check(current_user($pdo)!==null,'Previously valid login did not upgrade.');
$legacy=$_SESSION;
$sessionStatement->execute([auth_session_key($legacy['auth_session_key'])]);$row=$sessionStatement->fetch();
session_check(strtotime($row['expires_at'].' UTC')===$legacyStart+AUTH_SESSION_LIFETIME,'Migration extended authentication expiry.');
$_SESSION['auth_session_key']='invalid';
session_check(current_user($pdo)===null,'Malformed session was backfilled.');
$_SESSION=$legacy;
$pdo->prepare('UPDATE users SET auth_version=auth_version+1 WHERE id=?')->execute([$owner['id']]);
session_check(current_user($pdo)===null,'Version invalidation no longer clears sessions.');
$_SESSION=$foreign;
auth_logout($pdo);
$_SESSION=$foreign;
session_check(current_user($pdo)===null,'Logout left an active registry entry.');
echo "Auth session database checks passed: isolation, revoke, migration, token storage and logout.\n";
