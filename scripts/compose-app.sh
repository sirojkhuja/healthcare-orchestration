#!/usr/bin/env bash
set -euo pipefail

if [[ ! -f docker-compose.yml ]]; then
  echo "docker-compose.yml is required for Compose-backed commands." >&2
  exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker is required for Compose-backed commands." >&2
  exit 1
fi

if [[ -z "$(docker compose images -q app 2>/dev/null)" ]]; then
  docker compose build app >/dev/null
fi

docker compose run --rm --no-deps \
  --user "$(id -u):$(id -g)" \
  -e HOME=/tmp \
  -e COMPOSER_HOME=/tmp/composer \
  -e npm_config_cache=/tmp/npm \
  app \
  "$@"
