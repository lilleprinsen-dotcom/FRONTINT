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
echo "TODO: Configure client generation later using openapi-generator, swagger-codegen, or a Laravel/PHP HTTP client generator."

