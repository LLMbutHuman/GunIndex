#!/usr/bin/env bash
# Review before sudo. Existing data, credentials and source files are preserved.
set -euo pipefail
umask 077
usage() {
 cat <<'HELP'
Usage: sudo ./ops/install-ubuntu-22.04.sh
       sudo ./ops/install-ubuntu-22.04.sh --domain market.your-domain.tld --email admin@your-domain.tld [options]

Ubuntu 22.04 amd64. Docker's signed official repository, pinned PHP 8.4/nginx/MySQL.
  --directory DIR  Managed installation directory (default: /srv/gunindex).
  --guided         Ask for missing domain and email. Prompt for the initial admin on a fresh install.
  --dry-run        Display planned actions only. No root required.
  --prepare-only   Skip certificate provisioning. New sites start with HTTP 503.
  --finish-tls     Configure HTTPS for an existing prepared installation, without rebuilding it.
  --setup-admin    Prompt for the first administrator and confirm TOTP on this terminal.
  --backup-timer   Schedule daily verified backup with a brief pause of application writers.
  --update         Back up a managed installation before additive migrations.
Local monitoring starts automatically. No database is reset or overwritten.
HTTPS needs real DNS and reachable ports 80 and 443.
HELP
}
domain='' email='' directory='/srv/gunindex' dry=0 update=0 prepare=0 finish_tls=0 setup_admin=0 backup_timer=0 guided=0
if (( $# == 0 )); then guided=1; fi
while [ "$#" -gt 0 ]; do
 case "$1" in
  --help|-h) usage;exit 0;;
  --domain|--email|--directory) [ "$#" -ge 2 ] || { usage >&2;exit 2; };key="$1";value="$2";shift 2;case "$key" in --domain) domain="$value";;--email) email="$value";;--directory) directory="$value";;esac;;
  --dry-run) dry=1;shift;; --update) update=1;shift;; --prepare-only) prepare=1;shift;;
  --finish-tls) finish_tls=1;shift;;
  --guided) guided=1;shift;;
  --setup-admin) setup_admin=1;shift;;
  --backup-timer) backup_timer=1;shift;;
  *) printf '%s\n' 'Unknown option.' >&2;exit 2;;
 esac
done
if (( finish_tls && (update || prepare) )); then
 printf '%s\n' '--finish-tls cannot be combined with --update or --prepare-only.' >&2; exit 2
fi
if (( guided )); then
 if [[ ! -t 0 || ! -t 1 ]]; then
  printf '%s\n' 'Guided setup needs a terminal. Use --help for explicit options.' >&2; exit 2
 fi
 if [[ -z "$domain" ]]; then read -r -p 'Marketplace domain (without https://): ' domain; fi
 if [[ -z "$email" ]]; then read -r -p 'Certificate contact email: ' email; fi
 if (( !update && !finish_tls )); then setup_admin=1; fi
fi
if ! [[ "$domain" =~ ^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$ ]] || [[ "$domain" == *example.* || "$domain" == *.invalid || "$domain" == *.test ]];then
 printf '%s\n' 'A real lowercase domain is required; placeholders are not accepted.' >&2;exit 2
fi
[[ "$email" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,63}$ ]] || { printf '%s\n' 'A valid operator email is required.' >&2;exit 2; }
[[ "$directory" =~ ^/[A-Za-z0-9_/-]+$ ]] && [[ "$directory" != *'/../'* && "$directory" != */.. ]] || { printf '%s\n' 'Use a dedicated absolute installation directory without spaces or traversal.' >&2;exit 2; }
source_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
directory="$(realpath -m -- "$directory")"
[[ "$directory" =~ ^/[A-Za-z0-9_/-]+$ ]] || { printf '%s\n' 'The resolved installation path is unsafe.' >&2;exit 2; }
case "$directory" in
 /bin|/boot|/dev|/etc|/home|/lib|/lib64|/media|/mnt|/opt|/proc|/root|/run|/sbin|/srv|/sys|/tmp|/usr|/usr/bin|/usr/lib|/usr/lib64|/usr/sbin|/var) printf '%s\n' 'Use a dedicated installation directory.' >&2;exit 2;;
esac
if [[ "$directory/" == "$source_dir/"* || "$source_dir/" == "$directory/"* ]];then
 printf '%s\n' 'Keep the source checkout and managed installation in separate directories.' >&2;exit 2
fi
if [ "$dry" -eq 1 ];then
 printf '%s\n' 'DRY RUN: no files, packages, containers, firewall or DNS are changed.' \
  "Target=$directory domain=$domain update=$update prepare_only=$prepare setup_admin=$setup_admin backup_timer=$backup_timer"
 if (( finish_tls )); then
  printf '%s\n' 'Check the existing installation, obtain HTTPS, and open the protected setup page. No image rebuild or database migration.'
  exit 0
 fi
 printf '%s\n' \
  '1. Require Ubuntu22.04 amd64 and root for execution.' \
  '2. Install official Docker packages after signing-key fingerprint verification.' \
  '3. Preserve existing keys; create private configuration only for a new installation.' \
  '4. Copy a clean source release without .git, old exports/uploads or runtime secrets.' \
  '5. Build locked PHP8.4-FPM/local Postfix and start private MySQL without host ports.' \
  '6. Bootstrap only an empty database; verify encrypted DB/files backup before migrations.' \
  '7. Seed the reviewed postcode files for Germany and metropolitan France where there is no import history.' \
  '8. Verify database, private storage and worker startup. Start the local health timer.' \
  '9. Start ACME-only HTTP 503; request real TLS unless --prepare-only.' \
  'Fresh installations start with mail delivery and payments disabled.'
 exit 0
fi
[ "$(id -u)" -eq 0 ] || { printf '%s\n' 'Run with sudo after reviewing the script.' >&2;exit 2; }
if (( setup_admin )) && [[ ! -t 0 || ! -t 1 ]]; then
 printf '%s\n' '--setup-admin needs an interactive terminal for password and TOTP enrollment.' >&2; exit 2
fi
# Installation must never target a context selected for another Docker machine.
unset DOCKER_HOST DOCKER_CONTEXT DOCKER_TLS DOCKER_TLS_VERIFY DOCKER_CERT_PATH
export DOCKER_HOST=unix:///var/run/docker.sock
# shellcheck source=/dev/null
. /etc/os-release
[ "${ID:-}" = ubuntu ] && [ "${VERSION_ID:-}" = 22.04 ] && [ "$(dpkg --print-architecture)" = amd64 ] || { printf '%s\n' 'Supported target: Ubuntu22.04 amd64 only.' >&2;exit 2; }
exec 9>/run/lock/gunindex-install.lock
flock -n 9 || { printf '%s\n' 'Another GunIndex installation is running.' >&2; exit 1; }
path="$directory"
while [[ "$path" != / ]]; do
 if [[ -e "$path" ]]; then
  read -r owner permissions < <(stat -Lc '%u %a' -- "$path")
  if [[ ! -d "$path" || "$owner" != 0 ]] || (( 8#$permissions & 0022 )); then
   printf 'Installation path must be controlled by root: %s\n' "$path" >&2; exit 2
  fi
 fi
 path="$(dirname -- "$path")"
done

enable_https() {
 if ! command -v certbot >/dev/null; then
  if (( finish_tls )); then apt-get update; fi
  apt-get install -y certbot
 fi
 certbot certonly --webroot --webroot-path "$directory/acme" --domain "$domain" --email "$email" --agree-tos --non-interactive --keep-until-expiring
 "$directory/renew-certificate.sh"
 install -m 0755 "$directory/renew-certificate.sh" /etc/letsencrypt/renewal-hooks/deploy/gunindex
 systemctl enable --now certbot.timer
}

finish_setup() {
 local -a timer_options=()
 if (( backup_timer )); then timer_options+=(--with-backup); fi
 bash "$directory/current/ops/production/check-readiness.sh" "$directory/compose.sh"
 "$directory/compose.sh" exec -T web nginx -t
 bash "$directory/current/ops/production/install-timers.sh" "$directory" "${timer_options[@]}"
 if (( prepare )); then
  if [[ -s "$directory/tls/fullchain.pem" ]]; then
   printf 'Updated at %s with the existing HTTPS configuration.\n' "$directory"
   printf 'Protected setup: https://%s/admin/setup.php\n' "$domain"
  else
   printf 'Prepared at %s. Use --finish-tls with the same domain and directory once DNS and ports are ready.\n' "$directory"
  fi
 else
  enable_https
  printf 'Protected setup: https://%s/admin/setup.php\n' "$domain"
 fi
 if (( setup_admin )); then
  "$directory/compose.sh" exec php php ops/create-admin.php
 else
  printf 'For a fresh install, create the first administrator: sudo %s/compose.sh exec php php ops/create-admin.php\n' "$directory"
 fi
 printf '%s\n' 'Complete operator, mail, legal and external-provider checks in the protected setup and docs/UBUNTU_22_04.md.'
}

if (( finish_tls )); then
 if [[ ! -x "$directory/compose.sh" || ! -f "$directory/config/compose.env" ]] ||
    ! grep -Fxq -- "DOMAIN=$domain" "$directory/config/compose.env"; then
  printf '%s\n' 'The prepared installation and its configured domain are required.' >&2; exit 2
 fi
 finish_setup
 exit 0
fi
for file in public/index.php sql/schema.sql composer.lock;do [ -f "$source_dir/$file" ] || { printf '%s\n' 'Complete reviewed source including composer.lock is required.' >&2;exit 2; };done
commit="$(git -c safe.directory="$source_dir" -C "$source_dir" rev-parse --verify HEAD)"
git -c safe.directory="$source_dir" -C "$source_dir" diff --quiet "$commit" || { printf '%s\n' 'Preserve or commit tracked source changes first. Nothing was reset.' >&2;exit 2; }
if [ -e "$directory/current" ] && [ "$update" -ne 1 ];then printf '%s\n' 'Existing installation requires --update.' >&2;exit 2;fi
if [ "$update" -eq 1 ] && [ ! -f "$directory/config/compose.env" ];then printf '%s\n' 'Restore the managed installation configuration first.' >&2;exit 2;fi
if [[ -f "$directory/config/compose.env" ]] &&
   ! grep -Fxq -- "DOMAIN=$domain" "$directory/config/compose.env"; then
 printf '%s\n' 'Review the installation before changing its configured domain.' >&2; exit 2
fi
if [[ -d "$directory" && ! -f "$directory/config/compose.env" &&
      -n "$(find "$directory" -mindepth 1 -maxdepth 1 -print -quit)" ]]; then
 printf '%s\n' 'Choose an empty directory or restore the existing managed configuration.' >&2;exit 2
fi
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
release="$commit-$(date -u +%Y%m%d%H%M%S)"
release_dir="$directory/releases/$release"
mkdir -m 0755 -- "$release_dir"
bash "$source_dir/ops/production/archive-release.sh" "$source_dir" "$commit" "$release_dir"
docker build --secret id=proxy_ca,src=/etc/ssl/certs/ca-certificates.crt \
 --file "$release_dir/ops/production/Dockerfile" --tag "gunindex-production:$release" "$release_dir"
docker build --secret id=proxy_ca,src=/etc/ssl/certs/ca-certificates.crt \
 --file "$release_dir/ops/production/Dockerfile.mailer" --tag "gunindex-mailer:$release" "$release_dir"
if [ "$update" -eq 1 ];then
 if docker volume inspect gunindex_database >/dev/null 2>&1;then
  python3 "$directory/current/ops/production/backup.py" --directory "$directory" backup --maintenance --leave-stopped
 else
  printf '%s\n' 'Resuming a managed installation without an initialized database volume; existing credentials are preserved.'
 fi
fi
python3 "$release_dir/ops/production/configure.py" --directory "$directory" --domain "$domain" --email "$email" --release "$release" --source "$release_dir"
ln -sfn "$release_dir" "$directory/current"
"$directory/compose.sh" up -d db
mysql_database() {
 "$directory/compose.sh" exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -h127.0.0.1 -u"$MYSQL_USER" --default-character-set=utf8mb4 -N "$MYSQL_DATABASE" "$@"' sh "$@"
}
ready=0
for ((attempt=0;attempt<60;attempt++));do
 if mysql_database -e 'SELECT 1' >/dev/null 2>&1;then ready=1;break;fi
 sleep 1
done
[ "$ready" -eq 1 ] || { printf '%s\n' 'Database not ready. Volumes were retained.' >&2;exit 1; }
"$directory/compose.sh" run --rm --no-deps --user 0:0 --cap-add CHOWN --cap-add FOWNER --cap-add DAC_OVERRIDE --entrypoint sh maintenance -c 'mkdir -p /var/lib/gunindex/limits /var/lib/gunindex/images /var/lib/gunindex/private /var/lib/gunindex/sessions /var/lib/gunindex/heartbeats /var/lib/gunindex/invoices && chown -R 33:33 /var/lib/gunindex && chmod 700 /var/lib/gunindex /var/lib/gunindex/*'
count="$(mysql_database -e 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')"
if [ "$count" = 0 ];then mysql_database < "$release_dir/sql/schema.sql";fi
python3 "$release_dir/ops/production/backup.py" --directory "$directory" backup
"$directory/compose.sh" run --rm -T --no-deps maintenance php ops/migrate.php --backup-confirmed
seed_postcodes() {
 local postcodes="$release_dir/ZIP/$1"
 shift
 test -f "$postcodes"
 chmod 0444 "$postcodes"
 "$directory/compose.sh" run --rm -T --no-deps -v "$postcodes:/run/gunindex-postcodes.csv:ro" \
  maintenance php ops/import-postcodes.php --file=/run/gunindex-postcodes.csv --license-reviewed --seed-if-empty "$@"
}
seed_postcodes PLZ.csv --country=DE --format=legacy-de \
 --expected-sha256=3f8a041a81caceb948ba30b285ba80281d464cd22fc4a049eba4bebdd8aab59b \
 --source='German postcode CSV ZIP/PLZ.csv supplied by the GunIndex project owner' \
 --license-note='Project owner confirmed rights to the exact bundled SHA-256. Original provider and data date are undocumented.'
seed_postcodes PLZ-FR.csv --country=FR \
 --expected-sha256=3f9471db1ba4f8e6de2bcb35357a8d4e9ad50e9b24e06ce342bf2f978f020634 \
 --source='La Poste - Base officielle des codes postaux, 2026-11-08 (metropolitan France)' \
 --license-note='Licence Ouverte 2.0. Derived from commune centroids. Source: https://www.data.gouv.fr/datasets/base-officielle-des-codes-postaux'
seed_postcodes PLZ-AT.csv --country=AT \
 --expected-sha256=87109d717f9fdc9aba73d928ed7679b1a3efe563cd0229415cbfb1ab9b5ec29d \
 --source='GeoNames 2026-11-25; RTR-GmbH 2026-11-04 (Austria)' \
 --license-note='CC BY 4.0. Derived from GeoNames points filtered by the RTR postal register. Sources: https://www.geonames.org/ and https://www.data.gv.at/datasets/f76ed887-00d6-450f-a158-9f8b1cbbeebf'
seed_postcodes PLZ-CH.csv --country=CH \
 --expected-sha256=44a429892ba41991192d43746ef5f6863638b3f586e0ce77fb338ea221ac5e41 \
 --source='Federal Office of Topography swisstopo, official directory of towns and cities, 2026-11-01 (Switzerland)' \
 --license-note='swisstopo OGD. Derived from each PLZ6 address density point. https://www.swisstopo.admin.ch/en/terms-of-use-free-geodata-and-geoservices'
seed_postcodes PLZ-LI.csv --country=LI \
 --expected-sha256=000921546ad2897cd557eb7cb38fbed29cc5aea7fd0370404ed771f6736703ed \
 --source='Federal Office of Topography swisstopo, official directory of towns and cities, 2026-11-01 (Liechtenstein)' \
 --license-note='swisstopo OGD. Derived from each PLZ6 address density point. https://www.swisstopo.admin.ch/en/terms-of-use-free-geodata-and-geoservices'
seed_postcodes PLZ-LU.csv --country=LU \
 --expected-sha256=f3d93cab0264847181bd1d8bb58f1e6d26179e54ad05ec3b3b6f33ec5219a5df \
 --source='Administration du cadastre et de la topographie, BD-Adresses, 2026-11-23 (Luxembourg)' \
 --license-note='CC0 1.0. Aggregated WGS84 points; at least three distinct coordinates per postcode. https://data.public.lu/fr/datasets/adresses-georeferencees-bd-adresses/'
seed_postcodes PLZ-CZ.csv --country=CZ \
 --expected-sha256=b882ad73d8a0ee7a1be49f95630fc7156ea3ffb8ab57f3245314d703dca1d59a \
 --source='ČÚZK RÚIAN address points, effective 2026-10-31 (Czechia)' \
 --license-note='CC BY 4.0. Postcode means transformed from S-JTSK to WGS 84. Source: https://geoportal.gov.cz/php/micka/record/basic/CZ-00025712-CUZK_SERIES-MD_RUIAN-CSV-ADR-ST'
seed_postcodes PLZ-BE.csv --country=BE \
 --expected-sha256=c67ba0f01d89504e3aaf6beefbc03ac0f3b025b66309734c1daabf18cb00e3b2 \
 --source='BOSA BeST, Flanders, Brussels-Capital and Wallonia, 2026-11-25 (Belgium)' \
 --license-note='CC BY 4.0. WGS84 postcode means with at least three distinct source points. https://opendata.bosa.be/index.nl.html'
seed_postcodes PLZ-PL.csv --country=PL \
 --expected-sha256=2c9c6df7f4ed5824aaeae7ba4f21fb097d40adde48c948e33073311845615a63 \
 --source='GUGiK PRG (2026-11-25), compared with GeoNames (2026-11-25), Poland' \
 --license-note='PRG free reuse as stated by GUGiK. GeoNames comparison CC BY 4.0. Derived approximate postcode centres. Sources: https://www.geoportal.gov.pl/pl/dane/panstwowy-rejestr-granic-prg/ and https://www.geonames.org/'
"$directory/compose.sh" up -d php mailer web worker
finish_setup
