#!/usr/bin/env bash
set -euo pipefail

if [[ -f docker-compose.yml ]] && command -v docker >/dev/null 2>&1; then
  bash scripts/compose-app.sh php artisan "$@"
  exit 0
fi

if command -v php >/dev/null 2>&1; then
  php artisan "$@"
  exit 0
fi

echo "Neither Docker Compose nor local PHP is available." >&2
exit 1
