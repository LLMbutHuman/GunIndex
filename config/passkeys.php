<?php
declare(strict_types=1);

use lbuchs\WebAuthn\Attestation\AuthenticatorData;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;

function passkey_config(?string $url=null): ?array
{
    $url??=(string)getenv('APP_URL');
    $development=getenv('APP_ENV')==='development';
    if ($development&&getenv('DB_DATABASE')==='gunindex_rebuild_test'&&$url==='http://127.0.0.1:8081') $url='http://localhost:8081';
    if ($url===''||strlen($url)>300||preg_match('/[\x00-\x20\x7f\\\\]/',$url)) return null;
    $parts=parse_url($url);
    if (!$parts||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])
        ||!in_array($parts['path']??'',['','/'],true)) return null;
    $host=strtolower($parts['host']??'');$scheme=$parts['scheme']??'';$port=$parts['port']??null;
    if ($port!==null&&($port<1||$port>65535)) return null;
    if (!($development&&$host==='localhost'&&$scheme==='http')
        &&($scheme!=='https'||!str_contains($host,'.')||str_ends_with($host,'.')||filter_var($host,FILTER_VALIDATE_IP)
            ||!filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME))) return null;
    $suffix=$port===null||($scheme==='https'&&$port===443)||($scheme==='http'&&$port===80)?'':':'.$port;
    return ['origin'=>$scheme.'://'.$host.$suffix,'rp'=>$host];
}

function passkey_encode(string $bytes): string { return rtrim(strtr(base64_encode($bytes),'+/','-_'),'='); }

function passkey_decode(mixed $value,int $max): string
{
    if (!is_string($value)||strlen($value)>intdiv(4*$max+2,3)||!preg_match('/\A[A-Za-z0-9_-]+\z/',$value)) {
        throw new InvalidArgumentException(t('user_security.passkey_failed'));
    }
    $bytes=base64_decode(strtr($value,'-_','+/'),true);
    if (!is_string($bytes)||strlen($bytes)>$max||passkey_encode($bytes)!==$value) throw new InvalidArgumentException(t('user_security.passkey_failed'));
    return $bytes;
}

/** Strict exact-origin validation supplements the library's RP and signature checks. */
function passkey_client_data(string $json,string $origin,string $type): void
{
    $client=json_decode($json,true,8);
    if (!is_array($client)||($client['type']??null)!==$type||($client['origin']??null)!==$origin
        ||(array_key_exists('crossOrigin',$client)&&$client['crossOrigin']!==false)||array_key_exists('topOrigin',$client)
        ||strlen(passkey_decode($client['challenge']??null,32))!==32) throw new InvalidArgumentException(t('user_security.passkey_failed'));
}

function passkey_library(array $config): WebAuthn
{
    $loader=dirname(__DIR__).'/vendor/autoload.php';
    if (!is_file($loader)) throw new DomainException(t('user_security.unavailable'));
    require_once $loader;
    if (!class_exists(WebAuthn::class)) throw new DomainException(t('user_security.unavailable'));
    return new WebAuthn('GunIndex',$config['rp'],['none'],true);
}

function passkey_challenge(WebAuthn $library,array $config,string $mode,array $fields=[]): void
{
    $_SESSION['passkey_ceremony']=['mode'=>$mode,'origin'=>$config['origin'],'rp'=>$config['rp'],
        'challenge'=>passkey_encode($library->getChallenge()->getBinaryString()),'until'=>time()+120]+$fields;
}

function passkey_consume(array $config,string $mode): array
{
    $challenge=$_SESSION['passkey_ceremony']??null;
    unset($_SESSION['passkey_ceremony']);
    if (!is_array($challenge)||$challenge['until']<=time()||$challenge['mode']!==$mode||$challenge['origin']!==$config['origin']||$challenge['rp']!==$config['rp']) {
        throw new InvalidArgumentException(t('user_security.passkey_failed'));
    }
    return $challenge;
}

function passkey_password_and_factor(PDO $pdo,array $user,string $password,string $code): void
{
    user_security_password($user,$password);
    $id=(int)$user['id'];
    if ($factor=user_totp_state($pdo,$id,true)) user_totp_check($pdo,$id,$factor,$code);
}

function passkey_begin_add(PDO $pdo,array $viewer,array $config,array $input): object
{
    unset($_SESSION['passkey_ceremony']);
    $id=(int)$viewer['id'];$version=(int)$viewer['auth_version'];
    user_security_limit($id);
    $label=trim(auth_input($input,'label'));
    if (!preg_match('/\A[^\x00-\x1f\x7f]{1,80}\z/u',$label)) throw new InvalidArgumentException(t('user_security.passkey_failed'));
    $library=passkey_library($config);
    $pdo->beginTransaction();
    try {
        $user=user_security_lock($pdo,$id,$version);
        passkey_password_and_factor($pdo,$user,auth_input($input,'password',false),auth_input($input,'factor'));
        $q=$pdo->prepare('SELECT credential_id FROM market_passkeys WHERE user_id=? ORDER BY id FOR UPDATE');
        $q->execute([$id]);$credentials=$q->fetchAll(PDO::FETCH_COLUMN);
        if (count($credentials)>=10) throw new DomainException(t('user_security.passkey_limit'));
        $pdo->prepare('INSERT INTO market_passkey_accounts(user_id,user_handle) VALUES (?,?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)')->execute([$id,passkey_encode(random_bytes(32))]);
        $q=$pdo->prepare('SELECT user_handle FROM market_passkey_accounts WHERE user_id=?');$q->execute([$id]);
        $handle=passkey_decode($q->fetchColumn(),32);
        $args=$library->getCreateArgs($handle,$user['username'],$user['username'],90,true,true,null,array_map(static fn($value)=>passkey_decode($value,1024),$credentials));
        $pdo->commit();
        passkey_challenge($library,$config,'create',['id'=>$id,'version'=>$version,'label'=>$label]);
        return $args->publicKey;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function passkey_finish_add(PDO $pdo,array $viewer,array $config,array $input): void
{
    $pending=passkey_consume($config,'create');$id=(int)$viewer['id'];
    if ($pending['id']!==$id||$pending['version']!==(int)$viewer['auth_version']) throw new InvalidArgumentException(t('user_security.passkey_failed'));
    $client=passkey_decode($input['clientData']??null,4096);
    passkey_client_data($client,$config['origin'],'webauthn.create');
    $raw=passkey_decode($input['rawId']??null,1024);
    $library=passkey_library($config);
    try {
        $data=$library->processCreate($client,passkey_decode($input['attestation']??null,16384),passkey_decode($pending['challenge'],32),true,true);
    } catch (WebAuthnException|ValueError|TypeError) { throw new InvalidArgumentException(t('user_security.passkey_failed')); }
    if (!hash_equals($raw,$data->credentialId)||($data->isBackedUp&&!$data->isBackupEligible)) throw new InvalidArgumentException(t('user_security.passkey_failed'));
    $pdo->beginTransaction();
    try {
        $user=user_security_lock($pdo,$id,$pending['version']);
        $q=$pdo->prepare('INSERT INTO market_passkeys(user_id,credential_id,rp_id,public_key,sign_count,backup_eligible,label) VALUES (?,?,?,?,?,?,?)');
        $q->execute([$id,passkey_encode($raw),$config['rp'],$data->credentialPublicKey,$data->signatureCounter??0,(int)$data->isBackupEligible,$pending['label']]);
        $user=user_security_finish($pdo,$user);
        $pdo->commit();auth_issue_session($pdo,$user);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e->getCode()==='23000') throw new InvalidArgumentException(t('user_security.passkey_failed'));
        throw $e;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function passkey_begin_login(array $config): object
{
    $library=passkey_library($config);
    $args=$library->getGetArgs([],90,true,true,true,true,true,true);
    passkey_challenge($library,$config,'get');
    return $args->publicKey;
}

function passkey_finish_login(PDO $pdo,array $config,array $input): string
{
    $pending=passkey_consume($config,'get');
    $identifier=passkey_encode(passkey_decode($input['rawId']??null,1024));
    $handle=passkey_decode($input['userHandle']??null,32);
    $client=passkey_decode($input['clientData']??null,4096);
    $authenticator=passkey_decode($input['authenticator']??null,4096);
    $signature=passkey_decode($input['signature']??null,1024);
    passkey_client_data($client,$config['origin'],'webauthn.get');
    $q=$pdo->prepare('SELECT k.user_id,u.auth_version FROM market_passkeys k JOIN users u ON u.id=k.user_id WHERE k.credential_id=? AND k.rp_id=?');
    $q->execute([$identifier,$config['rp']]);$owner=$q->fetch();
    if (!$owner) throw new InvalidArgumentException(t('user_security.passkey_failed'));
    $library=passkey_library($config);
    $pdo->beginTransaction();
    try {
        $user=user_security_lock($pdo,(int)$owner['user_id'],(int)$owner['auth_version']);
        $q=$pdo->prepare('SELECT k.*,a.user_handle FROM market_passkeys k JOIN market_passkey_accounts a ON a.user_id=k.user_id WHERE k.user_id=? AND k.credential_id=? AND k.rp_id=? FOR UPDATE');
        $q->execute([$user['id'],$identifier,$config['rp']]);$credential=$q->fetch();
        if (!$credential||!hash_equals($credential['user_handle'],passkey_encode($handle))) throw new InvalidArgumentException(t('user_security.passkey_failed'));
        $backup=(int)$credential['backup_eligible']===1;
        $library->processGet($client,$authenticator,$signature,$credential['public_key'],passkey_decode($pending['challenge'],32),$backup?null:(int)$credential['sign_count'],true,true);
        $flags=new AuthenticatorData($authenticator);
        if ($flags->getIsBackupEligible()!==$backup||($flags->getIsBackup()&&!$backup)) throw new InvalidArgumentException(t('user_security.passkey_failed'));
        $pdo->prepare('UPDATE market_passkeys SET sign_count=GREATEST(sign_count,?),last_used_at=UTC_TIMESTAMP() WHERE id=?')->execute([$library->getSignatureCounter()??0,$credential['id']]);
        $pdo->commit();return user_security_primary($pdo,$user);
    } catch (WebAuthnException|ValueError|TypeError) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw new InvalidArgumentException(t('user_security.passkey_failed'));
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function passkey_remove(PDO $pdo,array $viewer,int $id,string $password,string $factor): void
{
    user_security_limit((int)$viewer['id']);
    $pdo->beginTransaction();
    try {
        $user=user_security_lock($pdo,(int)$viewer['id'],(int)$viewer['auth_version']);
        passkey_password_and_factor($pdo,$user,$password,$factor);
        $q=$pdo->prepare('DELETE FROM market_passkeys WHERE id=? AND user_id=?');$q->execute([$id,$user['id']]);
        if ($q->rowCount()!==1) throw new DomainException(t('user_security.unavailable'));
        $user=user_security_finish($pdo,$user);
        $pdo->commit();auth_issue_session($pdo,$user);
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
