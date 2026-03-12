#!/usr/bin/env bash
set -euo pipefail

docs_only="${1:-}"

required_files=(
  "AGENTS.md"
  "docs/README.md"
  "docs/project/source-of-truth-policy.md"
  "docs/project/progress-workflow.md"
  "docs/project/tasklist.md"
  "docs/dev/coding-standards.md"
  "docs/dev/architecture.md"
  "docs/adr/056-openapi-bundle-and-contract-governance.md"
  "docs/api/endpoint-matrix.md"
  "docs/api/openapi/openapi.yaml"
  "docs/api/openapi/openapi.json"
  ".codex/skills/medflow-development-governance/SKILL.md"
  ".githooks/pre-commit"
  ".githooks/pre-push"
  "Makefile"
  "docker-compose.yml"
  "scripts/compose-app.sh"
  "scripts/composer.sh"
  "scripts/artisan.sh"
  "scripts/node.sh"
  "scripts/openapi/build.mjs"
  "scripts/openapi/validate.mjs"
  "scripts/openapi/validate-schema.sh"
  "scripts/openapi/validate_schema.py"
  "scripts/openapi/schema/openapi-3.1.1.schema.json"
  "tests/Feature/Contracts/OpenApiContractCoverageTest.php"
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

if [[ ! -x "scripts/check-tasklist.sh" || ! -x "scripts/install-git-hooks.sh" || ! -x "scripts/openapi/validate-schema.sh" ]]; then
  echo "Governance scripts must be executable." >&2
  exit 1
fi

if [[ "$docs_only" == "--docs-only" || ! -f composer.json ]]; then
  if [[ -f docker-compose.yml ]]; then
    docker compose config >/dev/null
  fi

  echo "Documentation and governance quality gate passed."
  exit 0
fi

echo "Repository quality prerequisites are present. Run make targets for full application quality validation."
