#!/usr/bin/env python3
"""Authenticated backup errors and an actual synthetic MySQL restore."""
import hashlib,os,secrets,subprocess,tempfile,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];DOCKER=['docker','--host=unix:///var/run/docker.sock'];APP='gunindex-rebuild-app';DB='gunindex-rebuild-db'
def command(args,data=None):return subprocess.run(DOCKER+args,input=data,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
class Backups(unittest.TestCase):
 def setUp(self):
  self.directory=Path(tempfile.mkdtemp(prefix='crypto-',dir=ROOT/'tests/.runtime'));self.key=self.directory/'key';self.key.write_bytes(secrets.token_bytes(32));self.key.chmod(0o600)
 def tearDown(self):
  for file in self.directory.iterdir():file.unlink()
  self.directory.rmdir()
 def crypto(self,mode,data,key=None):
  key=key or self.key;return command(['exec','-i',APP,'php','ops/backup.php',mode,'/app/'+str(key.relative_to(ROOT))],data)
 def test_binary_and_empty_roundtrip(self):
  for plain in (b'',b'\x00\xffsynthetic\n'*10000,secrets.token_bytes(2100000)):
   encrypted=self.crypto('encrypt',plain);self.assertEqual(0,encrypted.returncode);opened=self.crypto('decrypt',encrypted.stdout);self.assertEqual(0,opened.returncode);self.assertEqual(hashlib.sha256(plain).digest(),hashlib.sha256(opened.stdout).digest())
 def test_tamper_truncation_and_trailing_bytes_release_no_plaintext(self):
  encrypted=self.crypto('encrypt',secrets.token_bytes(1100000)).stdout
  modified=bytearray(encrypted);modified[-20]^=1
  for invalid in (bytes(modified),encrypted[:-1],encrypted+b'extra',b'wrong'+encrypted[5:]):
   result=self.crypto('decrypt',invalid);self.assertNotEqual(0,result.returncode);self.assertEqual(b'',result.stdout)
 def test_wrong_key_and_unsafe_permissions_are_rejected(self):
  encrypted=self.crypto('encrypt',b'synthetic sensitive bytes').stdout
  other=self.directory/'other';other.write_bytes(secrets.token_bytes(32));other.chmod(0o600)
  result=self.crypto('decrypt',encrypted,other);self.assertNotEqual(0,result.returncode);self.assertEqual(b'',result.stdout)
  self.key.chmod(0o644);self.assertNotEqual(0,self.crypto('encrypt',b'example').returncode)
 def test_actual_database_restore_matches_all_data(self):
  source='gunindex_rebuild_test';scratch='gunindex_restore_test_'+secrets.token_hex(8)
  def mysql(sql):
   r=command(['exec','-i',DB,'sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot -N'],sql.encode());self.assertEqual(0,r.returncode,'Synthetic restore SQL failed');return r.stdout
  def dump(database,data_only=False):
   opts='--single-transaction --no-tablespaces --hex-blob --skip-comments --skip-add-locks --order-by-primary'
   if data_only:opts+=' --no-create-info --skip-disable-keys --compact'
   r=command(['exec',DB,'sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot '+opts+' '+database]);self.assertEqual(0,r.returncode,'Synthetic dump failed');return r.stdout
  original=dump(source);data=dump(source,True)
  encrypted=self.crypto('encrypt',original);self.assertEqual(0,encrypted.returncode);opened=self.crypto('decrypt',encrypted.stdout);self.assertEqual(0,opened.returncode);self.assertEqual(hashlib.sha256(original).digest(),hashlib.sha256(opened.stdout).digest())
  mysql('CREATE DATABASE '+scratch+' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
  imported=command(['exec','-i',DB,'sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot '+scratch],opened.stdout);self.assertEqual(0,imported.returncode,'Fresh scratch restore failed')
  self.assertEqual(hashlib.sha256(data).digest(),hashlib.sha256(dump(scratch,True)).digest())
  def metadata(database):
   name="'"+database+"'"
   return mysql('SET SESSION information_schema_stats_expiry=0; SELECT TABLE_NAME,ENGINE,TABLE_COLLATION,AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA='+name+' ORDER BY TABLE_NAME; SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COALESCE(COLUMN_DEFAULT,\'NULL\'),EXTRA,COALESCE(CHARACTER_SET_NAME,\'\'),COALESCE(COLLATION_NAME,\'\') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='+name+' ORDER BY TABLE_NAME,ORDINAL_POSITION; SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='+name+' ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX; SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='+name+' ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION;')
  self.assertEqual(metadata(source),metadata(scratch));self.assertEqual(hashlib.sha256(original).digest(),hashlib.sha256(dump(source)).digest())
if __name__=='__main__':unittest.main(verbosity=2)
