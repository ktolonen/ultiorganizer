#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"
DIST_DIR="${ROOT_DIR}/dist"

cd "${ROOT_DIR}"

if ! command -v git >/dev/null 2>&1; then
    echo "error: git is required to build a release package" >&2
    exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
    echo "error: zip is required to build a release package" >&2
    exit 1
fi

if [[ ! -f version.php ]]; then
    echo "error: version.php is missing" >&2
    exit 1
fi

APP_VERSION="$(
    php -r '$version = include "version.php"; if (!is_string($version) || trim($version) === "") { exit(1); } echo trim($version);' 2>/dev/null \
        || sed -n "s/^return ['\"]\\([^'\"]\\+\\)['\"];$/\\1/p" version.php
)"

if [[ -z "${APP_VERSION}" ]]; then
    echo "error: unable to read Ultiorganizer version from version.php" >&2
    exit 1
fi

COMMIT_HASH="$(git rev-parse --short HEAD)"
PACKAGE_VERSION="${APP_VERSION}-${COMMIT_HASH}"
PACKAGE_NAME="ultiorganizer-${PACKAGE_VERSION}"
ARCHIVE_PATH="${DIST_DIR}/${PACKAGE_NAME}.zip"

if ! git diff --quiet || ! git diff --cached --quiet || [[ -n "$(git ls-files --others --exclude-standard)" ]]; then
    echo "warning: working tree has uncommitted changes; package name still uses commit ${COMMIT_HASH}" >&2
fi

EXACT_TAG="$(git describe --exact-match --tags HEAD 2>/dev/null || true)"
if [[ -n "${EXACT_TAG}" ]]; then
    NORMALIZED_TAG="${EXACT_TAG#v}"
    NORMALIZED_TAG="${NORMALIZED_TAG#V}"
    NORMALIZED_TAG="${NORMALIZED_TAG#.}"
    if [[ "${NORMALIZED_TAG}" != "${APP_VERSION}" ]]; then
        echo "warning: exact tag '${EXACT_TAG}' does not match version.php '${APP_VERSION}'" >&2
    fi
fi

WORK_DIR="$(mktemp -d)"
trap 'rm -rf "${WORK_DIR}"' EXIT

PACKAGE_DIR="${WORK_DIR}/${PACKAGE_NAME}"
mkdir -p "${PACKAGE_DIR}" "${DIST_DIR}"

while IFS= read -r -d '' path; do
    if [[ -d "${path}" ]]; then
        continue
    fi

    attr="$(git check-attr export-ignore -- "${path}" | sed 's/^.*: export-ignore: //')"
    if [[ "${attr}" == "set" ]]; then
        continue
    fi

    mkdir -p "${PACKAGE_DIR}/$(dirname "${path}")"
    cp -p "${path}" "${PACKAGE_DIR}/${path}"
done < <(git ls-files --cached --others --exclude-standard -z)

required_paths=(
    "README.md"
    "LICENSE"
    "COPYING.txt"
    "version.php"
    "install.php"
    "index.php"
    "admin"
    "api"
    "conf/config.inc.example.php"
    "cust"
    "docs/deployment.md"
    "ext"
    "images"
    "lib"
    "locale"
    "login"
    "mobile"
    "plugins"
    "scorekeeper"
    "script"
    "spiritkeeper"
    "sql/ultiorganizer.sql"
    "user"
)

for required in "${required_paths[@]}"; do
    if [[ ! -e "${PACKAGE_DIR}/${required}" ]]; then
        echo "error: release package is missing required path: ${required}" >&2
        exit 1
    fi
done

forbidden_paths=(
    ".agents"
    ".claude"
    ".codex"
    ".git"
    ".gitattributes"
    ".githooks"
    ".github"
    ".gitignore"
    ".php-cs-fixer.dist.php"
    ".settings"
    ".vscode"
    "AGENTS.md"
    "CLAUDE.md"
    "composer.json"
    "composer.lock"
    "dist"
    "docs/ai"
    "docs/dev"
    "docs/release"
    "live"
    "phpstan-baseline.neon"
    "phpstan-stubs.php"
    "phpstan.neon.dist"
    "reports"
    "vendor"
)

for forbidden in "${forbidden_paths[@]}"; do
    if [[ -e "${PACKAGE_DIR}/${forbidden}" ]]; then
        echo "error: release package contains forbidden path: ${forbidden}" >&2
        exit 1
    fi
done

rm -f "${ARCHIVE_PATH}"
(
    cd "${WORK_DIR}"
    zip -qr "${ARCHIVE_PATH}" "${PACKAGE_NAME}"
)

echo "Built ${ARCHIVE_PATH}"
echo "Version: ${APP_VERSION}"
echo "Commit: ${COMMIT_HASH}"
