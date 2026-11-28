#!/usr/bin/env bash
# Wait for PHP, private storage and all expected worker heartbeats.
set -euo pipefail
compose="${1:?Provide the path to the managed Compose wrapper}"
budget="${2:-240}"
probe_limit="${3:-20}"
[[ "$budget" =~ ^[1-9][0-9]{0,3}$ && "$probe_limit" =~ ^[1-9][0-9]{0,2}$ ]] || exit 2
command -v timeout >/dev/null || { printf '%s\n' 'GNU timeout (coreutils) is required for readiness checks.' >&2; exit 2; }

deadline=$((SECONDS + budget))
report=''
status=1
while (( SECONDS < deadline )); do
    remaining=$((deadline - SECONDS))
    limit=$((probe_limit < remaining ? probe_limit : remaining))
    # Compose uses -T. A separate process group lets the timeout terminate both the wrapper and child CLI.
    if report="$(timeout --kill-after=2s "${limit}s" "$compose" exec -T php php ops/health.php --installation </dev/null 2>&1)"; then
        printf '%s\n' "$report"
        exit 0
    else
        status=$?
    fi
    (( SECONDS < deadline )) && sleep 1
done

printf 'Installation readiness expired after %s seconds (last probe exit %s).\n' "$budget" "$status" >&2
printf '%s\n' "${report:-No health report returned.}" >&2
printf '%s\n' 'Database, storage, maintenance state or workers failed the installation check.' >&2
printf 'Inspect: sudo %s logs --tail=50 php worker db\n' "$compose" >&2
exit 1
