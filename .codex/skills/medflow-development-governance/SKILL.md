---
name: medflow-development-governance
description: Use this skill whenever working in the MedFlow repository or answering questions about its implementation, architecture, API, workflow, task progress, standards, quality gates, or delivery process.
---

# MedFlow Development Governance

## Quick Start

Read these files in order before making changes:

1. `docs/project/source-of-truth-policy.md`
2. `docs/project/tasklist.md`
3. `docs/project/progress-workflow.md`
4. `docs/dev/coding-standards.md`
5. `docs/dev/architecture.md`
6. `docs/dev/authentication.md` for login, JWT, refresh-token, and session work
7. `docs/project/release-management.md` for releases, changelogs, cutovers, rollback plans, or tag publication work

Then read only the relevant product, API, testing, security, observability, and ADR documents for the task.

## Required Workflow

1. Pick the highest-priority unblocked task.
2. Mark it `In Progress` in `docs/project/tasklist.md`.
3. Implement the change using the documented clean architecture rules.
4. Update docs, OpenAPI, tests, and ADRs as needed.
5. Run `make format`, `make lint`, `make analyse`, `make test`, `make build`, and `make verify`.
6. Commit with `TASK-ID: imperative summary`.
7. Push to `origin`.
8. Mark the task `Done`.
9. Update and report progress percentage.

## Hard Rules

- The canonical SSoT wins on conflicts.
- Do not implement undocumented business decisions.
- Keep domain logic framework-free.
- Keep integrations behind contracts and adapters.
- Keep tenant isolation, audit, idempotency, and webhook verification mandatory.
- At most one task may be `In Progress`.

## References

- For document scope and reading map: `docs/README.md`
- For route inventory: `docs/api/endpoint-matrix.md`
- For release criteria: `docs/dev/definition-of-done.md`
