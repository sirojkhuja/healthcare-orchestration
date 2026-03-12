SHELL := /usr/bin/env bash

.PHONY: bootstrap format lint analyse test build verify harden docs-check install-hooks compose-config

bootstrap:
	@if [[ -f composer.json ]]; then \
		bash scripts/bootstrap-app.sh; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/check-tasklist.sh; \
	fi

format:
	@if [[ -f composer.json ]]; then \
		bash scripts/composer.sh run format; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/check-tasklist.sh; \
	fi

lint:
	@if [[ -f composer.json ]]; then \
		bash scripts/composer.sh run lint; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/quality-gate.sh --docs-only; \
	fi

analyse:
	@if [[ -f composer.json ]]; then \
		bash scripts/composer.sh run analyse; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/quality-gate.sh --docs-only; \
	fi

test:
	@if [[ -f composer.json ]]; then \
		bash scripts/node.sh run openapi:build; \
		bash scripts/composer.sh run test; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/check-tasklist.sh; \
	fi

build:
	@if [[ -f composer.json ]]; then \
		bash scripts/node.sh run build; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/quality-gate.sh --docs-only; \
	fi

harden:
	@if [[ -f composer.json ]]; then \
		bash scripts/architecture/check.sh; \
		bash scripts/performance/check.sh; \
		bash scripts/security/check.sh; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/check-tasklist.sh; \
		bash scripts/quality-gate.sh --docs-only; \
	fi

verify:
	@if [[ -f composer.json ]]; then \
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
	@bash scripts/install-git-hooks.sh

docs-check:
	@bash scripts/check-tasklist.sh
	@bash scripts/quality-gate.sh --docs-only

compose-config:
	@docker compose config >/dev/null
