<?php
declare(strict_types=1);

function privacy_verify_password(PDO $pdo,int $userId,string $password): array
{
    $q=$pdo->prepare('SELECT * FROM users WHERE id=? AND is_active=1');$q->execute([$userId]);$user=$q->fetch();
    if(!$user||strlen($password)>1024||!password_verify($password,(string)$user['password_hash']))throw new DomainException('Das aktuelle Passwort stimmt nicht.');
    return $user;
}

function privacy_private_directory(): string
{
    $directory=getenv('GENERAL_PRIVATE_DIR')?:'/var/lib/gunindex/private';
    if(!str_starts_with($directory,'/')||is_link($directory)||(!is_dir($directory)&&!mkdir($directory,0700,true)))throw new RuntimeException('Private Ablage nicht verfügbar.');
    $real=realpath($directory);$root=realpath(__DIR__.'/..');
    if(!$real||!$root||$real===$root||str_starts_with($real,$root.'/')||!is_writable($real))throw new RuntimeException('Vertrauliche Dateien benötigen eine private Ablage außerhalb des Webroots.');
    chmod($real,0700);return $real;
}

function verification_reason(PDO $pdo): ?string
{
    if(site_setting($pdo,'verification_enabled','0')!=='1')return 'Die Identitätsprüfung ist derzeit nicht aktiviert.';
    if(site_setting($pdo,'verification_review_approved','0')!=='1')return 'Prüfverfahren und Datenschutzvoraussetzungen sind noch nicht freigegeben.';
    $days=(int)site_setting($pdo,'verification_retention_days','0');if($days<1||$days>90)return 'Eine fachlich festgelegte Aufbewahrungsfrist fehlt.';
    if(!function_exists('crypto_encrypt')||!function_exists('crypto_decrypt'))return 'Die verschlüsselte Ablage ist noch nicht eingerichtet.';
    try{crypto_decrypt('identity',crypto_encrypt('identity','availability'));privacy_private_directory();}catch(Throwable){return 'Die sichere Dokumentenablage ist nicht verfügbar.';}
    return null;
}

function verification_document_path(string $key): string
{
    if(!preg_match('/\A[a-f0-9]{48}\z/',$key))throw new RuntimeException('Ungültiger Dokumentenschlüssel.');
    $dir=privacy_private_directory().'/verification';if(is_link($dir)||(!is_dir($dir)&&!mkdir($dir,0700,true)))throw new RuntimeException('Private Ablage nicht verfügbar.');
    chmod($dir,0700);return $dir.'/'.$key.'.bin';
}

/** JPEG/PNG only, recoded in memory and encrypted before persistent storage. */
function verification_prepare_document(array $file): array
{
    if(($file['error']??null)!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name'])||!is_int($file['size']??null)||$file['size']<1||$file['size']>5*1024*1024||filesize($file['tmp_name'])!==$file['size'])throw new InvalidArgumentException('Bitte ein vollständiges JPEG- oder PNG-Bild bis 5 MiB hochladen.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$info=@getimagesize($file['tmp_name']);
    if(!$info||!in_array($mime,['image/jpeg','image/png'],true)||($info['mime']??'')!==$mime||$info[0]<1||$info[1]<1||$info[0]>6000||$info[1]>6000||$info[0]*$info[1]>12000000)throw new InvalidArgumentException('Dokumentbilder dürfen höchstens 12 Megapixel und 6.000 Pixel Seitenlänge besitzen. PDFs werden nicht angenommen.');
    $image=$mime==='image/jpeg'?@imagecreatefromjpeg($file['tmp_name']):@imagecreatefrompng($file['tmp_name']);if(!$image instanceof GdImage)throw new InvalidArgumentException('Das Dokumentbild konnte nicht vollständig gelesen werden.');
    ob_start();try{if(!imagewebp($image,null,92))throw new RuntimeException('Bildneucodierung fehlgeschlagen.');$plain=ob_get_contents();}finally{ob_end_clean();imagedestroy($image);}
    if(!is_string($plain)||strlen($plain)<1||strlen($plain)>8*1024*1024)throw new RuntimeException('Das Dokumentbild ist zu groß.');
    $cipher=crypto_encrypt('identity',$plain);unset($plain);$key=bin2hex(random_bytes(24));$path=verification_document_path($key);
    $handle=fopen($path,'x+b');if($handle===false)throw new RuntimeException('Dokument konnte nicht sicher gespeichert werden.');
    try{chmod($path,0600);if(fwrite($handle,$cipher)!==strlen($cipher)||!fflush($handle))throw new RuntimeException('Dokument konnte nicht vollständig gespeichert werden.');}catch(Throwable $e){fclose($handle);@unlink($path);throw$e;}
    fclose($handle);return['key'=>$key,'path'=>$path];
}

function verification_submit(PDO $pdo,int $userId,array $files): int
{
    $reason=verification_reason($pdo);if($reason!==null)throw new DomainException($reason);
    if(!isset($files['name'],$files['tmp_name'],$files['size'],$files['error'])||!is_array($files['error'])||count($files['error'])>3)throw new InvalidArgumentException('Bitte ein bis drei Dokumentbilder auswählen.');
    $prepared=[];
    try{
        foreach($files['error'] as $i=>$error){if($error===UPLOAD_ERR_NO_FILE)continue;$prepared[]=verification_prepare_document(['error'=>$error,'tmp_name'=>$files['tmp_name'][$i]??null,'size'=>$files['size'][$i]??null]);}
        if(!$prepared)throw new InvalidArgumentException('Mindestens ein Dokumentbild fehlt.');
        $pdo->beginTransaction();$q=$pdo->prepare('SELECT id FROM users WHERE id=? AND is_active=1 FOR UPDATE');$q->execute([$userId]);if(!$q->fetchColumn())throw new DomainException('Konto nicht verfügbar.');
        $q=$pdo->prepare("SELECT id,status,assigned_to FROM market_verification_requests WHERE user_id=? AND status IN ('pending','needs_information') ORDER BY id DESC LIMIT 1 FOR UPDATE");$q->execute([$userId]);$previous=$q->fetch();
        if($previous&&$previous['status']==='pending')throw new DomainException('Es besteht bereits ein offener Prüfantrag.');
        if($previous)$pdo->prepare("UPDATE market_verification_requests SET status='superseded' WHERE id=?")->execute([$previous['id']]);
        $days=(int)site_setting($pdo,'verification_retention_days','0');$expires=gmdate('Y-m-d H:i:s',time()+$days*86400);
        $q=$pdo->prepare('INSERT INTO market_verification_requests(user_id,expires_at,assigned_to) VALUES (?,?,?)');$q->execute([$userId,$expires,$previous['assigned_to']??null]);$id=(int)$pdo->lastInsertId();
        $q=$pdo->prepare('INSERT INTO market_verification_documents(request_id,storage_key) VALUES (?,?)');foreach($prepared as $doc)$q->execute([$id,$doc['key']]);
        audit_log($pdo,$userId,'identity.submitted',$id,['documents'=>count($prepared),'retention_days'=>$days,'supersedes'=>$previous?(int)$previous['id']:null]);$pdo->commit();return$id;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();foreach($prepared as $doc)if(is_file($doc['path'])&&!is_link($doc['path']))@unlink($doc['path']);throw$e;}
}

function verification_access_token(PDO $pdo,int $actor,int $documentId,string $reason): string
{
    governance_assert_actor($pdo,$actor,'identity.review');$unavailable=verification_reason($pdo);if($unavailable!==null)throw new DomainException($unavailable);$reason=trim($reason);if(mb_strlen($reason)<10||mb_strlen($reason)>1000)throw new InvalidArgumentException('Bitte einen zweckgebundenen Zugriffsgrund angeben.');
    $pdo->beginTransaction();try{
        $q=$pdo->prepare('SELECT r.*,d.deleted_at FROM market_verification_documents d JOIN market_verification_requests r ON r.id=d.request_id WHERE d.id=? FOR UPDATE');$q->execute([$documentId]);$row=$q->fetch();
        if(!$row||$row['deleted_at']!==null||strtotime($row['expires_at'].' UTC')<=time()||$row['purged_at']!==null||(int)$row['user_id']===$actor)throw new DomainException('Das Dokument steht für diese Prüfung nicht zur Verfügung.');
        if($row['assigned_to']!==null&&(int)$row['assigned_to']!==$actor)throw new DomainException('Dieser Antrag ist einer anderen Prüfstelle zugewiesen.');
        $pdo->prepare('UPDATE market_verification_requests SET assigned_to=? WHERE id=?')->execute([$actor,$row['id']]);
        $token=bin2hex(random_bytes(32));$pdo->prepare('INSERT INTO market_document_access(token_hash,document_id,actor_id,reason,expires_at) VALUES (?,?,?,?,?)')->execute([hash('sha256',$token),$documentId,$actor,$reason,gmdate('Y-m-d H:i:s',time()+120)]);
        audit_log($pdo,$actor,'identity.access_requested',(int)$row['id'],['document_id'=>$documentId,'reason'=>$reason]);$pdo->commit();return$token;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function verification_open_document(PDO $pdo,int $actor,string $token): string
{
    governance_assert_actor($pdo,$actor,'identity.review');$unavailable=verification_reason($pdo);if($unavailable!==null)throw new DomainException($unavailable);if(!preg_match('/\A[a-f0-9]{64}\z/',$token))throw new DomainException('Zugriffstoken ungültig.');
    $pdo->beginTransaction();try{
        $q=$pdo->prepare('SELECT a.*,d.storage_key,d.deleted_at,r.id AS request_id,r.assigned_to,r.expires_at AS retention_end,r.purged_at FROM market_document_access a JOIN market_verification_documents d ON d.id=a.document_id JOIN market_verification_requests r ON r.id=d.request_id WHERE a.token_hash=? AND a.actor_id=? FOR UPDATE');$q->execute([hash('sha256',$token),$actor]);$row=$q->fetch();
        if(!$row||$row['used_at']!==null||strtotime($row['expires_at'].' UTC')<=time()||strtotime($row['retention_end'].' UTC')<=time()||$row['deleted_at']!==null||$row['purged_at']!==null||(int)$row['assigned_to']!==$actor)throw new DomainException('Der Dokumentenzugriff ist abgelaufen oder nicht berechtigt.');
        $path=verification_document_path($row['storage_key']);if(!is_file($path)||is_link($path)||filesize($path)>12*1024*1024)throw new RuntimeException('Dokument nicht verfügbar.');
        $cipher=file_get_contents($path);if(!is_string($cipher))throw new RuntimeException('Dokument nicht verfügbar.');$plain=crypto_decrypt('identity',$cipher);
        $pdo->prepare('UPDATE market_document_access SET used_at=UTC_TIMESTAMP() WHERE token_hash=?')->execute([hash('sha256',$token)]);
        audit_log($pdo,$actor,'identity.document_viewed',(int)$row['request_id'],['document_id'=>(int)$row['document_id'],'reason'=>$row['reason']]);$pdo->commit();return$plain;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function verification_decide(PDO $pdo,int $actor,int $id,string $decision,string $reason,bool $identity,bool $age): void
{
    governance_assert_actor($pdo,$actor,'identity.review');$unavailable=verification_reason($pdo);if($unavailable!==null)throw new DomainException($unavailable);$reason=trim($reason);if(!in_array($decision,['approved','rejected','needs_information'],true)||mb_strlen($reason)<10||mb_strlen($reason)>2000)throw new InvalidArgumentException('Bitte Entscheidung und begründete Prüffeststellung angeben.');
    if($decision==='approved'&&!$identity&&!$age)throw new InvalidArgumentException('Identität und Altersfeststellung müssen getrennt ausdrücklich bestätigt werden.');
    $pdo->beginTransaction();try{
        $q=$pdo->prepare('SELECT * FROM market_verification_requests WHERE id=? FOR UPDATE');$q->execute([$id]);$row=$q->fetch();
        if(!$row||(int)$row['assigned_to']!==$actor||(int)$row['user_id']===$actor||!in_array($row['status'],['pending','needs_information'],true)||strtotime($row['expires_at'].' UTC')<=time())throw new DomainException('Der Antrag ist nicht für diese Entscheidung verfügbar.');
        $q=$pdo->prepare('SELECT COUNT(*) FROM market_document_access a JOIN market_verification_documents d ON d.id=a.document_id WHERE d.request_id=? AND a.actor_id=? AND a.used_at>UTC_TIMESTAMP()-INTERVAL 15 MINUTE');$q->execute([$id,$actor]);if((int)$q->fetchColumn()===0)throw new DomainException('Vor einer Entscheidung ist eine dokumentierte, aktuelle Einsicht erforderlich.');
        $pdo->prepare('UPDATE market_verification_requests SET status=?,identity_confirmed=?,age_confirmed=?,decision_reason=?,reviewed_at=UTC_TIMESTAMP() WHERE id=?')->execute([$decision,(int)($decision==='approved'&&$identity),(int)($decision==='approved'&&$age),$reason,$id]);
        if($decision==='approved'){$pdo->prepare('UPDATE users SET is_verified=IF(?=1,1,is_verified),identity_verified_at=IF(?=1,UTC_TIMESTAMP(),identity_verified_at),age_verified_at=IF(?=1,UTC_TIMESTAMP(),age_verified_at) WHERE id=?')->execute([(int)$identity,(int)$identity,(int)$age,$row['user_id']]);}
        audit_log($pdo,$actor,'identity.'.$decision,$id,['identity'=>$identity,'age'=>$age]);$pdo->commit();
        if(function_exists('notify_user'))notify_user($pdo,(int)$row['user_id'],'identity:'.$id.':'.$decision,'Prüfung aktualisiert','Eine Entscheidung zu deiner Identitäts- oder Altersprüfung liegt vor.','/user/verification.php',true);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function privacy_request_deletion(PDO $pdo,int $userId,string $password,string $reason): void
{
    $reason=trim($reason);if(mb_strlen($reason)>2000)throw new InvalidArgumentException('Die Erläuterung ist zu lang.');
    $pdo->beginTransaction();try{
        $q=$pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');$q->execute([$userId]);$user=$q->fetch();
        if(!$user||!password_verify($password,(string)$user['password_hash'])||(int)$user['is_active']!==1||(int)$user['is_admin']===1)throw new DomainException('Das Konto kann mit diesen Angaben nicht deaktiviert werden. Initiale Administrationskonten benötigen ein getrenntes Übergabeverfahren.');
        $pdo->prepare('INSERT INTO market_deletion_requests(user_id,request_reason) VALUES (?,?)')->execute([$userId,$reason]);
        $pdo->prepare('UPDATE users SET is_active=0,auth_version=auth_version+1 WHERE id=?')->execute([$userId]);
        $pdo->prepare("UPDATE market_mail_queue SET status='cancelled',payload_ciphertext='',processing_token=NULL,locked_until=NULL WHERE user_id=? AND status IN ('pending','failed','processing','retry')")->execute([$userId]);
        $pdo->prepare('UPDATE market_notification_preferences SET inapp_enabled=0,mail_enabled=0 WHERE user_id=?')->execute([$userId]);
        $pdo->prepare("UPDATE market_wanted SET status='paused' WHERE user_id=? AND status='active'")->execute([$userId]);
        $pdo->prepare("UPDATE market_saved_searches SET status='paused' WHERE user_id=? AND status='active'")->execute([$userId]);
        $pdo->prepare("UPDATE market_listings l SET status=IF(type='auction' AND EXISTS(SELECT 1 FROM market_proxy_bids p WHERE p.listing_id=l.id),'review','paused') WHERE seller_id=? AND status='active'")->execute([$userId]);
        audit_log($pdo,$userId,'privacy.deletion_requested',$userId,[]);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function privacy_review_deletion(PDO $pdo,int $actor,int $requestId,string $decision,string $reason,bool $retentionConfirmed): void
{
    governance_assert_actor($pdo,$actor,'privacy.review');$reason=trim($reason);if(!in_array($decision,['waiting_retention','anonymized'],true)||mb_strlen($reason)<20||mb_strlen($reason)>4000)throw new InvalidArgumentException('Bitte eine dokumentierte Aufbewahrungs-/Löschentscheidung angeben.');
    if($decision==='anonymized'&&!$retentionConfirmed)throw new DomainException('Offene Verträge, gesetzliche Aufbewahrung und betroffene Datensysteme müssen vorab geprüft werden.');
    $pdo->beginTransaction();try{
        $q=$pdo->prepare('SELECT r.*,u.is_admin FROM market_deletion_requests r JOIN users u ON u.id=r.user_id WHERE r.id=? FOR UPDATE');$q->execute([$requestId]);$row=$q->fetch();if(!$row||$row['status']==='anonymized'||(int)$row['is_admin']===1)throw new DomainException('Der Antrag ist nicht für diese Änderung verfügbar.');
        if($decision==='anonymized'){
            $q=$pdo->prepare("SELECT COUNT(*) FROM market_listings WHERE seller_id=? AND status IN ('active','review') AND type='auction'");$q->execute([$row['user_id']]);if((int)$q->fetchColumn()>0)throw new DomainException('Offene oder zu prüfende Auktionen müssen zuerst fachlich bearbeitet werden.');
            $suffix=bin2hex(random_bytes(8));$pdo->prepare("UPDATE users SET username=?,email=?,full_name='',zip='',city='',password_hash=?,mfa_secret=NULL,is_active=0,is_verified=0,auth_version=auth_version+1 WHERE id=?")->execute(['deleted_'.$row['user_id'].'_'.$suffix,'deleted_'.$suffix.'@example.invalid',password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),$row['user_id']]);
            $pdo->prepare('DELETE FROM market_role_grants WHERE user_id=?')->execute([$row['user_id']]);
            foreach(['market_discovery_matches','market_discovery_digests','market_wanted','market_saved_searches','market_watchlist','market_notifications','market_notification_preferences','market_mail_queue','market_auth_tokens','market_billing_profiles','market_dealer_profiles'] as $table)$pdo->prepare('DELETE FROM '.$table.' WHERE user_id=?')->execute([$row['user_id']]);
            $pdo->prepare("UPDATE market_campaigns SET status='disabled' WHERE user_id=?")->execute([$row['user_id']]);
        }
        $pdo->prepare('UPDATE market_deletion_requests SET status=?,review_reason=?,reviewed_by=?,reviewed_at=UTC_TIMESTAMP() WHERE id=?')->execute([$decision,$reason,$actor,$requestId]);audit_log($pdo,$actor,'privacy.'.$decision,(int)$row['user_id'],['request_id'=>$requestId,'reason'=>$reason,'retained_records_reviewed'=>$retentionConfirmed]);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

/** Build the entire owner-only JSON before sending headers, using a 0600 temporary stream. */
function privacy_export(PDO $pdo,int $userId,string $password)
{
    $user=privacy_verify_password($pdo,$userId,$password);$stream=tmpfile();if($stream===false)throw new RuntimeException('Exportablage nicht verfügbar.');
    $write=static function(string $value)use($stream):void{if(fwrite($stream,$value)!==strlen($value)||ftell($stream)>128*1024*1024)throw new RuntimeException('Der Export überschreitet die sichere Einzeldateigrenze. Bitte den Datenschutzkontakt für einen vollständigen betreuten Export nutzen.');};
    try{
        $profile=array_intersect_key($user,array_flip(['id','username','email','full_name','zip','city','country_code','preferred_locale','created_at','email_verified_at','identity_verified_at','age_verified_at']));
        $write('{"generated_at":'.json_encode(gmdate('c'),JSON_THROW_ON_ERROR).',"profile":'.json_encode($profile,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        $sources=[
            'listings'=>['market_listings','seller_id=?',[$userId],['id','title','description','type','price_cents','current_bid_cents','status','zip','city','country_code','created_at','ends_at']],
            'bids'=>['market_bids','user_id=?',[$userId],['id','listing_id','amount_cents','event_type','created_at']],
            'private_maximum_bids'=>['market_proxy_bids','user_id=?',[$userId],['id','listing_id','max_cents','created_at','updated_at']],
            'messages'=>['market_messages','(sender_id=? OR receiver_id=?)',[$userId,$userId],['id','listing_id','sender_id','receiver_id','message_text','is_read','created_at']],
            'ratings'=>['market_ratings','(sender_id=? OR receiver_id=?)',[$userId,$userId],['id','listing_id','sender_id','receiver_id','stars','comment','status','created_at']],
            'invoices'=>['market_invoices','user_id=?',[$userId],['id','invoice_number','service_code','fee_version','gross_cents','currency','status','created_at']],
            'legal_acceptances'=>['market_legal_acceptances','user_id=?',[$userId],['id','document_id','content_hash','acceptance_type','created_at']],
        ];
        $sources += [
            'blocked_users'=>['market_user_blocks','user_id=?',[$userId],['blocked_id','created_at'],'blocked_id'],
            'watchlist'=>['market_watchlist','user_id=?',[$userId],['listing_id','created_at'],'listing_id'],
            'wanted'=>['market_wanted','user_id=?',[$userId],['id','category_id','title','description','keywords','manufacturer','model','min_cents','max_cents','condition_label','country_code','scope','zip','radius_km','notify_mode','status','created_at','updated_at']],
            'saved_searches'=>['market_saved_searches','user_id=?',[$userId],['id','name','criteria_json','notify_mode','status','created_at','updated_at']],
            'discovery_matches'=>['market_discovery_matches','user_id=?',[$userId],['id','query_kind','query_id','listing_id','score','reasons_json','notification_state','matched_at']],
            'notifications'=>['market_notifications','user_id=?',[$userId],['id','title','body','target_url','is_visible','read_at','created_at']],
            'notification_preferences'=>['market_notification_preferences','user_id=?',[$userId],['inapp_enabled','mail_enabled','updated_at'],'user_id'],
            'dealer_profile'=>['market_dealer_profiles','user_id=?',[$userId],['company_name','address_text','country_code','contact_email','website','legal_notice','status','moderation_reason','created_at','updated_at'],'user_id'],
            'campaigns'=>['market_campaigns','user_id=?',[$userId],['id','country_code','category_id','title','target_url','status','moderation_reason','created_at','updated_at']],
            'premium_bookings'=>['market_premium_bookings','user_id=?',[$userId],['id','invoice_id','listing_id','campaign_id','country_code','scope','category_id','status','reserved_until','starts_at','ends_at','immediate_start_at','created_at']],
            'manual_orders'=>['market_manual_orders','user_id=?',[$userId],['invoice_id','listing_id','reference','recipient','quantity','net_cents','tax_cents','tax_bps','terms_hash','terms_text','customer_claimed_at','created_at'],'invoice_id'],
            'bump_credits'=>['market_bump_credits','user_id=?',[$userId],['id','invoice_id','quantity','remaining','created_at']],
            'bump_history'=>['market_bump_events','user_id=?',[$userId],['id','credit_id','listing_id','created_at']],
            'cases'=>['market_reports','(reporter_id=? OR subject_user_id=?)',[$userId,$userId],['id','case_code','target_type','target_id','reason','status','decision_action','decision_reason','appeal_text','appeal_resolution','submitted_evidence','created_at','updated_at']],
            'verification_requests'=>['market_verification_requests','user_id=?',[$userId],['id','status','identity_confirmed','age_confirmed','decision_reason','created_at','reviewed_at','expires_at','purged_at']],
            'deletion_requests'=>['market_deletion_requests','user_id=?',[$userId],['id','status','request_reason','review_reason','created_at','reviewed_at']],
            'billing_profile'=>['market_billing_profiles','user_id=?',[$userId],['legal_name','address_line1','address_line2','postal_code','city','country_code','vat_id','updated_at'],'user_id'],
            'refund_requests'=>['market_refund_requests','user_id=?',[$userId],['id','invoice_id','requested_cents','status','reason','decision_reason','created_at','updated_at']],
            'refunds'=>['market_refunds','invoice_id IN (SELECT id FROM market_invoices WHERE user_id=?)',[$userId],['id','invoice_id','request_id','transaction_id','amount_cents','currency','reason','credit_document_id','created_at']],
            'invoice_snapshots'=>['market_invoice_snapshots','user_id=?',[$userId],['invoice_id','payload_hash','payload','created_at'],'invoice_id'],
            'invoice_documents'=>['market_invoice_documents','invoice_id IN (SELECT id FROM market_invoices WHERE user_id=?)',[$userId],['id','invoice_id','document_number','document_type','related_document_id','amount_cents','currency','content_hash','payload','issued_at']],
        ];
        foreach($sources as $name=>$source){
            [$table,$where,$values,$allowed]=$source;$cursor=$source[4]??'id';

            $write(','.json_encode($name,JSON_THROW_ON_ERROR).':[');$first=true;$last=0;
            do{$q=$pdo->prepare('SELECT * FROM '.$table.' WHERE '.$cursor.'>? AND '.$where.' ORDER BY '.$cursor.' LIMIT 250');$q->execute(array_merge([$last],$values));$rows=$q->fetchAll();foreach($rows as $row){$last=(int)$row[$cursor];if($name==='cases'&&(int)$row['reporter_id']===$userId&&!empty($row['evidence_cipher']))$row['submitted_evidence']=crypto_decrypt('report',$row['evidence_cipher']);if(in_array($name,['invoice_snapshots','invoice_documents'],true)){$plain=crypto_decrypt('finance',$row['payload_ciphertext']);$hash=$name==='invoice_snapshots'?$row['payload_hash']:$row['content_hash'];if(!hash_equals($hash,hash('sha256',$plain)))throw new RuntimeException('Der gespeicherte Rechnungsnachweis konnte nicht verifiziert werden.');$row['payload']=json_decode($plain,true,64,JSON_THROW_ON_ERROR);}if($name==='messages'){$cipherVersion=(int)($row['cipher_version']??0);$row['message_text']=function_exists('chat_message_plain')?chat_message_plain($row['message_text'],$cipherVersion):($cipherVersion===0?$row['message_text']:crypto_decrypt('chat',$row['message_text']));}$row=array_intersect_key($row,array_flip($allowed));$write(($first?'':',').json_encode($row,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));$first=false;}}while(count($rows)===250);
            $write(']');
        }
        $write(',"verification_documents":[');$q=$pdo->prepare('SELECT d.id,d.storage_key,r.id AS request_id FROM market_verification_documents d JOIN market_verification_requests r ON r.id=d.request_id WHERE r.user_id=? AND d.deleted_at IS NULL AND r.expires_at>UTC_TIMESTAMP() ORDER BY d.id');$q->execute([$userId]);$first=true;
        while($doc=$q->fetch()){$path=verification_document_path($doc['storage_key']);if(!is_file($path)||is_link($path)||filesize($path)>12*1024*1024)throw new RuntimeException('Ein eigenes Dokument konnte nicht in den Export aufgenommen werden.');$plain=crypto_decrypt('identity',file_get_contents($path));$write(($first?'':',').json_encode(['id'=>(int)$doc['id'],'request_id'=>(int)$doc['request_id'],'mime'=>'image/webp','base64'=>base64_encode($plain)],JSON_THROW_ON_ERROR));$first=false;}
        $write(']}');rewind($stream);audit_log($pdo,$userId,'privacy.exported',$userId,[]);return$stream;
    }catch(Throwable $e){fclose($stream);throw$e;}
}

function privacy_purge_expired(PDO $pdo,int $limit=100): int
{
    $limit=max(1,min(500,$limit));$ids=$pdo->query("SELECT id FROM market_verification_requests WHERE expires_at<=UTC_TIMESTAMP() AND purged_at IS NULL ORDER BY id LIMIT ".$limit)->fetchAll(PDO::FETCH_COLUMN);$purged=0;
    foreach($ids as $id){$pdo->beginTransaction();try{
        $q=$pdo->prepare('SELECT * FROM market_verification_requests WHERE id=? FOR UPDATE');$q->execute([$id]);$request=$q->fetch();if(!$request||$request['purged_at']!==null||strtotime($request['expires_at'].' UTC')>time()){$pdo->commit();continue;}
        $q=$pdo->prepare('SELECT * FROM market_verification_documents WHERE request_id=? AND deleted_at IS NULL FOR UPDATE');$q->execute([$id]);foreach($q->fetchAll() as $doc){$path=verification_document_path($doc['storage_key']);if(is_link($path))throw new RuntimeException('Unsicherer Dokumentenpfad.');if(is_file($path)&&!unlink($path))throw new RuntimeException('Dokument konnte nicht entfernt werden.');$pdo->prepare('UPDATE market_verification_documents SET deleted_at=UTC_TIMESTAMP() WHERE id=?')->execute([$doc['id']]);}
        $pdo->prepare("UPDATE market_verification_requests SET purged_at=UTC_TIMESTAMP(),status=IF(status IN ('pending','needs_information'),'expired',status) WHERE id=?")->execute([$id]);$pdo->prepare("INSERT INTO admin_logs(admin_id,action_type,target_id,details) VALUES (NULL,'identity.purged',?,'{}')")->execute([$id]);$pdo->commit();$purged++;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}}
    return$purged;
}
