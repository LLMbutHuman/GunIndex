<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
try {
 $path='/run/backup.key';if(!is_file($path)||is_link($path)||(fileperms($path)&0077)!==0)throw new RuntimeException();
 $key=file_get_contents($path);$body=stream_get_contents(STDIN,20971521);if(!is_string($key)||strlen($key)!==32||!is_string($body)||strlen($body)>20971520)throw new RuntimeException();
 $derived=hash_hkdf('sha256',$key,32,'GunIndex snapshot manifest v1');fwrite(STDOUT,hash_hmac('sha256',$body,$derived));sodium_memzero($derived);sodium_memzero($key);
}catch(Throwable){fwrite(STDERR,"Manifest authentication failed.\n");exit(1);}
