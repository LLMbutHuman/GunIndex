<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(2);}
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/governance.php';
require_once __DIR__.'/../config/privacy.php';
try{
 maintenance_worker_gate();
 $arguments=array_slice($argv,1);
 if(($arguments[0]??'')==='--seed-legal'){
  $actor=filter_var($arguments[1]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if(!$actor||count($arguments)!==2)throw new InvalidArgumentException('Use --seed-legal ADMIN_ID after initializing the operator.');
  governance_assert_actor($pdo,(int)$actor,'legal.manage');$manifest=require __DIR__.'/../docs/legal/manifest.php';$added=0;
  foreach($manifest as $code=>$title){
   $body=file_get_contents(__DIR__.'/../docs/legal/'.$code.'.md');if(!is_string($body))throw new RuntimeException('Legal source unavailable.');
   $q=$pdo->prepare("SELECT content_hash FROM market_legal_documents WHERE document_code=? AND version='draft-1.0' AND language='de'");$q->execute([$code]);$hash=$q->fetchColumn();
   if($hash!==false){if(!hash_equals($hash,hash('sha256',$body)))throw new RuntimeException('Existing version differs; create a new version rather than replacing it.');continue;}
   legal_create($pdo,(int)$actor,$code,'draft-1.0','de',$title,$body);$added++;
  }
  fwrite(STDOUT,'Legal drafts added: '.$added.". No publication or professional approval was performed.\n");
 }elseif($arguments===[]||$arguments===['--once']){
  fwrite(STDOUT,'Expired verification requests purged: '.privacy_purge_expired($pdo,100)."\n");
 }else throw new InvalidArgumentException('Unknown argument.');
}catch(Throwable $error){fwrite(STDERR,"Privacy worker did not complete. Inspect restricted application logs; no document content was logged.\n");exit(1);}
