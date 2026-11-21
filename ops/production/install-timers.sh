#!/usr/bin/env bash
# Run on the managed Ubuntu server. Daily backup pauses application writers.
set -euo pipefail
directory="${1:?Provide the absolute installation directory}"
[[ "$directory" =~ ^/[A-Za-z0-9_/-]+$ && -f "$directory/config/compose.env" && "$(id -u)" == 0 ]] || {
    printf '%s\n' 'Run as root with the managed installation directory.' >&2
    exit 2
}
units=(gunindex-monitor)
case "${2:-}" in
    '') ;;
    --with-backup) units+=(gunindex-backup) ;;
    *) printf '%s\n' 'Only --with-backup is supported.' >&2; exit 2 ;;
esac

for unit in "${units[@]}"; do
    for suffix in service timer; do
        name="$unit.$suffix"
        source="$directory/config/systemd/$name"
        target="/etc/systemd/system/$name"
        if [[ ! -f "$source" || -L "$source" || -L "$target" ]] ||
            { [[ -e "$target" ]] && ! cmp -s -- "$source" "$target"; }; then
            printf 'Review %s and %s before enabling timers.\n' "$source" "$target" >&2
            exit 1
        fi
    done
done

timers=()
for unit in "${units[@]}"; do
    for suffix in service timer; do
        name="$unit.$suffix"
        target="/etc/systemd/system/$name"
        if [[ ! -e "$target" ]]; then
            install -o root -g root -m 0644 -- "$directory/config/systemd/$name" "$target"
        fi
    done
    timers+=("$unit.timer")
done

systemctl daemon-reload
systemctl enable --now "${timers[@]}"
systemctl list-timers "${timers[@]}" --no-pager
