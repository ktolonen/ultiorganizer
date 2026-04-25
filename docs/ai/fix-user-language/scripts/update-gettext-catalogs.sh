#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../../.." && pwd)"
TMP_POT="$(mktemp)"
trap 'rm -f "$TMP_POT"' EXIT

cd "$REPO_ROOT"

php_files=()

while IFS= read -r file; do
  php_files+=("$file")
done < <(
  {
    find . -maxdepth 1 -type f -name '*.php'
    find admin api cust ext lib login mobile plugins scorekeeper spiritkeeper user -type f -name '*.php'
  } | LC_ALL=C sort
)

if [ "${#php_files[@]}" -eq 0 ]; then
  echo "No PHP files found for gettext extraction." >&2
  exit 1
fi

xgettext \
  --language=PHP \
  --from-code=UTF-8 \
  --keyword=_ \
  --sort-output \
  --output="$TMP_POT" \
  "${php_files[@]}"

for locale_dir in locale/de_DE.utf8 locale/fi_FI.utf8; do
  po_file="$locale_dir/LC_MESSAGES/messages.po"
  mo_file="$locale_dir/LC_MESSAGES/messages.mo"

  msgmerge \
    --update \
    --backup=none \
    --sort-output \
    "$po_file" \
    "$TMP_POT"

  msgfmt \
    --check \
    --output-file="$mo_file" \
    "$po_file"
done
