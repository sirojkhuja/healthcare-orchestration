#!/usr/bin/env bash
set -euo pipefail

if [[ -f docker-compose.yml ]] && command -v docker >/dev/null 2>&1; then
  bash scripts/compose-app.sh python3 scripts/openapi/validate_schema.py
  exit 0
fi

if command -v python3 >/dev/null 2>&1; then
  python3 scripts/openapi/validate_schema.py
  exit 0
fi

echo "Python 3 with jsonschema support is required for OpenAPI schema validation." >&2
exit 1
