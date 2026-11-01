<?php
declare(strict_types=1);
/** Purpose separation uses standard HKDF; payload encryption is libsodium AEAD. */
function crypto_master_keys(): array
{
    $keys = [];
    foreach (['current'=>'APP_KEY','previous'=>'APP_KEY_PREVIOUS'] as $id=>$variable) {
        $raw=getenv($variable);
        if (!is_string($raw)||$raw==='') continue;
        $key=base64_decode($raw,true);
        if (!is_string($key)||strlen($key)!==32) throw new RuntimeException('Encryption key configuration is invalid.');
        $keys[$id]=$key;
    }
    if (!isset($keys['current'])) throw new RuntimeException('Encryption is not configured.');
    return $keys;
}
function crypto_key_id(string $master): string { return substr(hash('sha256', $master),0,16); }
function crypto_encrypt(string $purpose,string $plain): string
{
    if (!preg_match('/\A[a-z_]{2,24}\z/',$purpose)) throw new InvalidArgumentException('Invalid encryption purpose.');
    $master=crypto_master_keys()['current'];$id=crypto_key_id($master);
    $key=hash_hkdf('sha256',$master,32,'gunindex/'.$purpose.'/v1');
    $nonce=random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
    $cipher=sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain,'gunindex:'.$purpose.':'.$id,$nonce,$key);
    sodium_memzero($key);return 'gx1:'.$id.':'.base64_encode($nonce.$cipher);
}
function crypto_decrypt(string $purpose,string $encoded): string
{
    if (!preg_match('/\A[a-z_]{2,24}\z/',$purpose)||!preg_match('/\Agx1:([a-f0-9]{16}):([A-Za-z0-9+\/=]+)\z/',$encoded,$match)) throw new RuntimeException('Encrypted data is invalid.');
    $bytes=base64_decode($match[2],true);
    if (!is_string($bytes)||strlen($bytes)<40) throw new RuntimeException('Encrypted data is invalid.');
    foreach (crypto_master_keys() as $master) {
        if (!hash_equals(crypto_key_id($master),$match[1])) continue;
        $key=hash_hkdf('sha256',$master,32,'gunindex/'.$purpose.'/v1');
        $plain=sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($bytes,24),'gunindex:'.$purpose.':'.$match[1],substr($bytes,0,24),$key);
        sodium_memzero($key);
        if ($plain===false) throw new RuntimeException('Encrypted data failed authentication.');
        return $plain;
    }
    throw new RuntimeException('The required encryption key is unavailable.');
}
