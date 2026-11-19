<?php
declare(strict_types=1);

function user_totp_state(PDO $pdo,int $user,bool $lock=false): ?array
{
    $q=$pdo->prepare('SELECT * FROM market_user_totp WHERE user_id=?'.($lock?' FOR UPDATE':''));
    $q->execute([$user]);
    return $q->fetch()?:null;
}

/** Security mutations share a single lock and order with other credential changes. */
function user_security_lock(PDO $pdo,int $id,int $version): array
{
    if (!$pdo->inTransaction()) throw new LogicException('Security changes require a transaction.');
    $q=$pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');
    $q->execute([$id]);$user=$q->fetch();
    if (!$user||(int)$user['is_active']!==1||(int)$user['auth_version']!==$version||auth_requires_mfa($pdo,$user)) {
        throw new DomainException(t('user_security.unavailable'));
    }
    return $user;
}

function user_security_password(array $user,string $password): void
{
    if (strlen($password)>1024||str_contains($password,"\0")||!password_verify($password,$user['password_hash'])) {
        throw new InvalidArgumentException(t('account.current_password_invalid'));
    }
}

function user_security_limit(int $id): void
{
    auth_rate_limit('user-security-ip',auth_ip(),40,300);
    auth_rate_limit('user-security-account',(string)$id,12,300);
}

function user_security_finish(PDO $pdo,array $user): array
{
    $pdo->prepare('UPDATE users SET auth_version=auth_version+1 WHERE id=?')->execute([$user['id']]);
    $user['auth_version']=(int)$user['auth_version']+1;
    return $user;
}

function user_totp_recovery_hash(string $submitted): ?string
{
    $text=strtolower(str_replace('-','',trim($submitted)));
    return preg_match('/\A[0-9a-f]{32}\z/',$text)?hash('sha256',$text):null;
}

function user_totp_make_recovery(PDO $pdo,int $user): array
{
    $pdo->prepare('DELETE FROM market_totp_recovery_codes WHERE user_id=?')->execute([$user]);
    $q=$pdo->prepare('INSERT INTO market_totp_recovery_codes(user_id,code_hash) VALUES (?,?)');
    $codes=[];
    for ($i=0;$i<8;$i++) {
        $raw=bin2hex(random_bytes(16));
        $q->execute([$user,hash('sha256',$raw)]);
        $codes[]=implode('-',str_split($raw,8));
    }
    return $codes;
}

/** Accept exactly one fresh TOTP or unused recovery code, inside the user transaction. */
function user_totp_check(PDO $pdo,int $user,array $factor,string $code): void
{
    if (strlen($code)>64) throw new InvalidArgumentException(t('user_security.invalid'));
    $code=trim($code);
    if (preg_match('/\A[0-9]{6}\z/',$code)) {
        $secret=crypto_decrypt('user_totp',$factor['secret_cipher']);
        try { $counter=auth_totp_counter($secret,$code,(int)$factor['last_counter']); }
        finally { sodium_memzero($secret); }
        if ($counter!==null) {
            $q=$pdo->prepare('UPDATE market_user_totp SET last_counter=? WHERE user_id=? AND last_counter<?');
            $q->execute([$counter,$user,$counter]);
            if ($q->rowCount()===1) return;
        }
    } elseif (($hash=user_totp_recovery_hash($code))!==null) {
        $q=$pdo->prepare('UPDATE market_totp_recovery_codes SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND code_hash=? AND used_at IS NULL');
        $q->execute([$user,$hash]);
        if ($q->rowCount()===1) return;
    }
    throw new InvalidArgumentException(t('user_security.invalid'));
}

function user_totp_begin(PDO $pdo,array $viewer,string $password): void
{
    $id=(int)$viewer['id'];$version=(int)$viewer['auth_version'];
    user_security_limit($id);
    $pdo->beginTransaction();
    try {
        $locked=user_security_lock($pdo,$id,$version);
        user_security_password($locked,$password);
        if (user_totp_state($pdo,$id,true)) throw new DomainException(t('user_security.unavailable'));
        $encrypted=crypto_encrypt('user_totp',random_bytes(20));
        $pdo->commit();
        $_SESSION['user_totp_setup']=['id'=>$id,'version'=>$version,'until'=>time()+300,'cipher'=>$encrypted];
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function user_totp_pending(array $viewer): ?array
{
    $pending=$_SESSION['user_totp_setup']??null;
    return is_array($pending)&&$pending['id']===(int)$viewer['id']&&$pending['version']===(int)$viewer['auth_version']
        &&$pending['until']>time()?$pending:null;
}

function user_totp_change(PDO $pdo,array $viewer,string $action,string $password,string $code): array
{
    $id=(int)$viewer['id'];$version=(int)$viewer['auth_version'];
    user_security_limit($id);
    $pdo->beginTransaction();
    try {
        $user=user_security_lock($pdo,$id,$version);
        $factor=user_totp_state($pdo,$id,true);
        $codes=[];
        if ($action==='enable') {
            $pending=user_totp_pending($viewer);
            if (!$pending||$factor) throw new DomainException(t('user_security.unavailable'));
            $secret=crypto_decrypt('user_totp',$pending['cipher']);
            try { $counter=auth_totp_counter($secret,$code); }
            finally { sodium_memzero($secret); }
            if ($counter===null) throw new InvalidArgumentException(t('user_security.invalid'));
            $pdo->prepare('INSERT INTO market_user_totp(user_id,secret_cipher,last_counter) VALUES (?,?,?)')->execute([$id,$pending['cipher'],$counter]);
            $codes=user_totp_make_recovery($pdo,$id);
        } elseif (in_array($action,['disable','rotate'],true)&&$factor) {
            user_security_password($user,$password);
            user_totp_check($pdo,$id,$factor,$code);
            if ($action==='rotate') $codes=user_totp_make_recovery($pdo,$id);
            else $pdo->prepare('DELETE FROM market_user_totp WHERE user_id=?')->execute([$id]);
        } else throw new DomainException(t('user_security.unavailable'));
        $user=user_security_finish($pdo,$user);
        $pdo->commit();
        auth_issue_session($pdo,$user);
        return $codes;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function user_security_primary(PDO $pdo,array $user): string
{
    if (auth_requires_mfa($pdo,$user)) throw new DomainException(t('user_security.unavailable'));
    if (user_totp_state($pdo,(int)$user['id'])) {
        auth_clear_session();
        $_SESSION['pending_user_factor']=['id'=>(int)$user['id'],'version'=>(int)$user['auth_version'],'until'=>time()+300];
        return '/auth/user-factor.php';
    }
    auth_issue_session($pdo,$user);
    return '/user/dashboard.php';
}

function user_security_complete(PDO $pdo,string $code): void
{
    $pending=$_SESSION['pending_user_factor']??null;
    if (!is_array($pending)||$pending['until']<=time()) throw new DomainException(t('user_security.unavailable'));
    $id=$pending['id'];user_security_limit($id);
    $pdo->beginTransaction();
    try {
        $user=user_security_lock($pdo,$id,$pending['version']);
        $factor=user_totp_state($pdo,$id,true);
        if (!$factor) throw new DomainException(t('user_security.unavailable'));
        user_totp_check($pdo,$id,$factor,$code);
        $pdo->commit();
        auth_issue_session($pdo,$user);
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
