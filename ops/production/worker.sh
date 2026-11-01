#!/bin/sh
set -u
stopping=0
trap 'stopping=1' TERM INT
while [ "$stopping" -eq 0 ]; do
  for worker in auction mail discovery business privacy; do
    file="/app/ops/${worker}-worker.php"
    if [ -f "$file" ]; then
      if php "$file" --once; then
        date -u +%s > "/var/lib/gunindex/heartbeats/${worker}.ok"
      else
        printf '%s\n' "Worker ${worker} failed; review restricted logs." >&2
        date -u +%s > "/var/lib/gunindex/heartbeats/${worker}.failed"
      fi
    fi
    [ "$stopping" -eq 0 ] || break
  done
  [ "$stopping" -eq 0 ] || break
  sleep 15 &
  wait "$!" || true
done
