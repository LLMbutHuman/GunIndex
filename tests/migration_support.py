"""Verified encrypted pre-migration snapshots, restricted to the synthetic DB."""
import hashlib,os,secrets
from pathlib import Path
def migrate(docker,sql,app,db,runtime,root):
 if sql(b'SELECT DATABASE();').stdout.strip()!=b'gunindex_rebuild_test':raise RuntimeError('Automatic migrations require the isolated test database.')
 versions={p.name:hashlib.sha256(p.read_bytes()).hexdigest() for p in (root/'sql/migrations').glob('*.sql')}
 exists=sql(b"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='schema_migrations';").stdout.strip()==b'1'
 applied={}
 if exists:
  for line in sql(b'SELECT version,checksum FROM schema_migrations;').stdout.decode().splitlines():
   version,checksum=line.split('\t');applied[version]=checksum
 for name,digest in applied.items():
  if name in versions and versions[name]!=digest:raise RuntimeError('An applied migration was modified.')
 if all(applied.get(name)==digest for name,digest in versions.items()):return
 key=runtime/'migration-backup.key'
 if not key.exists():
  fd=os.open(key,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
  with os.fdopen(fd,'wb') as handle:handle.write(secrets.token_bytes(32))
 if key.is_symlink() or key.stat().st_mode&0o077:raise RuntimeError('Unsafe backup key permissions.')
 command='MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump --single-transaction --no-tablespaces --hex-blob -u"$MYSQL_USER" "$MYSQL_DATABASE"'
 dump=docker('exec',db,'sh','-c',command,capture=True).stdout
 encrypted=docker('exec','-i',app,'php','ops/backup.php','encrypt','/app/tests/.runtime/migration-backup.key',capture=True,data=dump).stdout
 verified=docker('exec','-i',app,'php','ops/backup.php','decrypt','/app/tests/.runtime/migration-backup.key',capture=True,data=encrypted).stdout
 if hashlib.sha256(dump).digest()!=hashlib.sha256(verified).digest():raise RuntimeError('Backup verification failed.')
 dest=runtime/('before-migration-'+secrets.token_hex(8)+'.gib')
 fd=os.open(dest,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
 with os.fdopen(fd,'wb') as handle:handle.write(encrypted)
 docker('exec',app,'php','ops/migrate.php','--backup-confirmed')
