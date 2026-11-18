<?php
declare(strict_types=1);

const AUTH_SESSION_LIFETIME = 43200;
const AUTH_SESSION_IDLE = 1800;

/** Coarse display labels: no IP, raw user-agent, or device fingerprint. */
function auth_session_device(string $agent): array
{
    $agent=substr($agent,0,512);
    $browser=match(true) {
        preg_match('/(?:Edg|EdgA|EdgiOS)\//i',$agent)===1=>'Edge',
        preg_match('/(?:Firefox|FxiOS)\//i',$agent)===1=>'Firefox',
        preg_match('/(?:Chrome|CriOS)\//i',$agent)===1=>'Chrome',
        str_contains($agent,'Safari/')=>'Safari',
        default=>'unknown',
    };
    $platform=match(true) {
        str_contains($agent,'Android')=>'Android',
        preg_match('/iPhone|iPad|iPod/',$agent)===1=>'iOS',
        str_contains($agent,'Windows')=>'Windows',
        str_contains($agent,'Macintosh')=>'macOS',
        str_contains($agent,'Linux')=>'Linux',
        default=>'unknown',
    };
    return [$browser,$platform];
}

function auth_session_key(mixed $token): ?string
{
    return is_string($token)&&preg_match('/\A[a-f0-9]{64}\z/',$token)?hash('sha256',$token):null;
}

function auth_session_create(PDO $pdo,array $user,int $started): ?string
{
    $token=bin2hex(random_bytes(32));
    [$browser,$platform]=auth_session_device((string)($_SERVER['HTTP_USER_AGENT']??''));
    $q=$pdo->prepare('INSERT INTO market_auth_sessions(user_id,token_hash,auth_version,browser,platform,started_at,last_seen_at,expires_at)'
        .' SELECT id,?,auth_version,?,?,FROM_UNIXTIME(?),UTC_TIMESTAMP(),FROM_UNIXTIME(?) FROM users WHERE id=? AND is_active=1 AND auth_version=?');
    $q->execute([auth_session_key($token),$browser,$platform,$started,$started+AUTH_SESSION_LIFETIME,$user['id'],$user['auth_version']]);
    if ($q->rowCount()!==1) return null;
    return $token;
}

function auth_session_active(PDO $pdo,int $user,int $version,string $hash): bool
{
    $q=$pdo->prepare('SELECT id,last_seen_at FROM market_auth_sessions WHERE token_hash=? AND user_id=? AND auth_version=?'
        .' AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP()');
    $q->execute([$hash,$user,$version]);
    $row=$q->fetch();
    if (!$row) return false;
    // PHP's authenticated session clock retains the existing exact idle timeout.
    // The registry's display clock is updated at most once a minute per session.
    if (strtotime($row['last_seen_at'].' UTC')<=time()-60) {
        $q=$pdo->prepare('UPDATE market_auth_sessions SET last_seen_at=UTC_TIMESTAMP() WHERE id=? AND revoked_at IS NULL');
        $q->execute([$row['id']]);
        if ($q->rowCount()===0) {
            $q=$pdo->prepare('SELECT 1 FROM market_auth_sessions WHERE id=? AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP()');
            $q->execute([$row['id']]);
            return $q->fetchColumn()!==false;
        }
    }
    return true;
}

function auth_session_list(PDO $pdo,int $user,int $version,string $current,int $page=1): array
{
    $offset=(max(1,min(10000,$page))-1)*25;
    $q=$pdo->prepare('SELECT id,browser,platform,started_at,last_seen_at,(token_hash=?) AS current_session'
        .' FROM market_auth_sessions WHERE user_id=? AND auth_version=? AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP()'
        .' AND (token_hash=? OR last_seen_at>=UTC_TIMESTAMP()-INTERVAL 31 MINUTE) ORDER BY current_session DESC,last_seen_at DESC,id DESC LIMIT 25 OFFSET '.$offset);
    $q->execute([$current,$user,$version,$current]);
    return $q->fetchAll();
}

/** Return whether the revoked row was the caller's current session. */
function auth_session_revoke(PDO $pdo,int $user,int $target,string $current): ?bool
{
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT token_hash FROM market_auth_sessions WHERE id=? AND user_id=? FOR UPDATE');
        $q->execute([$target,$user]);
        $hash=$q->fetchColumn();
        if (!is_string($hash)) { $pdo->commit(); return null; }
        $pdo->prepare('UPDATE market_auth_sessions SET revoked_at=UTC_TIMESTAMP() WHERE id=? AND user_id=? AND revoked_at IS NULL')->execute([$target,$user]);
        $pdo->commit();
        return hash_equals($hash,$current);
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}

function auth_logout(PDO $pdo): void
{
    $id=$_SESSION['user_id']??null;
    $hash=auth_session_key($_SESSION['auth_session_key']??null);
    if (is_int($id)&&$hash!==null) {
        $pdo->prepare('UPDATE market_auth_sessions SET revoked_at=UTC_TIMESTAMP() WHERE user_id=? AND token_hash=? AND revoked_at IS NULL')->execute([$id,$hash]);
    }
    auth_clear_session();
}

/** The registry keeps expired rows for at most 30 days after their lifetime. */
function auth_session_purge(PDO $pdo,int $limit=500): int
{
    $limit=max(1,min(500,$limit));
    return $pdo->exec('DELETE FROM market_auth_sessions WHERE expires_at<UTC_TIMESTAMP()-INTERVAL 30 DAY ORDER BY expires_at,id LIMIT '.$limit);
}
