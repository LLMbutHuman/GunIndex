<?php
declare(strict_types=1);

function editorial_topics(): array
{
    return ['long-guns', 'handguns', 'air-co2', 'historical', 'ammunition', 'components', 'legal-basics'];
}

function editorial_statuses(): array
{
    return ['pending', 'permit', 'exempt', 'age', 'unregulated', 'prohibited'];
}

function editorial_sources(): array
{
    return [
        'DE' => 'https://www.gesetze-im-internet.de/waffg_2002/',
        'AT' => 'https://www.ris.bka.gv.at/GeltendeFassung.wxe?Abfrage=Bundesnormen&Gesetzesnummer=10006016',
        'CH' => 'https://www.fedlex.admin.ch/eli/cc/1998/2535_2535_2535/de',
        'FR' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000025503132/LEGISCTA000025505560/',
        'BE' => 'https://www.ejustice.just.fgov.be/eli/wet/2006/06/08/2006009449/justel',
        'NL' => 'https://wetten.overheid.nl/BWBR0008804/',
        'LU' => 'https://legilux.public.lu/eli/etat/leg/loi/2022/02/02/a49/jo',
        'DK' => 'https://www.retsinformation.dk/eli/lta/2021/1736',
        'PL' => 'https://isap.sejm.gov.pl/isap.nsf/DocDetails.xsp?id=WDU19990530549',
        'CZ' => 'https://e-sbirka.gov.cz/sb/2024/90',
        'LI' => 'https://www.gesetze.li/konso/2008275000',
    ];
}

function editorial_review_form(string $country, array $input): array
{
    $status = $input['status'] ?? null;
    $reference = $input['reference'] ?? '';
    $scope = $input['scope_note'] ?? '';
    if (!in_array($status, editorial_statuses(), true) || !is_string($reference) || !is_string($scope)) {
        throw new InvalidArgumentException(t('ember.invalid_review'));
    }
    if ($status === 'pending') return [$status, '', ''];
    $reference = trim($reference);
    $scope = trim(str_replace(["\r\n", "\r"], "\n", $scope));
    if (!isset(editorial_sources()[$country]) || ($input['review_confirmed'] ?? null) !== '1'
        || preg_match('/\A[^\p{Cc}]{4,255}\z/u', $reference) !== 1
        || preg_match('/\A(?:[^\p{Cc}]|\n){20,2000}\z/u', $scope) !== 1) {
        throw new InvalidArgumentException(t('ember.invalid_review'));
    }
    return [$status, $reference, $scope];
}

function editorial_review_status(?array $review, string $country, ?int $now = null): string
{
    $status = $review['status_code'] ?? 'pending';
    if ($status === 'pending' || !in_array($status, editorial_statuses(), true) || !isset(editorial_sources()[$country])
        || trim((string)($review['scope_note'] ?? '')) === '' || trim((string)($review['legal_reference'] ?? '')) === '') return 'pending';
    $date = (string)($review['reviewed_at'] ?? '');
    $checked = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date, new DateTimeZone('UTC'));
    if ($checked === false || $checked->format('Y-m-d H:i:s') !== $date) return 'pending';
    $checkedAt = $checked->getTimestamp();
    $now ??= time();
    return $checkedAt <= $now && $now - $checkedAt < 365 * 86400 ? $status : 'pending';
}

function editorial_reviews(PDO $pdo, string $country): array
{
    $query = $pdo->prepare('SELECT topic_code, status_code, legal_reference, scope_note, reviewed_at, revision FROM market_editorial_reviews WHERE country_code=?');
    $query->execute([$country]);
    return array_column($query->fetchAll(), null, 'topic_code');
}

function editorial_review_save(PDO $pdo, int $actor, array $session, string $topic, string $country, string $revision, array $input): void
{
    governance_assert_actor($pdo, $actor, 'legal.manage');
    if (!governance_role_mfa_recent($session, time()) || ($session['admin_mfa_user_id'] ?? null) !== $actor) {
        throw new DomainException(t('admin_roles.error_current_mfa'));
    }
    if (!in_array($topic, editorial_topics(), true) || !isset(geo_active_countries($pdo)[$country])
        || preg_match('/\A(?:0|[1-9][0-9]{0,19})\z/', $revision) !== 1) {
        throw new InvalidArgumentException(t('ember.invalid_review'));
    }
    [$status, $reference, $scope] = editorial_review_form($country, $input);
    $pdo->beginTransaction();
    try {
        governance_assert_actor($pdo, $actor, 'legal.manage');
        // Serialize first-time inserts and later updates for the same jurisdiction.
        $lock = $pdo->prepare('SELECT is_active FROM market_countries WHERE code=? FOR UPDATE');
        $lock->execute([$country]);
        if ((int)$lock->fetchColumn() !== 1) throw new DomainException(t('ember.invalid_review'));
        $query = $pdo->prepare('SELECT status_code, revision FROM market_editorial_reviews WHERE topic_code=? AND country_code=? FOR UPDATE');
        $query->execute([$topic, $country]);
        $current = $query->fetch();
        if ((string)($current['revision'] ?? 0) !== $revision) throw new DomainException(t('ember.review_conflict'));
        if ($current) {
            $pdo->prepare('UPDATE market_editorial_reviews SET status_code=?, legal_reference=?, scope_note=?, reviewed_by=?, reviewed_at=UTC_TIMESTAMP(), revision=revision+1
                WHERE topic_code=? AND country_code=?')->execute([$status,$reference,$scope,$actor,$topic,$country]);
        } else {
            $pdo->prepare('INSERT INTO market_editorial_reviews (status_code,legal_reference,scope_note,reviewed_by,topic_code,country_code,reviewed_at)
                VALUES (?,?,?,?,?,?,UTC_TIMESTAMP())')->execute([$status,$reference,$scope,$actor,$topic,$country]);
        }
        audit_log($pdo, $actor, 'editorial.reviewed', null, ['topic'=>$topic,'country'=>$country,'previous'=>$current['status_code'] ?? 'pending','status'=>$status,'reference'=>$reference,'scope'=>$scope]);
        $pdo->commit();
    } catch (Throwable $failure) {
        $pdo->rollBack();
        throw $failure;
    }
}
