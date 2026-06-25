#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  ./scripts/download-front-openapi.sh "https://example.com/front-openapi.json"

Pass the official Front Systems OpenAPI/Swagger JSON or YAML URL as the only argument.
Do not use URLs containing credentials, API keys, tokens, cookies, or private links unless Front explicitly allows them to be stored.
USAGE
}

if [ "$#" -ne 1 ] || [ -z "${1:-}" ]; then
  usage
  exit 1
fi

SOURCE_URL="$1"
TARGET_DIR="docs/vendor/front-systems/openapi"
HEADERS_FILE="$(mktemp)"
DOWNLOAD_FILE="$(mktemp)"

cleanup() {
  rm -f "$HEADERS_FILE" "$DOWNLOAD_FILE"
}
trap cleanup EXIT

mkdir -p "$TARGET_DIR"

if ! curl --fail --silent --show-error --location --dump-header "$HEADERS_FILE" --output "$DOWNLOAD_FILE" "$SOURCE_URL"; then
  echo "Error: could not download the Front Systems OpenAPI/Swagger file. Check the URL and your access." >&2
  exit 1
fi

LOWER_URL="$(printf '%s' "$SOURCE_URL" | tr '[:upper:]' '[:lower:]')"
CONTENT_TYPE="$(tr '[:upper:]' '[:lower:]' < "$HEADERS_FILE" | awk -F': ' '/^content-type:/ {print $2}' | tail -n 1 | tr -d '\r')"

if [[ "$LOWER_URL" == *.json || "$LOWER_URL" == *.json\?* || "$CONTENT_TYPE" == *json* ]]; then
  TARGET_FILE="$TARGET_DIR/frontsystems.openapi.json"
elif [[ "$LOWER_URL" == *.yaml || "$LOWER_URL" == *.yaml\?* || "$LOWER_URL" == *.yml || "$LOWER_URL" == *.yml\?* || "$CONTENT_TYPE" == *yaml* || "$CONTENT_TYPE" == *yml* ]]; then
  TARGET_FILE="$TARGET_DIR/frontsystems.openapi.yaml"
else
  TARGET_FILE="$TARGET_DIR/frontsystems.openapi.downloaded"
fi

mv "$DOWNLOAD_FILE" "$TARGET_FILE"
DOWNLOAD_FILE=""

SAFE_SOURCE_URL="$SOURCE_URL"
if [[ "$SAFE_SOURCE_URL" == *\?* ]]; then
  SAFE_SOURCE_URL="${SAFE_SOURCE_URL%%\?*}?REDACTED_QUERY_STRING"
fi

TIMESTAMP="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"

cat > "$TARGET_DIR/downloaded-at.txt" <<EOF
Source URL: $SAFE_SOURCE_URL
Downloaded at UTC: $TIMESTAMP
Saved file path: $TARGET_FILE

Warning: Do not commit private credentials, API keys, tokens, cookies, private links, restricted Front documentation, or unredacted customer data unless explicitly allowed.
EOF

echo "Downloaded Front Systems OpenAPI/Swagger file."
echo "Saved file: $TARGET_FILE"
echo "Metadata: $TARGET_DIR/downloaded-at.txt"

