SHELL := /usr/bin/env bash

.PHONY: bootstrap format lint analyse test build verify harden release-dry-run docs-check install-hooks compose-config

bootstrap:
	@set -euo pipefail; \
	if [[ -f composer.json ]]; then \
		bash scripts/bootstrap-app.sh; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/check-tasklist.sh; \
	fi

format:
	@set -euo pipefail; \
	if [[ -f composer.json ]]; then \
		bash scripts/composer.sh run format; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/check-tasklist.sh; \
	fi

lint:
	@set -euo pipefail; \
	if [[ -f composer.json ]]; then \
		bash scripts/composer.sh run lint; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/quality-gate.sh --docs-only; \
	fi

analyse:
	@set -euo pipefail; \
	if [[ -f composer.json ]]; then \
		bash scripts/composer.sh run analyse; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/quality-gate.sh --docs-only; \
	fi

test:
	@set -euo pipefail; \
	if [[ -f composer.json ]]; then \
		bash scripts/node.sh run openapi:build; \
		bash scripts/composer.sh run test; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/check-tasklist.sh; \
	fi

build:
	@set -euo pipefail; \
	if [[ -f composer.json ]]; then \
		bash scripts/node.sh run build; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/quality-gate.sh --docs-only; \
	fi

harden:
	@set -euo pipefail; \
	if [[ -f composer.json ]]; then \
		bash scripts/architecture/check.sh; \
		bash scripts/performance/check.sh; \
		bash scripts/security/check.sh; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/check-tasklist.sh; \
		bash scripts/quality-gate.sh --docs-only; \
	fi

release-dry-run:
	@set -euo pipefail; \
	if [[ -z "$${RELEASE_VERSION:-}" ]]; then \
		echo "RELEASE_VERSION is required."; \
		exit 1; \
	fi
	@args=(--version "$${RELEASE_VERSION}"); \
	if [[ "$${ALLOW_EXISTING_TAG:-0}" == "1" ]]; then \
		args+=(--allow-existing-tag); \
	fi; \
	if [[ "$${RELEASE_SKIP_VERIFY:-0}" == "1" ]]; then \
		args+=(--skip-verify); \
	fi; \
	bash scripts/release/dry-run.sh "$${args[@]}"

verify:
	@set -euo pipefail; \
	if [[ -f composer.json ]]; then \
		bash scripts/node.sh run openapi:validate; \
		bash scripts/openapi/validate-schema.sh; \
		bash scripts/composer.sh run verify; \
		bash scripts/node.sh run build; \
		bash scripts/architecture/check.sh; \
		bash scripts/performance/check.sh; \
		bash scripts/security/check.sh; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/check-tasklist.sh; \
		bash scripts/quality-gate.sh --docs-only; \
	fi

install-hooks:
	@set -euo pipefail; \
	bash scripts/install-git-hooks.sh

docs-check:
	@set -euo pipefail; \
	bash scripts/check-tasklist.sh; \
	bash scripts/quality-gate.sh --docs-only

compose-config:
	@set -euo pipefail; \
	docker compose config >/dev/null
