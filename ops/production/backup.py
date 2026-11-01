#!/usr/bin/env python3
"""Encrypted production DB/private-file snapshots and restore into a NEW schema.

No plaintext SQL is saved. Backup key is mounted only into an ops container.
Never overwrites a database or switches the application to a restored schema.
"""
import argparse,datetime,hashlib,hmac,json,os,re,secrets,subprocess,sys,tarfile
from pathlib import Path

def file_digest(path):
 digest=hashlib.sha256()
 with open(path,'rb') as handle:
  for chunk in iter(lambda:handle.read(1048576),b''):digest.update(chunk)
 return digest.hexdigest()

class Operations:
 def __init__(self,directory):
  self.base=Path(directory).resolve();self.wrapper=self.base/'compose.sh'
  if not self.wrapper.is_file() or self.wrapper.is_symlink():raise RuntimeError('Managed compose wrapper is missing.')
 def run(self,args,data=None,capture=True):
  p=subprocess.run([str(self.wrapper)]+args,input=data,stdout=subprocess.PIPE if capture else None,stderr=subprocess.DEVNULL)
  if p.returncode:raise RuntimeError('Deployment command failed; inspect restricted service logs.')
  return p.stdout or b''
 def pipe(self,args,**kwargs):
  return subprocess.Popen([str(self.wrapper)]+args,stderr=subprocess.DEVNULL,**kwargs)
 def dump_args(self,database=None,data_only=False):
  options='--single-transaction --no-tablespaces --default-character-set=utf8mb4 --hex-blob --skip-comments --skip-add-locks --order-by-primary'
  if data_only:options+=' --no-create-info --skip-disable-keys --compact'
  if database is None:
   command='MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump '+options+' -u"$MYSQL_USER" "$MYSQL_DATABASE"'
  else:command='MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump '+options+' -uroot '+database
  return ['exec','-T','db','sh','-c',command]
 def mysql(self,query):
  return self.run(['exec','-T','db','sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --default-character-set=utf8mb4 -uroot -N'],query.encode())
 def encrypt_stream(self,source_args,destination):
  fd=os.open(destination,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600);digest=hashlib.sha256()
  with os.fdopen(fd,'wb') as archive:
   source=self.pipe(source_args,stdout=subprocess.PIPE)
   encrypted=self.pipe(['run','--rm','-T','--no-deps','backup','ops/backup.php','encrypt','/run/backup.key'],stdin=subprocess.PIPE,stdout=archive)
   try:
    while True:
     chunk=source.stdout.read(1048576)
     if not chunk:break
     digest.update(chunk);encrypted.stdin.write(chunk)
    encrypted.stdin.close();source.stdout.close()
    if source.wait()!=0 or encrypted.wait()!=0:raise RuntimeError('Snapshot stream failed; incomplete archive retained privately.')
   finally:
    if source.poll() is None:source.kill();source.wait()
    if encrypted.poll() is None:encrypted.kill();encrypted.wait()
  if self.decrypt_digest(destination)!=digest.hexdigest():raise RuntimeError('Snapshot verification failed.')
  return digest.hexdigest()
 def decrypt_process(self,archive):
  handle=open(archive,'rb')
  try:p=self.pipe(['run','--rm','-T','--no-deps','backup','ops/backup.php','decrypt','/run/backup.key'],stdin=handle,stdout=subprocess.PIPE)
  finally:handle.close()
  return p
 def decrypt_digest(self,archive):
  p=self.decrypt_process(archive);digest=hashlib.sha256()
  try:
   while True:
    data=p.stdout.read(1048576)
    if not data:break
    digest.update(data)
   p.stdout.close()
   if p.wait()!=0:raise RuntimeError('Archive authentication failed.')
  finally:
   if p.poll() is None:p.kill();p.wait()
  return digest.hexdigest()
 def stream_digest(self,args):
  p=self.pipe(args,stdout=subprocess.PIPE);digest=hashlib.sha256()
  try:
   while True:
    data=p.stdout.read(1048576)
    if not data:break
    digest.update(data)
   p.stdout.close()
   if p.wait()!=0:raise RuntimeError('Snapshot comparison failed.')
  finally:
   if p.poll() is None:p.kill();p.wait()
  return digest.hexdigest()
 def metadata(self,database=None):
  args=['run','--rm','-T','--no-deps']
  if database:args+=['-e','DB_DATABASE='+database]
  return json.loads(self.run(args+['backup','ops/production/schema-metadata.php']))
 def manifest_signature(self,metadata):
  canonical=json.dumps(metadata,sort_keys=True,ensure_ascii=False,separators=(',',':')).encode()
  signature=self.run(['run','--rm','-T','--no-deps','backup','ops/production/manifest-auth.php'],canonical).decode().strip()
  if not re.fullmatch(r'[a-f0-9]{64}',signature):raise RuntimeError('Manifest authentication failed.')
  return signature
 def backup(self,maintenance=False,leave_stopped=False):
  running=set(self.run(['ps','--status','running','--services']).decode().splitlines())&{'php','worker'}
  if running and not maintenance:raise RuntimeError('Writers are running. Use --maintenance for a consistent DB/files snapshot.')
  if running:self.run(['stop']+sorted(running))
  destination=self.base/'backups';destination.mkdir(mode=0o700,exist_ok=True)
  stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-'+secrets.token_hex(4)
  prefix=destination/stamp
  try:
   database=prefix.with_suffix('.db.gib');private=prefix.with_suffix('.files.gib')
   meta={'format':'gunindex-snapshot-1','created_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'database_archive':database.name,'private_archive':private.name}
   meta['database_plaintext_sha256']=self.encrypt_stream(self.dump_args(),database)
   meta['database_data_sha256']=self.stream_digest(self.dump_args(data_only=True))
   meta['schema']=self.metadata()
   meta['private_plaintext_sha256']=self.encrypt_stream(['run','--rm','-T','--no-deps','--entrypoint','tar','backup','-C','/var/lib/gunindex','-cf','-','.'],private)
   meta['database_archive_sha256']=file_digest(database);meta['private_archive_sha256']=file_digest(private)
   meta['manifest_hmac']=self.manifest_signature(meta)
   fd=os.open(prefix.with_suffix('.json'),os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
   with os.fdopen(fd,'w') as handle:json.dump(meta,handle,ensure_ascii=False)
   print('Verified encrypted snapshot: '+str(prefix.with_suffix('.json')))
   return prefix.with_suffix('.json')
  finally:
   if running and not leave_stopped:self.run(['up','-d']+sorted(running))
 def restore(self,manifest,database,files_directory):
  if not re.fullmatch(r'gunindex_restore_[a-z0-9_]{4,60}',database):raise RuntimeError('Restore target must be a NEW gunindex_restore_* schema.')
  manifest=Path(manifest).absolute()
  if manifest.is_symlink() or not manifest.is_file():raise RuntimeError('A regular snapshot manifest is required.')
  meta=json.loads(manifest.read_text())
  if meta.get('format')!='gunindex-snapshot-1':raise RuntimeError('Unknown snapshot format.')
  signature=meta.pop('manifest_hmac',None)
  if not isinstance(signature,str) or not hmac.compare_digest(signature,self.manifest_signature(meta)):raise RuntimeError('Manifest authentication failed.')
  archives=[]
  for label in ('database','private'):
   name=meta[label+'_archive']
   if not isinstance(name,str) or Path(name).name!=name or not name.endswith('.gib'):raise RuntimeError('Unsafe archive filename.')
   archive=manifest.parent/name
   if archive.is_symlink() or file_digest(archive)!=meta[label+'_archive_sha256']:raise RuntimeError('Archive digest mismatch.')
   if self.decrypt_digest(archive)!=meta[label+'_plaintext_sha256']:raise RuntimeError('Plaintext digest mismatch.')
   archives.append(archive)
  target=Path(files_directory).resolve()
  if target.exists():raise RuntimeError('Private-file restore directory must not already exist.')
  if self.mysql("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='"+database+"';").strip()!=b'0':raise RuntimeError('Restore target exists; no overwrite is supported.')
  self.mysql('CREATE DATABASE '+database+' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT SELECT ON '+database+'.* TO \'gunindex_app\'@\'%\';')
  opened=self.decrypt_process(archives[0]);imported=self.pipe(['exec','-T','db','sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --default-character-set=utf8mb4 -uroot '+database],stdin=opened.stdout,stdout=subprocess.DEVNULL);opened.stdout.close()
  if imported.wait()!=0 or opened.wait()!=0:raise RuntimeError('Fresh restore failed; target preserved for diagnosis.')
  if self.stream_digest(self.dump_args(database,True))!=meta['database_data_sha256'] or self.metadata(database)!=meta['schema']:raise RuntimeError('Restored schema/data mismatch; target preserved.')
  target.mkdir(mode=0o700,parents=True);opened=self.decrypt_process(archives[1]);total=0
  try:
   with tarfile.open(fileobj=opened.stdout,mode='r|') as archive:
    for member in archive:
     name=Path(member.name)
     if name.is_absolute() or '..' in name.parts or not(member.isdir() or member.isfile()):raise RuntimeError('Unsafe private-file archive member.')
     destination=target/name
     if member.isdir():destination.mkdir(mode=0o700,parents=True,exist_ok=True);continue
     total+=member.size
     if member.size<0 or total>50*1024**3:raise RuntimeError('Restore exceeds the reviewed 50GiB limit.')
     destination.parent.mkdir(mode=0o700,parents=True,exist_ok=True);source=archive.extractfile(member)
     fd=os.open(destination,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
     with os.fdopen(fd,'wb') as out:
      while True:
       data=source.read(1048576)
       if not data:break
       out.write(data)
   opened.stdout.close()
   if opened.wait()!=0:raise RuntimeError('Private archive authentication failed.')
  finally:
   if opened.poll() is None:opened.kill();opened.wait()
  print('Validated fresh restore: '+database+'; private files: '+str(target))
  print('Application was NOT switched. Revoke historical sessions and perform operator acceptance before reopening.')

if __name__=='__main__':
 parser=argparse.ArgumentParser();parser.add_argument('--directory',default='/srv/gunindex');sub=parser.add_subparsers(dest='action',required=True)
 backup=sub.add_parser('backup');backup.add_argument('--maintenance',action='store_true');backup.add_argument('--leave-stopped',action='store_true')
 restore=sub.add_parser('restore');restore.add_argument('--manifest',required=True);restore.add_argument('--database',required=True);restore.add_argument('--files-directory',required=True)
 args=parser.parse_args()
 try:
  ops=Operations(args.directory)
  if args.action=='backup':ops.backup(args.maintenance,args.leave_stopped)
  else:ops.restore(args.manifest,args.database,args.files_directory)
 except (RuntimeError,ValueError,OSError,KeyError,tarfile.TarError):
  print('Operation stopped. No existing database was overwritten. Check private configuration and restricted service logs.',file=sys.stderr);raise SystemExit(1)
