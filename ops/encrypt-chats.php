<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
require_once __DIR__.'/../config/bootstrap.php';
$apply=in_array('--apply',$argv,true);
if($apply&&!in_array('--backup-confirmed',$argv,true)){fwrite(STDERR,"A verified encrypted backup is required.\n");exit(2);}
$count=(int)$pdo->query('SELECT COUNT(*) FROM market_messages WHERE cipher_version=0')->fetchColumn();
if(!$apply){fwrite(STDOUT,'Messages requiring encryption: '.$count."\n");exit;}
$done=0;
while(true){
 $pdo->beginTransaction();
 try{
  $rows=$pdo->query('SELECT id,message_text FROM market_messages WHERE cipher_version=0 ORDER BY id LIMIT 100 FOR UPDATE')->fetchAll();
  if(!$rows){$pdo->commit();break;}
  $q=$pdo->prepare('UPDATE market_messages SET message_text=?,cipher_version=1 WHERE id=? AND cipher_version=0');
  foreach($rows as $row){$cipher=crypto_encrypt('chat',$row['message_text']);if(!hash_equals($row['message_text'],crypto_decrypt('chat',$cipher)))throw new RuntimeException('Encryption verification failed.');$q->execute([$cipher,$row['id']]);$done++;}
  $pdo->commit();
 }catch(Throwable){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,"Message encryption stopped; existing values were preserved.\n");exit(1);}
}
fwrite(STDOUT,'Encrypted messages: '.$done."\n");
