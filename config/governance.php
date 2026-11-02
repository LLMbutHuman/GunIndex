<?php
declare(strict_types=1);

function governance_roles(): array
{
    return [
        'administrator'=>['label'=>'Administration','permissions'=>['dashboard.read','site.manage','users.manage','categories.manage','reports.read','reports.review','content.moderate','privacy.review','identity.review','legal.manage','finance.manage','campaigns.manage']],
        'moderator'=>['label'=>'Moderation','permissions'=>['dashboard.read','reports.read','reports.review','content.moderate']],
        'support'=>['label'=>'Support','permissions'=>['dashboard.read','reports.read','privacy.review']],
        'verification'=>['label'=>'Identitätsprüfung','permissions'=>['dashboard.read','identity.review']],
        'finance'=>['label'=>'Finanzen','permissions'=>['dashboard.read','finance.manage']],
    ];
}

function rbac_permissions(PDO $pdo,int $userId): array
{
    $q=$pdo->prepare('SELECT is_admin,is_active FROM users WHERE id=?');$q->execute([$userId]);$user=$q->fetch();
    if(!$user||(int)$user['is_active']!==1)return [];
    if((int)$user['is_admin']===1)return ['*'];
    try{$q=$pdo->prepare('SELECT role_code FROM market_role_grants WHERE user_id=?');$q->execute([$userId]);$roles=$q->fetchAll(PDO::FETCH_COLUMN);}
    catch(PDOException $e){if($e->getCode()==='42S02')return [];throw $e;}
    $permissions=[];foreach($roles as $role)foreach(governance_roles()[$role]['permissions']??[] as $permission)$permissions[$permission]=true;
    return array_keys($permissions);
}

function rbac_has_permission(PDO $pdo,int $userId,string $permission): bool
{
    $allowed=rbac_permissions($pdo,$userId);return in_array('*',$allowed,true)||in_array($permission,$allowed,true);
}

function permission_user_can(PDO $pdo,int $userId,string $permission): bool { return rbac_has_permission($pdo,$userId,$permission); }

function rbac_requires_mfa(PDO $pdo,int $userId): bool { return rbac_permissions($pdo,$userId)!==[]; }

function require_permission(PDO $pdo,string $permission): array
{
    $user=require_login($pdo);
    if(!rbac_has_permission($pdo,(int)$user['id'],$permission))abort_request(403,'Für diesen Bereich fehlt die erforderliche Berechtigung.');
    if(empty($user['mfa_secret'])||($_SESSION['admin_mfa_user_id']??null)!==(int)$user['id'])abort_request(403,'Dieser Bereich erfordert eine Anmeldung mit zweitem Faktor.');
    return $user;
}

function require_staff(PDO $pdo): array
{
    $user=require_login($pdo);
    if(!rbac_has_permission($pdo,(int)$user['id'],'dashboard.read'))abort_request(403,'Dieser Bereich ist für berechtigtes Personal vorgesehen.');
    if(empty($user['mfa_secret'])||($_SESSION['admin_mfa_user_id']??null)!==(int)$user['id'])abort_request(403,'Dieser Bereich erfordert eine Anmeldung mit zweitem Faktor.');
    return $user;
}

function governance_assert_actor(PDO $pdo,int $actor,string $permission): void
{
    if(!rbac_has_permission($pdo,$actor,$permission))throw new DomainException('Die erforderliche Berechtigung fehlt.');
    if(PHP_SAPI!=='cli'&&((int)($_SESSION['user_id']??0)!==$actor||($_SESSION['admin_mfa_user_id']??null)!==$actor))throw new DomainException('Eine aktuelle Mehrfaktoranmeldung ist erforderlich.');
}

function governance_role_change(PDO $pdo,int $actor,int $target,string $role,bool $grant): void
{
    if(!isset(governance_roles()[$role]))throw new InvalidArgumentException('Unbekannte Rolle.');
    $pdo->beginTransaction();
    try{
        governance_assert_actor($pdo,$actor,'roles.manage');
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
    elseif($type==='advertisement'){$q=$pdo->prepare("SELECT user_id FROM market_campaigns WHERE id=? AND status='approved'");$q->execute([$id]);$owner=(int)$q->fetchColumn();}
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
        audit_log($pdo,$actor,'report.'.$action,$id,['reason'=>$reason]);$pdo->commit();
        if($action!=='assign'&&function_exists('notify_user'))foreach(array_unique([(int)$case['reporter_id'],(int)$case['subject_user_id']]) as $recipient)notify_user($pdo,$recipient,'case:'.$id.':'.$action,'Entscheidung zu deinem Fall','Eine begründete Entscheidung oder Beschwerdeantwort ist in deinem Fall verfügbar.','/user/reports.php?id='.$id,true);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function legal_accept(PDO $pdo,int $userId,int $documentId,string $type): void
{
    if(!in_array($type,['registration','checkout','updated_terms'],true))throw new InvalidArgumentException('Ungültige Zustimmungsart.');
    $own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
    try{
        $q=$pdo->prepare("SELECT content_hash,body_text FROM market_legal_documents WHERE id=? AND status='approved' AND effective_at<=UTC_TIMESTAMP() FOR SHARE");$q->execute([$documentId]);$document=$q->fetch();if(!$document||!hash_equals(hash('sha256',$document['body_text']),$document['content_hash']))throw new DomainException('Dieses Dokument ist nicht zur Zustimmung freigegeben.');$hash=$document['content_hash'];
        $q=$pdo->prepare('SELECT content_hash FROM market_legal_acceptances WHERE user_id=? AND document_id=? AND acceptance_type=?');$q->execute([$userId,$documentId,$type]);$previous=$q->fetchColumn();
        if($previous!==false&&!hash_equals($previous,$hash))throw new DomainException('Die gespeicherte Zustimmung stimmt nicht mit dieser Fassung überein.');
        $pdo->prepare('INSERT IGNORE INTO market_legal_acceptances(user_id,document_id,content_hash,acceptance_type) VALUES (?,?,?,?)')->execute([$userId,$documentId,$hash,$type]);
        if($own)$pdo->commit();
    }catch(Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function legal_create(PDO $pdo,int $actor,string $code,string $version,string $language,string $title,string $body): int
{
    governance_assert_actor($pdo,$actor,'legal.manage');
    if(!preg_match('/\A[a-z0-9-]{2,80}\z/',$code)||!preg_match('/\A[a-zA-Z0-9.-]{1,30}\z/',$version)||!preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/',$language)||mb_strlen($title)<3||mb_strlen($title)>200||mb_strlen($body)<200||strlen($body)>200000||!preg_match('//u',$body))throw new InvalidArgumentException('Bitte Kennung, Version, Sprache, Titel und einen vollständigen Entwurf angeben.');
    $pdo->beginTransaction();try{$q=$pdo->prepare('INSERT INTO market_legal_documents(document_code,version,language,title,body_text,content_hash) VALUES (?,?,?,?,?,?)');$q->execute([$code,$version,$language,$title,$body,hash('sha256',$body)]);$id=(int)$pdo->lastInsertId();audit_log($pdo,$actor,'legal.drafted',$id,['code'=>$code,'version'=>$version,'language'=>$language]);$pdo->commit();return$id;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function legal_approve(PDO $pdo,int $actor,int $id,string $reviewer,string $effective,array $checks): void
{
    governance_assert_actor($pdo,$actor,'legal.manage');$reviewer=trim($reviewer);$date=DateTimeImmutable::createFromFormat('!Y-m-d',$effective,new DateTimeZone('UTC'));
    if(mb_strlen($reviewer)<5||mb_strlen($reviewer)>200||!$date||$date->format('Y-m-d')!==$effective||count(array_filter($checks,static fn($v)=>$v==='1'))!==5)throw new InvalidArgumentException('Verantwortliche fachliche Prüfstelle, Gültigkeitsdatum und alle fünf Prüfpunkte sind erforderlich.');
    $pdo->beginTransaction();try{$q=$pdo->prepare('SELECT * FROM market_legal_documents WHERE id=? FOR UPDATE');$q->execute([$id]);$doc=$q->fetch();if(!$doc||$doc['status']!=='draft')throw new DomainException('Nur neue Entwürfe können freigegeben werden.');if(preg_match('/\[Pflichtfeld[^\]]*\]/iu',$doc['body_text']))throw new DomainException('Nicht ausgefüllte Pflichtfelder verhindern die Freigabe.');
        if(!hash_equals($doc['content_hash'],hash('sha256',$doc['body_text'])))throw new DomainException('Der Dokumentenhash stimmt nicht überein.');
        $pdo->prepare("UPDATE market_legal_documents SET status='approved',reviewer_name=?,review_checklist=?,approved_by=?,approved_at=UTC_TIMESTAMP(),effective_at=? WHERE id=?")->execute([$reviewer,json_encode(array_fill(0,5,true),JSON_THROW_ON_ERROR),$actor,$date->format('Y-m-d H:i:s'),$id]);audit_log($pdo,$actor,'legal.approved',$id,['hash'=>$doc['content_hash'],'reviewer'=>$reviewer]);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
