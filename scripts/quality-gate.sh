#!/usr/bin/env bash
set -euo pipefail

docs_only="${1:-}"

required_files=(
  "AGENTS.md"
  "docs/README.md"
  "docs/project/source-of-truth-policy.md"
  "docs/project/progress-workflow.md"
  "docs/project/tasklist.md"
  "docs/project/release-management.md"
  "docs/project/production-readiness-review.md"
  "docs/project/cutover-checklist.md"
  "docs/project/rollback-plan.md"
  "docs/dev/coding-standards.md"
  "docs/dev/architecture.md"
  "docs/adr/056-openapi-bundle-and-contract-governance.md"
  "docs/adr/058-release-automation-and-go-live-governance.md"
  "docs/api/endpoint-matrix.md"
  "docs/api/openapi/openapi.yaml"
  "docs/api/openapi/openapi.json"
  ".codex/skills/medflow-development-governance/SKILL.md"
  ".githooks/pre-commit"
  ".githooks/pre-push"
  "Makefile"
  "CHANGELOG.md"
  "docker-compose.yml"
  "scripts/compose-app.sh"
  "scripts/composer.sh"
  "scripts/artisan.sh"
  "scripts/node.sh"
  "scripts/release/build-changelog.sh"
  "scripts/release/check-readiness.sh"
  "scripts/release/dry-run.sh"
  "scripts/openapi/build.mjs"
  "scripts/openapi/validate.mjs"
  "scripts/openapi/validate-schema.sh"
  "scripts/openapi/validate_schema.py"
  "scripts/openapi/schema/openapi-3.1.1.schema.json"
  "tests/Feature/Contracts/OpenApiContractCoverageTest.php"
  ".github/workflows/release.yml"
)

for file in "${required_files[@]}"; do
  if [[ ! -f "$file" ]]; then
    echo "Missing required governance artifact: $file" >&2
    exit 1
  fi
done

git diff --check >/dev/null
git diff --cached --check >/dev/null

if [[ ! -x ".githooks/pre-commit" || ! -x ".githooks/pre-push" ]]; then
  echo "Git hooks must be executable." >&2
  exit 1
fi

if [[ ! -x "scripts/check-tasklist.sh" || ! -x "scripts/install-git-hooks.sh" || ! -x "scripts/openapi/validate-schema.sh" || ! -x "scripts/release/build-changelog.sh" || ! -x "scripts/release/check-readiness.sh" || ! -x "scripts/release/dry-run.sh" ]]; then
  echo "Governance scripts must be executable." >&2
  exit 1
fi

if [[ "$docs_only" == "--docs-only" || ! -f composer.json ]]; then
  if [[ -f docker-compose.yml ]] && command -v docker >/dev/null 2>&1; then
    docker compose config >/dev/null
  fi

  echo "Documentation and governance quality gate passed."
  exit 0
fi

echo "Repository quality prerequisites are present. Run make targets for full application quality validation."
