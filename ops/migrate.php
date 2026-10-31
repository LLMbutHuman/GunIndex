<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
if(($argv[1]??'')!=='--backup-confirmed'){fwrite(STDERR,"A verified encrypted backup and recovery plan are required. Use --backup-confirmed only after completing them.\n");exit(2);}
require_once __DIR__.'/../config/db.php';
try {
 $lock=$pdo->prepare('SELECT GET_LOCK(?,10)');$lock->execute(['gunindex.migrate.'.substr(hash('sha256',(string)$database),0,32)]);if((int)$lock->fetchColumn()!==1)throw new RuntimeException();
 $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations(version VARCHAR(100) PRIMARY KEY,checksum CHAR(64) NOT NULL,applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
 $required=['users','market_listings','market_invoices'];foreach($required as $table){$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$q->execute([$table]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Core schema missing');}
 foreach(glob(__DIR__.'/../sql/migrations/*.sql')?:[] as $file){$version=basename($file);$hash=hash_file('sha256',$file);$q=$pdo->prepare('SELECT checksum FROM schema_migrations WHERE version=?');$q->execute([$version]);$previous=$q->fetchColumn();if($previous!==false){if(!hash_equals($previous,$hash))throw new RuntimeException('Applied migration changed');continue;}
  if($version==='001_manual_billing.sql'){$q=$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='market_listings' AND column_name='bumped_at'");if((int)$q->fetchColumn()===0)$pdo->exec('ALTER TABLE market_listings ADD COLUMN bumped_at DATETIME NULL');}
  $sql=preg_replace('/^--.*$/m','',file_get_contents($file));foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$pdo->exec($statement);
  $pdo->prepare('INSERT INTO schema_migrations(version,checksum) VALUES (?,?)')->execute([$version,$hash]);fwrite(STDOUT,'Applied '.$version."\n");
 }
 $pdo->query("SELECT RELEASE_ALL_LOCKS()");
}catch(Throwable){fwrite(STDERR,"Migration failed. Preserve partial additive DDL; inspect and repair forward. No automatic reset was executed.\n");exit(1);}
