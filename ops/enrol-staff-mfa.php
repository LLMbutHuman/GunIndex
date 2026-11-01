<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!function_exists('stream_isatty')||!stream_isatty(STDIN)){fwrite(STDERR,"Staff enrollment requires a private interactive terminal.\n");exit(2);}
require_once __DIR__.'/../config/bootstrap.php';require_once __DIR__.'/../config/governance.php';
try{
 $name=$argv[1]??'';if(!is_string($name)||!preg_match('/\A[A-Za-z0-9_-]{3,50}\z/',$name))throw new InvalidArgumentException('Usage: php ops/enrol-staff-mfa.php USERNAME');
 $q=$pdo->prepare('SELECT * FROM users WHERE username=? AND is_active=1');$q->execute([$name]);$user=$q->fetch();if(!$user||$user['mfa_secret']!==null||(int)$user['is_admin']===1)throw new DomainException('Only an active, not yet enrolled staff candidate may be enrolled here.');
 fwrite(STDOUT,"This local operation enrolls MFA but grants no role. Keep the terminal and enrollment secret private.\n");
 $secret=random_bytes(20);fwrite(STDOUT,'Authenticator secret: '.auth_base32($secret)."\n");fwrite(STDOUT,"Confirm a current six-digit authenticator code: ");$code=trim((string)fgets(STDIN));$counter=auth_totp_counter($secret,$code);if($counter===null)throw new DomainException('Authenticator confirmation failed.');
 $pdo->beginTransaction();$q=$pdo->prepare('SELECT mfa_secret FROM users WHERE id=? FOR UPDATE');$q->execute([$user['id']]);if($q->fetchColumn()!==null)throw new DomainException('Enrollment changed; stop and review.');
 $pdo->prepare('UPDATE users SET mfa_secret=?,mfa_last_counter=?,auth_version=auth_version+1 WHERE id=?')->execute([auth_secret_encrypt($secret),$counter,$user['id']]);audit_log($pdo,(int)$user['id'],'mfa.staff_enrolled',(int)$user['id'],[]);$pdo->commit();sodium_memzero($secret);fwrite(STDOUT,"MFA enrolled. A superadministrator may now grant a limited role through the website.\n");
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,"Staff enrollment failed; no role was granted.\n");exit(1);}
