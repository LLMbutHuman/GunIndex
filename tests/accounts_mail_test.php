<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('DB_DATABASE')!=='gunindex_rebuild_test'){if(PHP_SAPI!=='cli')http_response_code(404);exit(2);}
require_once __DIR__.'/../config/bootstrap.php';

$originalEnvironment=[];foreach(['APP_URL','MAIL_TRANSPORT','MAIL_FROM','MAIL_SENDMAIL_PATH','APP_ENV','DB_DATABASE'] as $key)$originalEnvironment[$key]=getenv($key);
putenv('APP_URL=http://127.0.0.1:8081');putenv('MAIL_TRANSPORT=test');putenv('APP_ENV=development');
$passed=0;
function am_assert(bool $condition):void{if(!$condition)throw new RuntimeException('Account/mail assertion failed.');}
function am_reject(callable $run):void{try{$run();}catch(Throwable){return;}throw new RuntimeException('Expected rejection.');}
function am_user(PDO $pdo,bool $verified=false):array{
    $name='test_'.bin2hex(random_bytes(8));$password='Synthetic mailbox '.bin2hex(random_bytes(16));
    $q=$pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,email_verified_at) VALUES (?,?,?,'Synthetic mail fixture','10115','Teststadt',?)");
    $q->execute([$name,$name.'@example.invalid',auth_hash($password),$verified?gmdate('Y-m-d H:i:s'):null]);
    return ['id'=>(int)$pdo->lastInsertId(),'email'=>$name.'@example.invalid','password'=>$password];
}
function am_token(PDO $pdo,array $user,string $purpose):string{
    account_token_request($pdo,$user['email'],$purpose);
    $q=$pdo->prepare('SELECT token_hash FROM market_auth_tokens WHERE user_id=? AND purpose=? AND used_at IS NULL ORDER BY id DESC LIMIT 1');$q->execute([$user['id'],$purpose]);$hash=$q->fetchColumn();am_assert(is_string($hash));
    $q=$pdo->prepare('SELECT payload_ciphertext FROM market_mail_queue WHERE dedupe_key=?');$q->execute(['account:'.$purpose.':'.$hash]);$cipher=$q->fetchColumn();am_assert(is_string($cipher)&&$cipher!=='');
    $payload=json_decode(crypto_decrypt('mail',$cipher),true,8,JSON_THROW_ON_ERROR);am_assert((bool)preg_match('/[?&]token=([a-f0-9]{64})/',$payload['body'],$match));
    am_assert(hash_equals($hash,hash('sha256',$match[1]))&&!str_contains($cipher,$match[1]));return $match[1];
}
function am_mail_link(PDO $pdo,string $token,string $purpose,string $locale):string{
    $q=$pdo->prepare('SELECT payload_ciphertext FROM market_mail_queue WHERE dedupe_key=?');$q->execute(['account:'.$purpose.':'.hash('sha256',$token)]);
    $payload=json_decode(crypto_decrypt('mail',$q->fetchColumn()),true,8,JSON_THROW_ON_ERROR);
    am_assert($payload['subject']===account_mail_text($locale,$purpose)['subject']);
    am_assert((bool)preg_match('~https?://[^\s]+~',$payload['body'],$match));$parts=parse_url($match[0]);
    am_assert($parts['scheme']==='http'&&$parts['host']==='127.0.0.1'&&(int)$parts['port']===8081);
    parse_str($parts['query'],$query);am_assert($query===['token'=>$token,'locale'=>$locale]);
    am_assert($parts['path']==='/auth/'.($purpose==='verify'?'verify-email':'reset-password').'.php');
    return $parts['path'].'?'.$parts['query'];
}
function am_browser():CurlHandle{
    $handle=curl_init();curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>15,CURLOPT_USERAGENT=>'GunIndex-Account-Regression']);return $handle;
}
function am_request(CurlHandle $browser,string $path,?array $fields=null):array{
    curl_setopt($browser,CURLOPT_URL,'http://127.0.0.1:8081'.$path);
    if($fields===null){curl_setopt($browser,CURLOPT_HTTPGET,true);}else{curl_setopt($browser,CURLOPT_POST,true);curl_setopt($browser,CURLOPT_POSTFIELDS,http_build_query($fields));}
    $body=curl_exec($browser);if(!is_string($body))throw new RuntimeException('Local HTTP request unavailable.');return [(int)curl_getinfo($browser,CURLINFO_RESPONSE_CODE),$body];
}
function am_csrf(string $body):string{am_assert((bool)preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/',$body,$match));return $match[1];}
function am_login(CurlHandle $browser,array $user):void{
    [$status,$body]=am_request($browser,'/auth/login.php');am_assert($status===200);
    [$status]=am_request($browser,'/auth/login.php',['csrf_token'=>am_csrf($body),'login'=>$user['email'],'password'=>$user['password']]);am_assert($status===303);
}
$tests=[
 'capability_links_reject_credentials_paths_and_public_http'=>static function():void{
    foreach(['https://user@example.invalid','https://u:p@example.invalid','https://market.example.invalid/path','https://market.example.invalid?x=1','http://market.example.invalid'] as $origin){putenv('APP_URL='.$origin);am_reject(static fn()=>mail_origin());}
    putenv('APP_URL=http://127.0.0.1:8081');am_assert(mail_origin()==='http://127.0.0.1:8081');
 },
 'queue_payload_is_encrypted_and_deduplicated'=>static function()use($pdo):void{
    $user=am_user($pdo);$key='test:'.bin2hex(random_bytes(12));$body='Only synthetic secret '.bin2hex(random_bytes(20));
    mail_enqueue($pdo,$user['id'],$user['email'],'Synthetic subject',$body,$key);mail_enqueue($pdo,$user['id'],$user['email'],'Duplicate',$body,$key);
    $q=$pdo->prepare('SELECT payload_ciphertext FROM market_mail_queue WHERE dedupe_key=?');$q->execute([$key]);$rows=$q->fetchAll();am_assert(count($rows)===1);$cipher=$rows[0]['payload_ciphertext'];
    am_assert(!str_contains($cipher,$body)&&!str_contains($cipher,$user['email']));$opened=json_decode(crypto_decrypt('mail',$cipher),true,8,JSON_THROW_ON_ERROR);am_assert($opened['body']===$body);
    am_reject(static fn()=>crypto_decrypt('chat',$cipher));
 },
 'mail_headers_and_foreign_urls_are_rejected'=>static function()use($pdo):void{
    $user=am_user($pdo);am_reject(static fn()=>mail_enqueue($pdo,$user['id'],"a@example.invalid\r\nBcc: b@example.invalid",'x','x','test:invalid'));
    am_reject(static fn()=>mail_enqueue($pdo,$user['id'],$user['email'],"Subject\r\nHeader: x",'x','test:invalid'));
    am_reject(static fn()=>notify_user($pdo,$user['id'],'test:bad-url','x','x','//example.invalid'));
 },
 'verification_is_purpose_bound_single_use_and_not_identity'=>static function()use($pdo):void{
    $user=am_user($pdo);$token=am_token($pdo,$user,'verify');am_assert(!account_token_consume($pdo,$token,'reset','Synthetic new password 123'));
    am_assert(account_token_consume($pdo,$token,'verify'));am_assert(!account_token_consume($pdo,$token,'verify'));
    $q=$pdo->prepare('SELECT email_verified_at,is_verified FROM users WHERE id=?');$q->execute([$user['id']]);$row=$q->fetch();am_assert($row['email_verified_at']!==null&&(int)$row['is_verified']===0);
 },
 'expired_and_replaced_reset_links_cannot_change_password'=>static function()use($pdo):void{
    $user=am_user($pdo);$expired=am_token($pdo,$user,'reset');$pdo->prepare('UPDATE market_auth_tokens SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE token_hash=?')->execute([hash('sha256',$expired)]);
    am_assert(!account_token_consume($pdo,$expired,'reset','Synthetic new password 123'));
    $old=am_token($pdo,$user,'reset');$fresh=am_token($pdo,$user,'reset');am_assert(!account_token_consume($pdo,$old,'reset','Synthetic new password 123'));
    am_assert(account_token_consume($pdo,$fresh,'reset','  <>& Synthetic reset password 123  '));
    $q=$pdo->prepare('SELECT password_hash,auth_version FROM users WHERE id=?');$q->execute([$user['id']]);$row=$q->fetch();am_assert(password_verify('  <>& Synthetic reset password 123  ',$row['password_hash'])&&!password_verify($user['password'],$row['password_hash'])&&(int)$row['auth_version']===1);
    am_assert(!account_token_consume($pdo,$fresh,'reset','Another synthetic password 123'));
 },
 'credential_version_or_email_change_invalidates_tokens'=>static function()use($pdo):void{
    $user=am_user($pdo);$token=am_token($pdo,$user,'reset');$pdo->prepare('UPDATE users SET auth_version=auth_version+1 WHERE id=?')->execute([$user['id']]);am_assert(!account_token_consume($pdo,$token,'reset','Synthetic new password 123'));
    $token=am_token($pdo,$user,'verify');$pdo->prepare('UPDATE users SET email=? WHERE id=?')->execute(['changed-'.bin2hex(random_bytes(6)).'@example.invalid',$user['id']]);am_assert(!account_token_consume($pdo,$token,'verify'));
 },
 'password_reset_preserves_admin_second_factor'=>static function()use($pdo):void{
    $user=am_user($pdo);$secret=random_bytes(20);$encrypted=auth_secret_encrypt($secret);$pdo->prepare('UPDATE users SET is_admin=1,mfa_secret=?,mfa_last_counter=12 WHERE id=?')->execute([$encrypted,$user['id']]);
    $token=am_token($pdo,$user,'reset');am_assert(account_token_consume($pdo,$token,'reset','Synthetic administrator password 123'));
    $q=$pdo->prepare('SELECT mfa_secret,mfa_last_counter FROM users WHERE id=?');$q->execute([$user['id']]);$row=$q->fetch();am_assert(hash_equals($secret,auth_secret_decrypt($row['mfa_secret']))&&(int)$row['mfa_last_counter']===12);
 },
 'unknown_addresses_and_missing_origin_create_no_capability'=>static function()use($pdo):void{
    $before=(int)$pdo->query('SELECT COUNT(*) FROM market_auth_tokens')->fetchColumn();account_token_request($pdo,'unknown-'.bin2hex(random_bytes(8)).'@example.invalid','reset');am_assert($before===(int)$pdo->query('SELECT COUNT(*) FROM market_auth_tokens')->fetchColumn());
    $user=am_user($pdo);putenv('APP_URL');account_token_request($pdo,$user['email'],'reset');putenv('APP_URL=http://127.0.0.1:8081');am_assert($before===(int)$pdo->query('SELECT COUNT(*) FROM market_auth_tokens')->fetchColumn());
 },
 'caller_rollback_removes_token_and_queue_atomically'=>static function()use($pdo):void{
    $user=am_user($pdo);$pdo->beginTransaction();account_token_request($pdo,$user['email'],'reset');am_assert($pdo->inTransaction());$pdo->rollBack();
    $q=$pdo->prepare('SELECT COUNT(*) FROM market_auth_tokens WHERE user_id=?');$q->execute([$user['id']]);am_assert((int)$q->fetchColumn()===0);$q=$pdo->prepare('SELECT COUNT(*) FROM market_mail_queue WHERE user_id=?');$q->execute([$user['id']]);am_assert((int)$q->fetchColumn()===0);
 },
 'notification_dedupe_and_mail_opt_in_are_enforced'=>static function()use($pdo):void{
    $user=am_user($pdo,true);$key='test:notice:'.bin2hex(random_bytes(8));notify_user($pdo,$user['id'],$key,'Test notice','Only synthetic public summary','/user/notifications.php',true);notify_user($pdo,$user['id'],$key,'Duplicate','Duplicate',null,true);
    $q=$pdo->prepare('SELECT COUNT(*) FROM market_notifications WHERE user_id=? AND event_key=?');$q->execute([$user['id'],$key]);am_assert((int)$q->fetchColumn()===1&&notification_unread_count($pdo,$user['id'])===1);
    $q=$pdo->prepare('SELECT COUNT(*) FROM market_mail_queue WHERE user_id=?');$q->execute([$user['id']]);am_assert((int)$q->fetchColumn()===0);
    $pdo->prepare('INSERT INTO market_notification_preferences(user_id,inapp_enabled,mail_enabled) VALUES (?,0,1)')->execute([$user['id']]);notify_user($pdo,$user['id'],$key.':mail','Mail notice','Synthetic summary',null,true);
    $q->execute([$user['id']]);am_assert((int)$q->fetchColumn()===1&&notification_unread_count($pdo,$user['id'])===1);
 },
 'unverified_email_never_receives_optional_alerts'=>static function()use($pdo):void{
    $user=am_user($pdo);$pdo->prepare('INSERT INTO market_notification_preferences(user_id,inapp_enabled,mail_enabled) VALUES (?,1,1)')->execute([$user['id']]);notify_user($pdo,$user['id'],'test:unverified','Test','Synthetic summary',null,true);
    $q=$pdo->prepare('SELECT COUNT(*) FROM market_mail_queue WHERE user_id=?');$q->execute([$user['id']]);am_assert((int)$q->fetchColumn()===0);
 },
 'test_delivery_is_once_and_removes_sensitive_payload'=>static function()use($pdo):void{
    $user=am_user($pdo);$key='test:delivery:'.bin2hex(random_bytes(8));mail_enqueue($pdo,$user['id'],$user['email'],'Test','Synthetic only',$key);$delivered=0;for($batch=0;$batch<15;++$batch){$counts=mail_worker_once($pdo,100);$delivered+=$counts['delivered'];if($counts['delivered']===0)break;}am_assert($delivered>=1);
    $q=$pdo->prepare('SELECT status,payload_ciphertext,attempts FROM market_mail_queue WHERE dedupe_key=?');$q->execute([$key]);$row=$q->fetch();am_assert($row['status']==='delivered'&&$row['payload_ciphertext']===''&&(int)$row['attempts']===1);
    mail_enqueue($pdo,$user['id'],$user['email'],'Duplicate','Not sent again',$key);mail_worker_once($pdo,100);$q->execute([$key]);$again=$q->fetch();am_assert((int)$again['attempts']===1&&$again['payload_ciphertext']==='');
 },
 'unconfigured_transport_does_not_consume_retry_budget'=>static function()use($pdo):void{
    $user=am_user($pdo);$key='test:deferred:'.bin2hex(random_bytes(8));mail_enqueue($pdo,$user['id'],$user['email'],'Test','Synthetic only',$key);putenv('MAIL_TRANSPORT=none');$counts=mail_worker_once($pdo);putenv('MAIL_TRANSPORT=test');am_assert($counts['deferred']>=1);
    $q=$pdo->prepare('SELECT status,attempts FROM market_mail_queue WHERE dedupe_key=?');$q->execute([$key]);$row=$q->fetch();am_assert($row['status']==='pending'&&(int)$row['attempts']===0);
 },
 'expired_reset_mail_is_redacted_instead_of_delivered'=>static function()use($pdo):void{
    $user=am_user($pdo);$token=am_token($pdo,$user,'reset');$key='account:reset:'.hash('sha256',$token);$pdo->prepare('UPDATE market_mail_queue SET created_at=UTC_TIMESTAMP()-INTERVAL 2 HOUR WHERE dedupe_key=?')->execute([$key]);mail_worker_once($pdo,100);
    $q=$pdo->prepare('SELECT status,payload_ciphertext,attempts FROM market_mail_queue WHERE dedupe_key=?');$q->execute([$key]);$row=$q->fetch();am_assert($row['status']==='expired'&&$row['payload_ciphertext']===''&&(int)$row['attempts']===0);
 },
 'inactive_accounts_cannot_consume_recovery_tokens'=>static function()use($pdo):void{
    $user=am_user($pdo);$token=am_token($pdo,$user,'reset');$pdo->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$user['id']]);am_assert(!account_token_consume($pdo,$token,'reset','Synthetic inactive password 123'));mail_worker_once($pdo,100);$q=$pdo->prepare('SELECT status,payload_ciphertext FROM market_mail_queue WHERE dedupe_key=?');$q->execute(['account:reset:'.hash('sha256',$token)]);$row=$q->fetch();am_assert($row['status']==='cancelled'&&$row['payload_ciphertext']==='');
 },
 'sendmail_argv_transport_accepts_only_a_successful_local_mta'=>static function():void{
    $directory=sys_get_temp_dir().'/gunindex-mail-'.bin2hex(random_bytes(8));am_assert(mkdir($directory,0700));$path=$directory.'/sendmail';
    try{
        file_put_contents($path,"#!/bin/sh\n[ \"\$1\" = \"-t\" ] && [ \"\$2\" = \"-i\" ] && [ \"\$3\" = \"-f\" ] || exit 64\ncat >/dev/null\nexit 0\n");chmod($path,0700);putenv('MAIL_FROM=no-reply@example.invalid');putenv('MAIL_SENDMAIL_PATH='.$path);putenv('MAIL_TRANSPORT=sendmail');am_assert(mail_transport_reason()===null);
        mail_send_local(['to'=>'recipient@example.invalid','subject'=>'Synthetic MTA smoke','body'=>'Only synthetic content.'],'test:local-mta');
        file_put_contents($path,"#!/bin/sh\ncat >/dev/null\nexit 75\n");chmod($path,0700);am_reject(static fn()=>mail_send_local(['to'=>'recipient@example.invalid','subject'=>'Synthetic MTA smoke','body'=>'Only synthetic content.'],'test:local-mta-fail'));
    }finally{putenv('MAIL_TRANSPORT=test');unlink($path);rmdir($directory);}
 },
 'tampered_queue_fails_without_success_and_records_retry'=>static function()use($pdo):void{
    $user=am_user($pdo);$key='test:tamper:'.bin2hex(random_bytes(8));mail_enqueue($pdo,$user['id'],$user['email'],'Test','Synthetic only',$key);$pdo->prepare("UPDATE market_mail_queue SET payload_ciphertext='invalid' WHERE dedupe_key=?")->execute([$key]);$counts=mail_worker_once($pdo,100);am_assert($counts['failed']>=1);
    $q=$pdo->prepare('SELECT status,attempts,last_error_code,next_attempt_at FROM market_mail_queue WHERE dedupe_key=?');$q->execute([$key]);$row=$q->fetch();am_assert($row['status']==='pending'&&(int)$row['attempts']===1&&$row['last_error_code']==='delivery_failed'&&strtotime($row['next_attempt_at'].' UTC')>time());
 },
 'mfa_key_rotation_keeps_only_authenticated_secrets'=>static function():void{
    $current=getenv('APP_KEY');$previous=getenv('APP_KEY_PREVIOUS');
    try{
        $old=base64_encode(random_bytes(32));putenv('APP_KEY='.$old);putenv('APP_KEY_PREVIOUS');$plain=random_bytes(20);$cipher=auth_secret_encrypt($plain);
        putenv('APP_KEY='.base64_encode(random_bytes(32)));putenv('APP_KEY_PREVIOUS='.$old);am_assert(hash_equals($plain,auth_secret_decrypt($cipher)));
        $encoded=substr($cipher,3);$bytes=base64_decode($encoded,true);$bytes[strlen($bytes)-1]=chr(ord($bytes[strlen($bytes)-1])^1);am_reject(static fn()=>auth_secret_decrypt('v1:'.base64_encode($bytes)));
        $rekeyed=auth_secret_encrypt($plain);putenv('APP_KEY_PREVIOUS');am_assert(hash_equals($plain,auth_secret_decrypt($rekeyed)));am_reject(static fn()=>auth_secret_decrypt($cipher));
    }finally{if($current===false)putenv('APP_KEY');else putenv('APP_KEY='.$current);if($previous===false)putenv('APP_KEY_PREVIOUS');else putenv('APP_KEY_PREVIOUS='.$previous);}
 },
 'invalid_retention_never_purges_queued_data'=>static function()use($pdo):void{
    $original=getenv('MAIL_QUEUE_RETENTION_DAYS');
    try{foreach(['0','31','invalid'] as $value){putenv('MAIL_QUEUE_RETENTION_DAYS='.$value);am_reject(static fn()=>mail_worker_once($pdo));}}
    finally{if($original===false)putenv('MAIL_QUEUE_RETENTION_DAYS');else putenv('MAIL_QUEUE_RETENTION_DAYS='.$original);}
 },
 'localized_account_mail_uses_the_recipient_language'=>static function()use($pdo):void{
    foreach(['de','en','fr','nl','pl','cs','da','it'] as $locale){$copy=account_mail_text($locale,'verify');am_assert($copy['subject']!==''&&preg_match('//u',$copy['subject'].$copy['body'].$copy['footer'])===1);}
    $user=am_user($pdo);$pdo->prepare("UPDATE users SET preferred_locale='en' WHERE id=?")->execute([$user['id']]);$token=am_token($pdo,$user,'reset');$q=$pdo->prepare('SELECT payload_ciphertext FROM market_mail_queue WHERE dedupe_key=?');$q->execute(['account:reset:'.hash('sha256',$token)]);$payload=json_decode(crypto_decrypt('mail',$q->fetchColumn()),true,8,JSON_THROW_ON_ERROR);am_assert($payload['subject']==='Reset your password'&&str_contains($payload['body'],'30 minutes'));
 },
 'recipient_links_initialize_all_eight_fresh_browser_languages_without_consuming'=>static function()use($pdo):void{
    $user=am_user($pdo);$snapshot=$pdo->prepare('SELECT password_hash,auth_version,email_verified_at,preferred_locale FROM users WHERE id=?');
    $used=$pdo->prepare('SELECT used_at FROM market_auth_tokens WHERE token_hash=?');
    foreach(array_keys(i18n_locales()) as $locale){
        $pdo->prepare('UPDATE users SET preferred_locale=? WHERE id=?')->execute([$locale,$user['id']]);
        foreach(['reset','verify'] as $purpose){
            $token=am_token($pdo,$user,$purpose);$path=am_mail_link($pdo,$token,$purpose,$locale);$browser=am_browser();
            $snapshot->execute([$user['id']]);$before=$snapshot->fetch();
            try{
                [$status,$body]=am_request($browser,$path);am_assert($status===200&&str_contains($body,'<html lang="'.$locale.'"'));
                $heading=i18n_dictionary($locale)[$purpose==='reset'?'account.reset_heading':'account.verify_heading'];am_assert(str_contains($body,h($heading)));
                $snapshot->execute([$user['id']]);am_assert($before===$snapshot->fetch());$used->execute([hash('sha256',$token)]);am_assert($used->fetchColumn()===null);
                [$status,$next]=am_request($browser,'/auth/login.php');am_assert($status===200&&str_contains($next,'<html lang="'.$locale.'"'));
            }finally{curl_close($browser);}
        }
    }
 },
 'email_link_does_not_override_an_explicit_browser_language'=>static function()use($pdo):void{
    $user=am_user($pdo);$pdo->prepare("UPDATE users SET preferred_locale='fr' WHERE id=?")->execute([$user['id']]);$browser=am_browser();
    $country=$pdo->query('SELECT code FROM market_countries WHERE is_active=1 ORDER BY code LIMIT 1')->fetchColumn();am_assert(is_string($country));
    try{
        [$status,$body]=am_request($browser,'/auth/login.php');am_assert($status===200);
        [$status]=am_request($browser,'/user/language.php',['csrf_token'=>am_csrf($body),'locale'=>'pl','market_country'=>$country,'return_path'=>'/auth/login.php']);am_assert($status===303);
        foreach(['reset','verify'] as $purpose){
            $token=am_token($pdo,$user,$purpose);$path=am_mail_link($pdo,$token,$purpose,'fr');
            [$status,$body]=am_request($browser,$path);am_assert($status===200&&str_contains($body,'<html lang="pl"'));
            $q=$pdo->prepare('SELECT used_at FROM market_auth_tokens WHERE token_hash=?');$q->execute([hash('sha256',$token)]);am_assert($q->fetchColumn()===null);
        }
        $q=$pdo->prepare('SELECT preferred_locale FROM users WHERE id=?');$q->execute([$user['id']]);am_assert($q->fetchColumn()==='fr');
    }finally{curl_close($browser);}
 },
 'legacy_array_and_unknown_link_languages_keep_default_behavior'=>static function()use($pdo):void{
    $user=am_user($pdo);
    foreach(['reset','verify'] as $purpose){
        $token=am_token($pdo,$user,$purpose);$base='/auth/'.($purpose==='verify'?'verify-email':'reset-password').'.php?token='.$token;
        foreach(['','&locale%5B%5D=fr','&locale=unknown','&locale=..%2F..%2Fetc%2Fpasswd'] as $hint){
            $browser=am_browser();
            try{[$status,$body]=am_request($browser,$base.$hint);am_assert($status===200&&str_contains($body,'<html lang="de"'));
                $q=$pdo->prepare('SELECT used_at FROM market_auth_tokens WHERE token_hash=?');$q->execute([hash('sha256',$token)]);am_assert($q->fetchColumn()===null);
            }finally{curl_close($browser);}
        }
    }
 },
 'verification_http_get_is_read_only_and_post_requires_csrf'=>static function()use($pdo):void{
    $user=am_user($pdo);$token=am_token($pdo,$user,'verify');$browser=am_browser();$path='/auth/verify-email.php?token='.$token;
    try{[$status,$body]=am_request($browser,$path);am_assert($status===200);$q=$pdo->prepare('SELECT email_verified_at FROM users WHERE id=?');$q->execute([$user['id']]);am_assert($q->fetchColumn()===null);
        [$status]=am_request($browser,$path,['action'=>'confirm','token'=>$token]);am_assert($status===403);
        [$status]=am_request($browser,$path,['csrf_token'=>am_csrf($body),'action'=>'confirm','token'=>$token]);am_assert($status===200);$q->execute([$user['id']]);am_assert($q->fetchColumn()!==null);
        [$status,$body]=am_request($browser,$path);am_assert($status===200);[$status]=am_request($browser,$path,['csrf_token'=>am_csrf($body),'action'=>'confirm','token'=>$token]);am_assert($status===422);
    }finally{curl_close($browser);}
 },
 'password_reset_http_preserves_get_and_rejects_replay'=>static function()use($pdo):void{
    $user=am_user($pdo);$token=am_token($pdo,$user,'reset');$browser=am_browser();$path='/auth/reset-password.php?token='.$token;$next='  <>& Synthetic HTTP reset password 123  ';
    try{[$status,$body]=am_request($browser,$path);am_assert($status===200);$q=$pdo->prepare('SELECT password_hash FROM users WHERE id=?');$q->execute([$user['id']]);am_assert(password_verify($user['password'],$q->fetchColumn()));
        [$status]=am_request($browser,$path,['token'=>$token,'password'=>$next,'password_confirm'=>$next]);am_assert($status===403);
        [$status]=am_request($browser,$path,['csrf_token'=>am_csrf($body),'token'=>$token,'password'=>$next,'password_confirm'=>$next]);am_assert($status===200);$q->execute([$user['id']]);am_assert(password_verify($next,$q->fetchColumn()));
        [$status,$body]=am_request($browser,$path);am_assert($status===200);[$status]=am_request($browser,$path,['csrf_token'=>am_csrf($body),'token'=>$token,'password'=>$next,'password_confirm'=>$next]);am_assert($status===422);
    }finally{curl_close($browser);}
 },
 'recovery_http_has_the_same_public_outcome_for_unknown_addresses'=>static function()use($pdo):void{
    $user=am_user($pdo);$browser=am_browser();$outcomes=[];
    try{foreach([$user['email'],'unknown-'.bin2hex(random_bytes(8)).'@example.invalid'] as $email){[$status,$body]=am_request($browser,'/auth/forgot-password.php');am_assert($status===200);[$status,$body]=am_request($browser,'/auth/forgot-password.php',['csrf_token'=>am_csrf($body),'email'=>$email]);$outcomes[]=[$status,str_contains($body,'Wenn für diese Adresse ein aktives Konto besteht')];}am_assert($outcomes[0]===[200,true]&&$outcomes[1]===$outcomes[0]);
        $q=$pdo->prepare("SELECT COUNT(*) FROM market_auth_tokens WHERE user_id=? AND purpose='reset' AND used_at IS NULL");$q->execute([$user['id']]);am_assert((int)$q->fetchColumn()===1);
    }finally{curl_close($browser);}
 },
 'notification_http_rejects_csrf_and_foreign_read_markers'=>static function()use($pdo):void{
    $owner=am_user($pdo);$other=am_user($pdo);$key='test:http-notice:'.bin2hex(random_bytes(8));$marker='Private synthetic notice '.bin2hex(random_bytes(8));notify_user($pdo,$owner['id'],$key,'Synthetic notice',$marker,'/user/profile.php');$q=$pdo->prepare('SELECT id,read_at FROM market_notifications WHERE user_id=? AND event_key=?');$q->execute([$owner['id'],$key]);$notice=$q->fetch();$a=am_browser();$b=am_browser();
    try{am_login($a,$owner);am_login($b,$other);[$status,$body]=am_request($a,'/user/notifications.php');am_assert($status===200&&str_contains($body,$marker));[$status]=am_request($a,'/user/notifications.php',['id'=>$notice['id']]);am_assert($status===403);
        [$status,$foreign]=am_request($b,'/user/notifications.php');am_assert($status===200&&!str_contains($foreign,$marker));[$status,$profile]=am_request($b,'/user/profile.php');am_assert($status===200);[$status]=am_request($b,'/user/notifications.php',['csrf_token'=>am_csrf($profile),'id'=>$notice['id']]);am_assert($status===303);$q->execute([$owner['id'],$key]);am_assert($q->fetch()['read_at']===null);
        [$status,$body]=am_request($a,'/user/notifications.php');am_assert($status===200);[$status]=am_request($a,'/user/notifications.php',['csrf_token'=>am_csrf($body),'id'=>$notice['id']]);am_assert($status===303);$q->execute([$owner['id'],$key]);am_assert($q->fetch()['read_at']!==null);
    }finally{curl_close($a);curl_close($b);}
 },
 'test_transport_is_not_usable_in_a_production_database'=>static function():void{
    putenv('DB_DATABASE=production');am_assert(mail_transport_reason()!==null);putenv('DB_DATABASE=gunindex_rebuild_test');am_assert(mail_transport_reason()===null);
 },
];
try{
    foreach($tests as $name=>$run){try{$run();++$passed;echo 'PASS '.$name.PHP_EOL;}catch(Throwable $error){fwrite(STDERR,'FAIL '.$name.' ('.get_class($error).')'.PHP_EOL);exit(1);}}
    echo 'Account and notification checks passed: '.$passed.PHP_EOL;
}finally{foreach($originalEnvironment as $key=>$value){if($value===false)putenv($key);else putenv($key.'='.$value);}}
