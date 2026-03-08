#!/usr/bin/env bash
set -euo pipefail

if [[ -f docker-compose.yml ]] && command -v docker >/dev/null 2>&1; then
  bash scripts/compose-app.sh composer "$@"
  exit 0
fi

if command -v composer >/dev/null 2>&1; then
  composer "$@"
  exit 0
fi

echo "Neither Docker nor local Composer is available." >&2
exit 1
