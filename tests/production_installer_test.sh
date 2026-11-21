#!/usr/bin/env bash
set -euo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${RUNNER_ENVIRONMENT:-}" == github-hosted ]]
directory=/srv/gunindex
domain=installer.gunindex-ci.internal
email=operator@example.invalid
installer=./ops/install-ubuntu-22.04.sh
compose="$directory/compose.sh"
[[ ! -e "$directory" ]]
[[ -z "$(docker --host=unix:///var/run/docker.sock volume ls --quiet --filter name=^gunindex_)" ]]
trap '[[ ! -x "$compose" ]] || sudo "$compose" stop >/dev/null 2>&1 || true' EXIT

sudo "$installer" --domain "$domain" --email "$email" --prepare-only --backup-timer
[[ "$(curl --silent --show-error --max-time 10 -o /dev/null -w '%{http_code}' http://127.0.0.1/)" == 503 ]]
for timer in gunindex-monitor.timer gunindex-backup.timer; do
    sudo systemctl is-enabled --quiet "$timer"
    sudo systemctl is-active --quiet "$timer"
done
sudo "$compose" exec -T php php ops/health.php --installation
sudo "$compose" exec -T php php -r 'require "config/db.php"; exit((int)$pdo->query("SELECT COUNT(*) FROM geo_locations WHERE country_code = '\''DE'\''")->fetchColumn() === 8289 ? 0 : 1);'
python3 tests/production_admin_enrollment.py "$compose"
if sudo "$compose" exec -T php php ops/create-admin.php; then
    printf '%s\n' 'A second initial administrator was accepted.' >&2
    exit 1
fi
sudo test -s "$directory/keys/backup.key"
sudo find "$directory/backups" -maxdepth 1 -name '*.json' -print -quit | grep -q .
sudo bash "$directory/current/ops/production/install-timers.sh" "$directory" --with-backup

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
https=(curl --silent --show-error --retry 3 --retry-connrefused --max-time 10
       --cacert "$RUNNER_TEMP/gunindex-test-ca.pem" --resolve "$domain:443:127.0.0.1")
"${https[@]}" --fail "https://$domain/" -o /dev/null
headers="$("${https[@]}" -D - -o /dev/null "https://$domain/admin/setup.php")"
grep -qi '^location: .*login' <<<"$headers"

# Refuse to overwrite administrator-managed timer configuration.
timer=/etc/systemd/system/gunindex-monitor.timer
printf '\n# synthetic operator change\n' | sudo tee -a "$timer" >/dev/null
if sudo bash "$directory/current/ops/production/install-timers.sh" "$directory" --with-backup; then
    printf '%s\n' 'Timer configuration was not protected.' >&2
    exit 1
fi
sudo grep -qF '# synthetic operator change' "$timer"
printf '%s\n' 'Verified executable installer, bootstrap HTTP gate, HTTPS deploy hook, protected setup and persistent timers.'
