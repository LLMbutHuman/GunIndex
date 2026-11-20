<?php
declare(strict_types=1);

function dealer_team_roles(): array
{
    return [
        'owner'=>['team.manage','inventory.read','inventory.write','orders.read'],
        'viewer'=>['inventory.read'],
        'inventory'=>['inventory.read','inventory.write'],
        'operations'=>['inventory.read','orders.read'],
    ];
}

function dealer_team_assert(PDO $pdo, int $actor, int $owner, string $permission, bool $lock = false): void
{
    if ($lock && !$pdo->inTransaction()) throw new LogicException('Dealer authorization lock requires a transaction.');
    $scope = " FROM market_dealer_profiles p JOIN users u ON u.id=p.user_id AND u.is_active=1";
    $published = " WHERE p.user_id=? AND p.status='published'";
    $suffix = $lock ? ' FOR SHARE' : '';
    if ($actor === $owner) {
        $query = $pdo->prepare("SELECT 'owner'" . $scope . $published . $suffix);
        $query->execute([$owner]);
    } else {
        $query = $pdo->prepare('SELECT m.role' . $scope . ' JOIN market_dealer_team m ON m.owner_id=p.user_id'
            . ' JOIN users s ON s.id=m.member_id AND s.is_active=1' . $published . " AND m.member_id=? AND m.status='active'" . $suffix);
        $query->execute([$owner,$actor]);
    }
    $role = $query->fetchColumn();
    if (!is_string($role) || !in_array($permission, dealer_team_roles()[$role] ?? [], true)) {
        throw new MarketAccessException(t('dealer_team.forbidden'));
    }
}

function dealer_team_lock_owner(PDO $pdo, int $owner): void
{
    $query = $pdo->prepare("SELECT user_id FROM market_dealer_profiles WHERE user_id=? AND status='published' FOR UPDATE");
    $query->execute([$owner]);
    if ($query->fetchColumn() === false) throw new DomainException(t('dealer_team.unavailable'));
    dealer_team_assert($pdo, $owner, $owner, 'team.manage', true);
}

function dealer_team_role_input(?string $role): string
{
    if ($role === null || $role === 'owner' || !isset(dealer_team_roles()[$role])) throw new InvalidArgumentException(t('dealer_team.invalid'));
    return $role;
}

function dealer_team_invite(PDO $pdo, int $owner, string $username, string $role): void
{
    $username = trim($username);
    if (strlen($username) > 50 || !preg_match('/\A[^\x00-\x20\x7f@]+\z/u', $username)) {
        throw new InvalidArgumentException(t('dealer_team.invalid'));
    }
    $role = dealer_team_role_input($role);
    trade_transaction($pdo, static function () use ($pdo,$owner,$username,$role): void {
        dealer_team_lock_owner($pdo, $owner);
        $memberQuery = $pdo->prepare('SELECT id FROM users WHERE username=? AND is_active=1 FOR SHARE');
        $memberQuery->execute([$username]);
        $member = (int)$memberQuery->fetchColumn();
        if ($member < 1 || $member === $owner) throw new DomainException(t('dealer_team.unavailable'));

        $row = dealer_team_row($pdo, $owner, $member);
        if ($row && ($row['status'] === 'active' || $row['is_live_invite'])) {
            if ($row['is_live_invite'] && $row['role'] === $role) return;
            throw new DomainException(t('dealer_team.changed'));
        }
        $limit = $pdo->prepare("SELECT COUNT(*) AS total,SUM(status='invited') AS invites FROM market_dealer_team WHERE owner_id=? AND (status='active' OR (status='invited' AND expires_at>UTC_TIMESTAMP()))");
        $limit->execute([$owner]);
        $counts = $limit->fetch();
        if ((int)$counts['total'] >= 25 || (int)$counts['invites'] >= 10) throw new DomainException(t('dealer_team.unavailable'));

        $query = $pdo->prepare("INSERT INTO market_dealer_team(owner_id,member_id,role,status,expires_at) VALUES (?,?,?,'invited',UTC_TIMESTAMP()+INTERVAL 7 DAY) ON DUPLICATE KEY UPDATE role=VALUES(role),status='invited',expires_at=VALUES(expires_at),version=version+1");
        $query->execute([$owner,$member,$role]);
        $next = $row ? (int)$row['version'] + 1 : 1;
        audit_log($pdo,$owner,'dealer.team.invited',$member,['owner_id'=>$owner,'role'=>$role]);
        notify_user($pdo,$member,'dealer-team:' . $owner . ':' . $next,
            i18n_user_text($pdo,$member,'dealer_team.teams_title'), i18n_user_text($pdo,$member,'dealer_team.respond_help'), '/user/dealer-team.php');
    });
}

function dealer_team_row(PDO $pdo, int $owner, int $member): array|false
{
    $query = $pdo->prepare("SELECT role,status,version,(status='invited' AND expires_at>UTC_TIMESTAMP()) AS is_live_invite FROM market_dealer_team WHERE owner_id=? AND member_id=? FOR UPDATE");
    $query->execute([$owner,$member]);
    return $query->fetch();
}

function dealer_team_owner_change(PDO $pdo, int $owner, int $member, int $version, string $action, ?string $role = null): void
{
    if (!in_array($action, ['role','revoke'], true)) throw new InvalidArgumentException(t('dealer_team.invalid'));
    if ($action === 'role') $role = dealer_team_role_input($role);
    trade_transaction($pdo, static function () use ($pdo,$owner,$member,$version,$action,$role): void {
        dealer_team_lock_owner($pdo, $owner);
        $row = dealer_team_row($pdo, $owner, $member);
        if (!$row || (int)$row['version'] !== $version) throw new DomainException(t('dealer_team.changed'));
        if ($action === 'role' && $row['status'] === 'active') {
            if ($row['role'] === $role) return;
            $pdo->prepare('UPDATE market_dealer_team SET role=?,version=version+1 WHERE owner_id=? AND member_id=?')->execute([$role,$owner,$member]);
        } elseif ($action === 'revoke' && ($row['status'] === 'active' || $row['is_live_invite'])) {
            $pdo->prepare("UPDATE market_dealer_team SET status='revoked',expires_at=NULL,version=version+1 WHERE owner_id=? AND member_id=?")->execute([$owner,$member]);
        } else { throw new DomainException(t('dealer_team.changed')); }
        audit_log($pdo,$owner,'dealer.team.' . $action,$member,['owner_id'=>$owner,'role'=>$role]);
    });
}

function dealer_team_respond(PDO $pdo, int $member, int $owner, int $version, bool $accept): void
{
    trade_transaction($pdo, static function () use ($pdo,$member,$owner,$version,$accept): void {
        dealer_team_lock_owner($pdo, $owner);
        $query = $pdo->prepare('SELECT id FROM users WHERE id=? AND is_active=1 FOR SHARE');
        $query->execute([$member]);
        if ($query->fetchColumn() === false) throw new DomainException(t('dealer_team.forbidden'));
        $row = dealer_team_row($pdo, $owner, $member);
        if (!$row) throw new DomainException(t('dealer_team.unavailable'));
        if ((int)$row['version'] !== $version || !$row['is_live_invite']) throw new DomainException(t('dealer_team.changed'));
        $status = $accept ? 'active' : 'declined';
        $pdo->prepare('UPDATE market_dealer_team SET status=?,expires_at=NULL,version=version+1 WHERE owner_id=? AND member_id=?')->execute([$status,$owner,$member]);
        audit_log($pdo,$member,'dealer.team.' . ($accept ? 'accepted' : 'declined'),$owner,['owner_id'=>$owner,'role'=>$row['role']]);
    });
}

function dealer_team_view(PDO $pdo, int $actor): array
{
    $owned = business_dealer_profile($pdo, $actor) !== null;
    $members = [];
    $status = "CASE WHEN m.status='invited' AND m.expires_at<=UTC_TIMESTAMP() THEN 'expired' ELSE m.status END AS status";
    if ($owned) {
        $query = $pdo->prepare('SELECT m.member_id,u.username,m.role,' . $status . ',m.version,m.expires_at FROM market_dealer_team m'
            . " JOIN users u ON u.id=m.member_id WHERE m.owner_id=? ORDER BY (m.status='active') DESC,(m.expires_at>UTC_TIMESTAMP()) DESC,m.updated_at DESC,m.member_id LIMIT 100");
        $query->execute([$actor]);
        $members = $query->fetchAll();
    }
    $query = $pdo->prepare('SELECT m.owner_id,p.company_name,m.role,' . $status . ',m.version,m.expires_at FROM market_dealer_team m'
        . ' JOIN users current_member ON current_member.id=m.member_id AND current_member.is_active=1'
        . ' JOIN users u ON u.id=m.owner_id AND u.is_active=1'
        . " JOIN market_dealer_profiles p ON p.user_id=m.owner_id AND p.status='published' WHERE m.member_id=? ORDER BY (m.status='active') DESC,(m.expires_at>UTC_TIMESTAMP()) DESC,m.updated_at DESC,m.owner_id LIMIT 100");
    $query->execute([$actor]);
    return ['owned'=>$owned,'members'=>$members,'teams'=>$query->fetchAll()];
}
