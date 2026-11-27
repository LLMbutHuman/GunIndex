<?php
declare(strict_types=1);

function governance_roles(): array
{
    return [
        'administrator'=>['label'=>'Administration','permissions'=>['dashboard.read','site.manage','users.manage','categories.manage','reports.read','reports.review','content.moderate','privacy.review','identity.review','legal.manage','finance.manage','campaigns.manage','dealers.manage']],
        'moderator'=>['label'=>'Moderation','permissions'=>['dashboard.read','reports.read','reports.review','content.moderate']],
        'support'=>['label'=>'Support','permissions'=>['dashboard.read','reports.read','privacy.review']],
        'verification'=>['label'=>'Identitätsprüfung','permissions'=>['dashboard.read','identity.review']],
        'finance'=>['label'=>'Finanzen','permissions'=>['dashboard.read','finance.manage']],
    ];
}

function governance_permissions(): array
{
    $permissions = array_merge(['roles.manage'], ...array_column(governance_roles(), 'permissions'));
    $permissions = array_values(array_unique($permissions));
    sort($permissions, SORT_STRING);
    return $permissions;
}

function governance_role_policy(PDO $pdo): array
{
    $roles = array_map(static fn(array $role): array => $role['permissions'], governance_roles());
    $known = array_fill_keys(governance_permissions(), true);
    $rows = $pdo->query('SELECT s.revision,p.role_code,p.permission_code,p.granted FROM market_role_policy_state s'
        .' LEFT JOIN market_role_permission_overrides p ON 1=1 WHERE s.id=1')->fetchAll();
    if ($rows === []) throw new RuntimeException('Role policy state is unavailable.');
    foreach ($rows as $row) {
        $role = $row['role_code'];
        $permission = $row['permission_code'];
        if (!isset($roles[$role], $known[$permission])) continue;
        $roles[$role] = array_values(array_diff($roles[$role], [$permission]));
        if ((int)$row['granted'] === 1) $roles[$role][] = $permission;
    }
    foreach ($roles as &$permissions) sort($permissions, SORT_STRING);
    unset($permissions);
    return ['revision'=>(string)$rows[0]['revision'], 'roles'=>$roles];
}

function governance_user_roles(PDO $pdo, int $userId): array
{
    try {
        $query = $pdo->prepare('SELECT role_code FROM market_role_grants WHERE user_id=?');
        $query->execute([$userId]);
        return array_values(array_intersect($query->fetchAll(PDO::FETCH_COLUMN), array_keys(governance_roles())));
    } catch (PDOException $error) {
        if ($error->getCode() === '42S02') return [];
        throw $error;
    }
}

function rbac_permissions(PDO $pdo,int $userId): array
{
    $q=$pdo->prepare('SELECT is_admin,is_active FROM users WHERE id=?');$q->execute([$userId]);$user=$q->fetch();
    if(!$user||(int)$user['is_active']!==1)return [];
    if((int)$user['is_admin']===1)return ['*'];
    $roles = governance_user_roles($pdo, $userId);
    if ($roles === []) return [];
    $effective = governance_role_policy($pdo)['roles'];
    return array_values(array_unique(array_merge(...array_intersect_key($effective, array_fill_keys($roles, true)))));
}

function rbac_has_permission(PDO $pdo,int $userId,string $permission): bool
{
    $allowed=rbac_permissions($pdo,$userId);return in_array('*',$allowed,true)||in_array($permission,$allowed,true);
}

function permission_user_can(PDO $pdo,int $userId,string $permission): bool { return rbac_has_permission($pdo,$userId,$permission); }

function rbac_requires_mfa(PDO $pdo,int $userId): bool
{
    $query = $pdo->prepare('SELECT is_admin,is_active FROM users WHERE id=?');
    $query->execute([$userId]);
    $user = $query->fetch();
    return $user && (int)$user['is_active'] === 1 && ((int)$user['is_admin'] === 1 || governance_user_roles($pdo, $userId) !== []);
}

function require_permission(PDO $pdo,string $permission): array
{
    $user=require_login($pdo);
    if(!rbac_has_permission($pdo,(int)$user['id'],$permission))abort_request(403,t('admin_roles.error_permission'));
    if(empty($user['mfa_secret'])||($_SESSION['admin_mfa_user_id']??null)!==(int)$user['id'])abort_request(403,t('admin_roles.error_current_mfa'));
    return $user;
}

function require_staff(PDO $pdo): array
{
    $user=require_login($pdo);
    if(!rbac_has_permission($pdo,(int)$user['id'],'dashboard.read'))abort_request(403,t('admin_roles.error_permission'));
    if(empty($user['mfa_secret'])||($_SESSION['admin_mfa_user_id']??null)!==(int)$user['id'])abort_request(403,t('admin_roles.error_current_mfa'));
    return $user;
}

function governance_assert_actor(PDO $pdo,int $actor,string $permission): void
{
    if(!rbac_has_permission($pdo,$actor,$permission))throw new DomainException('Die erforderliche Berechtigung fehlt.');
    if(PHP_SAPI!=='cli'&&((int)($_SESSION['user_id']??0)!==$actor||($_SESSION['admin_mfa_user_id']??null)!==$actor))throw new DomainException('Eine aktuelle Mehrfaktoranmeldung ist erforderlich.');
}

function governance_role_mfa_recent(array $session, int $now): bool
{
    $started = $session['auth_started_at'] ?? null;
    return is_int($started) && $started > 0 && $started <= $now && $now - $started <= 900;
}

function governance_lock_role_policy(PDO $pdo, int $actor): void
{
    if ($pdo->query('SELECT id FROM market_role_policy_state WHERE id=1 FOR UPDATE')->fetchColumn() === false) {
        throw new RuntimeException('Role policy state is unavailable.');
    }
    governance_assert_actor($pdo, $actor, 'roles.manage');
    if (PHP_SAPI !== 'cli' && !governance_role_mfa_recent($_SESSION ?? [], time())) {
        throw new DomainException(t('admin_roles.matrix_mfa'));
    }
}

function governance_role_policy_changes(array $current, mixed $submitted, array $actorPermissions, array $actorRoles): array
{
    if (!is_array($submitted) || count($submitted) !== count($current) || array_diff_key($submitted, $current) !== []) {
        throw new InvalidArgumentException(t('admin_roles.matrix_invalid'));
    }
    $known = governance_permissions();
    $full = in_array('*', $actorPermissions, true);
    $changes = [];
    foreach ($current as $role => $existing) {
        $requested = $submitted[$role];
        if (!is_array($requested) || !array_is_list($requested) || array_shift($requested) !== '' || count($requested) > count($known)) {
            throw new InvalidArgumentException(t('admin_roles.matrix_invalid'));
        }
        foreach ($requested as $permission) {
            if (!is_string($permission) || !in_array($permission, $known, true)) throw new InvalidArgumentException(t('admin_roles.matrix_invalid'));
        }
        if (count(array_unique($requested)) !== count($requested)) throw new InvalidArgumentException(t('admin_roles.matrix_invalid'));
        $added = array_values(array_diff($requested, $existing));
        $removed = array_values(array_diff($existing, $requested));
        if (!$added && !$removed) continue;
        if (in_array($role, $actorRoles, true)
            || (!$full && array_diff(array_merge($added, $removed), $actorPermissions) !== [])) {
            throw new DomainException(t('admin_roles.matrix_scope'));
        }
        $changes[$role] = compact('added', 'removed');
    }
    return $changes;
}

function governance_role_policy_save(PDO $pdo, int $actor, string $revision, mixed $submitted): void
{
    $pdo->beginTransaction();
    try {
        governance_lock_role_policy($pdo, $actor);
        $policy = governance_role_policy($pdo);
        if (!hash_equals($policy['revision'], $revision)) throw new DomainException(t('admin_roles.matrix_stale'));
        $actorRoles = governance_user_roles($pdo, $actor);
        $changes = governance_role_policy_changes($policy['roles'], $submitted, rbac_permissions($pdo, $actor), $actorRoles);
        if ($changes !== []) {
            $defaults = governance_roles();
            $set = $pdo->prepare('INSERT INTO market_role_permission_overrides (role_code,permission_code,granted) VALUES (?,?,?)'
                .' ON DUPLICATE KEY UPDATE granted=VALUES(granted)');
            $reset = $pdo->prepare('DELETE FROM market_role_permission_overrides WHERE role_code=? AND permission_code=?');
            foreach ($changes as $role => $change) {
                foreach (['added'=>true, 'removed'=>false] as $kind => $grant) {
                    foreach ($change[$kind] as $permission) {
                        if (in_array($permission, $defaults[$role]['permissions'], true) === $grant) $reset->execute([$role, $permission]);
                        else $set->execute([$role, $permission, (int)$grant]);
                    }
                }
            }
            $placeholders = implode(',', array_fill(0, count($changes), '?'));
            $pdo->prepare('UPDATE users u JOIN market_role_grants g ON g.user_id=u.id SET u.auth_version=u.auth_version+1'
                .' WHERE u.is_admin=0 AND g.role_code IN ('.$placeholders.')')->execute(array_keys($changes));
            $pdo->exec('UPDATE market_role_policy_state SET revision=revision+1 WHERE id=1');
            audit_log($pdo, $actor, 'role.permissions_changed', null, ['roles'=>$changes]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function governance_role_change(PDO $pdo,int $actor,int $target,string $role,bool $grant): void
{
    if(!isset(governance_roles()[$role]))throw new InvalidArgumentException('Unbekannte Rolle.');
    $pdo->beginTransaction();
    try{
        governance_lock_role_policy($pdo, $actor);
        $permissions = rbac_permissions($pdo, $actor);
        $rolePermissions = governance_role_policy($pdo)['roles'][$role];
        if (!in_array('*', $permissions, true) && array_diff($rolePermissions, $permissions) !== []) {
            throw new DomainException(t('admin_roles.matrix_scope'));
        }
        $q=$pdo->prepare('SELECT id,is_admin,is_active,mfa_secret FROM users WHERE id=? FOR UPDATE');$q->execute([$target]);$user=$q->fetch();
        if(!$user||(int)$user['is_admin']===1||$actor===$target)throw new DomainException('Das eigene oder ein initiales Administrationskonto wird hier nicht verändert.');
        if($grant&&((int)$user['is_active']!==1||empty($user['mfa_secret'])))throw new DomainException('Das Zielkonto benötigt vor einer Personalrolle eine eingerichtete Zwei-Faktor-Anmeldung.');
        if($grant)$pdo->prepare('INSERT IGNORE INTO market_role_grants(user_id,role_code,granted_by) VALUES (?,?,?)')->execute([$target,$role,$actor]);
        else $pdo->prepare('DELETE FROM market_role_grants WHERE user_id=? AND role_code=?')->execute([$target,$role]);
        $pdo->prepare('UPDATE users SET auth_version=auth_version+1 WHERE id=?')->execute([$target]);
        audit_log($pdo,$actor,$grant?'role.granted':'role.revoked',$target,['role'=>$role]);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

/** Only new-market objects; private messages require actual participation. */
function governance_subject(PDO $pdo,int $reporter,string $type,int $id): int
{
    if($id<1)throw new InvalidArgumentException('Ungültiges Meldeziel.');
    if($type==='listing'){$row=market_find($pdo,$id,true);if(!$row)throw new InvalidArgumentException('Das Angebot ist nicht verfügbar.');$owner=(int)$row['seller_id'];}
    elseif($type==='user'){$q=$pdo->prepare('SELECT id FROM users WHERE id=? AND is_active=1');$q->execute([$id]);$owner=(int)$q->fetchColumn();}
    elseif($type==='message'){$q=$pdo->prepare('SELECT sender_id,receiver_id FROM market_messages WHERE id=? AND (sender_id=? OR receiver_id=?)');$q->execute([$id,$reporter,$reporter]);$row=$q->fetch();if(!$row)throw new DomainException('Diese Nachricht ist privat.');$owner=(int)$row['sender_id'];}
    elseif($type==='rating'){$q=$pdo->prepare("SELECT sender_id FROM market_ratings WHERE id=? AND status='active'");$q->execute([$id]);$owner=(int)$q->fetchColumn();}
    elseif($type==='advertisement'){
        $q=$pdo->prepare("SELECT user_id,country_code,placement FROM market_campaigns WHERE id=? AND status='approved'");$q->execute([$id]);$campaign=$q->fetch();
        $owner=$campaign&&($campaign['placement']==='legacy' || banner_ads_public($pdo,$campaign['placement'],$campaign['country_code'],null,$id))?(int)$campaign['user_id']:0;
    }
    else throw new InvalidArgumentException('Dieses Meldeziel wird nicht unterstützt.');
    if(!$owner||$owner===$reporter)throw new InvalidArgumentException('Dieses Objekt kann so nicht gemeldet werden.');
    return $owner;
}

function governance_report(PDO $pdo,int $reporter,string $type,int $target,string $reason,string $evidence=''): int
{
    $reason=trim($reason);$evidence=trim($evidence);
    if(mb_strlen($reason)<10||mb_strlen($reason)>4000||mb_strlen($evidence)>8000||!preg_match('//u',$reason.$evidence))throw new InvalidArgumentException('Bitte einen nachvollziehbaren Grund mit 10 bis 4.000 Zeichen angeben.');
    $owner=governance_subject($pdo,$reporter,$type,$target);$pdo->beginTransaction();
    try{
        $q=$pdo->prepare('SELECT id FROM users WHERE id=? AND is_active=1 FOR UPDATE');$q->execute([$reporter]);if(!$q->fetchColumn())throw new DomainException('Konto nicht verfügbar.');
        $q=$pdo->prepare("SELECT id FROM market_reports WHERE reporter_id=? AND target_type=? AND target_id=? AND status IN ('open','reviewing','appealed') ORDER BY id DESC LIMIT 1");$q->execute([$reporter,$type,$target]);$existing=$q->fetchColumn();if($existing){$pdo->commit();return(int)$existing;}
        $q=$pdo->prepare('SELECT COUNT(*) FROM market_reports WHERE reporter_id=? AND created_at>UTC_TIMESTAMP()-INTERVAL 1 HOUR');$q->execute([$reporter]);if((int)$q->fetchColumn()>=5)throw new DomainException('Das Stundenlimit für Meldungen ist erreicht.');
        $q=$pdo->prepare('INSERT INTO market_reports(case_code,reporter_id,subject_user_id,target_type,target_id,reason,evidence_cipher) VALUES (?,?,?,?,?,?,?)');
        $q->execute(['GI-CASE-'.strtoupper(bin2hex(random_bytes(8))),$reporter,$owner,$type,$target,$reason,$evidence===''?null:crypto_encrypt('report',$evidence)]);$id=(int)$pdo->lastInsertId();
        audit_log($pdo,$reporter,'report.created',$id,['target_type'=>$type,'target_id'=>$target]);$pdo->commit();return$id;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function governance_case_for_user(PDO $pdo,int $id,int $user): array
{
    $q=$pdo->prepare('SELECT id,case_code,reporter_id,subject_user_id,target_type,target_id,reason,status,decision_action,decision_reason,appeal_text,appeal_resolution,created_at,updated_at FROM market_reports WHERE id=? AND (reporter_id=? OR subject_user_id=?)');$q->execute([$id,$user,$user]);$row=$q->fetch();if(!$row)throw new DomainException('Fall nicht gefunden.');return$row;
}

function governance_appeal(PDO $pdo,int $user,int $id,string $text): void
{
    $text=trim($text);if(mb_strlen($text)<10||mb_strlen($text)>4000)throw new InvalidArgumentException('Bitte die Beschwerde mit 10 bis 4.000 Zeichen begründen.');
    $pdo->beginTransaction();try{
        $q=$pdo->prepare('SELECT * FROM market_reports WHERE id=? AND (reporter_id=? OR subject_user_id=?) FOR UPDATE');$q->execute([$id,$user,$user]);$case=$q->fetch();
        if(!$case||$case['status']!=='resolved'||$case['appeal_by']!==null)throw new DomainException('Für diesen Fall ist derzeit keine neue Beschwerde möglich.');
        $pdo->prepare("UPDATE market_reports SET status='appealed',appeal_text=?,appeal_by=? WHERE id=?")->execute([$text,$user,$id]);audit_log($pdo,$user,'report.appealed',$id,[]);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function governance_decide(PDO $pdo,int $actor,int $id,string $action,string $reason): void
{
    $reason=trim($reason);if(mb_strlen($reason)<10||mb_strlen($reason)>4000||!in_array($action,['assign','dismiss','hide','warn','restrict','appeal_upheld','appeal_reconsider'],true))throw new InvalidArgumentException('Entscheidung und nachvollziehbare Begründung fehlen.');
    $pdo->beginTransaction();try{
        governance_assert_actor($pdo,$actor,'reports.review');$q=$pdo->prepare('SELECT * FROM market_reports WHERE id=? FOR UPDATE');$q->execute([$id]);$case=$q->fetch();if(!$case)throw new DomainException('Fall nicht gefunden.');
        if($action==='assign'){$pdo->prepare("UPDATE market_reports SET assigned_to=?,status=IF(status='open','reviewing',status) WHERE id=?")->execute([$actor,$id]);}
        else{
            if((int)($case['assigned_to']??0)!==$actor)throw new DomainException('Der Fall muss zuerst dir zugewiesen werden.');
            if(str_starts_with($action,'appeal_')){
                if($case['status']!=='appealed')throw new DomainException('Es liegt keine offene Beschwerde vor.');
                $pdo->prepare("UPDATE market_reports SET status='resolved',appeal_resolution=? WHERE id=?")->execute([$reason,$id]);
            }else{
                if(!in_array($case['status'],['open','reviewing'],true))throw new DomainException('Der Fall wurde bereits entschieden.');
                if($action==='hide'){
                    governance_assert_actor($pdo,$actor,'content.moderate');
                    if($case['target_type']==='listing'){$q=$pdo->prepare('SELECT type,status FROM market_listings WHERE id=? FOR UPDATE');$q->execute([$case['target_id']]);$listing=$q->fetch();if(!$listing)throw new DomainException('Angebot nicht gefunden.');$status=$listing['type']==='auction'?'review':'paused';$pdo->prepare('UPDATE market_listings SET status=? WHERE id=?')->execute([$status,$case['target_id']]);}
                    elseif($case['target_type']==='rating')$pdo->prepare("UPDATE market_ratings SET status='hidden' WHERE id=?")->execute([$case['target_id']]);
                    elseif($case['target_type']==='advertisement')$pdo->prepare("UPDATE market_campaigns SET status='disabled' WHERE id=?")->execute([$case['target_id']]);
                    else throw new DomainException('Dieses Ziel kann hier nicht pauschal ausgeblendet werden.');
                }elseif($action==='restrict'){
                    governance_assert_actor($pdo,$actor,'users.manage');$q=$pdo->prepare('SELECT is_admin FROM users WHERE id=? FOR UPDATE');$q->execute([$case['subject_user_id']]);if((int)$q->fetchColumn()===1||$actor===(int)$case['subject_user_id'])throw new DomainException('Ein Administrationskonto wird hier nicht gesperrt.');
                    $pdo->prepare('UPDATE users SET is_active=0,auth_version=auth_version+1 WHERE id=?')->execute([$case['subject_user_id']]);
                }
                $pdo->prepare("UPDATE market_reports SET status='resolved',decision_action=?,decision_reason=? WHERE id=?")->execute([$action,$reason,$id]);
            }
        }
        audit_log($pdo,$actor,'report.'.$action,$id,['reason'=>$reason]);
        if($action!=='assign'&&function_exists('notify_user')){
            foreach(array_unique([(int)$case['reporter_id'],(int)$case['subject_user_id']]) as $recipient){
                notify_user($pdo,$recipient,'case:'.$id.':'.$action,
                    i18n_user_text($pdo,$recipient,'notification.report_decision.title'),
                    i18n_user_text($pdo,$recipient,'notification.report_decision.body'),'/user/reports.php?id='.$id,true);
            }
        }
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function legal_accept(PDO $pdo,int $userId,int $documentId,string $type): void
{
    if(!in_array($type,['registration','checkout','updated_terms'],true))throw new InvalidArgumentException(t('legal_public.confirm'));
    $own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
    try{
        $q=$pdo->prepare("SELECT content_hash,body_text FROM market_legal_documents WHERE id=? AND status='approved' AND effective_at<=UTC_TIMESTAMP() FOR SHARE");$q->execute([$documentId]);$document=$q->fetch();if(!$document||!hash_equals(hash('sha256',$document['body_text']),$document['content_hash']))throw new DomainException(t('consent.unavailable'));$hash=$document['content_hash'];
        $q=$pdo->prepare('SELECT content_hash FROM market_legal_acceptances WHERE user_id=? AND document_id=? AND acceptance_type=?');$q->execute([$userId,$documentId,$type]);$previous=$q->fetchColumn();
        if($previous!==false&&!hash_equals($previous,$hash))throw new DomainException(t('consent.changed'));
        $pdo->prepare('INSERT IGNORE INTO market_legal_acceptances(user_id,document_id,content_hash,acceptance_type) VALUES (?,?,?,?)')->execute([$userId,$documentId,$hash,$type]);
        if($own)$pdo->commit();
    }catch(Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function legal_create(PDO $pdo,int $actor,string $code,string $version,string $language,string $title,string $body): int
{
    governance_assert_actor($pdo,$actor,'legal.manage');
    $pdo->beginTransaction();
    try {
        $id = legal_insert_draft($pdo,$actor,$code,$version,$language,$title,$body);
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function legal_insert_draft(PDO $pdo,int $actor,string $code,string $version,string $language,string $title,string $body,bool $skipExisting=false): ?int
{
    if(!preg_match('/\A[a-z0-9-]{2,80}\z/',$code)||!preg_match('/\A[a-zA-Z0-9.-]{1,30}\z/',$version)||!preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/',$language)||mb_strlen($title)<3||mb_strlen($title)>200||mb_strlen($body)<200||strlen($body)>200000||!preg_match('//u',$body))throw new InvalidArgumentException(t('legal_admin.invalid_document'));
    $q = $pdo->prepare('INSERT INTO market_legal_documents(document_code,version,language,title,body_text,content_hash) VALUES (?,?,?,?,?,?)');
    try {
        $q->execute([$code,$version,$language,$title,$body,hash('sha256',$body)]);
    } catch (PDOException $error) {
        if ($skipExisting && (int)($error->errorInfo[1] ?? 0) === 1062) return null;
        throw $error;
    }
    $id = (int)$pdo->lastInsertId();
    audit_log($pdo,$actor,'legal.drafted',$id,['code'=>$code,'version'=>$version,'language'=>$language]);
    return $id;
}

function legal_bundled_draft_languages(): array
{
    $directory=__DIR__.'/../docs/legal/translations/';
    return array_filter(i18n_locales(),static fn(string $language):bool => $language==='de'
        || (is_file($directory.$language.'.php') && !is_link($directory.$language.'.php')),ARRAY_FILTER_USE_KEY);
}

function legal_has_required_fields(string $body): bool
{
    return preg_match('/\[(?:Pflichtfeld|REQUIRED\b)[^\]]*\]/iu',$body)===1;
}

/** Load repository-owned draft text in an available language. */
function legal_bundled_drafts(string $language): array
{
    if (!isset(legal_bundled_draft_languages()[$language])) throw new InvalidArgumentException(t('legal_admin.bundle_unavailable'));
    $directory = __DIR__.'/../docs/legal';
    $manifest = require $directory.'/manifest.php';
    $translation = $language==='de' ? null : require $directory.'/translations/'.$language.'.php';
    $translated = $translation['documents'] ?? null;
    $header = $translation['header'] ?? null;
    if ($language!=='de' && (!is_array($translated) || !is_string($header) || !legal_has_required_fields($header)
        || array_diff_key($manifest,$translated) || array_diff_key($translated,$manifest))) {
        throw new DomainException(t('legal_admin.bundle_unavailable'));
    }
    $drafts = [];
    foreach ($manifest as $code => $title) {
        if ($language==='de') {
            $path=$directory.'/'.$code.'.md';
            $body=is_file($path) && !is_link($path) ? file_get_contents($path,false,null,0,200001) : false;
        } else {
            $title=$translated[$code]['title'] ?? null;
            $provisions=$translated[$code]['provisions'] ?? null;
            if (!is_string($title) || !is_string($provisions) || !legal_has_required_fields($provisions)) {
                throw new DomainException(t('legal_admin.bundle_unavailable'));
            }
            $body='# '.$title."\n\n".trim($header)."\n\n".trim($provisions)."\n";
        }
        if (!is_string($body) || !legal_has_required_fields($body) || strlen($body) > 200000) {
            throw new DomainException(t('legal_admin.bundle_unavailable'));
        }
        $drafts[$code] = [$title,$body];
    }
    return $drafts;
}

function legal_import_bundled_drafts(PDO $pdo,int $actor,string $language='de'): array
{
    governance_assert_actor($pdo,$actor,'legal.manage');
    $languages=$language==='all' ? array_keys(legal_bundled_draft_languages()) : [$language];
    $drafts=[];
    foreach ($languages as $selected) {
        foreach (legal_bundled_drafts($selected) as $code=>[$title,$body]) {
            $drafts[$code."\0draft-1.0\0".$selected]=[$selected,$code,$title,$body];
        }
    }
    ksort($drafts,SORT_STRING);
    $created = 0;
    $pdo->beginTransaction();
    try {
        foreach ($drafts as [$selected,$code,$title,$body]) {
            if (legal_insert_draft($pdo,$actor,$code,'draft-1.0',$selected,$title,$body,true) !== null) $created++;
        }
        $pdo->commit();
        return ['created'=>$created,'existing'=>count($drafts)-$created];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** Import immutable drafts supplied by an authorized editor, preserving prior versions. */
function legal_import_draft_file(PDO $pdo,int $actor,array $upload): array
{
    governance_assert_actor($pdo,$actor,'legal.manage');
    $path=$upload['tmp_name']??null;
    $size=$upload['size']??null;
    if (($upload['error']??null)!==UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path)
        || !is_int($size) || $size<1 || $size>5*1024**2 || filesize($path)!==$size) {
        throw new InvalidArgumentException(t('legal_admin.import_invalid'));
    }
    $content=file_get_contents($path,false,null,0,$size);
    if ($content===false || strlen($content)!==$size) throw new InvalidArgumentException(t('legal_admin.import_invalid'));
    if (str_starts_with($content,"\xEF\xBB\xBF")) $content=substr($content,3);
    try { $package=json_decode($content,true,8,JSON_THROW_ON_ERROR); }
    catch (JsonException) { throw new InvalidArgumentException(t('legal_admin.import_invalid')); }
    $documents=$package['documents']??null;
    if (($package['format']??null)!=='gunindex-legal-drafts-1' || !is_array($documents) || !array_is_list($documents)
        || !$documents || count($documents)>250) {
        throw new InvalidArgumentException(t('legal_admin.import_invalid'));
    }
    $languages=i18n_locales();
    $fields=['code','version','language','title','body'];
    $drafts=[];
    foreach ($documents as $document) {
        if (!is_array($document) || count($document)!==count($fields)) throw new InvalidArgumentException(t('legal_admin.import_invalid'));
        foreach ($fields as $field) {
            if (!is_string($document[$field]??null)) throw new InvalidArgumentException(t('legal_admin.import_invalid'));
        }
        if (!isset($languages[$document['language']])) throw new InvalidArgumentException(t('legal_admin.import_invalid'));
        $key=$document['code']."\0".$document['version']."\0".$document['language'];
        if (isset($drafts[$key])) throw new InvalidArgumentException(t('legal_admin.import_duplicate_entry'));
        $drafts[$key]=$document;
    }
    ksort($drafts,SORT_STRING);
    $created=0;
    $pdo->beginTransaction();
    try {
        $previous=$pdo->prepare('SELECT version,title,body_text,content_hash FROM market_legal_documents WHERE document_code=? AND version=? AND language=? FOR SHARE');
        foreach ($drafts as $document) {
            ['code'=>$code,'version'=>$version,'language'=>$language,'title'=>$title,'body'=>$body]=$document;
            if (legal_insert_draft($pdo,$actor,$code,$version,$language,$title,$body,true)!==null) { $created++; continue; }
            $previous->execute([$code,$version,$language]);
            $existing=$previous->fetch();
            if (!$existing || $existing['version']!==$version || $existing['title']!==$title || $existing['body_text']!==$body
                || !hash_equals($existing['content_hash'],hash('sha256',$body))) {
                throw new DomainException(t('legal_admin.duplicate'));
            }
        }
        $pdo->commit();
        return ['created'=>$created,'existing'=>count($drafts)-$created];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** Pick the current approved, scheduled or newest draft version for each required language. */
function legal_language_coverage(PDO $pdo,array $codes,array $languages): array
{
    $codeSlots = implode(',',array_fill(0,count($codes),'?'));
    $languageSlots = implode(',',array_fill(0,count($languages),'?'));
    $q = $pdo->prepare("WITH ranked AS (
        SELECT id,document_code,language,version,
               CASE WHEN status='draft' THEN 'draft'
                    WHEN effective_at<=UTC_TIMESTAMP() THEN 'approved' ELSE 'scheduled' END AS review_state,
               ROW_NUMBER() OVER (PARTITION BY document_code,language ORDER BY
                   CASE WHEN status='approved' AND effective_at<=UTC_TIMESTAMP() THEN 0
                        WHEN status='approved' THEN 1 ELSE 2 END,
                   CASE WHEN effective_at<=UTC_TIMESTAMP() THEN effective_at END DESC, id DESC) AS rank_order
        FROM market_legal_documents
        WHERE document_code IN ($codeSlots) AND language IN ($languageSlots) AND status IN ('draft','approved')
    ) SELECT id,document_code,language,version,review_state FROM ranked WHERE rank_order=1");
    $q->execute(array_merge($codes,$languages));
    $coverage = [];
    foreach ($q->fetchAll() as $row) $coverage[$row['document_code']][$row['language']] = $row;
    return $coverage;
}

function legal_approve(PDO $pdo,int $actor,int $id,string $reviewer,string $effective,array $checks): void
{
    governance_assert_actor($pdo,$actor,'legal.manage');$reviewer=trim($reviewer);$date=DateTimeImmutable::createFromFormat('!Y-m-d',$effective,new DateTimeZone('UTC'));
    if(mb_strlen($reviewer)<5||mb_strlen($reviewer)>200||!$date||$date->format('Y-m-d')!==$effective||count(array_filter($checks,static fn($v)=>$v==='1'))!==5)throw new InvalidArgumentException(t('legal_admin.review_incomplete'));
    $pdo->beginTransaction();try{$q=$pdo->prepare('SELECT * FROM market_legal_documents WHERE id=? FOR UPDATE');$q->execute([$id]);$doc=$q->fetch();if(!$doc||$doc['status']!=='draft')throw new DomainException(t('legal_admin.draft_only'));if(legal_has_required_fields($doc['body_text']))throw new DomainException(t('legal_admin.placeholders'));
        if(!hash_equals($doc['content_hash'],hash('sha256',$doc['body_text'])))throw new DomainException(t('legal_admin.hash_invalid'));
        $pdo->prepare("UPDATE market_legal_documents SET status='approved',reviewer_name=?,review_checklist=?,approved_by=?,approved_at=UTC_TIMESTAMP(),effective_at=? WHERE id=?")->execute([$reviewer,json_encode(array_fill(0,5,true),JSON_THROW_ON_ERROR),$actor,$date->format('Y-m-d H:i:s'),$id]);audit_log($pdo,$actor,'legal.approved',$id,['hash'=>$doc['content_hash'],'reviewer'=>$reviewer]);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
