<?php
declare(strict_types=1);

function profile_image_url(int $owner, string $kind, string $revision, bool $review = false): string
{
    return ($review ? '/admin/campaigns.php?logo=1&' : '/marketplace/profile-image.php?')
        . http_build_query(['user_id'=>$owner,'kind'=>$kind,'revision'=>$revision], '', '&', PHP_QUERY_RFC3986);
}

function profile_image_revision(PDO $pdo, int $owner, string $kind): ?string
{
    $q=$pdo->prepare('SELECT revision FROM market_profile_images WHERE user_id=? AND kind=?');
    $q->execute([$owner,$kind]);
    $revision=$q->fetchColumn();
    return is_string($revision) ? $revision : null;
}

function profile_image_prepare(array $file): string
{
    $upload=[];
    foreach (['name','tmp_name','error','size'] as $field) {
        if (!isset($file[$field]) || !is_scalar($file[$field])) throw new InvalidArgumentException(t('profile_images.invalid'));
        $upload[$field]=[$file[$field]];
    }
    try {
        $images=market_prepare_images($upload);
    } catch (InvalidArgumentException) {
        throw new InvalidArgumentException(t('profile_images.invalid'));
    }
    try {
        if (count($images)!==1) throw new InvalidArgumentException(t('profile_images.invalid'));
        $webp=file_get_contents($images[0]['paths'][1], false, null, 0, 524289);
        if (!is_string($webp) || strlen($webp)<12 || strlen($webp)>524288
            || substr($webp,0,4)!=='RIFF' || substr($webp,8,4)!=='WEBP') {
            throw new RuntimeException('Profile image encoding failed');
        }
        return $webp;
    } finally {
        market_remove_prepared_images($images);
    }
}

function profile_image_change(PDO $pdo, array $user, string $kind, ?array $upload): void
{
    if (!in_array($kind,['avatar','logo'],true)) throw new InvalidArgumentException(t('profile_images.invalid'));
    $owner=(int)$user['id'];
    $webp=$upload===null ? null : profile_image_prepare($upload);
    $revision=$webp===null ? null : bin2hex(random_bytes(16));
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT auth_version FROM users WHERE id=? AND is_active=1 FOR UPDATE');
        $q->execute([$owner]);
        $version=$q->fetchColumn();
        if ($version===false || (int)$version!==(int)$user['auth_version']) throw new DomainException(t('account.unavailable'));
        if ($kind==='logo') {
            $q=$pdo->prepare('SELECT user_id FROM market_dealer_profiles WHERE user_id=? FOR UPDATE');
            $q->execute([$owner]);
            if (!$q->fetchColumn() || ($webp!==null && site_setting($pdo,'dealer_profiles_enabled','1')!=='1')) {
                throw new DomainException(t('profile_images.dealer_required'));
            }
            $status=$webp===null ? '' : ",status='pending',moderation_reason=''";
            $pdo->prepare('UPDATE market_dealer_profiles SET logo_revision=?'.$status.' WHERE user_id=?')->execute([$revision,$owner]);
        }
        if ($webp===null) {
            $pdo->prepare('DELETE FROM market_profile_images WHERE user_id=? AND kind=?')->execute([$owner,$kind]);
        } else {
            $pdo->prepare('INSERT INTO market_profile_images(user_id,kind,revision,webp) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE revision=VALUES(revision),webp=VALUES(webp)')
                ->execute([$owner,$kind,$revision,$webp]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function profile_image_bytes(PDO $pdo, int $owner, string $kind, string $revision, bool $private): ?string
{
    if (!in_array($kind,['avatar','logo'],true) || !preg_match('/\A[a-f0-9]{32}\z/',$revision)) return null;
    $sql='SELECT i.webp FROM market_profile_images i JOIN users u ON u.id=i.user_id AND u.is_active=1';
    if ($kind==='logo') {
        if (!$private && site_setting($pdo,'dealer_profiles_enabled','1')!=='1') return null;
        $sql.=' JOIN market_dealer_profiles p ON p.user_id=i.user_id AND p.logo_revision=i.revision';
        if (!$private) $sql.=" AND p.status='published'";
    }
    $q=$pdo->prepare($sql.' WHERE i.user_id=? AND i.kind=? AND i.revision=?');
    $q->execute([$owner,$kind,$revision]);
    $value=$q->fetchColumn();
    return is_string($value) ? $value : null;
}

function profile_image_serve(PDO $pdo, bool $review = false): never
{
    $method=$_SERVER['REQUEST_METHOD']??'GET';
    header('Cache-Control: private, no-store');
    if (!in_array($method,['GET','HEAD'],true)) { header('Allow: GET, HEAD'); abort_request(405,t('account.method')); }
    $raw=$_GET['user_id']??null;
    $id=is_string($raw) ? filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) : false;
    $kind=$_GET['kind']??null;
    $revision=$_GET['revision']??null;
    $user=$review ? null : current_user($pdo);
    $private=$review || ($id!==false && $user!==null && (int)$user['id']===$id);
    if ($id===false || !is_string($kind) || ($review && $kind!=='logo') || !is_string($revision)
        || ($bytes=profile_image_bytes($pdo,$id,$kind,$revision,$private))===null) {
        abort_request(404,t('profile_images.missing'));
    }
    header('Content-Type: image/webp');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: '.strlen($bytes));
    if ($method==='GET') echo $bytes;
    exit;
}
