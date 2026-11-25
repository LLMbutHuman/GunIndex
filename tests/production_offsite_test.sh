#!/usr/bin/env bash
# Exercise OpenSSH on loopback with disposable keys and encrypted fixture files.
set -euo pipefail
umask 077
[[ "${GITHUB_ACTIONS:-}" == true && "${RUNNER_ENVIRONMENT:-}" == github-hosted ]]
release="$1"
deployment="$2"
manifest="$3"
fixture="$(mktemp -d "$RUNNER_TEMP/gunindex-sftp.XXXXXXXXXX")"
trap 'if [[ -s "$fixture/sshd.pid" ]]; then sudo kill "$(cat "$fixture/sshd.pid")" || true; fi' EXIT

mkdir -- "$fixture/remote"
ssh-keygen -q -t ed25519 -N '' -f "$fixture/host"
ssh-keygen -q -t ed25519 -N '' -f "$fixture/client"
port="$(python3 -c 'import socket; s=socket.socket(); s.bind(("127.0.0.1", 0)); print(s.getsockname()[1])')"
printf '[127.0.0.1]:%s %s\n' "$port" "$(cut -d' ' -f1,2 < "$fixture/host.pub")" > "$fixture/known_hosts"
printf '[127.0.0.1]:%s %s\n' "$port" "$(cut -d' ' -f1,2 < "$fixture/client.pub")" > "$fixture/wrong_hosts"
cat > "$fixture/sshd_config" <<EOF
Port $port
ListenAddress 127.0.0.1
HostKey $fixture/host
PidFile $fixture/sshd.pid
AuthorizedKeysFile $fixture/client.pub
AllowUsers $(id -un)
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
UsePAM yes
StrictModes no
DisableForwarding yes
ForceCommand internal-sftp -u 077
Subsystem sftp internal-sftp
EOF
sudo install -d -m 0755 /run/sshd
sudo /usr/sbin/sshd -f "$fixture/sshd_config" -E "$fixture/sshd.log"

offsite=(bash "$release/ops/offsite-backup.sh" --directory "$deployment" --port "$port"
 --destination "$(id -un)@127.0.0.1" --remote-directory "$fixture/remote" --identity "$fixture/client")
"${offsite[@]}" --known-hosts "$fixture/wrong_hosts" --manifest "$manifest" --check-only
if "${offsite[@]}" --known-hosts "$fixture/wrong_hosts" --manifest "$manifest"; then
 printf '%s\n' 'An untrusted SSH host key was accepted.' >&2; exit 1
fi
[[ -z "$(find "$fixture/remote" -mindepth 1 -print -quit)" ]]
altered="${manifest%.json}-altered.json"
python3 - "$manifest" "$altered" <<'PY'
import json, sys
from pathlib import Path

metadata = json.loads(Path(sys.argv[1]).read_text())
metadata['created_at'] = 'modified fixture'
Path(sys.argv[2]).write_text(json.dumps(metadata))
PY
if "${offsite[@]}" --known-hosts "$fixture/known_hosts" --manifest "$altered" --check-only; then
 printf '%s\n' 'An altered snapshot manifest was accepted.' >&2; exit 1
fi

"${offsite[@]}" --known-hosts "$fixture/known_hosts" --manifest "$manifest"
if "${offsite[@]}" --known-hosts "$fixture/known_hosts" --manifest "$manifest"; then
 printf '%s\n' 'An already published snapshot was accepted for overwrite.' >&2; exit 1
fi
name="${manifest##*/}"
published="$fixture/remote/${name%.json}"
mapfile -t bundle < <(find "$published" -type f -printf '%f\n' | sort)
[[ "${#bundle[@]}" == 3 && "$(stat -c %a "$published")" == 700 ]]
for file in "${bundle[@]}"; do
 [[ "$file" == "$name" || "$file" == "${name%.json}.db.gib" || "$file" == "${name%.json}.files.gib" ]]
 [[ "$(stat -c %a "$published/$file")" == 600 ]]
done
mkdir -- "$deployment/restored-source"
{
 for file in "${bundle[@]}"; do
  printf 'get %s/%s %s/restored-source/%s\n' "$published" "$file" "$deployment" "$file"
 done
} | sftp -F /dev/null -oBatchMode=yes -oStrictHostKeyChecking=yes -oUserKnownHostsFile="$fixture/known_hosts" \
 -i "$fixture/client" -P "$port" -b - "$(id -un)@127.0.0.1"
