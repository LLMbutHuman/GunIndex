<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_csrf();
$config=passkey_config();
if (!$config||($_SERVER['HTTP_ORIGIN']??null)!==$config['origin']) abort_request(403,t('user_security.passkey_failed'));
if ((int)($_SERVER['CONTENT_LENGTH']??0)>49152) abort_request(413,t('user_security.passkey_failed'));
header('Content-Type: application/json; charset=utf-8');
try {
    $action=post_string('action',20);
    $viewer=current_user($pdo);
    if (in_array($action,['start_add','finish_add'],true)) {
        if (!$viewer) throw new DomainException(t('user_security.unavailable'));
        if ($action==='start_add') $result=['publicKey'=>passkey_begin_add($pdo,$viewer,$config,$_POST)];
        else { passkey_finish_add($pdo,$viewer,$config,$_POST);$result=['next'=>'/user/passkeys.php']; }
    } elseif (in_array($action,['start_login','finish_login'],true)&&$viewer===null) {
        auth_rate_limit('passkey-ip',auth_ip(),50,300);
        $result=$action==='start_login'?['publicKey'=>passkey_begin_login($config)]:['next'=>passkey_finish_login($pdo,$config,$_POST)];
    } else throw new InvalidArgumentException(t('user_security.passkey_failed'));
    echo json_encode($result,JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException|DomainException $e) {
    http_response_code(422);
    echo json_encode(['error'=>$e->getMessage()],JSON_THROW_ON_ERROR);
}
