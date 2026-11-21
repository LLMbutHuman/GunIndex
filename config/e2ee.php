<?php
declare(strict_types=1);

/** Opaque OpenPGP storage. Only the separate Ed25519 device manifest is verified here. */
final class E2EEConflictException extends RuntimeException {}
final class E2EEUnavailableException extends RuntimeException {}

function e2ee_enabled(): bool { return getenv('E2EE_ENABLED') === '1'; }
function e2ee_require_enabled(): void {
    if (!e2ee_enabled() || !function_exists('sodium_crypto_sign_verify_detached')) {
        throw new E2EEUnavailableException('Encrypted messaging is unavailable.');
    }
}

/** Never bypass this persistent guard when the feature is subsequently disabled. */
function e2ee_legacy_allowed(PDO $pdo, int $first, int $second): bool {
    $q = $pdo->prepare('SELECT 1 FROM market_e2ee_accounts WHERE user_id IN (?,?) LIMIT 1'.($pdo->inTransaction() ? ' FOR SHARE' : ''));
    $q->execute([$first, $second]);
    return !$q->fetchColumn();
}

function e2ee_fields(array $value, array $fields): void {
    $actual = array_keys($value); sort($actual); sort($fields);
    if ($actual !== $fields) throw new InvalidArgumentException('Unexpected or missing fields.');
}
function e2ee_integer(mixed $value, int $minimum = 1): int {
    if (!is_int($value) || $value < $minimum || $value > 2147483647) throw new InvalidArgumentException('Invalid integer.');
    return $value;
}
function e2ee_hex(mixed $value, int $length = 32): string {
    if (!is_string($value) || !preg_match('/\A[a-f0-9]{'.$length.'}\z/D', $value)) throw new InvalidArgumentException('Invalid identifier.');
    return $value;
}
function e2ee_base64(mixed $value, int $maximum, ?int $exact = null): string {
    if (!is_string($value) || $value === '' || strlen($value) > 4 * (int)ceil($maximum / 3)) throw new InvalidArgumentException('Invalid ciphertext encoding or size.');
    $bytes = base64_decode($value, true);
    if ($bytes === false || $bytes === '' || strlen($bytes) > $maximum || ($exact !== null && strlen($bytes) !== $exact) || base64_encode($bytes) !== $value) {
        throw new InvalidArgumentException('Invalid ciphertext encoding or size.');
    }
    return $bytes;
}
function e2ee_public_key(mixed $key): string {
    // This checks a bounded public armor envelope, not the validity of OpenPGP packets.
    // Clients must parse the packet, check fingerprints, algorithms and signatures.
    if (!is_string($key) || strlen($key) > 16384 || !preg_match('/\A-----BEGIN PGP PUBLIC KEY BLOCK-----\r?\n[\x09\x0a\x0d\x20-\x7e]+\r?\n-----END PGP PUBLIC KEY BLOCK-----\r?\n?\z/D', $key)
        || str_contains($key, 'PRIVATE KEY') || substr_count($key, '-----BEGIN ') !== 1 || substr_count($key, '-----END ') !== 1) {
        throw new InvalidArgumentException('Invalid public key envelope.');
    }
    return $key;
}

/** Return validated fields without reserializing the signed exact UTF-8 bytes. */
function e2ee_validate_manifest(string $manifest, string $signature, int $user): array {
    if (strlen($manifest) > 262144 || !preg_match('//u', $manifest)) throw new InvalidArgumentException('Invalid manifest.');
    try { $value = json_decode($manifest, true, 32, JSON_THROW_ON_ERROR); }
    catch (JsonException) { throw new InvalidArgumentException('Invalid manifest JSON.'); }
    if (!is_array($value) || array_is_list($value)) throw new InvalidArgumentException('Invalid manifest object.');
    e2ee_fields($value, ['protocol','user_id','version','previous_hash','identity_key','recovery_public','recovery_hash','devices']);
    if ($value['protocol'] !== 'gunindex-devices-1' || e2ee_integer($value['user_id']) !== $user) throw new InvalidArgumentException('Invalid manifest identity.');
    e2ee_integer($value['version']); e2ee_hex($value['previous_hash'], 64); e2ee_hex($value['recovery_hash'], 64);
    $identity = e2ee_base64($value['identity_key'], 32, 32);
    $signed = e2ee_base64($signature, 64, 64);
    e2ee_public_key($value['recovery_public']);
    if (!is_array($value['devices']) || !array_is_list($value['devices']) || count($value['devices']) > 64 || !$value['devices']) throw new InvalidArgumentException('Invalid device list.');
    $seen = []; $keys = []; $fingerprints = []; $active = 0;
    foreach ($value['devices'] as $device) {
        if (!is_array($device)) throw new InvalidArgumentException('Invalid device.');
        e2ee_fields($device, ['id','name','public_key','fingerprint','revoked']);
        $id = e2ee_hex($device['id']);
        if (isset($seen[$id]) || !is_bool($device['revoked']) || !is_string($device['name']) || !preg_match('//u', $device['name'])
            || trim($device['name']) === '' || mb_strlen($device['name'], 'UTF-8') > 80 || preg_match('/[\x00-\x1f\x7f]/', $device['name'])) throw new InvalidArgumentException('Invalid device metadata.');
        if (!is_string($device['fingerprint']) || !preg_match('/\A(?:[a-f0-9]{40}|[a-f0-9]{64})\z/D', $device['fingerprint'])) throw new InvalidArgumentException('Invalid fingerprint.');
        e2ee_public_key($device['public_key']);
        $keyHash = hash('sha256',$device['public_key']);
        if (isset($keys[$keyHash]) || isset($fingerprints[$device['fingerprint']]) || $device['public_key'] === $value['recovery_public']) throw new InvalidArgumentException('Devices need separate public keys.');
        $seen[$id] = true; $keys[$keyHash] = true; $fingerprints[$device['fingerprint']] = true;
        if (!$device['revoked']) $active++;
    }
    if ($active < 1 || $active > 10) throw new InvalidArgumentException('Invalid active device count.');
    if (!function_exists('sodium_crypto_sign_verify_detached') || !sodium_crypto_sign_verify_detached($signed, "GunIndex device manifest v1\0".$manifest, $identity)) throw new InvalidArgumentException('Invalid manifest signature.');
    return $value;
}

function e2ee_account(PDO $pdo, int $user, bool $lock = false): ?array {
    $q = $pdo->prepare('SELECT a.identity_key,a.current_version,a.current_hash,m.manifest,m.signature,m.manifest_hash AS hash,m.recovery_cipher FROM market_e2ee_accounts a JOIN market_e2ee_manifests m ON m.user_id=a.user_id AND m.version=a.current_version WHERE a.user_id=?'.($lock ? ' FOR UPDATE' : ''));
    $q->execute([$user]); $row = $q->fetch();
    if (!$row) {
        $q = $pdo->prepare('SELECT 1 FROM market_e2ee_accounts WHERE user_id=?'.($lock ? ' FOR UPDATE' : '')); $q->execute([$user]);
        if ($q->fetchColumn()) throw new E2EEUnavailableException('Invalid encrypted account state.');
        return null;
    }
    if (!hash_equals($row['current_hash'], $row['hash']) || !hash_equals($row['hash'], hash('sha256', $row['manifest']))) throw new E2EEUnavailableException('Invalid encrypted account state.');
    try { $manifest = json_decode($row['manifest'],true,32,JSON_THROW_ON_ERROR); }
    catch (JsonException) { throw new E2EEUnavailableException('Invalid encrypted account state.'); }
    if (!is_array($manifest) || ($manifest['user_id'] ?? null) !== $user || ($manifest['version'] ?? null) !== (int)$row['current_version'] || ($manifest['identity_key'] ?? null) !== $row['identity_key']) throw new E2EEUnavailableException('Invalid encrypted account state.');
    return ['manifest'=>$row['manifest'],'signature'=>$row['signature'],'hash'=>$row['hash'],'recovery_cipher'=>$row['recovery_cipher']];
}

function e2ee_public_account(?array $account): ?array {
    if ($account === null) return null;
    return ['manifest'=>$account['manifest'],'signature'=>$account['signature'],'hash'=>$account['hash']];
}

function e2ee_account_save(PDO $pdo, int $user, array $input): array {
    e2ee_require_enabled();
    e2ee_fields($input, ['expected_version','expected_hash','manifest','signature','recovery_cipher']);
    $expected = e2ee_integer($input['expected_version'], 0); $expectedHash = e2ee_hex($input['expected_hash'], 64);
    if (!is_string($input['manifest']) || !is_string($input['signature'])) throw new InvalidArgumentException('Invalid manifest.');
    $value = e2ee_validate_manifest($input['manifest'], $input['signature'], $user);
    if ($value['version'] !== $expected + 1 || !hash_equals($value['previous_hash'],$expectedHash)) throw new E2EEConflictException('Invalid expected manifest version.');
    $backup = e2ee_base64($input['recovery_cipher'], 1048576);
    if (!hash_equals($value['recovery_hash'], hash('sha256', $backup))) throw new InvalidArgumentException('Recovery digest mismatch.');
    $hash = hash('sha256', $input['manifest']);
    $pdo->beginTransaction();
    try {
        maintenance_assert_market_available($pdo);
        $q = $pdo->prepare('SELECT id,is_active FROM users WHERE id=? FOR UPDATE'); $q->execute([$user]); $person = $q->fetch();
        if (!$person || (int)$person['is_active'] !== 1) throw new MarketAccessException('Account unavailable.');
        $old = e2ee_account($pdo, $user, true);
        if ($old !== null && hash_equals($old['hash'],$hash) && $old['manifest'] === $input['manifest'] && $old['signature'] === $input['signature'] && $old['recovery_cipher'] === $input['recovery_cipher']) {
            $pdo->commit(); return $old;
        }
        $previous = $old === null ? null : json_decode($old['manifest'], true, 32, JSON_THROW_ON_ERROR);
        $version = $previous === null ? 0 : (int)$previous['version'];
        $oldHash = $old === null ? str_repeat('0', 64) : $old['hash'];
        if ($expected !== $version || !hash_equals($expectedHash, $oldHash) || $value['version'] !== $version + 1 || !hash_equals($value['previous_hash'], $oldHash)) throw new E2EEConflictException('Device manifest changed.');
        if ($previous !== null) {
            if (!hash_equals($previous['identity_key'], $value['identity_key'])) throw new E2EEConflictException('Account identity is immutable.');
            if ($previous['recovery_public'] === $value['recovery_public'] || $previous['recovery_hash'] === $value['recovery_hash']) throw new InvalidArgumentException('Recovery recipient and backup must rotate.');
            $next = array_column($value['devices'], null, 'id');
            foreach ($previous['devices'] as $device) {
                $replacement = $next[$device['id']] ?? null;
                if ($replacement === null || $replacement['public_key'] !== $device['public_key'] || $replacement['fingerprint'] !== $device['fingerprint'] || ($device['revoked'] && !$replacement['revoked'])) throw new E2EEConflictException('Existing devices cannot be replaced or reactivated.');
            }
        }
        $q = $pdo->prepare('SELECT COUNT(*) FROM market_e2ee_manifests WHERE user_id=? AND created_at>UTC_TIMESTAMP()-INTERVAL 1 DAY'); $q->execute([$user]);
        if ((int)$q->fetchColumn() >= 60) throw new OverflowException('Device manifest update limit exceeded.');
        if ($old === null) $pdo->prepare('INSERT INTO market_e2ee_accounts(user_id,identity_key,current_version,current_hash) VALUES (?,?,?,?)')->execute([$user,$value['identity_key'],$value['version'],$hash]);
        $pdo->prepare('INSERT INTO market_e2ee_manifests(user_id,version,manifest,signature,manifest_hash,recovery_cipher) VALUES (?,?,?,?,?,?)')->execute([$user,$value['version'],$input['manifest'],$input['signature'],$hash,$input['recovery_cipher']]);
        if ($old === null) $pdo->prepare('INSERT INTO market_e2ee_identity_epochs(user_id,start_version,identity_key) VALUES (?,1,?)')->execute([$user,$value['identity_key']]);
        if ($old !== null) $pdo->prepare('UPDATE market_e2ee_accounts SET current_version=?,current_hash=? WHERE user_id=?')->execute([$value['version'],$hash,$user]);
        audit_log($pdo,$user,'e2ee.manifest_updated',$user,['version'=>$value['version'],'hash'=>$hash]);
        $pdo->commit();
        return ['manifest'=>$input['manifest'],'signature'=>$input['signature'],'hash'=>$hash,'recovery_cipher'=>$input['recovery_cipher']];
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

/** Require a live session under the same locked account as the reauthentication. */
function e2ee_reset_account_lock(PDO $pdo, array $viewer): array {
    $user = (int)$viewer['id'];
    $query = $pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');
    $query->execute([$user]); $locked = $query->fetch();
    if (!$locked || (int)$locked['is_active'] !== 1 || (int)$locked['auth_version'] !== (int)$viewer['auth_version']) {
        throw new MarketAccessException('Identity reset is unavailable for this account.');
    }
    if (auth_requires_mfa($pdo,$locked) && (empty($locked['mfa_secret']) || ($_SESSION['admin_mfa_user_id']??null)!==$user)) {
        throw new MarketAccessException('A staff MFA session is required.');
    }
    $session = auth_session_key($_SESSION['auth_session_key'] ?? null);
    if ($session === null) throw new MarketAccessException('Current session required.');
    $query = $pdo->prepare('SELECT 1 FROM market_auth_sessions WHERE user_id=? AND token_hash=? AND auth_version=? AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR SHARE');
    $query->execute([$user,$session,$locked['auth_version']]);
    if (!$query->fetchColumn()) throw new MarketAccessException('Current session required.');
    return $locked;
}

/** Verify a fresh staff TOTP with the same replay counter as ordinary staff login. */
function e2ee_reset_staff_factor(PDO $pdo,array $person,#[SensitiveParameter] string $code): void {
    $secret=auth_secret_decrypt((string)$person['mfa_secret']);
    try { $counter=auth_totp_counter($secret,trim($code),$person['mfa_last_counter']===null?-1:(int)$person['mfa_last_counter']); }
    finally { sodium_memzero($secret); }
    if ($counter===null) throw new InvalidArgumentException('Identity reset credentials were not verified.');
    $pdo->prepare('UPDATE users SET mfa_last_counter=? WHERE id=?')->execute([$counter,$person['id']]);
}

/** Compare underlying v6 Ed25519/X25519 material, independent of armor, timestamps and certification packets. */
function e2ee_reset_key_material(string $armor): array {
    $lines = explode("\n",str_replace("\r\n","\n",trim(e2ee_public_key($armor))));
    $separator = array_search('', $lines, true);
    if ($separator === false) throw new InvalidArgumentException('Invalid OpenPGP armor.');
    $encoded = array_slice($lines,$separator+1,-1);
    if (preg_match('/\A=[A-Za-z0-9+\/]{4}\z/',end($encoded) ?: '')) array_pop($encoded);
    $bytes = base64_decode(implode('',$encoded),true);
    if ($bytes === false || $bytes === '') throw new InvalidArgumentException('Invalid OpenPGP armor.');
    $size = strlen($bytes); $offset = 0; $keys = []; $fingerprint = null;
    while ($offset+2 <= $size) {
        $tag = ord($bytes[$offset++]); $length = ord($bytes[$offset++]);
        if (($tag & 0xc0) !== 0xc0) throw new InvalidArgumentException('Invalid OpenPGP packet header.');
        $tag &= 0x3f;
        if ($length >= 192 && $length <= 223 && $offset < $size) {
            $length = (($length-192)<<8) + ord($bytes[$offset++]) + 192;
        } elseif ($length === 255 && $size-$offset >= 4) {
            $length = unpack('N',substr($bytes,$offset,4))[1]; $offset += 4;
        } elseif ($length >= 192) {
            throw new InvalidArgumentException('Unsupported OpenPGP packet length.');
        }
        if ($length > $size-$offset || in_array($tag,[5,7],true)) throw new InvalidArgumentException('Invalid OpenPGP public packet.');
        if ($tag === 6 || $tag === 14) {
            $packet = substr($bytes,$offset,$length);
            $algorithm = $tag === 6 ? "\x1b" : "\x19";
            if ($length !== 42 || $packet[0] !== "\x06" || $packet[5] !== $algorithm
                || substr($packet,6,4) !== "\x00\x00\x00\x20" || isset($keys[$tag])) {
                throw new InvalidArgumentException('An independent v6 Curve25519 key pair is required.');
            }
            $keys[$tag] = hash('sha256',$algorithm.substr($packet,10,32));
            if ($tag === 6) $fingerprint = hash('sha256',"\x9b".pack('N',$length).$packet);
        }
        $offset += $length;
    }
    if ($offset !== $size || count($keys) !== 2 || $fingerprint === null) throw new InvalidArgumentException('Incomplete OpenPGP public key.');
    return ['fingerprint'=>$fingerprint,'material'=>array_values($keys)];
}

function e2ee_assert_reset_keys_fresh(PDO $pdo, int $user, array $next): void {
    $device = $next['devices'][0];
    $deviceKey = e2ee_reset_key_material($device['public_key']);
    $recoveryKey = e2ee_reset_key_material($next['recovery_public']);
    $material = [hash('sha256',"\x1b".e2ee_base64($next['identity_key'],32,32)),...$deviceKey['material'],...$recoveryKey['material']];
    if (!hash_equals($device['fingerprint'],$deviceKey['fingerprint']) || count(array_unique($material)) !== 5) {
        throw new InvalidArgumentException('Reset keys must be independent.');
    }
    $query = $pdo->prepare('SELECT version,manifest FROM market_e2ee_manifests WHERE user_id=? AND version>? ORDER BY version LIMIT 32');
    $after = 0;
    do {
        $query->execute([$user,$after]); $rows = $query->fetchAll();
        foreach ($rows as $row) {
            $after = (int)$row['version'];
            $old = json_decode($row['manifest'],true,32,JSON_THROW_ON_ERROR);
            if ($old['recovery_hash'] === $next['recovery_hash']
                || in_array(hash('sha256',"\x1b".e2ee_base64($old['identity_key'],32,32)),$material,true)) {
                throw new E2EEConflictException('Retired account material cannot be reused.');
            }
            foreach ($old['devices'] as $entry) {
                if ($entry['id'] === $device['id'] || $entry['fingerprint'] === $device['fingerprint']) {
                    throw new E2EEConflictException('Retired devices cannot be reused.');
                }
            }
            foreach ([$old['recovery_public'],...array_column($old['devices'],'public_key')] as $armor) {
                if ($armor === $device['public_key'] || $armor === $next['recovery_public']) {
                    throw new E2EEConflictException('Retired OpenPGP material cannot be reused.');
                }
                // Earlier storage validated only the armor envelope. A malformed historic key
                // cannot prevent reset, and must still be verified by clients before use.
                try { $oldKey = e2ee_reset_key_material($armor); }
                catch (InvalidArgumentException) { continue; }
                if (array_intersect($oldKey['material'],$material)) throw new E2EEConflictException('Retired OpenPGP material cannot be reused.');
            }
        }
    } while (count($rows) === 32);
}

function e2ee_reset_identity(PDO $pdo, array $viewer, #[SensitiveParameter] array $input): array {
    e2ee_require_enabled();
    e2ee_fields($input,['expected_version','expected_hash','manifest','signature','recovery_cipher','password','factor']);
    $user = (int)$viewer['id'];
    $expected = e2ee_integer($input['expected_version']); $expectedHash = e2ee_hex($input['expected_hash'],64);
    if (!is_string($input['manifest']) || !is_string($input['signature']) || !is_string($input['password'])
        || !is_string($input['factor']) || strlen($input['password']) > 1024 || strlen($input['factor']) > 64) {
        throw new InvalidArgumentException('Invalid reset request.');
    }
    auth_rate_limit('e2ee-reset-ip',auth_ip(),35,300);
    auth_rate_limit('e2ee-reset-account',(string)$user,12,300);
    $next = e2ee_validate_manifest($input['manifest'],$input['signature'],$user);
    if ($next['version'] !== $expected + 1 || !hash_equals($next['previous_hash'],$expectedHash)) {
        throw new E2EEConflictException('Invalid expected manifest version.');
    }
    if (!hash_equals($next['recovery_hash'],hash('sha256',e2ee_base64($input['recovery_cipher'],1048576)))) {
        throw new InvalidArgumentException('Recovery digest mismatch.');
    }
    if (count($next['devices']) !== 1 || $next['devices'][0]['revoked']) {
        throw new InvalidArgumentException('Identity reset requires one new device.');
    }
    $hash = hash('sha256',$input['manifest']);
    $pdo->beginTransaction();
    try {
        maintenance_assert_market_available($pdo);
        $locked = e2ee_reset_account_lock($pdo,$viewer);
        $staff = auth_requires_mfa($pdo,$locked);
        $factor = $staff ? null : user_totp_state($pdo,$user,true);
        if (!$staff && $factor === null) throw new MarketAccessException('Identity reset requires an enrolled second factor.');
        try { user_security_password($locked,$input['password']); }
        catch (InvalidArgumentException) { throw new MarketAccessException('Identity reset credentials were not verified.'); }

        $old = e2ee_account($pdo,$user,true);
        if ($old === null) throw new E2EEConflictException('An existing identity is required.');
        $current = json_decode($old['manifest'],true,32,JSON_THROW_ON_ERROR);
        $epoch = $pdo->prepare('SELECT start_version FROM market_e2ee_identity_epochs WHERE user_id=? AND identity_key=?');
        $epoch->execute([$user,$next['identity_key']]); $used = $epoch->fetchColumn();
        $retry = $used !== false && (int)$used === $next['version'] && hash_equals($old['hash'],$hash)
            && $old['manifest'] === $input['manifest'] && $old['signature'] === $input['signature'] && $old['recovery_cipher'] === $input['recovery_cipher'];
        if (!$retry && ($used !== false || $current['version'] !== $expected || !hash_equals($old['hash'],$expectedHash))) {
            throw new E2EEConflictException('Device manifest or identity changed.');
        }
        $grant = $_SESSION['e2ee_reset_retry'] ?? null;
        $recentRetry = $retry && is_array($grant) && $grant['user'] === $user && $grant['auth_version'] === (int)$locked['auth_version']
            && $grant['hash'] === $hash && $grant['until'] >= time();
        if (!$recentRetry) {
            try {
                if ($staff) e2ee_reset_staff_factor($pdo,$locked,$input['factor']);
                else user_totp_check($pdo,$user,$factor,$input['factor']);
            }
            catch (InvalidArgumentException) { throw new MarketAccessException('Identity reset credentials were not verified.'); }
        }
        if (!$retry) {
            e2ee_assert_reset_keys_fresh($pdo,$user,$next);
            $pdo->prepare('INSERT INTO market_e2ee_manifests(user_id,version,manifest,signature,manifest_hash,recovery_cipher) VALUES (?,?,?,?,?,?)')
                ->execute([$user,$next['version'],$input['manifest'],$input['signature'],$hash,$input['recovery_cipher']]);
            $pdo->prepare('INSERT INTO market_e2ee_identity_epochs(user_id,start_version,identity_key) VALUES (?,?,?)')
                ->execute([$user,$next['version'],$next['identity_key']]);
            $pdo->prepare('UPDATE market_e2ee_accounts SET identity_key=?,current_version=?,current_hash=? WHERE user_id=?')
                ->execute([$next['identity_key'],$next['version'],$hash,$user]);
            audit_log($pdo,$user,'e2ee.identity_reset',$user,['version'=>$next['version'],'hash'=>$hash,'previous_hash'=>$expectedHash]);
        }
        $pdo->commit();
        if (!$recentRetry) $_SESSION['e2ee_reset_retry'] = ['user'=>$user,'auth_version'=>(int)$locked['auth_version'],'hash'=>$hash,'until'=>time()+300];
        return ['manifest'=>$input['manifest'],'signature'=>$input['signature'],'hash'=>$hash,'recovery_cipher'=>$input['recovery_cipher']];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function e2ee_context(PDO $pdo, int $user, int $listing, int $peer): array {
    e2ee_require_enabled(); $context = chat_context($pdo,$user,$listing,$peer);
    $self = e2ee_account($pdo,$user); $other = e2ee_account($pdo,$peer);
    return ['self'=>e2ee_public_account($self),'peer'=>e2ee_public_account($other),'can_send'=>(bool)$context['can_send'] && $self !== null && $other !== null];
}

/** Call only in a new mutation transaction, before account, attachment or chat rows. */
function e2ee_lock_conversation(PDO $pdo, int $user, int $listing, int $peer): array {
    maintenance_assert_market_available($pdo);
    if ($user < 1 || $peer < 1 || $listing < 1 || $user === $peer) throw new InvalidArgumentException('Invalid conversation.');
    $q = $pdo->prepare('SELECT id FROM market_listings WHERE id=? FOR UPDATE'); $q->execute([$listing]);
    if (!$q->fetchColumn()) throw new MarketNotFoundException('Conversation unavailable.');
    $ids = [$user,$peer]; sort($ids,SORT_NUMERIC);
    $q = $pdo->prepare('SELECT id,is_active FROM users WHERE id IN (?,?) ORDER BY id FOR UPDATE'); $q->execute($ids); $people = $q->fetchAll();
    if (count($people) !== 2 || array_filter($people,static fn(array $p): bool => (int)$p['is_active'] !== 1)) throw new MarketAccessException('Conversation unavailable.');
    $context = chat_context($pdo,$user,$listing,$peer);
    if (!$context['can_send'] || !market_category_locked_allowed($pdo,(int)$context['item']['category_id']) || !geo_country_locked_enabled($pdo,$context['item']['country_code'])) throw new MarketAccessException('Conversation unavailable.');
    return $context;
}

function e2ee_current_devices(PDO $pdo, int $user, int $peer, string $device): array {
    // Caller holds both user rows, serializing enrollment, revocation, sends and quotas.
    $self = e2ee_account($pdo,$user,true); $other = e2ee_account($pdo,$peer,true);
    if ($self === null || $other === null) throw new E2EEConflictException('Both accounts need encrypted messaging.');
    $ownManifest = json_decode($self['manifest'],true,32,JSON_THROW_ON_ERROR);
    $peerManifest = json_decode($other['manifest'],true,32,JSON_THROW_ON_ERROR);
    $found = false;
    foreach ($ownManifest['devices'] as $item) if ($item['id'] === $device && !$item['revoked']) $found = true;
    if (!$found) throw new MarketAccessException('Device unavailable.');
    return [$ownManifest,$peerManifest];
}

/** Caller holds both user rows under the conversation lock. */
function e2ee_pair_has_identity_reset(PDO $pdo, int $user, int $peer): bool {
    $query = $pdo->prepare('SELECT 1 FROM market_e2ee_identity_epochs WHERE user_id IN (?,?) AND start_version>1 LIMIT 1 FOR SHARE');
    $query->execute([$user,$peer]); return $query->fetchColumn() !== false;
}

function e2ee_storage_dir(bool $create = false): string {
    $configured = getenv('E2EE_ATTACHMENT_DIR');
    $path = $configured === false || $configured === '' ? '/var/lib/gunindex/e2ee' : $configured;
    if (str_contains($path,"\0") || str_contains($path,'://') || !preg_match('~\A(?:/|[A-Za-z]:[\\\\/])~',$path) || preg_match('~(?:^|[\\\\/])\.\.?([\\\\/]|$)~',$path)) throw new E2EEUnavailableException('Invalid private storage configuration.');
    $cursor = rtrim($path,'/\\');
    while ($cursor !== '' && dirname($cursor) !== $cursor) {
        if (is_link($cursor)) throw new E2EEUnavailableException('Private storage cannot use symbolic links.');
        $cursor = dirname($cursor);
    }
    if (!is_dir($path) && $create) {
        $mask = umask(0077);
        try { if (!mkdir($path,0700,true) && !is_dir($path)) throw new E2EEUnavailableException('Private storage unavailable.'); }
        finally { umask($mask); }
    }
    $resolved = realpath($path);
    if ($resolved === false || !is_dir($resolved) || is_link($path)) throw new E2EEUnavailableException('Private storage unavailable.');
    $normalize = static fn(string $p): string => PHP_OS_FAMILY === 'Windows' ? strtolower(str_replace('\\','/',$p)) : str_replace('\\','/',$p);
    $actual = rtrim($normalize($resolved),'/');
    foreach ([dirname(__DIR__),$_SERVER['DOCUMENT_ROOT'] ?? ''] as $public) {
        if (!is_string($public) || $public === '' || ($root = realpath($public)) === false) continue;
        $root = rtrim($normalize($root),'/');
        if ($actual === $root || str_starts_with($actual,$root.'/')) throw new E2EEUnavailableException('Private storage must be outside the webroot and repository.');
    }
    if (PHP_OS_FAMILY !== 'Windows' && ((fileperms($resolved) & 0077) !== 0)) throw new E2EEUnavailableException('Private storage permissions are too broad.');
    return $resolved;
}

function e2ee_storage_path(string $key, bool $create = false): string {
    return e2ee_storage_dir($create).DIRECTORY_SEPARATOR.e2ee_hex($key,64).'.bin';
}

function e2ee_attachment_summary(array $row): array {
    return ['id'=>$row['id'],'sha256'=>$row['cipher_hash'],'bytes'=>(int)$row['cipher_bytes']];
}

function e2ee_upload(PDO $pdo, int $user, array $input): array {
    e2ee_require_enabled();
    $fields = ['listing_id','peer_id','device_id','client_id','id','ciphertext'];
    $versioned = array_key_exists('sender_version',$input) || array_key_exists('receiver_version',$input);
    if ($versioned) array_push($fields,'sender_version','receiver_version');
    e2ee_fields($input,$fields);
    $listing = e2ee_integer($input['listing_id']); $peer = e2ee_integer($input['peer_id']);
    $device = e2ee_hex($input['device_id']); $client = e2ee_hex($input['client_id']); $id = e2ee_hex($input['id']);
    $senderVersion = $versioned ? e2ee_integer($input['sender_version']) : null;
    $receiverVersion = $versioned ? e2ee_integer($input['receiver_version']) : null;
    $bytes = e2ee_base64($input['ciphertext'],5242880); $hash = hash('sha256',$bytes); $size = strlen($bytes);
    $path = null; $commitAttempted = false;
    $pdo->beginTransaction();
    try {
        e2ee_lock_conversation($pdo,$user,$listing,$peer);
        [$own,$other] = e2ee_current_devices($pdo,$user,$peer,$device);
        $resetPair = !$versioned && e2ee_pair_has_identity_reset($pdo,$user,$peer);
        if ($resetPair || ($versioned && ($senderVersion !== $own['version'] || $receiverVersion !== $other['version']))) {
            throw new E2EEConflictException('Device manifest changed. Refresh recipients before uploading.');
        }
        $q = $pdo->prepare('SELECT * FROM market_e2ee_attachments WHERE id=? FOR UPDATE'); $q->execute([$id]); $existing = $q->fetch();
        if ($existing) {
            if ((int)$existing['owner_id'] !== $user || (int)$existing['listing_id'] !== $listing || (int)$existing['peer_id'] !== $peer || $existing['device_id'] !== $device || $existing['client_id'] !== $client || !hash_equals($existing['cipher_hash'],$hash) || (int)$existing['cipher_bytes'] !== $size
                || ($existing['sender_version'] === null ? $versioned : ((int)$existing['sender_version'] !== $own['version'] || (int)$existing['receiver_version'] !== $other['version']))) {
                throw new E2EEConflictException('Attachment identifier already used.');
            }
            $stream = e2ee_attachment_stream($existing); fclose($stream);
            $pdo->commit(); return e2ee_attachment_summary($existing);
        }
        $q = $pdo->prepare('SELECT COUNT(CASE WHEN message_id IS NULL THEN 1 END) AS unbound,COALESCE(SUM(cipher_bytes),0) AS bytes FROM market_e2ee_attachments WHERE owner_id=?'); $q->execute([$user]); $quota = $q->fetch();
        if ((int)$quota['unbound'] >= 10 || (int)$quota['bytes'] + $size > 52428800) throw new OverflowException('Encrypted attachment quota exceeded.');
        $key = bin2hex(random_bytes(32)); $path = e2ee_storage_path($key,true);
        $mask = umask(0077);
        try { $file = fopen($path,'x+b'); } finally { umask($mask); }
        if ($file === false) throw new E2EEUnavailableException('Private attachment storage unavailable.');
        try {
            for ($written = 0; $written < $size;) {
                $count = fwrite($file,substr($bytes,$written,1048576));
                if ($count === false || $count === 0) throw new E2EEUnavailableException('Private attachment write failed.');
                $written += $count;
            }
            if (!fflush($file) || (function_exists('fsync') && !fsync($file))) throw new E2EEUnavailableException('Private attachment write failed.');
        } finally { fclose($file); }
        $pdo->prepare('INSERT INTO market_e2ee_attachments(id,owner_id,listing_id,peer_id,device_id,client_id,storage_key,cipher_hash,cipher_bytes,sender_version,receiver_version) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$id,$user,$listing,$peer,$device,$client,$key,$hash,$size,$own['version'],$other['version']]);
        $commitAttempted = true; $pdo->commit();
        return ['id'=>$id,'sha256'=>$hash,'bytes'=>$size];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // An ambiguous commit must never destroy a possibly committed attachment.
        if (!$commitAttempted && $path !== null && is_file($path) && !is_link($path)) unlink($path);
        throw $e;
    }
}

function e2ee_send(PDO $pdo, int $user, array $input): int {
    e2ee_require_enabled(); e2ee_fields($input,['listing_id','peer_id','device_id','client_id','sender_version','receiver_version','ciphertext','attachments']);
    $listing = e2ee_integer($input['listing_id']); $peer = e2ee_integer($input['peer_id']);
    $device = e2ee_hex($input['device_id']); $client = e2ee_hex($input['client_id']);
    $senderVersion = e2ee_integer($input['sender_version']); $receiverVersion = e2ee_integer($input['receiver_version']);
    e2ee_base64($input['ciphertext'],131072);
    if (!is_array($input['attachments']) || !array_is_list($input['attachments']) || count($input['attachments']) > 3) throw new InvalidArgumentException('Invalid attachments.');
    foreach ($input['attachments'] as $id) e2ee_hex($id);
    if (count(array_unique($input['attachments'])) !== count($input['attachments'])) throw new InvalidArgumentException('Duplicate attachments.');
    // Explicit field order makes equivalent JSON object order irrelevant to retries.
    $digest = hash('sha256',json_encode([$listing,$user,$peer,$device,$client,$senderVersion,$receiverVersion,$input['ciphertext'],$input['attachments']],JSON_THROW_ON_ERROR));
    $pdo->beginTransaction();
    try {
        $context = e2ee_lock_conversation($pdo,$user,$listing,$peer);
        $q = $pdo->prepare('SELECT message_id,payload_hash FROM market_e2ee_messages WHERE sender_id=? AND client_id=? FOR UPDATE'); $q->execute([$user,$client]); $existing = $q->fetch();
        if ($existing) {
            if (!hash_equals($existing['payload_hash'],$digest)) throw new E2EEConflictException('Message identifier already used.');
            $pdo->commit(); return (int)$existing['message_id'];
        }
        [$own,$other] = e2ee_current_devices($pdo,$user,$peer,$device);
        if ($own['version'] !== $senderVersion || $other['version'] !== $receiverVersion) throw new E2EEConflictException('Device manifest changed. Refresh recipients before sending.');
        $q = $pdo->prepare('SELECT COUNT(*) FROM market_messages WHERE sender_id=? AND created_at>UTC_TIMESTAMP()-INTERVAL 1 MINUTE'); $q->execute([$user]);
        if ((int)$q->fetchColumn() >= 20) throw new OverflowException('Message rate limit exceeded.');
        foreach ($input['attachments'] as $id) {
            $q = $pdo->prepare('SELECT * FROM market_e2ee_attachments WHERE id=? FOR UPDATE'); $q->execute([$id]); $attachment = $q->fetch();
            if (!$attachment || (int)$attachment['owner_id'] !== $user || (int)$attachment['listing_id'] !== $listing || (int)$attachment['peer_id'] !== $peer || $attachment['device_id'] !== $device || $attachment['client_id'] !== $client || $attachment['message_id'] !== null) throw new MarketAccessException('Attachment unavailable.');
            if ($attachment['sender_version'] === null ? e2ee_pair_has_identity_reset($pdo,$user,$peer)
                : ((int)$attachment['sender_version'] !== $senderVersion || (int)$attachment['receiver_version'] !== $receiverVersion)) {
                throw new E2EEConflictException('Attachment recipients have changed. Encrypt the file again.');
            }
            // Detect missing/corrupted private data before binding it permanently.
            $stream = e2ee_attachment_stream($attachment); fclose($stream);
        }
        $pdo->prepare("INSERT INTO market_messages(listing_id,sender_id,receiver_id,message_text,cipher_version) VALUES (?,?,?,'',2)")->execute([$listing,$user,$peer]); $id = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO market_e2ee_messages(message_id,sender_id,receiver_id,device_id,client_id,sender_version,receiver_version,ciphertext,payload_hash) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$id,$user,$peer,$device,$client,$senderVersion,$receiverVersion,$input['ciphertext'],$digest]);
        foreach ($input['attachments'] as $attachmentId) $pdo->prepare('UPDATE market_e2ee_attachments SET message_id=? WHERE id=? AND message_id IS NULL')->execute([$id,$attachmentId]);
        $seller = (int)$context['item']['seller_id']; $buyer = $user === $seller ? $peer : $user;
        $pdo->prepare('INSERT INTO market_conversations(listing_id,seller_id,buyer_id,last_message_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE last_message_id=GREATEST(last_message_id,VALUES(last_message_id)),updated_at=UTC_TIMESTAMP()')->execute([$listing,$seller,$buyer,$id]);
        if (function_exists('notify_user')) notify_user($pdo,$peer,'chat:'.$id,i18n_user_text($pdo,$peer,'notification.new_chat.title'),i18n_user_text($pdo,$peer,'notification.new_chat.body',['listing'=>$context['item']['title']]),'/user/secure-chat.php?listing_id='.$listing.'&user_id='.$user,true);
        $pdo->commit(); return $id;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function e2ee_historical_manifest(PDO $pdo, int $user, int $version): array {
    $q = $pdo->prepare('SELECT manifest,signature,manifest_hash AS hash FROM market_e2ee_manifests WHERE user_id=? AND version=?'); $q->execute([$user,$version]); $row = $q->fetch();
    if (!$row || !hash_equals($row['hash'],hash('sha256',$row['manifest']))) throw new E2EEUnavailableException('Historical manifest unavailable.');
    return $row;
}

/** Authorized, bounded public proof pages. Clients independently validate the pinned chain. */
function e2ee_manifest_chain(PDO $pdo, int $user, int $listing, int $peer, int $subject, int $from, int $through): array {
    e2ee_require_enabled();
    if ($from < 1 || $through < $from || $through-$from >= 32) throw new InvalidArgumentException('Invalid historical manifest range.');
    if ($subject !== $user && $subject !== $peer) throw new MarketAccessException('Manifest history unavailable.');
    $context = chat_context($pdo,$user,$listing,$peer);
    if ($subject === $peer && !$context['existing']) throw new MarketAccessException('Manifest history unavailable.');
    $query = $pdo->prepare('SELECT version,manifest,signature,manifest_hash AS hash FROM market_e2ee_manifests WHERE user_id=? AND version BETWEEN ? AND ? ORDER BY version');
    $query->execute([$subject,$from,$through]); $rows = $query->fetchAll();
    if (count($rows) !== $through-$from+1) throw new MarketNotFoundException('Historical manifest range unavailable.');
    $previous = null;
    foreach ($rows as $index => &$row) {
        try { $manifest = json_decode($row['manifest'],true,32,JSON_THROW_ON_ERROR); }
        catch (JsonException) { throw new E2EEUnavailableException('Historical manifest chain unavailable.'); }
        if ((int)$row['version'] !== $from+$index || ($manifest['version'] ?? null) !== $from+$index
            || ($manifest['user_id'] ?? null) !== $subject || !hash_equals($row['hash'],hash('sha256',$row['manifest']))
            || ($previous !== null && ($manifest['previous_hash'] ?? null) !== $previous)) {
            throw new E2EEUnavailableException('Historical manifest chain unavailable.');
        }
        $previous = $row['hash']; unset($row['version']);
    }
    unset($row);
    return $rows;
}

function e2ee_history(PDO $pdo, int $user, int $listing, int $peer, int $after = 0): array {
    e2ee_require_enabled(); if ($after < 0) throw new InvalidArgumentException('Invalid cursor.');
    chat_context($pdo,$user,$listing,$peer);
    $q = $pdo->prepare('SELECT m.id,m.listing_id,m.sender_id,m.receiver_id,m.created_at,e.device_id,e.client_id,e.sender_version,e.receiver_version,e.ciphertext FROM market_messages m JOIN market_e2ee_messages e ON e.message_id=m.id AND e.sender_id=m.sender_id AND e.receiver_id=m.receiver_id WHERE m.listing_id=? AND ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?)) AND m.id>? AND m.cipher_version=2 ORDER BY m.id ASC LIMIT 25');
    $q->execute([$listing,$user,$peer,$peer,$user,$after]); $rows = $q->fetchAll(); $manifests = [];
    foreach ($rows as &$row) {
        foreach (['id','listing_id','sender_id','receiver_id','sender_version','receiver_version'] as $field) $row[$field] = (int)$row[$field];
        foreach (['sender','receiver'] as $party) {
            $key = $row[$party.'_id'].':'.$row[$party.'_version'];
            $manifests[$key] ??= e2ee_historical_manifest($pdo,$row[$party.'_id'],$row[$party.'_version']);
            $row[$party.'_manifest'] = $manifests[$key];
        }
        $q = $pdo->prepare('SELECT id,cipher_hash,cipher_bytes FROM market_e2ee_attachments WHERE message_id=? ORDER BY id'); $q->execute([$row['id']]);
        $row['attachments'] = array_map('e2ee_attachment_summary',$q->fetchAll());
    }
    unset($row); return $rows;
}

/** Open and verify before response headers. The caller closes the returned resource. */
function e2ee_attachment_stream(array $attachment) {
    $path = e2ee_storage_path($attachment['storage_key']);
    if (is_link($path) || !is_file($path)) throw new E2EEUnavailableException('Attachment unavailable.');
    $before = lstat($path); $stream = fopen($path,'rb');
    if ($stream === false) throw new E2EEUnavailableException('Attachment unavailable.');
    try {
        $stat = fstat($stream);
        if ($before === false || $stat === false || ($stat['mode'] & 0170000) !== 0100000 || $stat['ino'] !== $before['ino'] || $stat['dev'] !== $before['dev'] || $stat['size'] !== (int)$attachment['cipher_bytes'] || $stat['size'] < 1 || $stat['size'] > 5242880) throw new E2EEUnavailableException('Attachment integrity failed.');
        $hash = hash_init('sha256'); hash_update_stream($hash,$stream);
        if (!hash_equals($attachment['cipher_hash'],hash_final($hash)) || !rewind($stream)) throw new E2EEUnavailableException('Attachment integrity failed.');
        return $stream;
    } catch (Throwable $e) { fclose($stream); throw $e; }
}

function e2ee_download(PDO $pdo, int $user, string $id): array {
    e2ee_require_enabled(); e2ee_hex($id);
    $q = $pdo->prepare('SELECT a.*,m.sender_id AS message_sender,m.receiver_id AS message_receiver FROM market_e2ee_attachments a LEFT JOIN market_messages m ON m.id=a.message_id WHERE a.id=?'); $q->execute([$id]); $attachment = $q->fetch();
    if (!$attachment || ($attachment['message_id'] === null ? (int)$attachment['owner_id'] !== $user : !in_array($user,[(int)$attachment['message_sender'],(int)$attachment['message_receiver']],true))) throw new MarketNotFoundException('Attachment unavailable.');
    return e2ee_attachment_summary($attachment) + ['stream'=>e2ee_attachment_stream($attachment)];
}

function e2ee_uploads(PDO $pdo, int $user): array {
    e2ee_require_enabled();
    $q = $pdo->prepare('SELECT id,cipher_hash,cipher_bytes,created_at FROM market_e2ee_attachments WHERE owner_id=? AND message_id IS NULL ORDER BY created_at,id LIMIT 10');
    $q->execute([$user]);
    return array_map(static fn(array $row): array => e2ee_attachment_summary($row) + ['created_at'=>$row['created_at']],$q->fetchAll());
}

/** Read receipts describe the authenticated conversation, never arbitrary global IDs. */
function e2ee_read(PDO $pdo, int $user, int $listing, int $peer, int $through): int {
    e2ee_require_enabled(); e2ee_integer($user); e2ee_integer($listing); e2ee_integer($peer); e2ee_integer($through);
    if ($user === $peer) throw new InvalidArgumentException('Invalid conversation.');
    $pdo->beginTransaction();
    try {
        maintenance_assert_market_available($pdo);
        $q = $pdo->prepare('SELECT id FROM market_listings WHERE id=? FOR SHARE'); $q->execute([$listing]);
        if (!$q->fetchColumn()) throw new MarketNotFoundException('Conversation unavailable.');
        $ids = [$user,$peer]; sort($ids,SORT_NUMERIC);
        $q = $pdo->prepare('SELECT id,is_active FROM users WHERE id IN (?,?) ORDER BY id FOR UPDATE'); $q->execute($ids); $people = $q->fetchAll();
        if (count($people) !== 2) throw new MarketNotFoundException('Conversation unavailable.');
        foreach ($people as $person) if ((int)$person['id'] === $user && (int)$person['is_active'] !== 1) throw new MarketAccessException('Account unavailable.');
        chat_context($pdo,$user,$listing,$peer);
        $q = $pdo->prepare('SELECT id FROM market_messages WHERE id=? AND listing_id=? AND cipher_version=2 AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) FOR UPDATE');
        $q->execute([$through,$listing,$user,$peer,$peer,$user]);
        if (!$q->fetchColumn()) throw new MarketNotFoundException('Read boundary unavailable.');
        $q = $pdo->prepare('UPDATE market_messages SET is_read=1 WHERE listing_id=? AND sender_id=? AND receiver_id=? AND cipher_version=2 AND id<=? AND is_read=0');
        $q->execute([$listing,$peer,$user,$through]); $count = $q->rowCount();
        $pdo->commit(); return $count;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function e2ee_discard(PDO $pdo, int $user, string $id): void {
    e2ee_require_enabled(); e2ee_hex($id); $path = null;
    $pdo->beginTransaction();
    try {
        maintenance_assert_market_available($pdo);
        $q = $pdo->prepare('SELECT id,is_active FROM users WHERE id=? FOR UPDATE'); $q->execute([$user]); $person = $q->fetch();
        if (!$person || (int)$person['is_active'] !== 1) throw new MarketAccessException('Account unavailable.');
        $q = $pdo->prepare('SELECT * FROM market_e2ee_attachments WHERE id=? FOR UPDATE'); $q->execute([$id]); $attachment = $q->fetch();
        if (!$attachment || (int)$attachment['owner_id'] !== $user) throw new MarketNotFoundException('Attachment unavailable.');
        if ($attachment['message_id'] !== null) throw new E2EEConflictException('Bound attachments cannot be discarded.');
        $path = e2ee_storage_path($attachment['storage_key']);
        $pdo->prepare('DELETE FROM market_e2ee_attachments WHERE id=?')->execute([$id]); $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    // Delete only after a certain commit. Failed deletion leaves inaccessible opaque data.
    if ($path !== null && is_file($path) && !is_link($path)) unlink($path);
}
