#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"
DIST_DIR="${ROOT_DIR}/dist"
PACKAGE_TYPE="install"
ALL_CUSTOMIZATIONS=1
SELECTED_CUSTOMIZATIONS=()

usage() {
    cat <<'EOF'
Usage: docs/release/build-release.sh [options]

Options:
  --type install|update  Build a full install package or an update package.
                         Default: install.
  --install              Same as --type install.
  --update               Same as --type update.
  --cust ID              Include only cust/default and cust/ID. Can be repeated
                         or given as a comma-separated list.
  --all-cust             Include all customizations. Default behavior.
  -h, --help             Show this help.

Update packages exclude install.php and *.sql files. cust/default is always
included, even when --cust is used.
EOF
}

cd "${ROOT_DIR}"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --type)
            if [[ $# -lt 2 ]]; then
                echo "error: --type requires install or update" >&2
                exit 1
            fi
            PACKAGE_TYPE="$2"
            shift 2
            ;;
        --install)
            PACKAGE_TYPE="install"
            shift
            ;;
        --update)
            PACKAGE_TYPE="update"
            shift
            ;;
        --cust)
            if [[ $# -lt 2 ]]; then
                echo "error: --cust requires a customization id" >&2
                exit 1
            fi
            IFS=',' read -r -a requested_customizations <<< "$2"
            for customization in "${requested_customizations[@]}"; do
                if [[ -n "${customization}" ]]; then
                    SELECTED_CUSTOMIZATIONS+=("${customization}")
                fi
            done
            ALL_CUSTOMIZATIONS=0
            shift 2
            ;;
        --all-cust)
            ALL_CUSTOMIZATIONS=1
            SELECTED_CUSTOMIZATIONS=()
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "error: unknown option: $1" >&2
            usage >&2
            exit 1
            ;;
    esac
done

if [[ "${PACKAGE_TYPE}" != "install" && "${PACKAGE_TYPE}" != "update" ]]; then
    echo "error: --type must be install or update" >&2
    exit 1
fi

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

contains_customization() {
    local needle="$1"
    local customization
    for customization in "${SELECTED_CUSTOMIZATIONS[@]}"; do
        if [[ "${customization}" == "${needle}" ]]; then
            return 0
        fi
    done
    return 1
}

validate_customizations() {
    local customization
    for customization in "${SELECTED_CUSTOMIZATIONS[@]}"; do
        if [[ ! "${customization}" =~ ^[A-Za-z0-9._-]+$ ]]; then
            echo "error: invalid customization id: ${customization}" >&2
            exit 1
        fi
        if [[ ! -d "cust/${customization}" ]]; then
            echo "error: customization does not exist: cust/${customization}" >&2
            exit 1
        fi
    done
}

customization_package_suffix() {
    local suffix=""
    local customization

    if [[ "${ALL_CUSTOMIZATIONS}" -eq 1 ]]; then
        echo ""
        return
    fi

    suffix="default"
    for customization in "${SELECTED_CUSTOMIZATIONS[@]}"; do
        if [[ "${customization}" != "default" ]]; then
            suffix="${suffix}-${customization}"
        fi
    done

    echo "-cust-${suffix}"
}

should_skip_package_path() {
    local path="$1"
    local cust_path
    local cust_id

    if [[ "${PACKAGE_TYPE}" == "update" ]]; then
        if [[ "${path}" == "install.php" || "${path}" == *.sql ]]; then
            return 0
        fi
    fi

    if [[ "${path}" == cust/* && "${ALL_CUSTOMIZATIONS}" -eq 0 ]]; then
        cust_path="${path#cust/}"
        cust_id="${cust_path%%/*}"

        if [[ "${cust_path}" == "${cust_id}" ]]; then
            return 1
        fi
        if [[ "${cust_id}" == "default" ]] || contains_customization "${cust_id}"; then
            return 1
        fi
        return 0
    fi

    return 1
}

validate_customizations

COMMIT_HASH="$(git rev-parse --short HEAD)"
CUSTOMIZATION_SUFFIX="$(customization_package_suffix)"
PACKAGE_VERSION="${APP_VERSION}-${COMMIT_HASH}"
PACKAGE_NAME="ultiorganizer-${PACKAGE_TYPE}${CUSTOMIZATION_SUFFIX}-${PACKAGE_VERSION}"
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

    if should_skip_package_path "${path}"; then
        continue
    fi

    attr="$(git check-attr export-ignore -- "${path}" | sed 's/^.*: export-ignore: //')"
    if [[ "${attr}" == "set" ]]; then
        continue
    fi

    mkdir -p "${PACKAGE_DIR}/$(dirname "${path}")"
    cp -p "${path}" "${PACKAGE_DIR}/${path}"
done < <(git ls-files -z)

required_paths=(
    "README.md"
    "LICENSE"
    "COPYING.txt"
    "version.php"
    "index.php"
    "admin"
    "api"
    "conf/config.inc.example.php"
    "cust/default"
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
    "sql/upgrade_db.php"
    "user"
)

if [[ "${PACKAGE_TYPE}" == "install" ]]; then
    required_paths+=(
        "install.php"
        "sql/ultiorganizer.sql"
    )
fi

if [[ "${ALL_CUSTOMIZATIONS}" -eq 0 ]]; then
    for customization in "${SELECTED_CUSTOMIZATIONS[@]}"; do
        if [[ "${customization}" != "default" ]]; then
            required_paths+=("cust/${customization}")
        fi
    done
fi

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
    "docs"
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

if [[ "${PACKAGE_TYPE}" == "update" ]]; then
    if [[ -e "${PACKAGE_DIR}/install.php" ]]; then
        echo "error: update package contains forbidden path: install.php" >&2
        exit 1
    fi
    if find "${PACKAGE_DIR}" -name '*.sql' -print -quit | grep -q .; then
        echo "error: update package contains .sql files" >&2
        exit 1
    fi
fi

if [[ "${ALL_CUSTOMIZATIONS}" -eq 0 ]]; then
    while IFS= read -r customization_path; do
        customization="$(basename "${customization_path}")"
        if [[ "${customization}" != "default" ]] && ! contains_customization "${customization}"; then
            echo "error: package contains unselected customization: cust/${customization}" >&2
            exit 1
        fi
    done < <(find "${PACKAGE_DIR}/cust" -mindepth 1 -maxdepth 1 -type d)
fi

rm -f "${ARCHIVE_PATH}"
(
    cd "${WORK_DIR}"
    zip -qr "${ARCHIVE_PATH}" "${PACKAGE_NAME}"
)

echo "Built ${ARCHIVE_PATH}"
echo "Version: ${APP_VERSION}"
echo "Commit: ${COMMIT_HASH}"
echo "Type: ${PACKAGE_TYPE}"
if [[ "${ALL_CUSTOMIZATIONS}" -eq 1 ]]; then
    echo "Customizations: all"
elif [[ "${#SELECTED_CUSTOMIZATIONS[@]}" -eq 0 ]]; then
    echo "Customizations: default"
else
    echo "Customizations: default,${SELECTED_CUSTOMIZATIONS[*]}"
fi
