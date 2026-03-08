SHELL := /usr/bin/env bash

.PHONY: format lint analyse test build verify docs-check install-hooks

format:
	@if [[ -f composer.json ]]; then \
		composer run format; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/check-tasklist.sh; \
	fi

lint:
	@if [[ -f composer.json ]]; then \
		composer run lint; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/quality-gate.sh --docs-only; \
	fi

analyse:
	@if [[ -f composer.json ]]; then \
		composer run analyse; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/quality-gate.sh --docs-only; \
	fi

test:
	@if [[ -f composer.json ]]; then \
		composer run test; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/check-tasklist.sh; \
	fi

build:
	@if [[ -f composer.json ]]; then \
		composer run build; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/quality-gate.sh --docs-only; \
	fi

verify:
	@if [[ -f composer.json ]]; then \
		composer run verify; \
	else \
		echo "No composer.json yet; running documentation checks only."; \
		bash scripts/check-tasklist.sh; \
		bash scripts/quality-gate.sh --docs-only; \
	fi

docs-check:
	@bash scripts/check-tasklist.sh
	@bash scripts/quality-gate.sh --docs-only

install-hooks:
	@bash scripts/install-git-hooks.sh
