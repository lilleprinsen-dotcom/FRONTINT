#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLATFORM_DIR="${ROOT_DIR}/apps/platform"
OPENAPI_FILE="${ROOT_DIR}/docs/vendor/front-systems/openapi/frontsystems.openapi.json"

echo "OmniBridge scaffold verification"
echo

if [ ! -d "${PLATFORM_DIR}" ]; then
  echo "ERROR: apps/platform was not found."
  exit 1
fi

echo "OK: apps/platform exists."

if [ ! -f "${OPENAPI_FILE}" ]; then
  echo "ERROR: Front OpenAPI file was not found at docs/vendor/front-systems/openapi/frontsystems.openapi.json."
  exit 1
fi

echo "OK: Front OpenAPI file exists."

"${ROOT_DIR}/scripts/generate-front-client.sh"

if command -v php >/dev/null 2>&1; then
  echo
  echo "Running PHP syntax check for apps/platform..."
  find "${PLATFORM_DIR}" \
    -path "${PLATFORM_DIR}/vendor" -prune -o \
    -path "${PLATFORM_DIR}/storage" -prune -o \
    -name '*.php' -print0 | xargs -0 -n1 php -l
else
  echo
  echo "PHP is not installed or not on PATH. Install PHP 8.3+ or use Docker, then run:"
  echo "  docker compose run --rm platform php artisan test"
fi

if command -v composer >/dev/null 2>&1; then
  echo
  echo "Validating Composer configuration..."
  (cd "${PLATFORM_DIR}" && composer validate --strict)
else
  echo
  echo "Composer is not installed or not on PATH. Install Composer or run it through Docker:"
  echo "  docker compose run --rm platform composer install"
fi

echo
echo "Scaffold verification completed. This script does not require real Front or WooCommerce credentials."
