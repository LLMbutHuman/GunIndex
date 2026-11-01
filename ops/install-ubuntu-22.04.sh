#!/usr/bin/env bash
# Review before sudo. Existing data, credentials and source files are preserved.
set -euo pipefail
umask 077
usage() {
 cat <<'HELP'
Usage: sudo bash ops/install-ubuntu-22.04.sh --domain market.your-domain.tld --email admin@your-domain.tld [--directory /srv/gunindex] [--update] [--prepare-only]
       bash ops/install-ubuntu-22.04.sh --domain market.your-domain.tld --email admin@your-domain.tld --dry-run

Ubuntu22.04 amd64. Docker's signed official repository, pinned PHP8.4/nginx/MySQL.
--dry-run writes nothing. --prepare-only leaves HTTP at503 until TLS is configured.
--update backs up an existing managed installation before additive migrations.
No historical SQL or identities are imported. No database is reset or overwritten.
Real DNS and reachable ports80/443 are necessary for Certbot's optional TLS step.
HELP
}
domain='' email='' directory='/srv/gunindex' dry=0 update=0 prepare=0
while [ "$#" -gt 0 ]; do
 case "$1" in
  --help|-h) usage;exit 0;;
  --domain|--email|--directory) [ "$#" -ge 2 ] || { usage >&2;exit 2; };key="$1";value="$2";shift 2;case "$key" in --domain) domain="$value";;--email) email="$value";;--directory) directory="$value";;esac;;
  --dry-run) dry=1;shift;; --update) update=1;shift;; --prepare-only) prepare=1;shift;;
  *) printf '%s\n' 'Unknown option.' >&2;exit 2;;
 esac
done
if ! [[ "$domain" =~ ^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$ ]] || [[ "$domain" == *example.* || "$domain" == *.invalid || "$domain" == *.test ]];then
 printf '%s\n' 'A real lowercase domain is required; placeholders are not accepted.' >&2;exit 2
fi
[[ "$email" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,63}$ ]] || { printf '%s\n' 'A valid operator email is required.' >&2;exit 2; }
[[ "$directory" =~ ^/[A-Za-z0-9_/-]+$ ]] && [[ "$directory" != / && "$directory" != /etc && "$directory" != /usr && "$directory" != /var && "$directory" != /home && "$directory" != *'/../'* ]] || { printf '%s\n' 'Use a dedicated absolute installation directory without spaces or traversal.' >&2;exit 2; }
source_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
directory="$(realpath -m -- "$directory")"
[[ "$directory" =~ ^/[A-Za-z0-9_/-]+$ ]] || { printf '%s\n' 'The resolved installation path is unsafe.' >&2;exit 2; }
if [[ "$directory/" == "$source_dir/"* || "$source_dir/" == "$directory/"* ]];then
 printf '%s\n' 'Keep the source checkout and managed installation in separate directories.' >&2;exit 2
fi
if [ "$dry" -eq 1 ];then
 printf '%s\n' 'DRY RUN: no files, packages, containers, firewall or DNS are changed.' \
  '1. Require Ubuntu22.04 amd64 and root for execution.' \
  '2. Install official Docker packages after signing-key fingerprint verification.' \
  '3. Preserve existing keys; create private configuration only for a new installation.' \
  '4. Copy a clean source release without .git, old exports/uploads or runtime secrets.' \
  '5. Build locked PHP8.4-FPM/local Postfix and start private MySQL without host ports.' \
  '6. Bootstrap only an empty database; verify encrypted DB/files backup before migrations.' \
  '7. Start ACME-only HTTP503; request real TLS unless --prepare-only.' \
  '8. Start non-root application and background workers; run documented readiness checks.' \
  "Target=$directory domain=$domain update=$update prepare_only=$prepare" \
  'Email transport and payment flags stay disabled until operator configuration.'
 exit 0
fi
[ "$(id -u)" -eq 0 ] || { printf '%s\n' 'Run with sudo after reviewing the script.' >&2;exit 2; }
# Installation must never target a context selected for another Docker machine.
unset DOCKER_HOST DOCKER_CONTEXT DOCKER_TLS DOCKER_TLS_VERIFY DOCKER_CERT_PATH
export DOCKER_HOST=unix:///var/run/docker.sock
# shellcheck source=/dev/null
. /etc/os-release
[ "${ID:-}" = ubuntu ] && [ "${VERSION_ID:-}" = 22.04 ] && [ "$(dpkg --print-architecture)" = amd64 ] || { printf '%s\n' 'Supported target: Ubuntu22.04 amd64 only.' >&2;exit 2; }
for file in public/index.php sql/schema.sql composer.lock;do [ -f "$source_dir/$file" ] || { printf '%s\n' 'Complete reviewed source including composer.lock is required.' >&2;exit 2; };done
git -c safe.directory="$source_dir" -C "$source_dir" rev-parse --verify HEAD >/dev/null
git -c safe.directory="$source_dir" -C "$source_dir" diff --quiet HEAD && [ -z "$(git -c safe.directory="$source_dir" -C "$source_dir" ls-files --others --exclude-standard)" ] || { printf '%s\n' 'Preserve or commit source changes first. Nothing was reset.' >&2;exit 2; }
if [ -e "$directory/current" ] && [ "$update" -ne 1 ];then printf '%s\n' 'Existing installation requires --update.' >&2;exit 2;fi
if [ "$update" -eq 1 ] && [ ! -f "$directory/config/compose.env" ];then printf '%s\n' 'Restore the managed installation configuration first.' >&2;exit 2;fi
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y ca-certificates curl gnupg python3 git openssl tar
if ! command -v docker >/dev/null || ! docker compose version >/dev/null 2>&1;then
 install -d -m 0755 /etc/apt/keyrings
 key_download="$(mktemp)";trap 'rm -f "$key_download"' EXIT
 curl --fail --show-error --location --proto '=https' --tlsv1.2 https://download.docker.com/linux/ubuntu/gpg -o "$key_download"
 fingerprint="$(gpg --show-keys --with-colons "$key_download" | awk -F: '$1=="fpr"{print $10;exit}')"
 [ "$fingerprint" = 9DC858229FC7DD38854AE2D88D81803C0EBFCD88 ] || { printf '%s\n' 'Docker signing-key fingerprint mismatch.' >&2;exit 1; }
 gpg --batch --yes --dearmor -o /etc/apt/keyrings/docker.gpg "$key_download"
 chmod 0644 /etc/apt/keyrings/docker.gpg
 printf '%s\n' 'deb [arch=amd64 signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu jammy stable' > /etc/apt/sources.list.d/docker.list
 apt-get update
 apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
 rm -f "$key_download";trap - EXIT
fi
systemctl enable --now docker
install -d -m 0700 "$directory" "$directory/config" "$directory/keys" "$directory/backups" "$directory/releases"
if [ ! -f "$directory/config/app.env" ] && docker volume inspect gunindex_database >/dev/null 2>&1;then printf '%s\n' 'Database exists without configuration. Restore credentials/keys; no new secrets were generated.' >&2;exit 2;fi
if [ "$update" -eq 1 ];then
 if docker volume inspect gunindex_database >/dev/null 2>&1;then
  python3 "$directory/current/ops/production/backup.py" --directory "$directory" backup --maintenance --leave-stopped
 else
  printf '%s\n' 'Resuming a managed installation without an initialized database volume; existing credentials are preserved.'
 fi
fi
release="$(git -c safe.directory="$source_dir" -C "$source_dir" rev-parse HEAD)-$(date -u +%Y%m%d%H%M%S)"
release_dir="$directory/releases/$release";install -d -m 0755 "$release_dir"
tar -C "$source_dir" --exclude='.git' --exclude='.env' --exclude='.env.*' --exclude='assets/uploads' --exclude='datenbank' --exclude='tests/.runtime' --exclude='var' --exclude='node_modules' --exclude='vendor' -cf - . | tar -C "$release_dir" -xf -
python3 "$release_dir/ops/production/configure.py" --directory "$directory" --domain "$domain" --email "$email" --release "$release" --source "$release_dir"
ln -sfn "$release_dir" "$directory/current"
"$directory/compose.sh" build php mailer
"$directory/compose.sh" up -d db
ready=0
for ((attempt=0;attempt<60;attempt++));do
 if "$directory/compose.sh" exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql --default-character-set=utf8mb4 -u"$MYSQL_USER" -N "$MYSQL_DATABASE" -e "SELECT 1"' >/dev/null 2>&1;then ready=1;break;fi
 sleep 1
done
[ "$ready" -eq 1 ] || { printf '%s\n' 'Database not ready. Volumes were retained.' >&2;exit 1; }
"$directory/compose.sh" run --rm --no-deps --user 0:0 --cap-add CHOWN --cap-add FOWNER --cap-add DAC_OVERRIDE --entrypoint sh maintenance -c 'mkdir -p /var/lib/gunindex/limits /var/lib/gunindex/images /var/lib/gunindex/private /var/lib/gunindex/sessions /var/lib/gunindex/heartbeats /var/lib/gunindex/invoices && chown -R 33:33 /var/lib/gunindex && chmod 700 /var/lib/gunindex /var/lib/gunindex/*'
count="$("$directory/compose.sh" exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql --default-character-set=utf8mb4 -u"$MYSQL_USER" -N "$MYSQL_DATABASE" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()"')"
if [ "$count" = 0 ];then "$directory/compose.sh" exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql --default-character-set=utf8mb4 -u"$MYSQL_USER" "$MYSQL_DATABASE"' < "$release_dir/sql/schema.sql";fi
python3 "$release_dir/ops/production/backup.py" --directory "$directory" backup
"$directory/compose.sh" run --rm --no-deps maintenance php ops/migrate.php --backup-confirmed
"$directory/compose.sh" up -d php mailer web
if [ "$prepare" -ne 1 ];then
 apt-get install -y certbot
 certbot certonly --webroot --webroot-path "$directory/acme" --domain "$domain" --email "$email" --agree-tos --non-interactive --keep-until-expiring
 "$directory/renew-certificate.sh"
 install -m 0755 "$directory/renew-certificate.sh" /etc/letsencrypt/renewal-hooks/deploy/gunindex
 systemctl enable --now certbot.timer
fi
"$directory/compose.sh" up -d worker
printf '%s\n' "Base installation prepared at $directory." 'Email transport and payments remain disabled.' 'Complete DNS/mail, operator/legal configuration and readiness checks in docs/UBUNTU_22_04.md.'
