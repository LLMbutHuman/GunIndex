<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit(1);}
try {
 $mode=$argv[1]??'';$keyPath=$argv[2]??'';
 if(!in_array($mode,['encrypt','decrypt'],true)||!is_file($keyPath)||is_link($keyPath)||(fileperms($keyPath)&0077)!==0)throw new RuntimeException();
 $key=file_get_contents($keyPath);if(!is_string($key)||strlen($key)!==SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES)throw new RuntimeException();
 if($mode==='encrypt'){
  [$state,$header]=sodium_crypto_secretstream_xchacha20poly1305_init_push($key);fwrite(STDOUT,"GIDXBK01".$header);
  while(!feof(STDIN)){$plain=fread(STDIN,1048576);if($plain===false)throw new RuntimeException();if($plain!==''){$cipher=sodium_crypto_secretstream_xchacha20poly1305_push($state,$plain);fwrite(STDOUT,pack('N',strlen($cipher)).$cipher);}}
  $end=sodium_crypto_secretstream_xchacha20poly1305_push($state,'','',SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);fwrite(STDOUT,pack('N',strlen($end)).$end);
 }else{
  $read=static function(int $size):string{$result='';while(strlen($result)<$size&&!feof(STDIN)){$next=fread(STDIN,$size-strlen($result));if($next===false)throw new RuntimeException();$result.=$next;}if(strlen($result)!==$size)throw new RuntimeException();return $result;};
  if($read(8)!=='GIDXBK01')throw new RuntimeException();$state=sodium_crypto_secretstream_xchacha20poly1305_init_pull($read(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES),$key);
  $staged=tmpfile();if($staged===false)throw new RuntimeException();$final=false;
  while(!$final){$size=unpack('N',$read(4))[1];if($size<17||$size>1048593)throw new RuntimeException();$opened=sodium_crypto_secretstream_xchacha20poly1305_pull($state,$read($size));if($opened===false)throw new RuntimeException();[$plain,$tag]=$opened;if(!in_array($tag,[0,SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL],true))throw new RuntimeException();if(fwrite($staged,$plain)!==strlen($plain))throw new RuntimeException();$final=$tag===SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;}
  if(fread(STDIN,1)!==''||!feof(STDIN))throw new RuntimeException();rewind($staged);stream_copy_to_stream($staged,STDOUT);fclose($staged);
 }
 sodium_memzero($key);
}catch(Throwable){fwrite(STDERR,"Backup integrity or configuration check failed.\n");exit(1);}
