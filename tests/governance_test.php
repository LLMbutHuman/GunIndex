<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('DB_DATABASE')!=='gunindex_rebuild_test'){if(PHP_SAPI!=='cli')http_response_code(404);exit(2);}
require_once __DIR__.'/../config/bootstrap.php';require_once __DIR__.'/../config/governance.php';require_once __DIR__.'/../config/privacy.php';
$passed=0;
function gov_check(bool $condition):void{if(!$condition)throw new RuntimeException('Governance assertion failed.');}
function gov_reject(callable $run,string $class=DomainException::class):void{try{$run();}catch(Throwable $e){if($e instanceof $class)return;throw$e;}throw new RuntimeException('Expected rejection.');}
function gov_test(string $name,callable $run):void{global$passed;try{$run();$passed++;fwrite(STDOUT,'PASS '.$name."\n");}catch(Throwable $e){fwrite(STDERR,'FAIL '.$name.' ('.get_class($e).' at '.basename($e->getFile()).':'.$e->getLine().")\n");exit(1);}}
function gov_user(PDO $pdo,bool $admin=false,bool $mfa=false):array{
    $name='test_'.bin2hex(random_bytes(8));$password='Synthetic governance '.bin2hex(random_bytes(16));$q=$pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,is_admin,mfa_secret) VALUES (?,?,?,'Governance synthetic fixture','10115','Teststadt',?,?)");$q->execute([$name,$name.'@example.invalid',auth_hash($password),(int)$admin,$mfa?auth_secret_encrypt(random_bytes(20)):null]);return['id'=>(int)$pdo->lastInsertId(),'username'=>$name,'password'=>$password];
}
function gov_listing(PDO $pdo,int $seller):int{
    $category=(int)$pdo->query('SELECT id FROM market_categories WHERE is_active=1 AND parent_id IS NULL ORDER BY id LIMIT 1')->fetchColumn();$q=$pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city) VALUES (?,? ,?,'Synthetic ordinary object for isolated governance checks.','fixed',1000,'active','10115','Teststadt')");$q->execute([$seller,$category,'Fixture '.bin2hex(random_bytes(8)).' Governance']);return(int)$pdo->lastInsertId();
}
function gov_setting(PDO $pdo,string $key,string $value):void{$pdo->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,$value]);site_settings($pdo,true);}
$admin=gov_user($pdo,true,true);$owner=gov_user($pdo);$reporter=gov_user($pdo);$stranger=gov_user($pdo);$staff=gov_user($pdo,false,true);$unenrolled=gov_user($pdo);$listing=gov_listing($pdo,$owner['id']);$case=0;$legal=0;

gov_test('finite_staff_roles_no_implicit_superadmin',static function():void{
    foreach(governance_roles() as $role){gov_check(in_array('dashboard.read',$role['permissions'],true));gov_check(!in_array('roles.manage',$role['permissions'],true));gov_check(!in_array('*',$role['permissions'],true));}
});
gov_test('role_grant_requires_mfa_and_revokes_sessions',static function()use($pdo,$admin,$staff,$unenrolled):void{
    gov_reject(static fn()=>governance_role_change($pdo,$admin['id'],$unenrolled['id'],'moderator',true));
    governance_role_change($pdo,$admin['id'],$staff['id'],'moderator',true);gov_check(rbac_has_permission($pdo,$staff['id'],'reports.review'));gov_check(!rbac_has_permission($pdo,$staff['id'],'finance.manage'));gov_check(rbac_requires_mfa($pdo,$staff['id']));
    $q=$pdo->prepare('SELECT auth_version FROM users WHERE id=?');$q->execute([$staff['id']]);gov_check((int)$q->fetchColumn()===1);
});
gov_test('role_escalation_and_inactive_staff_fail_closed',static function()use($pdo,$admin,$staff,$owner):void{
    gov_reject(static fn()=>governance_role_change($pdo,$staff['id'],$owner['id'],'administrator',true));gov_reject(static fn()=>governance_role_change($pdo,$admin['id'],$admin['id'],'moderator',true));
    $pdo->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$staff['id']]);gov_check(!rbac_requires_mfa($pdo,$staff['id']));$pdo->prepare('UPDATE users SET is_active=1 WHERE id=?')->execute([$staff['id']]);
});
gov_test('report_creation_deduplication_encrypted_optional_evidence',static function()use($pdo,$owner,$reporter,$listing,&$case):void{
    gov_reject(static fn()=>governance_report($pdo,$owner['id'],'listing',$listing,'A sufficiently clear reason.'),InvalidArgumentException::class);
    $case=governance_report($pdo,$reporter['id'],'listing',$listing,'Synthetic check of the offered item.','Private voluntarily submitted evidence.');
    gov_check($case===governance_report($pdo,$reporter['id'],'listing',$listing,'Duplicate synthetic report.'));
    $q=$pdo->prepare('SELECT evidence_cipher FROM market_reports WHERE id=?');$q->execute([$case]);$cipher=$q->fetchColumn();gov_check($cipher!=='Private voluntarily submitted evidence.');gov_check(crypto_decrypt('report',$cipher)==='Private voluntarily submitted evidence.');
});
gov_test('case_and_private_message_access_is_object_bound',static function()use($pdo,$owner,$reporter,$stranger,$listing,&$case):void{
    gov_reject(static fn()=>governance_case_for_user($pdo,$case,$stranger['id']));gov_check((int)governance_case_for_user($pdo,$case,$owner['id'])['id']===$case);
    $q=$pdo->prepare('INSERT INTO market_messages(listing_id,sender_id,receiver_id,message_text,cipher_version) VALUES (?,?,?,?,1)');$q->execute([$listing,$owner['id'],$reporter['id'],crypto_encrypt('chat','Synthetic message evidence.')]);$message=(int)$pdo->lastInsertId();
    gov_reject(static fn()=>governance_subject($pdo,$stranger['id'],'message',$message));gov_check(governance_subject($pdo,$reporter['id'],'message',$message)===$owner['id']);
});
gov_test('moderation_requires_assignment_preserves_data_and_handles_appeal',static function()use($pdo,$admin,$reporter,$listing,&$case):void{
    gov_reject(static fn()=>governance_decide($pdo,$admin['id'],$case,'hide','Synthetic reason with sufficient detail.'));
    governance_decide($pdo,$admin['id'],$case,'assign','Synthetic assignment for review.');governance_decide($pdo,$admin['id'],$case,'hide','Synthetic content decision with clear reasoning.');
    $q=$pdo->prepare('SELECT status,title FROM market_listings WHERE id=?');$q->execute([$listing]);$row=$q->fetch();gov_check($row&&$row['status']==='paused'&&str_contains($row['title'],'Governance'));
    governance_appeal($pdo,$reporter['id'],$case,'Synthetic complaint requesting a renewed review.');governance_decide($pdo,$admin['id'],$case,'appeal_upheld','Synthetic complaint reviewed and original decision confirmed.');
    $row=governance_case_for_user($pdo,$case,$reporter['id']);gov_check($row['status']==='resolved'&&$row['appeal_resolution']!==null);gov_reject(static fn()=>governance_appeal($pdo,$reporter['id'],$case,'Another duplicate appeal should not overwrite the record.'));
});
gov_test('moderator_cannot_restrict_accounts_without_permission',static function()use($pdo,$staff,$reporter,$stranger):void{
    $target=governance_report($pdo,$reporter['id'],'user',$stranger['id'],'Synthetic account complaint for permission checks.');governance_decide($pdo,$staff['id'],$target,'assign','Synthetic moderator assignment.');
    gov_reject(static fn()=>governance_decide($pdo,$staff['id'],$target,'restrict','Synthetic attempt without account management permission.'));$q=$pdo->prepare('SELECT is_active FROM users WHERE id=?');$q->execute([$stranger['id']]);gov_check((int)$q->fetchColumn()===1);
});
gov_test('twenty_legal_drafts_present_and_unapproved',static function():void{
    $manifest=require __DIR__.'/../docs/legal/manifest.php';gov_check(count($manifest)===20);foreach($manifest as $code=>$title){$body=file_get_contents(__DIR__.'/../docs/legal/'.$code.'.md');gov_check(is_string($body)&&strlen($body)>1000&&str_contains($body,'Prüfung ausstehend')&&str_contains($body,'[Pflichtfeld:'));}
});
gov_test('legal_placeholders_block_approval',static function()use($pdo,$admin):void{
    $body=str_repeat('Synthetic legal draft with required operator details. ',8).'[Pflichtfeld: Betreiber]';$id=legal_create($pdo,$admin['id'],'test-'.bin2hex(random_bytes(8)),'1.0','de','Synthetic unfinished legal document',$body);
    gov_reject(static fn()=>legal_approve($pdo,$admin['id'],$id,'Synthetic test reviewer',gmdate('Y-m-d'),array_fill(0,5,'1')));
});
gov_test('legal_approval_hash_and_explicit_version_acceptance',static function()use($pdo,$admin,$owner,&$legal):void{
    $body=str_repeat('Synthetic isolated test document, not a real legal approval. ',8);$legal=legal_create($pdo,$admin['id'],'test-'.bin2hex(random_bytes(8)),'1.0','de','Synthetic legal fixture',$body);
    gov_reject(static fn()=>legal_accept($pdo,$owner['id'],$legal,'updated_terms'));
    legal_approve($pdo,$admin['id'],$legal,'Synthetic test reviewer - no actual legal review',gmdate('Y-m-d'),array_fill(0,5,'1'));legal_accept($pdo,$owner['id'],$legal,'updated_terms');legal_accept($pdo,$owner['id'],$legal,'updated_terms');
    $q=$pdo->prepare('SELECT COUNT(*) AS total,MIN(content_hash) AS hash FROM market_legal_acceptances WHERE user_id=? AND document_id=?');$q->execute([$owner['id'],$legal]);$row=$q->fetch();gov_check((int)$row['total']===1&&$row['hash']===hash('sha256',$body));
});
gov_test('legal_tampering_and_wrong_permissions_are_rejected',static function()use($pdo,$admin,$staff):void{
    gov_reject(static fn()=>legal_create($pdo,$staff['id'],'test-'.bin2hex(random_bytes(8)),'1.0','de','Unauthorized document',str_repeat('Synthetic content. ',30)));
    $id=legal_create($pdo,$admin['id'],'test-'.bin2hex(random_bytes(8)),'1.0','de','Synthetic tampered document',str_repeat('Synthetic original content. ',20));$pdo->prepare('UPDATE market_legal_documents SET content_hash=? WHERE id=?')->execute([str_repeat('0',64),$id]);
    gov_reject(static fn()=>legal_approve($pdo,$admin['id'],$id,'Synthetic test reviewer',gmdate('Y-m-d'),array_fill(0,5,'1')));
});
gov_test('verification_disabled_and_non_http_uploads_are_rejected',static function()use($pdo):void{
    gov_setting($pdo,'verification_enabled','0');gov_check(verification_reason($pdo)!==null);
    gov_reject(static fn()=>verification_prepare_document(['error'=>UPLOAD_ERR_OK,'tmp_name'=>__FILE__,'size'=>filesize(__FILE__)]),InvalidArgumentException::class);
});
gov_test('identity_documents_encrypted_one_use_audited_and_separate_age',static function()use($pdo,$admin,$owner):void{
    $original=site_settings($pdo);$path=null;
    try{
        foreach(['verification_enabled'=>'1','verification_review_approved'=>'1','verification_retention_days'=>'1'] as $key=>$value)gov_setting($pdo,$key,$value);
        gov_check(verification_reason($pdo)===null);
        $q=$pdo->prepare('INSERT INTO market_verification_requests(user_id,expires_at,assigned_to) VALUES (?,?,?)');$q->execute([$owner['id'],gmdate('Y-m-d H:i:s',time()+86400),$admin['id']]);$request=(int)$pdo->lastInsertId();
        $image=imagecreatetruecolor(4,4);ob_start();imagewebp($image);$plain=ob_get_clean();imagedestroy($image);$key=bin2hex(random_bytes(24));$path=verification_document_path($key);$cipher=crypto_encrypt('identity',$plain);file_put_contents($path,$cipher,LOCK_EX);chmod($path,0600);
        $pdo->prepare('INSERT INTO market_verification_documents(request_id,storage_key) VALUES (?,?)')->execute([$request,$key]);$document=(int)$pdo->lastInsertId();
        gov_check(file_get_contents($path)!==$plain);gov_reject(static fn()=>verification_decide($pdo,$admin['id'],$request,'approved','Synthetic review before actual access.',true,false));
        $token=verification_access_token($pdo,$admin['id'],$document,'Synthetic purpose-bound identity review.');gov_check(verification_open_document($pdo,$admin['id'],$token)===$plain);gov_reject(static fn()=>verification_open_document($pdo,$admin['id'],$token));
        verification_decide($pdo,$admin['id'],$request,'approved','Synthetic identity-only decision after document access.',true,false);
        $q=$pdo->prepare('SELECT identity_verified_at,age_verified_at FROM users WHERE id=?');$q->execute([$owner['id']]);$row=$q->fetch();gov_check($row['identity_verified_at']!==null&&$row['age_verified_at']===null);
        $pdo->prepare('UPDATE market_verification_requests SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id=?')->execute([$request]);privacy_purge_expired($pdo,500);gov_check(!file_exists($path));$q=$pdo->prepare('SELECT purged_at FROM market_verification_requests WHERE id=?');$q->execute([$request]);gov_check($q->fetchColumn()!==null);
    }finally{foreach(['verification_enabled','verification_review_approved','verification_retention_days'] as $key)gov_setting($pdo,$key,(string)($original[$key]??'0'));if($path&&is_file($path))unlink($path);}
});
gov_test('privacy_export_is_owner_bound_and_omits_credentials',static function()use($pdo,$owner,$stranger):void{
    gov_reject(static fn()=>privacy_export($pdo,$owner['id'],'Wrong synthetic password.'));
    $stream=privacy_export($pdo,$owner['id'],$owner['password']);$json=stream_get_contents($stream);fclose($stream);$data=json_decode($json,true,64,JSON_THROW_ON_ERROR);
    gov_check((int)$data['profile']['id']===$owner['id']);gov_check(!str_contains($json,'password_hash')&&!str_contains($json,'mfa_secret')&&!str_contains($json,$stranger['username'].'@example.invalid'));gov_check(isset($data['wanted'],$data['saved_searches'],$data['invoice_documents']));
});
gov_test('deletion_request_disables_sessions_preserves_contract_records',static function()use($pdo):void{
    $user=gov_user($pdo);$listing=gov_listing($pdo,$user['id']);$q=$pdo->prepare("INSERT INTO market_invoices(user_id,service_code,fee_version,gross_cents,status) VALUES (?,'private_fixed','synthetic',0,'draft')");$q->execute([$user['id']]);$invoice=(int)$pdo->lastInsertId();
    privacy_request_deletion($pdo,$user['id'],$user['password'],'Synthetic deletion request with retained evidence.');
    $q=$pdo->prepare('SELECT is_active,auth_version FROM users WHERE id=?');$q->execute([$user['id']]);$row=$q->fetch();gov_check((int)$row['is_active']===0&&(int)$row['auth_version']===1);
    $q=$pdo->prepare('SELECT status FROM market_listings WHERE id=?');$q->execute([$listing]);gov_check($q->fetchColumn()==='paused');$q=$pdo->prepare('SELECT id FROM market_invoices WHERE id=?');$q->execute([$invoice]);gov_check((int)$q->fetchColumn()===$invoice);
});
gov_test('anonymization_requires_retention_review_and_keeps_invoice',static function()use($pdo,$admin):void{
    $user=gov_user($pdo);$q=$pdo->prepare("INSERT INTO market_invoices(user_id,service_code,fee_version,gross_cents,status) VALUES (?,'private_fixed','synthetic',0,'draft')");$q->execute([$user['id']]);$invoice=(int)$pdo->lastInsertId();privacy_request_deletion($pdo,$user['id'],$user['password'],'Synthetic account anonymization request.');
    $q=$pdo->prepare('SELECT id FROM market_deletion_requests WHERE user_id=?');$q->execute([$user['id']]);$request=(int)$q->fetchColumn();gov_reject(static fn()=>privacy_review_deletion($pdo,$admin['id'],$request,'anonymized','Synthetic insufficient retention confirmation.',false));
    privacy_review_deletion($pdo,$admin['id'],$request,'anonymized','Synthetic retention review completed for this isolated fixture.',true);$q=$pdo->prepare('SELECT username,email,is_active FROM users WHERE id=?');$q->execute([$user['id']]);$row=$q->fetch();gov_check($row['username']!==$user['username']&&str_starts_with($row['username'],'deleted_')&&(int)$row['is_active']===0);$q=$pdo->prepare('SELECT id FROM market_invoices WHERE id=?');$q->execute([$invoice]);gov_check((int)$q->fetchColumn()===$invoice);
});
gov_test('delegated_administration_uses_site_permission_and_mfa_over_http',static function()use($pdo,$admin):void{
    $user=gov_user($pdo,false,true);governance_role_change($pdo,$admin['id'],$user['id'],'administrator',true);
    $browser=curl_init();curl_setopt_array($browser,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15]);
    $request=static function(string $path,?array $fields=null)use($browser):array{curl_setopt($browser,CURLOPT_URL,'http://127.0.0.1:8081'.$path);if($fields===null)curl_setopt($browser,CURLOPT_HTTPGET,true);else{curl_setopt($browser,CURLOPT_POST,true);curl_setopt($browser,CURLOPT_POSTFIELDS,http_build_query($fields));}$body=curl_exec($browser);gov_check(is_string($body));return[(int)curl_getinfo($browser,CURLINFO_RESPONSE_CODE),$body];};
    $token=static function(string $body):string{gov_check(preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/',$body,$m)===1);return$m[1];};
    try{
        [$status,$body]=$request('/auth/login.php');gov_check($status===200);[$status]=$request('/auth/login.php',['csrf_token'=>$token($body),'login'=>$user['username'],'password'=>$user['password']]);gov_check($status===303);
        [$status]=$request('/admin/geography.php');gov_check($status===303);
        [$status,$body]=$request('/auth/mfa.php');gov_check($status===200);$q=$pdo->prepare('SELECT mfa_secret FROM users WHERE id=?');$q->execute([$user['id']]);$code=auth_totp(auth_secret_decrypt($q->fetchColumn()),intdiv(time(),30));
        [$status]=$request('/auth/mfa.php',['csrf_token'=>$token($body),'code'=>$code]);gov_check($status===303);
        [$status]=$request('/admin/geography.php');gov_check($status===200);
        [$status]=$request('/admin/geography.php',['action'=>'country','country_code'=>'DE','is_active'=>'1']);gov_check($status===403);
        [$status]=$request('/admin/roles.php');gov_check($status===403);
    }finally{curl_close($browser);}
});
fwrite(STDOUT,'Governance checks passed: '.$passed."\n");
