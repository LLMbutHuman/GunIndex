#!/usr/bin/env bash
set -euo pipefail

root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
scratch="$(realpath -- "${TMPDIR:-/tmp}")"
work="$(mktemp -d -- "$scratch/gunindex-package-test.XXXXXXXXXX")"
[[ "$(realpath -- "$work")" == "$scratch"/gunindex-package-test.* ]]
trap 'rm -rf -- "$work"' EXIT

source="$work/source"
mkdir -p -- "$source/ops/production" "$source/admin" "$source/public" "$source/sql" "$source/assets/vendor/openpgp"
git init -q "$source"
git -C "$source" config core.autocrlf false
for file in ops/install-ubuntu-22.04.sh ops/production/build-ubuntu-installer.sh ops/production/ubuntu-installer-wrapper.sh.in; do
    sed 's/\r$//' "$root/$file" > "$source/$file"
done
printf '%s\n' '/admin/retired.php export-ignore' > "$source/.gitattributes"
printf '%s\n' '.env' > "$source/.gitignore"
printf 'fixture\n' > "$source/admin/retired.php"
for file in sql/schema.sql public/index.php composer.lock assets/vendor/openpgp/openpgp.min.mjs ops/production/Dockerfile ops/production/Dockerfile.mailer ops/production/composer-vendor.tar.gz ops/production/composer-vendor.sha256; do
    printf 'fixture\n' > "$source/$file"
done

fixture_commit() {
    git -C "$source" -c user.name='Isolated package test' -c user.email='fixture@example.invalid' -c commit.gpgsign=false commit -qm "$1"
    git -C "$source" rev-parse HEAD
}
git -C "$source" add .
commit="$(fixture_commit 'Synthetic installer source')"
printf 'fixture untracked\n' > "$source/GunIndex_Codex_Fortsetzung.md"
printf 'fixture ignored\n' > "$source/.env"
printf 'fixture changed\n' > "$source/public/index.php"

builder="$source/ops/production/build-ubuntu-installer.sh"
first="$work/first.sh"
second="$work/second.sh"
bash "$builder" "$commit" "$first"
bash "$builder" "$commit" "$second"
cmp "$first" "$second"
if bash "$builder" "$commit" "$first"; then
    printf '%s\n' 'An existing installer artifact was overwritten.' >&2
    exit 1
fi

awk '/^GUNINDEX_RELEASE_ARCHIVE$/ { exit } data { print } /<<.GUNINDEX_RELEASE_ARCHIVE.$/ { data=1 }' "$first" \
    | base64 --decode | gzip -d > "$work/release.tar"
test "$(git get-tar-commit-id < "$work/release.tar")" = "$commit"
test "$(tar -xOf "$work/release.tar" public/index.php)" = fixture
tar -tf "$work/release.tar" > "$work/file-list"
if grep -Eq '(^|/)(\.git|\.env|GunIndex_Codex_Fortsetzung\.md|retired\.php)(/|$)' "$work/file-list"; then
    printf '%s\n' 'The archive contains a private, untracked or retired path.' >&2
    exit 1
fi

verify="$(bash "$first" --verify)"
[[ "$verify" == *"Archive verified for commit $commit"* ]]
preview="$(bash "$first" --domain installer.gunindex-ci.internal --email operator@example.invalid --dry-run)"
[[ "$preview" == *'DRY RUN'* ]]
help="$(bash "$first" --help)"
[[ "$help" == *'--finish-tls'* && "$help" == *'GunIndex-Install-Ubuntu.sh --verify'* ]]
if bash "$first" </dev/null > "$work/no-terminal-output" 2>&1; then
    printf '%s\n' 'The bundle started guided installation without a terminal.' >&2
    exit 1
fi
grep -Fq 'Guided setup needs a terminal' "$work/no-terminal-output"
if command -v script >/dev/null; then
    printf -v command '%q ' bash "$first" --guided --dry-run
    guided="$(printf 'installer.gunindex-ci.internal\noperator@example.invalid\n' | script -qec "$command" /dev/null)"
    [[ "$guided" == *'DRY RUN'* && "$guided" == *'setup_admin=1'* ]]
fi
awk '/<<.GUNINDEX_RELEASE_ARCHIVE.$/ { print; getline; sub(/^./, $0 ~ /^A/ ? "B" : "A") } { print }' "$first" > "$work/corrupted.sh"
if bash "$work/corrupted.sh" --help > "$work/corruption-output" 2>&1; then
    printf '%s\n' 'A corrupted payload was passed to the bundled installer.' >&2
    exit 1
fi
grep -Fq 'checksum does not match' "$work/corruption-output"

# Simulate a private file accidentally committed in a separate fixture repository.
git -C "$source" add -f -- .env
private_commit="$(fixture_commit 'Synthetic private file')"
if bash "$builder" "$private_commit" "$work/rejected.sh" > "$work/rejection-output" 2>&1; then
    printf '%s\n' 'The builder accepted a private release path.' >&2
    exit 1
fi
test ! -e "$work/rejected.sh"
grep -Fq 'Private or generated path' "$work/rejection-output"
printf '%s\n' 'Verified deterministic single-file packaging, exact commit, private-path refusal, archive integrity and installer dispatch.'
