#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

usage() {
  cat <<'EOF' >&2
Usage: bash scripts/release/check-readiness.sh --version <semver>
EOF
}

version=''

while [[ $# -gt 0 ]]; do
  case "$1" in
    --version)
      version="${2:-}"
      shift 2
      ;;
    *)
      usage
      exit 1
      ;;
  esac
done

if [[ -z "$version" ]]; then
  usage
  exit 1
fi

semver_regex='^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?(\+[0-9A-Za-z.-]+)?$'

if [[ ! "$version" =~ $semver_regex ]]; then
  echo "Release version must be semantic versioning compliant: $version" >&2
  exit 1
fi

cd "$ROOT_DIR"

required_files=(
  "CHANGELOG.md"
  ".github/workflows/release.yml"
  "docs/project/release-management.md"
  "docs/project/production-readiness-review.md"
  "docs/project/cutover-checklist.md"
  "docs/project/rollback-plan.md"
)

for file in "${required_files[@]}"; do
  if [[ ! -f "$file" ]]; then
    echo "Missing release readiness artifact: $file" >&2
    exit 1
  fi
done

approved_docs=(
  "docs/project/production-readiness-review.md"
  "docs/project/cutover-checklist.md"
  "docs/project/rollback-plan.md"
)

for file in "${approved_docs[@]}"; do
  if ! grep -q '^Status: Approved$' "$file"; then
    echo "Release readiness document must be approved: $file" >&2
    exit 1
  fi

  if ! grep -Eq '^Reviewed On: [0-9]{4}-[0-9]{2}-[0-9]{2}$' "$file"; then
    echo "Release readiness document must include a review date: $file" >&2
    exit 1
  fi
done

if ! grep -q '^## Unreleased$' CHANGELOG.md; then
  echo "CHANGELOG.md must keep an Unreleased section." >&2
  exit 1
fi

if ! grep -q 'make release-dry-run RELEASE_VERSION=<semver>' docs/project/release-management.md; then
  echo "Release management doc must describe the repository dry-run command." >&2
  exit 1
fi

if ! grep -q 'scripts/release/dry-run.sh' .github/workflows/release.yml; then
  echo "Release workflow must execute the repository dry-run script." >&2
  exit 1
fi

echo "Release readiness review passed for $version."
