#!/usr/bin/env python3
"""Isolated, repeatable development runtime. Never imports historical data."""
import argparse, base64, hashlib, json, os, secrets, subprocess, sys, time, urllib.request
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
RUNTIME=ROOT/'tests/.runtime'
DOCKER=['docker','--host=unix:///var/run/docker.sock']
ENV={k:v for k,v in os.environ.items() if k not in ('DOCKER_HOST','DOCKER_CONTEXT','DOCKER_TLS','DOCKER_TLS_VERIFY','DOCKER_CERT_PATH')}
DB='gunindex-rebuild-db';APP='gunindex-rebuild-app';WORKER='gunindex-rebuild-worker';IMAGE='gunindex-rebuild-php:local'
MYSQL='mysql:8.0@sha256:7dcddc01f13bab2f15cde676d44d01f61fc9f99fe7785e86196dfc07d358ae2b'
def docker(*args,capture=False,data=None):
 if sys.platform != 'linux':
  raise RuntimeError('This runtime requires Linux. On Windows use Ubuntu WSL2 with Docker integration; see docs/WINDOWS_DEVELOPMENT.md. No Docker action was executed.')
 return subprocess.run(DOCKER+list(args),env=ENV,input=data,check=True,stdout=subprocess.PIPE if capture else None,stderr=subprocess.PIPE if capture else None)
def exists(name):
 if sys.platform != 'linux':
  raise RuntimeError('Container inspection requires Linux; see docs/WINDOWS_DEVELOPMENT.md.')
 return subprocess.run(DOCKER+['inspect',name],env=ENV,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL).returncode==0
def private(path,body):
 fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
 with os.fdopen(fd,'w') as handle: handle.write(body)
def sql(body):
 return docker('exec','-i',DB,'sh','-c','MYSQL_PWD="$MYSQL_PASSWORD" exec mysql --default-character-set=utf8mb4 -u"$MYSQL_USER" -N "$MYSQL_DATABASE"',capture=True,data=body)
def up():
 docker('info','--format','{{.ServerVersion}}')
 RUNTIME.mkdir(mode=0o700,parents=True,exist_ok=True);os.chmod(RUNTIME,0o700)
 dbfile,appfile=RUNTIME/'database.env',RUNTIME/'application.env'
 if not dbfile.exists() and not appfile.exists():
  if exists(DB) or exists(APP):raise RuntimeError('Existing runtime lacks configuration; restore it instead of replacing credentials.')
  rootsecret,secret=secrets.token_urlsafe(48),secrets.token_urlsafe(48)
  private(dbfile,'MYSQL_ROOT_PASSWORD='+rootsecret+'\nMYSQL_DATABASE=gunindex_rebuild_test\nMYSQL_USER=gunindex_test\nMYSQL_PASSWORD='+secret+'\n')
  private(appfile,'DB_DATABASE=gunindex_rebuild_test\nDB_USERNAME=gunindex_test\nDB_PASSWORD='+secret+'\nDB_SOCKET=/var/run/mysqld/mysqld.sock\nAPP_KEY='+base64.b64encode(secrets.token_bytes(32)).decode()+'\nSESSION_COOKIE_SECURE=0\nAPP_ENV=development\nAUTH_RATE_LIMIT_DIR=/var/lib/gunindex/limits\nGENERAL_IMAGE_DIR=/var/lib/gunindex/images\n')
 if not dbfile.exists() or not appfile.exists():raise RuntimeError('Incomplete private configuration; restore before proceeding.')
 # Only synthetic runtime configuration is updated; existing secrets are preserved.
 names={line.split('=',1)[0] for line in appfile.read_text().splitlines() if '=' in line}
 with appfile.open('a') as handle:
  for name,value in {'APP_URL':'http://127.0.0.1:8081','MAIL_TRANSPORT':'test'}.items():
   if name not in names:handle.write(name+'='+value+'\n')
 if exists(WORKER):docker('stop',WORKER,capture=True)
 docker('build','--secret','id=proxy_ca,src=/etc/ssl/certs/ca-certificates.crt','-q','-t',IMAGE,'-f',str(ROOT/'ops/Dockerfile'),str(ROOT))
 if (ROOT/'composer.lock').exists():
  lock_hash=hashlib.sha256((ROOT/'composer.lock').read_bytes()).hexdigest()
  marker=RUNTIME/'composer-lock.sha256'
  if not (ROOT/'vendor/autoload.php').exists() or not marker.exists() or marker.read_text()!=lock_hash:
   docker('run','--rm','-v',str(ROOT)+':/app','-v','/etc/ssl/certs/ca-certificates.crt:/run/gunindex-ca.pem:ro','-e','COMPOSER_CAFILE=/run/gunindex-ca.pem','-e','GIT_SSL_CAINFO=/run/gunindex-ca.pem','-e','COMPOSER_ALLOW_SUPERUSER=1','composer:2.8.12@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c','sh','-c','git config --global --add safe.directory /app && composer install --no-dev --no-interaction --prefer-source --no-scripts --no-plugins',capture=True)
   marker.write_text(lock_hash)
 if not exists(DB):
  docker('run','-d','--name',DB,'--network','none','--env-file',str(dbfile),'-v','gunindex-rebuild-data:/var/lib/mysql','-v','gunindex-rebuild-socket:/var/run/mysqld',MYSQL,capture=True)
 else:docker('start',DB,capture=True)
 for _ in range(60):
  try:sql(b'SELECT 1;');break
  except subprocess.CalledProcessError:time.sleep(1)
 else:raise RuntimeError('Isolated MySQL is not ready.')
 count=sql(b'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE();').stdout.strip()
 if count==b'0':sql((ROOT/'sql/schema.sql').read_bytes())
 else:
  if sql(b"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='market_listings';").stdout.strip()!=b'1':raise RuntimeError('Existing database needs a reviewed migration; it will not be reset.')
 for container,worker in ((APP,False),(WORKER,True)):
  if exists(container):
   old=docker('inspect','--format','{{.Image}}',container,capture=True).stdout.strip()
   new=docker('image','inspect','--format','{{.Id}}',IMAGE,capture=True).stdout.strip()
   version=docker('inspect','--format','{{index .Config.Labels \"gunindex.runtime\"}}',container,capture=True).stdout.strip()
   if old!=new or version!=b'phase2':docker('stop',container,capture=True);docker('rm',container,capture=True)
  if not exists(container):
   args=['run','-d','--name',container,'--label','gunindex.runtime=phase2','--env-file',str(appfile),'-v',str(ROOT)+':/app:ro','-v','gunindex-rebuild-socket:/var/run/mysqld','-v','gunindex-rebuild-private:/var/lib/gunindex','-v','/etc/ssl/certs/ca-certificates.crt:/run/gunindex-ca.pem:ro','-e','CURL_CA_BUNDLE=/run/gunindex-ca.pem']
   for name in ('PAYPAL_CLIENT_ID','PAYPAL_CLIENT_SECRET','PAYPAL_MERCHANT_ID','PAYPAL_WEBHOOK_ID','PAYPAL_MODE','PAYPAL_ACCOUNT_TYPE','PAYPAL_BUSINESS_SCOPE','PAYPAL_ENABLED','PLATFORM_BILLING_APPROVED','PAYPAL_LIVE_APPROVED','APP_URL'):
    if name in os.environ:args+=['-e',name]
   if worker:
    args+=['--restart','unless-stopped',IMAGE,'sh','-c','exec sh ops/production/worker.sh']
   else:args+=['-p','127.0.0.1:8081:8081',IMAGE,'php','-d','memory_limit=256M','-d','upload_max_filesize=5M','-d','post_max_size=28M','-d','max_file_uploads=20','-S','0.0.0.0:8081','-t','public','public/index.php']
   docker(*args,capture=True)
  else:docker('start',container,capture=True)
 docker('exec',APP,'sh','-c','mkdir -p /var/lib/gunindex/limits /var/lib/gunindex/images /var/lib/gunindex/private /var/lib/gunindex/heartbeats && chmod 700 /var/lib/gunindex /var/lib/gunindex/limits /var/lib/gunindex/images /var/lib/gunindex/private /var/lib/gunindex/heartbeats',capture=True)
 from migration_support import migrate
 if exists(WORKER):docker('stop',WORKER,capture=True)
 migrate(docker,sql,APP,DB,RUNTIME,ROOT)
 opener=urllib.request.build_opener(urllib.request.ProxyHandler({}))
 for _ in range(30):
  try:
   with opener.open('http://127.0.0.1:8081/',timeout=2) as response:
    if response.status==200 and b'<!' in response.read():docker('start',WORKER,capture=True);print('General marketplace development HTTP ready.');return
  except (OSError,urllib.error.HTTPError):time.sleep(1)
 raise RuntimeError('HTTP readiness failed; inspect application logs.')
def test():
 up();docker('stop',WORKER,capture=True)
 try:
  docker('exec',APP,'sh','-c',"find /app -name '*.php' -not -path '/app/tests/.runtime/*' -not -path '/app/vendor/*' -print0 | xargs -0 -n1 php -l")
  for suite in ('http_test.py','manual_http_test.py','business_http_test.py','installation_test.py'):
   subprocess.run([sys.executable,str(ROOT/'tests'/suite)],cwd=ROOT,env=ENV,check=True)
  for suite,arguments in (('payments_unit.php',[]),('auth_test.php',[]),('admin_unit.php',[]),('accounts_mail_test.php',[]),('geo_test.php',['--database']),('discovery_test.php',['--database']),('business_test.php',[]),('governance_test.php',[]),('finance_test.php',[]),('chat_test.php',[])):
   docker('exec',APP,'php','tests/'+suite,*arguments)
  for suite in ('legacy_import_test.py','chat_migration_test.py','production_manifest_test.py','backup_test.py'):
   subprocess.run([sys.executable,str(ROOT/'tests'/suite)],cwd=ROOT,env=ENV,check=True)
 finally:docker('start',WORKER,capture=True)
if __name__=='__main__':
 parser=argparse.ArgumentParser();parser.add_argument('action',choices=['up','test','stop'],default='test',nargs='?');action=parser.parse_args().action
 if sys.platform != 'linux':
  parser.exit(2,'Linux runtime required: use Ubuntu WSL2 with Docker integration. See docs/WINDOWS_DEVELOPMENT.md. No services or files were changed.\n')
 if action=='up':up()
 elif action=='test':test()
 else:
  for name in (WORKER,APP,DB):
   if exists(name):docker('stop',name,capture=True)
  print('Only development services stopped; persistent volumes retained.')
