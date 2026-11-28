#!/usr/bin/env bash
set -euo pipefail
root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
work="$(mktemp -d)"
trap 'rm -f -- "$work/probe" "$work/state" "$work/report"; rmdir -- "$work"' EXIT
cat > "$work/probe" <<'SH'
#!/usr/bin/env bash
[[ "$*" == 'exec -T php php ops/health.php --installation' ]] || exit 3
case "$GUNINDEX_READINESS_TEST_MODE" in
    slow)
        sleep 1
        if [[ ! -e "$GUNINDEX_READINESS_TEST_STATE" ]]; then
            : > "$GUNINDEX_READINESS_TEST_STATE"
            printf '%s\n' 'first worker tick pending' >&2
            exit 1
        fi
        printf '%s\n' '{"status":"ok"}'
        ;;
    hung) sleep 90 ;;
esac
SH
chmod 0755 "$work/probe"
export GUNINDEX_READINESS_TEST_STATE="$work/state"
GUNINDEX_READINESS_TEST_MODE=slow bash "$root/ops/production/check-readiness.sh" "$work/probe" 8 3 | grep -Fxq '{"status":"ok"}'
start=$SECONDS
if GUNINDEX_READINESS_TEST_MODE=hung bash "$root/ops/production/check-readiness.sh" "$work/probe" 2 1 >"$work/report" 2>&1; then
    printf '%s\n' 'A hanging probe was treated as healthy.' >&2
    exit 1
fi
(( SECONDS - start < 7 ))
grep -Fq 'last probe exit 124' "$work/report"
printf '%s\n' 'PASS readiness retries and bounds a hanging probe'
