#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../../.." && pwd)"
TMP_POT="$(mktemp)"
TMP_POT_UTF8="$(mktemp)"
trap 'rm -f "$TMP_POT" "$TMP_POT_UTF8"' EXIT

cd "$REPO_ROOT"

sort_option_for() {
  local tool="$1"

  if LC_ALL=C "$tool" --help 2>&1 | grep -q -- '--sort-output.*deprecated'; then
    printf '%s\n' '--sort-by-file'
    return
  fi

  printf '%s\n' '--sort-output'
}

php_files=()
locale_dirs=()
xgettext_sort_option="$(sort_option_for xgettext)"
msgmerge_sort_option="$(sort_option_for msgmerge)"

while IFS= read -r file; do
  php_files+=("$file")
done < <(
  {
    find . -maxdepth 1 -type f -name '*.php'
    find admin api cust ext lib login mobile plugins scorekeeper spiritkeeper sql timekeeper user -type f -name '*.php'
  } | LC_ALL=C sort
)

if [ "${#php_files[@]}" -eq 0 ]; then
  echo "No PHP files found for gettext extraction." >&2
  exit 1
fi

while IFS= read -r po_file; do
  locale_dirs+=("${po_file%/LC_MESSAGES/messages.po}")
done < <(find locale -mindepth 3 -maxdepth 3 -type f -path '*/LC_MESSAGES/messages.po' | LC_ALL=C sort)

if [ "${#locale_dirs[@]}" -eq 0 ]; then
  echo "No gettext catalogs found under locale/." >&2
  exit 1
fi

xgettext \
  --language=PHP \
  --from-code=UTF-8 \
  --keyword=_ \
  "$xgettext_sort_option" \
  --output="$TMP_POT" \
  "${php_files[@]}"

sed 's/charset=CHARSET/charset=UTF-8/' "$TMP_POT" > "$TMP_POT_UTF8"
mv "$TMP_POT_UTF8" "$TMP_POT"

for locale_dir in "${locale_dirs[@]}"; do
  po_file="$locale_dir/LC_MESSAGES/messages.po"
  mo_file="$locale_dir/LC_MESSAGES/messages.mo"

  msgmerge \
    --update \
    --backup=none \
    "$msgmerge_sort_option" \
    "$po_file" \
    "$TMP_POT"

  msgfmt \
    --check \
    --output-file="$mo_file" \
    "$po_file"
done
