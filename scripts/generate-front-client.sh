#!/usr/bin/env bash
set -euo pipefail

JSON_SPEC="docs/vendor/front-systems/openapi/frontsystems.openapi.json"
YAML_SPEC="docs/vendor/front-systems/openapi/frontsystems.openapi.yaml"

if [ -f "$JSON_SPEC" ]; then
  SPEC_FILE="$JSON_SPEC"
elif [ -f "$YAML_SPEC" ]; then
  SPEC_FILE="$YAML_SPEC"
else
  echo "No Front Systems OpenAPI file found. Place the official spec in docs/vendor/front-systems/openapi/ or run scripts/download-front-openapi.sh <url>."
  exit 1
fi

echo "Found Front Systems OpenAPI file: $SPEC_FILE"

if [ "$SPEC_FILE" = "$JSON_SPEC" ] && command -v python3 >/dev/null 2>&1; then
  python3 - "$SPEC_FILE" <<'PY'
import json
import sys

spec_path = sys.argv[1]
with open(spec_path, "r", encoding="utf-8") as handle:
    spec = json.load(handle)

info = spec.get("info", {})
servers = spec.get("servers", [])
server_url = servers[0].get("url", "not specified") if servers else "not specified"

print(f"OpenAPI version: {spec.get('openapi', 'not specified')}")
print(f"API title: {info.get('title', 'not specified')}")
print(f"API version: {info.get('version', 'not specified')}")
print(f"Base server URL: {server_url}")
PY
elif [ "$SPEC_FILE" = "$YAML_SPEC" ]; then
  echo "YAML spec metadata parsing is skipped because no YAML parser dependency is installed."
else
  echo "Python 3 not found; skipping spec metadata summary."
fi

echo "TODO: Configure client generation later using openapi-generator, swagger-codegen, or a Laravel/PHP HTTP client generator."
