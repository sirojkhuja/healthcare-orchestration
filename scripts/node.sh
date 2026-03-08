#!/usr/bin/env bash
set -euo pipefail

if [[ -f docker-compose.yml ]] && command -v docker >/dev/null 2>&1; then
  docker compose run --rm --no-deps \
    --user "$(id -u):$(id -g)" \
    -e HOME=/tmp \
    -e npm_config_cache=/tmp/npm \
    node \
    npm "$@"
  exit 0
fi

if command -v npm >/dev/null 2>&1; then
  npm "$@"
  exit 0
fi

echo "Neither Docker nor local npm is available." >&2
exit 1
