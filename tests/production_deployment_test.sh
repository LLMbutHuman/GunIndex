#!/usr/bin/env bash
# Rehearse database, storage, backup and workers on a fresh GitHub-hosted runner.
set -euo pipefail
umask 077
[[ "${GITHUB_ACTIONS:-}" == true && "${RUNNER_ENVIRONMENT:-}" == github-hosted ]]
[[ "${GITHUB_SHA:-}" =~ ^[0-9a-f]{40}$ ]]
release="$RUNNER_TEMP/gunindex-release"
deployment="$RUNNER_TEMP/gunindex-deployment"
[[ -d "$release" && ! -e "$deployment" ]]
unset DOCKER_HOST DOCKER_CONTEXT DOCKER_TLS DOCKER_TLS_VERIFY DOCKER_CERT_PATH
export DOCKER_HOST=unix:///var/run/docker.sock
for resource in 'container ls --all' 'network ls' 'volume ls'; do
  read -r -a command <<< "$resource"
  [[ -z "$(docker "${command[@]}" --quiet --filter label=com.docker.compose.project=gunindex)" ]]
done
[[ -z "$(docker volume ls --quiet --filter name=^gunindex_)" ]]

python3 "$release/ops/production/configure.py" --directory "$deployment" \
  --domain market.example.invalid --email operator@example.invalid --release "$GITHUB_SHA" --source "$release"
compose="$deployment/compose.sh"
trap '"$compose" stop >/dev/null 2>&1 || true' EXIT
docker image tag "gunindex-production-ci:$GITHUB_SHA" "gunindex-production:$GITHUB_SHA"
"$compose" up -d --no-build db

mysql_fixture() {
  "$compose" exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -h127.0.0.1 -u"$MYSQL_USER" --default-character-set=utf8mb4 --batch --skip-column-names "$MYSQL_DATABASE" "$@"' sh "$@"
}
ready=0
for ((attempt=0;attempt<90;attempt++)); do
  if mysql_fixture -e 'SELECT 1' >/dev/null 2>&1; then ready=1; break; fi
  sleep 1
done
[[ "$ready" == 1 ]]
[[ "$(mysql_fixture -e 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')" == 0 ]]
mysql_fixture < "$release/sql/schema.sql"

# Simulate an interruption after additive DDL and before its migration entry.
sed '/^ALTER TABLE market_relisting_chains/,$d' "$release/sql/migrations/014_relisting.sql" | mysql_fixture
mysql_fixture < "$release/sql/migrations/038_auction_relisting_outcomes.sql"
"$compose" run --rm -T --no-deps --user 0:0 --cap-add CHOWN --cap-add FOWNER --cap-add DAC_OVERRIDE \
  --entrypoint sh maintenance -c 'for part in limits images private sessions heartbeats invoices; do install -d -m 0700 -o 33 -g 33 "/var/lib/gunindex/$part"; done'
"$compose" run --rm -T --no-deps --entrypoint sh maintenance -c 'printf "%s\n" synthetic-recovery > /var/lib/gunindex/private/recovery-probe.txt'

python3 "$release/ops/production/backup.py" --directory "$deployment" backup
[[ -s "$deployment/ops-status/backup.verified" && ! -e "$deployment/ops-status/restore.verified" ]]
shopt -s nullglob
manifests=("$deployment"/backups/*.json)
[[ "${#manifests[@]}" == 1 ]]
"$compose" run --rm -T --no-deps maintenance php ops/migrate.php --backup-confirmed
"$compose" run --rm -T --no-deps maintenance php ops/migrate.php --backup-confirmed
[[ "$(mysql_fixture -e "SELECT COUNT(*) FROM schema_migrations WHERE version='038_auction_relisting_outcomes.sql'")" == 1 ]]
"$compose" run --rm -T --no-deps maintenance php ops/health.php --core-only
if "$compose" run --rm -T --no-deps maintenance php ops/health.php --installation; then
  printf '%s\n' 'The installation check succeeded with missing worker heartbeats.' >&2
  exit 1
fi

postal_digest=3f8a041a81caceb948ba30b285ba80281d464cd22fc4a049eba4bebdd8aab59b
import_postcodes=("$compose" run --rm -T --no-deps -v "$release/ZIP/PLZ.csv:/run/gunindex-postcodes.csv:ro"
  maintenance php ops/import-postcodes.php --country=DE --file=/run/gunindex-postcodes.csv --format=legacy-de
  --source='User-provided German postcode CSV fixture' --license-note='Rights to these bytes affirmed by the owner of the project. Isolated import test.' --license-reviewed --seed-if-empty)
if "${import_postcodes[@]}" --expected-sha256="$(printf '%064d' 0)"; then
  printf '%s\n' 'A mismatching source digest was accepted.' >&2
  exit 1
fi
[[ "$(mysql_fixture -e 'SELECT COUNT(*) FROM geo_imports')" == 0 ]]
"${import_postcodes[@]}" --expected-sha256="$postal_digest"
"$compose" run --rm -T --no-deps -e GUNINDEX_CI_FIXTURE=postal-reference maintenance php tests/production_postcodes_test.php
mysql_fixture -e "UPDATE geo_imports SET is_active=0 WHERE country_code='DE'"
"${import_postcodes[@]}" --expected-sha256="$postal_digest"
[[ "$(mysql_fixture -e "SELECT CONCAT(COUNT(*),':',SUM(is_active)) FROM geo_imports WHERE country_code='DE'")" == 1:0 ]]

bash "$release/tests/production_offsite_test.sh" "$release" "$deployment" "${manifests[0]}"
python3 "$release/ops/production/backup.py" --directory "$deployment" restore \
  --manifest "$deployment/restored-source/${manifests[0]##*/}" --database gunindex_restore_ci_archive --files-directory "$deployment/restored"
[[ "$(cat "$deployment/restored/private/recovery-probe.txt")" == synthetic-recovery ]]
[[ -s "$deployment/ops-status/restore.verified" ]]
"$compose" up -d --no-build php worker
ready=0
for ((attempt=0;attempt<45;attempt++)); do
  if "$compose" exec -T worker sh -c 'for worker in auction mail discovery business privacy paypal-refund bitcoin; do test -s "/var/lib/gunindex/heartbeats/$worker.ok" || exit 1; done' >/dev/null 2>&1; then
    ready=1
    break
  fi
  sleep 2
done
[[ "$ready" == 1 ]]
"$compose" exec -T php php ops/health.php --core-only
"$compose" exec -T php php -r 'require "config/setup.php"; exit(setup_verified_operation("/run/gunindex-ops/backup.verified",time()) && setup_verified_operation("/run/gunindex-ops/restore.verified",time()) && setup_verified_operation("/run/gunindex-ops/offsite.verified",time()) && !is_writable("/run/gunindex-ops") ? 0 : 1);'
bash "$release/ops/production/check-readiness.sh" "$compose"
"$compose" exec -T -e GUNINDEX_CI_FIXTURE=mail-health php php tests/production_mail_health_test.php
health_status=0
"$compose" exec -T php php ops/health.php > "$deployment/health.json" || health_status=$?
[[ "$health_status" == 2 ]]
python3 - "$deployment/health.json" <<'PY'
import json, sys
from pathlib import Path

report=json.loads(Path(sys.argv[1]).read_text())
expected={'auction','mail','discovery','business','privacy','paypal-refund','bitcoin'}
assert report['status']=='degraded' and report['warnings']==['mail_not_configured']
assert set(report['workers'])==expected and all(worker['ready'] for worker in report['workers'].values())
PY
printf '%s\n' 'Verified production image, additive migration recovery, authenticated backup, SFTP copy and restore, writable private storage and seven worker heartbeats.'
