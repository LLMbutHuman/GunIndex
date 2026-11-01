#!/usr/bin/env python3
"""Private deployment config; no secret values are printed or regenerated."""
import argparse,base64,hashlib,json,os,re,secrets
from pathlib import Path

def private(path,body,mode=0o600):
 fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,mode)
 with os.fdopen(fd,'wb') as out:out.write(body if isinstance(body,bytes) else body.encode())

def configure(directory,domain,email,release,source):
 base=Path(directory).resolve();config=base/'config';keys=base/'keys';source=Path(source).resolve()
 for path in (base,config,keys,base/'backups',base/'releases'):path.mkdir(mode=0o700,parents=True,exist_ok=True)
 files=(config/'app.env',config/'database.env',keys/'backup.key')
 if any(p.is_symlink() for p in files):raise RuntimeError('Configuration links are not accepted.')
 if any(p.exists() for p in files) and not all(p.exists() for p in files):raise RuntimeError('Incomplete configuration; restore it instead of replacing keys.')
 if not any(p.exists() for p in files):
  password=secrets.token_urlsafe(48);root_password=secrets.token_urlsafe(48);app_key=base64.b64encode(secrets.token_bytes(32)).decode()
  private(config/'database.env',f'MYSQL_DATABASE=gunindex\nMYSQL_USER=gunindex_app\nMYSQL_PASSWORD={password}\nMYSQL_ROOT_PASSWORD={root_password}\n')
  private(config/'app.env',f'DB_DATABASE=gunindex\nDB_USERNAME=gunindex_app\nDB_PASSWORD={password}\nDB_HOST=db\nDB_PORT=3306\nAPP_KEY={app_key}\nAPP_URL=https://{domain}\nAPP_ENV=production\nSESSION_COOKIE_SECURE=1\nAUTH_RATE_LIMIT_DIR=/var/lib/gunindex/limits\nGENERAL_IMAGE_DIR=/var/lib/gunindex/images\nGENERAL_PRIVATE_DIR=/var/lib/gunindex/private\nMAIL_TRANSPORT=none\nMAIL_FROM=no-reply@{domain}\nMAIL_SENDMAIL_PATH=/usr/sbin/sendmail\nPAYPAL_ACCOUNT_TYPE=personal\nPAYPAL_MODE=sandbox\nPAYPAL_BUSINESS_SCOPE=general_only\nPAYPAL_ENABLED=0\nPLATFORM_BILLING_APPROVED=0\nPAYPAL_LIVE_APPROVED=0\n')
  private(keys/'backup.key',secrets.token_bytes(32))
 else:
  for path in files:
   if path.stat().st_mode&0o077:raise RuntimeError('Private configuration has unsafe permissions.')
  old=(config/'compose.env').read_text() if (config/'compose.env').exists() else ''
  if 'DOMAIN='+domain+'\n' not in old:raise RuntimeError('Existing domain differs; preserve and review the installation.')
 manifest_path=config/'managed.json';manifest=json.loads(manifest_path.read_text()) if manifest_path.exists() else {}
 def managed(name,body,mode=0o644):
  path=config/name;encoded=body.encode()
  if path.exists() and hashlib.sha256(path.read_bytes()).hexdigest()!=manifest.get(name):raise RuntimeError('Administrator-modified file needs manual merge: '+name)
  temp=config/(name+'.new');temp.write_bytes(encoded);temp.chmod(mode);temp.replace(path);manifest[name]=hashlib.sha256(encoded).hexdigest()
 managed('compose.yml',(source/'ops/production/compose.yml').read_text())
 managed('msmtprc',f'defaults\nlogfile /tmp/msmtp.log\nauth off\ntls off\naccount default\nhost mailer\nport 25\nfrom no-reply@{domain}\n')
 if not (config/'nginx.conf').exists():managed('nginx.conf',(source/'ops/production/nginx-bootstrap.conf').read_text())
 # TLS nginx configuration is deliberately not overwritten during an update.
 (config/'managed.json').write_text(json.dumps(manifest,indent=2));(config/'managed.json').chmod(0o600)
 compose_env=f'INSTALL_DIR={base}\nENV_DIR={config}\nKEY_DIR={keys}\nAPP_SOURCE={source}\nRELEASE_ID={release}\nDOMAIN={domain}\nBUILD_CA_FILE=/etc/ssl/certs/ca-certificates.crt\n'
 (config/'compose.env').write_text(compose_env);(config/'compose.env').chmod(0o600)
 for name,mode in (('acme',0o755),('tls',0o710)):
  path=base/name;path.mkdir(mode=mode,exist_ok=True);path.chmod(mode)
 if os.geteuid()==0:os.chown(base/'tls',0,101)
 units=config/'systemd';units.mkdir(mode=0o700,exist_ok=True)
 for template in (source/'ops/production').glob('gunindex-*.*'):
  if template.suffix in ('.service','.timer'):
   unit=units/template.name;unit.write_text(template.read_text().replace('__DIRECTORY__',str(base)));unit.chmod(0o600)
 wrapper='#!/bin/sh\nset -eu\nbase="'+str(base)+'"\nexec env -u DOCKER_HOST -u DOCKER_CONTEXT -u DOCKER_TLS -u DOCKER_TLS_VERIFY -u DOCKER_CERT_PATH docker --host=unix:///var/run/docker.sock compose --project-name gunindex --env-file "$base/config/compose.env" -f "$base/config/compose.yml" "$@"\n'
 path=base/'compose.sh';path.write_text(wrapper);path.chmod(0o700)
 renew=f'''#!/bin/sh
set -eu
base="{base}"
install -o 0 -g 101 -m 0644 "/etc/letsencrypt/live/{domain}/fullchain.pem" "$base/tls/fullchain.pem"
install -o 0 -g 101 -m 0640 "/etc/letsencrypt/live/{domain}/privkey.pem" "$base/tls/privkey.pem"
sed 's/__DOMAIN__/{domain}/g' "$base/current/ops/production/nginx.conf.template" > "$base/config/nginx.conf.new"
chmod 0644 "$base/config/nginx.conf.new"
cat "$base/config/nginx.conf.new" > "$base/config/nginx.conf"
rm -f "$base/config/nginx.conf.new"
"$base/compose.sh" exec -T web nginx -t
"$base/compose.sh" exec -T web nginx -s reload
'''
 path=base/'renew-certificate.sh';path.write_text(renew);path.chmod(0o700)

if __name__=='__main__':
 parser=argparse.ArgumentParser();parser.add_argument('--directory',required=True);parser.add_argument('--domain',required=True);parser.add_argument('--email',required=True);parser.add_argument('--release',required=True);parser.add_argument('--source',type=Path,required=True);a=parser.parse_args()
 if not re.fullmatch(r'(?:[a-z0-9-]+\.)+[a-z]{2,63}',a.domain) or not re.fullmatch(r'[A-Za-z0-9_.-]+',a.release):parser.error('Invalid domain or release.')
 configure(a.directory,a.domain,a.email,a.release,a.source)
