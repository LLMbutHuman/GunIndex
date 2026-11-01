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
  $sql=preg_replace('/^--.*$/m','',file_get_contents($file));foreach(explode(';',$sql) as $statement){
   $statement=trim($statement);if($statement==='')continue;
   if(preg_match('/\AALTER TABLE `?([a-z_]+)`? ADD COLUMN `?([a-z_]+)`? (.+)\z/is',$statement,$add)){
    $column=$pdo->prepare('SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$column->execute([$add[1],$add[2]]);$present=$column->fetch();
    if($present){
     if(!preg_match('/\A((?:VARCHAR|CHAR|DECIMAL)\([0-9, ]+\)|(?:DATETIME|TIMESTAMP)(?:\([0-9]+\))?|(?:TINYINT|BIGINT|INT)(?:\([0-9]+\))?|MEDIUMTEXT|TEXT)(?:\s+(UNSIGNED))?/i',$add[3],$type))throw new RuntimeException('Unsupported additive column type');
     $normalize=static fn($value)=>strtolower(preg_replace('/\b(tinyint|int|bigint)\([0-9]+\)/i','$1',preg_replace('/\s+/',' ',trim($value))));
     $expected=$type[1].(empty($type[2])?'':' UNSIGNED');
     if($normalize($expected)!==$normalize($present['COLUMN_TYPE'])||$present['IS_NULLABLE']!==(stripos($add[3],'NOT NULL')!==false?'NO':'YES'))throw new RuntimeException('Existing column differs from migration');
     if(preg_match('/DEFAULT\s+(?:\x27([^\x27]*)\x27|([0-9]+))/i',$add[3],$default)&& (string)$present['COLUMN_DEFAULT']!==(string)($default[1]!==''?$default[1]:($default[2]??'')))throw new RuntimeException('Existing column default differs');
     continue;
    }
   }
   if(preg_match('/\AALTER TABLE `?([a-z_]+)`? ADD INDEX `?([a-z_]+)`?\s*\(([^)]+)\)\z/i',$statement,$index)){
    $q=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX');$q->execute([$index[1],$index[2]]);$present=$q->fetchAll(PDO::FETCH_COLUMN);
    if($present){$expected=array_map(static fn($value)=>trim($value,' `'),explode(',',$index[3]));if($present!==$expected)throw new RuntimeException('Existing index differs');continue;}
   }
   $pdo->exec($statement);
  }
  $pdo->prepare('INSERT INTO schema_migrations(version,checksum) VALUES (?,?)')->execute([$version,$hash]);fwrite(STDOUT,'Applied '.$version."\n");
 }
 $pdo->query("SELECT RELEASE_ALL_LOCKS()");
}catch(Throwable $error){error_log('Migration error: '.get_class($error).' code '.(string)$error->getCode().' at '.basename($error->getFile()).':'.$error->getLine());fwrite(STDERR,"Migration failed. Preserve partial additive DDL; inspect and repair forward. No automatic reset was executed.\n");exit(1);}
