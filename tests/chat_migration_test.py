#!/usr/bin/env python3
"""Verified encrypted backup followed by conversion, only in the synthetic DB."""
import hashlib,json,os,secrets,subprocess,tempfile,unittest
from pathlib import Path
from environment import docker,sql,APP,DB,ROOT,RUNTIME
from http_test import fixture,identity,title
class ChatMigration(unittest.TestCase):
 def test_verified_backup_conversion_keeps_legacy_text_and_metadata(self):
  self.assertEqual(b'gunindex_rebuild_test',sql(b'SELECT DATABASE();').stdout.strip())
  seller,buyer=identity(),identity()
  fixture('seed',username=seller,password='Synthetic '+secrets.token_hex(20))
  buyer_id=fixture('seed',username=buyer,password='Synthetic '+secrets.token_hex(20))['id']
  category=int(fixture('categories')['categories'][0]['id']);listing=fixture('seed_listing',username=seller,title=title(),category_id=category,type='fixed')['id']
  code=r'''require '/app/config/bootstrap.php';if(getenv('DB_DATABASE')!=='gunindex_rebuild_test')exit(2);$v=json_decode(stream_get_contents(STDIN),true,8,JSON_THROW_ON_ERROR);$q=$pdo->prepare("INSERT INTO market_messages(listing_id,sender_id,receiver_id,message_text,cipher_version,is_read,created_at) SELECT id,?,seller_id,'gx1:legacy-prefix-in-synthetic-test',0,1,'2020-01-02 03:04:05' FROM market_listings WHERE id=?");$q->execute([$v['buyer'],$v['listing']]);echo json_encode(['id'=>(int)$pdo->lastInsertId()]);'''
  response=docker('exec','-i',APP,'php','-r',code,capture=True,data=json.dumps({'buyer':buyer_id,'listing':listing}).encode());message=json.loads(response.stdout)['id']
  # The CLI must refuse mutation without an explicit backup acknowledgement.
  refused=subprocess.run(['docker','--host=unix:///var/run/docker.sock','exec',APP,'php','ops/encrypt-chats.php','--apply'],capture_output=True);self.assertEqual(2,refused.returncode)
  with tempfile.TemporaryDirectory(prefix='chat-backup-',dir=RUNTIME) as directory:
   key=Path(directory)/'key';key.write_bytes(secrets.token_bytes(32));key.chmod(0o600);key_path='/app/'+str(key.relative_to(ROOT))
   dump=docker('exec',DB,'sh','-c','MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump --single-transaction --no-tablespaces --hex-blob -u"$MYSQL_USER" "$MYSQL_DATABASE"',capture=True).stdout
   encrypted=docker('exec','-i',APP,'php','ops/backup.php','encrypt',key_path,capture=True,data=dump).stdout
   opened=docker('exec','-i',APP,'php','ops/backup.php','decrypt',key_path,capture=True,data=encrypted).stdout
   self.assertEqual(hashlib.sha256(dump).digest(),hashlib.sha256(opened).digest())
   archive=Path(directory)/'before-conversion.gib';archive.write_bytes(encrypted);archive.chmod(0o600)
   docker('exec',APP,'php','ops/encrypt-chats.php','--apply','--backup-confirmed',capture=True)
   self.assertEqual(b'0',sql(b'SELECT COUNT(*) FROM market_messages WHERE cipher_version=0;').stdout.strip())
   check=r'''require '/app/config/bootstrap.php';if(getenv('DB_DATABASE')!=='gunindex_rebuild_test')exit(2);$id=json_decode(stream_get_contents(STDIN),true,8,JSON_THROW_ON_ERROR)['id'];$q=$pdo->prepare('SELECT * FROM market_messages WHERE id=?');$q->execute([$id]);$r=$q->fetch();echo (int)($r&&$r['cipher_version']==1&&$r['is_read']==1&&$r['created_at']==='2020-01-02 03:04:05'&&chat_message_plain($r['message_text'],1)==='gx1:legacy-prefix-in-synthetic-test');'''
   self.assertEqual(b'1',docker('exec','-i',APP,'php','-r',check,capture=True,data=json.dumps({'id':message}).encode()).stdout)
   self.assertIn(b'Encrypted messages: 0',docker('exec',APP,'php','ops/encrypt-chats.php','--apply','--backup-confirmed',capture=True).stdout)
if __name__=='__main__':unittest.main(verbosity=2)
