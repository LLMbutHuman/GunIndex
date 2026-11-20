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
shopt -s nullglob
manifests=("$deployment"/backups/*.json)
[[ "${#manifests[@]}" == 1 ]]
"$compose" run --rm -T --no-deps maintenance php ops/migrate.php --backup-confirmed
"$compose" run --rm -T --no-deps maintenance php ops/migrate.php --backup-confirmed
[[ "$(mysql_fixture -e "SELECT COUNT(*) FROM schema_migrations WHERE version='038_auction_relisting_outcomes.sql'")" == 1 ]]
"$compose" run --rm -T --no-deps maintenance php ops/health.php --core-only

python3 "$release/ops/production/backup.py" --directory "$deployment" restore \
  --manifest "${manifests[0]}" --database gunindex_restore_ci_archive --files-directory "$deployment/restored"
[[ "$(cat "$deployment/restored/private/recovery-probe.txt")" == synthetic-recovery ]]
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
printf '%s\n' 'Verified production image, additive migration recovery, authenticated backup and restore, writable private storage and seven worker heartbeats.'
