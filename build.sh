#!/usr/bin/env bash
#
# build.sh - Packages the Siusk24 WooCommerce module into a ZIP file ready for installation.
#
# Usage:
#   ./build.sh            # creates siusk24-woocommerce.zip in the repository root
#   ./build.sh my.zip     # creates the specified ZIP file
#
set -euo pipefail

# Script directory (repository root)
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Module folder (plugin slug)
PLUGIN_SLUG="siusk24-woocommerce"
PLUGIN_DIR="${ROOT_DIR}/${PLUGIN_SLUG}"

# Check that the module folder exists
if [[ ! -d "${PLUGIN_DIR}" ]]; then
    echo "Error: module folder '${PLUGIN_DIR}' not found" >&2
    exit 1
fi

# Determine the ZIP file name
if [[ $# -ge 1 ]]; then
    ZIP_PATH="$1"
    case "${ZIP_PATH}" in
        /*) : ;;                              # absolute path
        *)  ZIP_PATH="${ROOT_DIR}/${ZIP_PATH}" ;;
    esac
else
    ZIP_PATH="${ROOT_DIR}/${PLUGIN_SLUG}.zip"
fi

# Remove old ZIP if it exists
rm -f "${ZIP_PATH}"

# Files/folders to exclude from the ZIP
EXCLUDES=(
    "*/.git/*"
    "*/.github/*"
    "*/node_modules/*"
    "*/.DS_Store"
    "*.map"
    "*/tests/*"
    "*/test/*"
)

# Create the ZIP from the module folder so the siusk24-woocommerce/ folder stays inside
echo "Packaging module '${PLUGIN_SLUG}'..."
(
    cd "${ROOT_DIR}"
    zip -r -q "${ZIP_PATH}" "${PLUGIN_SLUG}" -x "${EXCLUDES[@]}"
)

echo "Created ZIP file: ${ZIP_PATH}"
