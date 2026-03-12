#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

cd "$ROOT_DIR"

audit_output="$(bash scripts/composer.sh audit --locked --no-dev --format=json || true)"

if [[ -f docker-compose.yml ]] && command -v docker >/dev/null 2>&1; then
  php_runner=(bash scripts/compose-app.sh php -r)
elif command -v php >/dev/null 2>&1; then
  php_runner=(php -r)
else
  echo "Neither Docker Compose nor local PHP is available for security audit parsing." >&2
  exit 1
fi

"${php_runner[@]}" '
$payload = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$advisories = $payload["advisories"] ?? [];
$count = 0;

foreach ($advisories as $packageAdvisories) {
    if (is_array($packageAdvisories)) {
        $count += count($packageAdvisories);
    }
}

if ($count > 0) {
    fwrite(STDERR, "Composer audit found runtime security advisories.\n");
    exit(1);
}

$abandoned = $payload["abandoned"] ?? [];

if (is_array($abandoned) && $abandoned !== []) {
    fwrite(STDERR, "Composer audit reported abandoned packages for manual review:\n");

    foreach (array_keys($abandoned) as $package) {
        fwrite(STDERR, " - ".$package."\n");
    }
}
' <<<"$audit_output"

if [[ -f package-lock.json ]]; then
  bash scripts/node.sh audit --package-lock-only --omit=dev --audit-level=high
fi
