#!/usr/bin/env bash
# Transfers already encrypted snapshots. The encryption key is never sent.
set -euo pipefail
usage(){ printf '%s\n' 'Usage: ops/offsite-backup.sh --manifest /srv/gunindex/backups/SNAPSHOT.json --destination user@backup-host --remote-directory /private/gunindex --identity /root/.ssh/backup_ed25519 --known-hosts /root/.ssh/backup_known_hosts [--directory /srv/gunindex] [--port 22] [--check-only]'; }
manifest='' destination='' remote='' identity='' known='' check=0 directory=/srv/gunindex port=22
while [ "$#" -gt 0 ];do
 case "$1" in
  --help|-h) usage;exit 0;; --check-only) check=1;shift;;
  --manifest|--destination|--remote-directory|--identity|--known-hosts|--directory|--port)
   [ "$#" -ge 2 ] || { usage >&2; exit 2; }
   case "$1" in
    --manifest) manifest="$2";; --destination) destination="$2";; --remote-directory) remote="$2";;
    --identity) identity="$2";; --known-hosts) known="$2";; --directory) directory="$2";; --port) port="$2";;
   esac
   shift 2;;
  *) usage >&2;exit 2;;
 esac
done
[[ "$destination" =~ ^([A-Za-z0-9_][A-Za-z0-9_.-]*@)?[A-Za-z0-9][A-Za-z0-9.-]*$ && "$remote" =~ ^(/[A-Za-z0-9_-]+)+$ && "$port" =~ ^[1-9][0-9]{0,4}$ && "$port" -le 65535 ]] || { printf '%s\n' 'Unsafe SSH destination, port or remote path.' >&2;exit 2; }
for file in "$manifest" "$identity" "$known";do [ -f "$file" ] && [ ! -L "$file" ] || { printf '%s\n' 'Regular manifest, identity and verified known-hosts files are required.' >&2;exit 2; };done
name="${manifest##*/}"
[[ "$manifest" =~ ^/[A-Za-z0-9_./-]+\.json$ && "$name" =~ ^[A-Za-z0-9_][A-Za-z0-9_.-]*\.json$ ]] || { printf '%s\n' 'Unsafe manifest path.' >&2;exit 2; }
script_directory="$(cd -- "$(dirname -- "$0")" && pwd)"
archive_paths="$(python3 "$script_directory/production/backup.py" --directory "$directory" verify --manifest "$manifest")"
mapfile -t archives <<< "$archive_paths"
[ "${#archives[@]}" -eq 2 ] || { printf '%s\n' 'Encrypted archives failed validation.' >&2;exit 2; }
if [ "$check" -eq 1 ]; then
 printf '%s\n' 'Manifest and encrypted archives authenticated locally. No SSH transfer performed.'
 exit 0
fi

umask 077
scratch="$(mktemp -d /tmp/gunindex-offsite.XXXXXXXXXX)"
trap 'rm -rf -- "$scratch"' EXIT
sftp_options=(-F /dev/null -oBatchMode=yes -oIdentitiesOnly=yes -oStrictHostKeyChecking=yes
 -oGlobalKnownHostsFile=/dev/null -oUpdateHostKeys=no -oConnectTimeout=15
 -oUserKnownHostsFile="$known" -i "$identity" -P "$port")
upload="$remote/.${scratch##*/}"
published="$remote/${name%.json}"
if printf 'ls %s\n' "$published" | sftp "${sftp_options[@]}" -b - "$destination" >/dev/null 2>&1; then
 printf '%s\n' 'A snapshot already exists at this destination.' >&2
 exit 1
fi
files=("${archives[@]}" "$manifest")
{
 printf 'mkdir %s\nchmod 700 %s\n' "$upload" "$upload"
 for file in "${files[@]}"; do
  uploaded="$upload/${file##*/}"
  printf 'put %s %s\nchmod 600 %s\nget %s %s\n' "$file" "$uploaded" "$uploaded" "$uploaded" "$scratch/${file##*/}"
 done
} | sftp "${sftp_options[@]}" -b - "$destination"

for file in "${files[@]}"; do
 cmp --silent -- "$file" "$scratch/${file##*/}" || { printf '%s\n' 'Remote verification failed. The snapshot was not published.' >&2; exit 1; }
done
python3 "$script_directory/production/backup.py" --directory "$directory" verify --manifest "$scratch/$name" >/dev/null
printf 'rename %s %s\n' "$upload" "$published" | sftp "${sftp_options[@]}" -b - "$destination"
printf 'Encrypted snapshot transferred and verified: %s/%s\n' "$published" "$name"
