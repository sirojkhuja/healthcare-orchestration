#!/usr/bin/env bash
set -euo pipefail

docker compose build app >/dev/null
bash scripts/composer.sh install --no-interaction
bash scripts/node.sh install
bash scripts/compose-app.sh php -r "file_exists('.env') || copy('.env.example', '.env');"
bash scripts/artisan.sh key:generate --ansi --force
bash scripts/artisan.sh config:clear --ansi
bash scripts/composer.sh run test
