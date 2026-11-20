#!/usr/bin/env bash
set -euo pipefail

# Export the same tracked source that identifies the release, independent of host files.
source_directory="$1"
commit="$2"
release_directory="$3"
[[ "$commit" =~ ^[0-9a-f]{40}$ ]]
[[ "$(git -c safe.directory="$source_directory" -C "$source_directory" rev-parse --verify "$commit^{commit}")" == "$commit" ]]
[[ -d "$release_directory" && ! -L "$release_directory" ]]
[[ -z "$(find "$release_directory" -mindepth 1 -maxdepth 1 -print -quit)" ]]

git -c safe.directory="$source_directory" -C "$source_directory" archive --format=tar "$commit" \
 | tar --extract --file=- --directory="$release_directory" --no-same-owner --no-same-permissions
test -f "$release_directory/public/index.php"
test -f "$release_directory/composer.lock"
test -f "$release_directory/assets/vendor/openpgp/openpgp.min.mjs"
