#!/usr/bin/env bash
# Wait for PHP, private storage and all expected worker heartbeats.
set -euo pipefail
compose="${1:?Provide the path to the managed Compose wrapper}"

for ((attempt=0; attempt<45; attempt++)); do
    if report="$(timeout --foreground 8s "$compose" exec -T php php ops/health.php --installation)"; then
        printf '%s\n' "$report"
        exit 0
    fi
    sleep 2
done

printf '%s\n' "${report:-No health report returned.}" >&2
printf '%s\n' 'Database, storage, maintenance state or workers failed the installation check.' >&2
printf 'Inspect: sudo %s logs --tail=50 php worker db\n' "$compose" >&2
exit 1
