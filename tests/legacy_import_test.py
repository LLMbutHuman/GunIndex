#!/usr/bin/env python3
"""Actual CLI import checks against NEW synthetic MySQL scratch schemas only."""
import json,os,secrets,subprocess,tempfile,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];DOCKER=['docker','--host=unix:///var/run/docker.sock'];APP='gunindex-rebuild-app';DB='gunindex-rebuild-db'
def call(args,data=None):return subprocess.run(DOCKER+args,input=data,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
def sql(query):
 result=call(['exec','-i',DB,'sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --default-character-set=utf8mb4 -uroot -N'],query.encode())
 if result.returncode:raise RuntimeError('Synthetic scratch SQL failed; details withheld.')
 return result.stdout.decode().strip()

class LegacyImport(unittest.TestCase):
 @classmethod
 def setUpClass(cls):
  check=call(['exec',DB,'sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot -N -e "SELECT DATABASE()" "$MYSQL_DATABASE"'])
  if check.returncode or check.stdout.strip()!=b'gunindex_rebuild_test':raise RuntimeError('Only the isolated development database server is supported.')
  nonce=secrets.token_hex(8);cls.source='gunindex_legacy_source_test_'+nonce;cls.target='gunindex_legacy_target_test_'+nonce;cls.collision='gunindex_legacy_collision_test_'+nonce;cls.account='legacy_test_'+nonce;password='Synthetic_'+secrets.token_hex(24)
  cls.directory=Path(tempfile.mkdtemp(prefix='legacy-',dir=ROOT/'tests/.runtime'));cls.environment=cls.directory/'source.env'
  cls.environment.write_text('LEGACY_DB_DATABASE='+cls.source+'\nLEGACY_DB_USERNAME='+cls.account+'\nLEGACY_DB_PASSWORD='+password+'\nLEGACY_DB_SOCKET=/var/run/mysqld/mysqld.sock\n');cls.environment.chmod(0o600)
  statement=''
  for name in (cls.source,cls.target,cls.collision):statement+='CREATE DATABASE '+name+' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
  statement+="CREATE USER '"+cls.account+"'@'%' IDENTIFIED BY '"+password+"';GRANT SELECT ON "+cls.source+".* TO '"+cls.account+"'@'%';"
  statement+='USE '+cls.source+";CREATE TABLE users(id INT PRIMARY KEY,username VARCHAR(50),email VARCHAR(190),password_hash VARCHAR(255),full_name VARCHAR(150),zip VARCHAR(10),city VARCHAR(100),is_admin TINYINT,is_verified TINYINT);"
  statement+="INSERT INTO users VALUES(1,'legacy_"+nonce+"_a','a_"+nonce+"@example.invalid','UNTRUSTED-OLD-HASH','Synthetic legacy fixture','10115','Teststadt',1,1),(2,'legacy_"+nonce+"_b','b_"+nonce+"@example.invalid','UNTRUSTED-OLD-HASH','Synthetic legacy fixture','10115','Teststadt',0,1);"
  users="CREATE TABLE users(id INT AUTO_INCREMENT PRIMARY KEY,username VARCHAR(50) UNIQUE,email VARCHAR(190) UNIQUE,password_hash VARCHAR(255),full_name VARCHAR(150),zip VARCHAR(10),city VARCHAR(100),is_admin TINYINT DEFAULT0,is_verified TINYINT DEFAULT0,is_active TINYINT DEFAULT1,verification_status VARCHAR(16) DEFAULT 'none',auth_version INT DEFAULT0,country_code CHAR(2) DEFAULT 'DE',mfa_secret TEXT NULL,email_verified_at DATETIME NULL) ENGINE=InnoDB;".replace('DEFAULT0','DEFAULT 0').replace('DEFAULT1','DEFAULT 1')
  migration=(ROOT/'sql/migrations/010_legacy_import.sql').read_text()
  for name in (cls.target,cls.collision):statement+='USE '+name+';'+users+migration+"GRANT SELECT,INSERT,UPDATE ON "+name+".* TO 'gunindex_test'@'%';"
  statement+='USE '+cls.collision+";INSERT INTO users(id,username,email,password_hash) VALUES(1,'collision_fixture','collision@example.invalid','UNKNOWN');"
  sql(statement)
 @classmethod
 def tearDownClass(cls):
  # Drop only this run's randomly named synthetic login; keep scratch data for inspection.
  if hasattr(cls,'account'):sql("DROP USER IF EXISTS '"+cls.account+"'@'%';")
  if hasattr(cls,'directory'):
   for file in cls.directory.iterdir():file.unlink()
   cls.directory.rmdir()
 def run_import(self,*options,target=None):
  path='/app/'+str(self.environment.relative_to(ROOT))
  return call(['exec','-e','DB_DATABASE='+(target or self.target),APP,'php','ops/import-legacy.php','--source-env='+path,*options])
 def test_01_dry_run_does_not_write_or_copy_privileges(self):
  result=self.run_import();self.assertEqual(0,result.returncode);report=json.loads(result.stdout);self.assertEqual(2,report['importable']);self.assertFalse(report['historical_privileges_imported']);self.assertEqual('0',sql('SELECT COUNT(*) FROM '+self.target+'.users;'))
 def test_02_apply_requires_backup_confirmation(self):
  self.assertNotEqual(0,self.run_import('--apply').returncode);self.assertEqual('0',sql('SELECT COUNT(*) FROM '+self.target+'.users;'))
 def test_03_apply_preserves_ids_but_never_old_hash_or_admin(self):
  result=self.run_import('--apply','--backup-confirmed');self.assertEqual(0,result.returncode);self.assertEqual(2,json.loads(result.stdout)['imported'])
  aggregate=sql("SELECT COUNT(*),MIN(id),MAX(id),SUM(is_admin),SUM(is_verified),SUM(mfa_secret IS NOT NULL),SUM(email_verified_at IS NOT NULL),SUM(password_hash='UNTRUSTED-OLD-HASH') FROM "+self.target+'.users;')
  self.assertEqual('2\t1\t2\t0\t0\t0\t0\t0',aggregate)
 def test_04_repeated_import_is_idempotent(self):
  result=self.run_import('--apply','--backup-confirmed');self.assertEqual(0,result.returncode);report=json.loads(result.stdout);self.assertEqual(0,report['imported']);self.assertEqual(2,report['already_mapped']);self.assertEqual('2',sql('SELECT COUNT(*) FROM '+self.target+'.users;'))
 def test_05_id_collision_is_atomic_and_can_be_explicitly_remapped(self):
  self.assertNotEqual(0,self.run_import('--apply','--backup-confirmed',target=self.collision).returncode);self.assertEqual('1',sql('SELECT COUNT(*) FROM '+self.collision+'.users;'))
  result=self.run_import('--apply','--backup-confirmed','--remap-ids',target=self.collision);self.assertEqual(0,result.returncode);self.assertEqual(2,json.loads(result.stdout)['imported']);self.assertEqual('3',sql('SELECT COUNT(*) FROM '+self.collision+'.users;'))
 def test_06_source_stays_unchanged_and_no_listing_table_is_created(self):
  self.assertEqual('2\t1\t2',sql('SELECT COUNT(*),MIN(id),MAX(id) FROM '+self.source+'.users;'))
  self.assertEqual('0',sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='"+self.target+"' AND TABLE_NAME='market_listings';"))
if __name__=='__main__':unittest.main(verbosity=2)
