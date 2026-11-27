<?php
declare(strict_types=1);

function dealer_admin_access(PDO $pdo, int $owner): array
{
    $q = $pdo->prepare("SELECT m.*,u.username FROM market_dealer_team m JOIN users u ON u.id=m.member_id
        WHERE m.owner_id=? AND m.status IN ('active','invited') ORDER BY m.member_id LIMIT 100");
    $q->execute([$owner]);
    $team = $q->fetchAll();
    $q = $pdo->prepare('SELECT id,label,scopes,expires_at FROM market_dealer_api_keys
        WHERE owner_id=? AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 100');
    $q->execute([$owner]);
    return ['team'=>$team,'api_keys'=>$q->fetchAll()];
}

function dealer_admin_revoke(PDO $pdo, int $actor, int $owner, string $kind, int $id, int $version, string $reason): void
{
    $reason = trim($reason);
    if ($id<1 || !in_array($kind,['member','api_key'],true) || mb_strlen($reason)<10 || mb_strlen($reason)>1000) {
        throw new InvalidArgumentException(t('admin_campaigns.review_validation'));
    }
    $pdo->beginTransaction();
    try {
        business_assert_dealer_manager($pdo,$actor);
        $q = $pdo->prepare('SELECT user_id FROM market_dealer_profiles WHERE user_id=? FOR UPDATE');
        $q->execute([$owner]);
        if (!$q->fetchColumn()) throw new DomainException(t('admin_campaigns.profile_missing'));
        if ($kind==='member') {
            $q = $pdo->prepare("UPDATE market_dealer_team SET status='revoked',expires_at=NULL,version=version+1
                WHERE owner_id=? AND member_id=? AND version=? AND status IN ('active','invited')");
            $q->execute([$owner,$id,$version]);
        } else {
            $q = $pdo->prepare('UPDATE market_dealer_api_keys SET revoked_at=UTC_TIMESTAMP()
                WHERE owner_id=? AND id=? AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP()');
            $q->execute([$owner,$id]);
        }
        if ($q->rowCount()!==1) throw new DomainException(t('admin_campaigns.profile_changed'));
        audit_log($pdo,$actor,'dealer.access_revoked',$owner,['kind'=>$kind,'subject_id'=>$id,'reason'=>$reason]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
