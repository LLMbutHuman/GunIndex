#!/usr/bin/env bash
set -euo pipefail
umask 077
unset TAR_OPTIONS GZIP

if [[ $# -lt 1 || $# -gt 2 || ! "$1" =~ ^[0-9a-f]{40}$ ]]; then
    printf '%s\n' 'Usage: bash ops/production/build-ubuntu-installer.sh FULL_COMMIT_SHA [OUTPUT.sh]' >&2
    exit 2
fi
repository="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)"
commit="$1"
output="${2:-$repository/GunIndex-Install-Ubuntu.sh}"
if [[ -e "$output" || -L "$output" ]]; then
    printf 'Installer output already exists: %s\n' "$output" >&2
    exit 2
fi
[[ "$(git -C "$repository" rev-parse --verify "$commit^{commit}")" == "$commit" ]]

work="$(mktemp -d)"
candidate=''
cleanup() {
    rm -f -- "$work/tree" "$work/members" "$work/release.tar.gz"
    rmdir -- "$work"
    if [[ -n "$candidate" ]]; then rm -f -- "$candidate"; fi
}
trap cleanup EXIT
git -C "$repository" ls-tree -rz "$commit" > "$work/tree"
while IFS= read -r -d '' entry; do
    mode="${entry%% *}"
    name="${entry#*$'\t'}"
    if [[ "$mode" != 100644 && "$mode" != 100755 ]] || [[ "$name" == *[[:cntrl:]\\]* ]]; then
        printf 'Unsupported release entry: %q\n' "$name" >&2
        exit 2
    fi
    case "/${name,,}" in
        /.env.example|/sql/schema.sql|/sql/structure.sql|/sql/migrations/*.sql|/zip/plz.csv|/zip/plz-[a-z][a-z].csv) ;;
        */.git/*|*/.git|*/.env|*/.env.*|*/.runtime/*|*/uploads/*|*/private/*|*/data/*|*/datenbank/*|*/var/*|*/node_modules/*|*/keys/*|*/backups/*|*/logs/*|*/gunindex_codex_fortsetzung.md|/gunindex-install-ubuntu.sh|*.sql|*.csv|*.dump|*.bak|*.pem|*.key|*.pfx|*.p12|*.log|*.zip|*.tar|*.gz|*/id_rsa*|*/id_ed25519*)
            printf 'Private or generated path in release commit: %q\n' "$name" >&2
            exit 2;;
    esac
done < "$work/tree"

git -C "$repository" archive --format=tar "$commit" | gzip -n > "$work/release.tar.gz"
tar --list --gzip --file="$work/release.tar.gz" > "$work/members"
for name in ops/install-ubuntu-22.04.sh ops/production/Dockerfile ops/production/Dockerfile.mailer \
    public/index.php sql/schema.sql composer.lock assets/vendor/openpgp/openpgp.min.mjs; do
    if ! grep -Fxq -- "$name" "$work/members"; then
        printf 'Required release file missing: %s\n' "$name" >&2
        exit 2
    fi
done
read -r checksum _ < <(sha256sum -- "$work/release.tar.gz")
candidate="$(mktemp -- "${output}.XXXXXXXXXX")"
{
    git -C "$repository" show "$commit:ops/production/ubuntu-installer-wrapper.sh.in" \
        | sed -e "s/@RELEASE_COMMIT@/$commit/" -e "s/@ARCHIVE_SHA256@/$checksum/"
    base64 -- "$work/release.tar.gz"
    printf '%s\n' GUNINDEX_RELEASE_ARCHIVE
} > "$candidate"
bash -n "$candidate"
chmod 0755 -- "$candidate"
mv -nT -- "$candidate" "$output"
if [[ -e "$candidate" ]]; then
    printf 'Installer output already exists: %s\n' "$output" >&2
    exit 2
fi
candidate=''
printf 'Built %s from %s\n' "$output" "$commit"
sha256sum -- "$output"
