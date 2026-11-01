#!/usr/bin/env bash
# Transfers already encrypted snapshots. The encryption key is never sent.
set -euo pipefail
usage(){ printf '%s\n' 'Usage: ops/offsite-backup.sh --manifest /srv/gunindex/backups/SNAPSHOT.json --destination user@backup-host --remote-directory /private/gunindex --identity /root/.ssh/backup_ed25519 --known-hosts /root/.ssh/backup_known_hosts [--check-only]'; }
manifest='' destination='' remote='' identity='' known='' check=0
while [ "$#" -gt 0 ];do
 case "$1" in
  --help|-h) usage;exit 0;; --check-only) check=1;shift;;
  --manifest|--destination|--remote-directory|--identity|--known-hosts) [ "$#" -ge 2 ]||{ usage >&2;exit 2; };key="$1";value="$2";shift 2;case "$key" in --manifest) manifest="$value";;--destination) destination="$value";;--remote-directory) remote="$value";;--identity) identity="$value";;--known-hosts) known="$value";;esac;;
  *) usage >&2;exit 2;;
 esac
done
[[ "$destination" =~ ^[A-Za-z0-9_][A-Za-z0-9_.@-]*$ ]] && [[ "$remote" =~ ^/[A-Za-z0-9_/-]+$ ]] || { printf '%s\n' 'Unsafe SSH destination or remote path.' >&2;exit 2; }
for file in "$manifest" "$identity" "$known";do [ -f "$file" ] && [ ! -L "$file" ] || { printf '%s\n' 'Regular manifest, identity and verified known-hosts files are required.' >&2;exit 2; };done
[[ "$manifest" =~ ^/[A-Za-z0-9_./-]+\.json$ ]] || { printf '%s\n' 'Unsafe manifest path.' >&2;exit 2; }
mapfile -t archives < <(python3 - "$manifest" <<'PY'
import hashlib,json,re,sys
from pathlib import Path
def file_digest(path):
 digest=hashlib.sha256()
 with path.open('rb') as handle:
  for chunk in iter(lambda:handle.read(1048576),b''):digest.update(chunk)
 return digest.hexdigest()
path=Path(sys.argv[1]);data=json.loads(path.read_text())
if data.get('format')!='gunindex-snapshot-1':raise SystemExit(2)
for key in ('database','private'):
 name=data[key+'_archive']
 if not isinstance(name,str) or not re.fullmatch(r'[A-Za-z0-9_.-]+\.gib',name) or Path(name).name!=name:raise SystemExit(2)
 file=path.parent/name
 if file.is_symlink() or not file.is_file() or file_digest(file)!=data[key+'_archive_sha256']:raise SystemExit(2)
 with file.open('rb') as stream:
  if stream.read(8)!=b'GIDXBK01':raise SystemExit(2)
 print(file)
PY
)
[ "${#archives[@]}" -eq 2 ] || { printf '%s\n' 'Encrypted archives failed validation.' >&2;exit 2; }
if [ "$check" -eq 1 ];then printf '%s\n' 'Snapshot files validated; no network connection or transfer performed.';exit 0;fi
batch="$(mktemp)";chmod 0600 "$batch";trap 'rm -f "$batch"' EXIT
printf '%s\n' "-mkdir $remote" > "$batch"
for file in "${archives[@]}" "$manifest";do
 name="$(basename "$file")";temporary="$remote/.$name.part-$(date -u +%s)"
 printf 'put %s %s\nrename %s %s\n' "$file" "$temporary" "$temporary" "$remote/$name" >> "$batch"
done
sftp -oBatchMode=yes -oStrictHostKeyChecking=yes -oUserKnownHostsFile="$known" -i "$identity" -b "$batch" "$destination"
printf '%s\n' 'Encrypted snapshot transferred. Retain its encryption key separately.'
