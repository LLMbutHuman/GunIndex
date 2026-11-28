#!/usr/bin/env bash
set -euo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${RUNNER_ENVIRONMENT:-}" == github-hosted ]]
directory=/srv/gunindex
domain=installer.gunindex-ci.internal
email=operator@example.invalid
installer="$RUNNER_TEMP/GunIndex-Install-Ubuntu.sh"
compose="$directory/compose.sh"
[[ ! -e "$directory" ]]
[[ -z "$(docker --host=unix:///var/run/docker.sock volume ls --quiet --filter name=^gunindex_)" ]]
trap '[[ ! -x "$compose" ]] || sudo "$compose" stop >/dev/null 2>&1 || true' EXIT

bash tests/production_package_test.sh
bash ops/production/build-ubuntu-installer.sh "$GITHUB_SHA" "$installer"
sha256sum "$installer"
"$installer" --verify

legacy_directory=/srv/gunindex-legacy-ci
[[ ! -e "$legacy_directory" ]]
sudo install -d -m 0700 "$legacy_directory/config"
printf 'DOMAIN=%s\n' "$domain" | sudo tee "$legacy_directory/config/compose.env" >/dev/null
printf '%s\n' '    image: mysql:8.0@sha256:fixture' | sudo tee "$legacy_directory/config/compose.yml" >/dev/null
if sudo "$installer" --domain "$domain" --email "$email" --directory "$legacy_directory" --update --prepare-only > "$RUNNER_TEMP/gunindex-legacy-output" 2>&1; then
    printf '%s\n' 'Updating the MySQL 8.0 installation was accepted without migration.' >&2
    exit 1
fi
grep -Fq 'MySQL 8.0 detected' "$RUNNER_TEMP/gunindex-legacy-output"
sudo test ! -e "$legacy_directory/releases"

sudo "$installer" --domain "$domain" --email "$email" --prepare-only --backup-timer
[[ "$(curl --silent --show-error --max-time 10 -o /dev/null -w '%{http_code}' http://127.0.0.1/)" == 503 ]]
for timer in gunindex-monitor.timer gunindex-backup.timer; do
    sudo systemctl is-enabled --quiet "$timer"
    sudo systemctl is-active --quiet "$timer"
done
sudo "$compose" exec -T php php ops/health.php --installation
sudo "$compose" exec -T php sh -ec '! command -v git && ! command -v composer && php ops/production/verify-composer-vendor.php'
db_security="$(sudo "$compose" exec -T db sh -ec 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --batch --skip-column-names -uroot' <<'SQL'
SELECT VERSION(), @@global.local_infile,
    (SELECT COUNT(*) FROM information_schema.plugins WHERE plugin_name='mysqlx' AND plugin_status='ACTIVE');
SELECT Host FROM mysql.user WHERE User='root' ORDER BY Host;
SQL
)"
[[ "${db_security%%$'\n'*}" == 8.4.*$'\t0\t0' ]]
[[ "${db_security#*$'\n'}" == localhost ]]
[[ -z "$(sudo "$compose" port db 3306 2>/dev/null || true)" ]]
sudo "$compose" exec -T php php -r 'require "config/db.php"; exit((int)$pdo->query("SELECT COUNT(*) FROM geo_locations WHERE country_code = '\''DE'\''")->fetchColumn() === 8289 ? 0 : 1);'
sudo "$compose" exec -T -e GUNINDEX_CI_FIXTURE=french-postcodes php php tests/production_french_postcodes_test.php
sudo "$compose" exec -T -e GUNINDEX_CI_FIXTURE=austrian-postcodes php php tests/production_austrian_postcodes_test.php
sudo "$compose" exec -T -e GUNINDEX_CI_FIXTURE=swisstopo-postcodes php php tests/production_swisstopo_postcodes_test.php
sudo "$compose" exec -T -e GUNINDEX_CI_FIXTURE=luxembourg-postcodes php php tests/production_luxembourg_postcodes_test.php
sudo "$compose" exec -T -e GUNINDEX_CI_FIXTURE=czech-postcodes php php tests/production_czech_postcodes_test.php
sudo "$compose" exec -T -e GUNINDEX_CI_FIXTURE=belgian-postcodes php php tests/production_belgian_postcodes_test.php
sudo "$compose" exec -T -e GUNINDEX_CI_FIXTURE=polish-postcodes php php tests/production_polish_postcodes_test.php
sudo "$compose" exec -T -e GUNINDEX_CI_FIXTURE=danish-postcodes php php tests/production_danish_postcodes_test.php
sudo "$compose" exec -T -e GUNINDEX_CI_FIXTURE=dutch-postcodes php php tests/production_dutch_postcodes_test.php
installer_test_credentials="$RUNNER_TEMP/gunindex-installer-admin.json"
python3 tests/production_admin_enrollment.py "$compose" "$installer_test_credentials"
if sudo "$compose" exec -T php php ops/create-admin.php; then
    printf '%s\n' 'A second initial administrator was accepted.' >&2
    exit 1
fi
initial_release="$(sudo readlink "$directory/current")"
initial_config="$(sudo sha256sum "$directory/config/app.env" "$directory/config/database.env" "$directory/keys/backup.key")"
sudo "$compose" exec -T php sh -ec 'printf "%s\n" installer-update-probe > /var/lib/gunindex/private/installer-update-probe.txt'
sudo test -s "$directory/keys/backup.key"
sudo find "$directory/backups" -maxdepth 1 -name '*.json' -print -quit | grep -q .
sudo bash "$directory/current/ops/production/install-timers.sh" "$directory" --with-backup
sudo "$installer" --domain "$domain" --email "$email" --update --prepare-only
[[ "$(sudo readlink "$directory/current")" != "$initial_release" ]]
[[ "$(sudo sha256sum "$directory/config/app.env" "$directory/config/database.env" "$directory/keys/backup.key")" == "$initial_config" ]]
sudo "$compose" exec -T php grep -qx installer-update-probe /var/lib/gunindex/private/installer-update-probe.txt
sudo "$compose" exec -T php php -r 'require "config/db.php"; exit((int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_admin=1")->fetchColumn() === 1 && (int)$pdo->query("SELECT COUNT(*) FROM geo_imports")->fetchColumn() === 11 ? 0 : 1);'
[[ "$(curl --silent --show-error --max-time 10 -o /dev/null -w '%{http_code}' http://127.0.0.1/)" == 503 ]]

# Reject a preparation attempt for a different domain before making certificate changes.
if sudo "$installer" --domain wrong.gunindex-ci.internal --email "$email" --finish-tls; then
    printf '%s\n' 'TLS finalization accepted a different domain.' >&2
    exit 1
fi

# Rehearse the actual deploy hook with an isolated certificate and trust only this fixture.
certificates="/etc/letsencrypt/live/$domain"
sudo install -d -m 0700 "$certificates"
sudo openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj "/CN=$domain" \
    -addext "subjectAltName=DNS:$domain" -keyout "$certificates/privkey.pem" -out "$certificates/fullchain.pem"
sudo cp "$certificates/fullchain.pem" "$RUNNER_TEMP/gunindex-test-ca.pem"
sudo chmod 0644 "$RUNNER_TEMP/gunindex-test-ca.pem"
sudo "$directory/renew-certificate.sh"
https=(curl --silent --show-error --noproxy '*' --max-time 10
       --cacert "$RUNNER_TEMP/gunindex-test-ca.pem" --resolve "$domain:443:127.0.0.1")
# The TLS endpoint shows the installation gate until the administrator activates the site.
for attempt in {1..10}; do
    status="$("${https[@]}" -o /dev/null -w '%{http_code}' "https://$domain/" 2>/dev/null)" && break
    sleep 1
done
[[ "$status" == 503 ]]
headers="$("${https[@]}" -D - -o /dev/null "https://$domain/admin/setup.php")"
grep -qi '^location: .*login' <<<"$headers"
grep -qi '^strict-transport-security: max-age=31536000' <<<"$headers"
grep -qi '^x-content-type-options: nosniff' <<<"$headers"
grep -qi '^x-frame-options: DENY' <<<"$headers"
grep -qi "^content-security-policy: .*frame-ancestors 'none'" <<<"$headers"
if grep -qi '^server: nginx/' <<<"$headers"; then
    printf '%s\n' 'The web response exposed the nginx version.' >&2
    exit 1
fi

python3 tests/production_setup_activation.py "$installer_test_credentials" "$RUNNER_TEMP/gunindex-test-ca.pem"
"${https[@]}" --fail "https://$domain/" -o /dev/null
"${https[@]}" --fail "https://$domain/marketplace/search.php?country_code=FR&zip=75001&radius_km=10" | grep -F 'Base officielle des codes postaux (2026-11-08)' >/dev/null
austrian_search="$("${https[@]}" --fail "https://$domain/marketplace/search.php?country_code=AT&zip=5020&radius_km=25")"
grep -Fq 'GeoNames (2026-11-25)' <<<"$austrian_search"
grep -Fq 'RTR-GmbH (2026-11-04)' <<<"$austrian_search"
for country_and_zip in CH:8001 LI:9490; do
    swisstopo_search="$("${https[@]}" --fail "https://$domain/marketplace/search.php?country_code=${country_and_zip%%:*}&zip=${country_and_zip#*:}&radius_km=10")"
    grep -Fq '©swisstopo (2026-11-01)' <<<"$swisstopo_search"
done
luxembourg_search="$("${https[@]}" --fail "https://$domain/marketplace/search.php?country_code=LU&zip=1111&radius_km=5")"
grep -Fq 'BD-Adresses (2026-11-23)' <<<"$luxembourg_search"
czech_search="$("${https[@]}" --fail "https://$domain/marketplace/search.php?country_code=CZ&zip=11000&radius_km=5")"
grep -Fq 'RÚIAN (2026-10-31)' <<<"$czech_search"
belgian_search="$("${https[@]}" --fail "https://$domain/marketplace/search.php?country_code=BE&zip=1000&radius_km=10")"
grep -Fq 'BeST (2026-11-25)' <<<"$belgian_search"
polish_search="$("${https[@]}" --fail "https://$domain/marketplace/search.php?country_code=PL&zip=01-067&radius_km=5")"
grep -Fq 'PRG (2026-11-25)' <<<"$polish_search"
grep -Fq 'GeoNames (2026-11-25)' <<<"$polish_search"
danish_search="$("${https[@]}" --fail "https://$domain/marketplace/search.php?country_code=DK&zip=1050&radius_km=5")"
grep -Fq 'DAGI (2026-11-26)' <<<"$danish_search"
grep -Fq 'POSTNR2 (2026)' <<<"$danish_search"
dutch_search="$("${https[@]}" --fail "https://$domain/marketplace/search.php?country_code=NL&zip=1012%20AA&radius_km=5")"
grep -Fq 'BAG (2026-11-01)' <<<"$dutch_search"
grep -Fq 'Public Domain Mark 1.0' <<<"$dutch_search"
# Refuse to overwrite administrator-managed timer configuration.
timer=/etc/systemd/system/gunindex-monitor.timer
printf '\n# synthetic operator change\n' | sudo tee -a "$timer" >/dev/null
if sudo bash "$directory/current/ops/production/install-timers.sh" "$directory" --with-backup; then
    printf '%s\n' 'Timer configuration was not protected.' >&2
    exit 1
fi
sudo grep -qF '# synthetic operator change' "$timer"
printf '%s\n' 'Verified single-file installer, bootstrap and HTTPS launch gates, HTTPS deploy hook, protected setup and persistent timers.'
