# AGENTS.md

## Purpose

This repository is governed by the canonical source document at `docs/healthcare_orchestration_platform_laravel_single_source_of_truth.md`. All development work must follow that document and the split documentation derived from it.

## Mandatory Reading Order

Before answering questions, planning work, or editing code, read:

1. `docs/project/source-of-truth-policy.md`
2. `docs/project/tasklist.md`
3. `docs/project/progress-workflow.md`
4. `docs/dev/coding-standards.md`
5. `docs/dev/architecture.md`
6. The most relevant module document under `docs/api/modules/`
7. The most relevant product, testing, security, observability, and CI/CD documents

Also load the local skill at `.codex/skills/medflow-development-governance/SKILL.md`.

## Authority Order

1. `docs/healthcare_orchestration_platform_laravel_single_source_of_truth.md`
2. Split documents in `docs/`
3. ADRs in `docs/adr/`
4. This `AGENTS.md`
5. Code comments and commit messages

If any lower-priority artifact conflicts with a higher-priority artifact, the higher-priority artifact wins and the lower-priority artifact must be updated immediately.

## Mandatory Task Workflow

For every implementation task:

1. Open `docs/project/tasklist.md`.
2. Choose the highest-priority unblocked task.
3. Mark it `In Progress`.
4. Update `Active Task` and progress if needed.
5. Implement the change.
6. Update all affected docs, OpenAPI, ADRs, and tests.
7. Run `make format`, `make lint`, `make analyse`, `make test`, `make build`, and `make verify`.
8. Fix all failures.
9. Commit with `TASK-ID: imperative summary`.
10. Push to `origin`.
11. Mark the task `Done`.
12. Update progress percentage and display it in the work summary.
13. Continue to the next task.

At most one task may be `In Progress` at a time.

## Implementation Rules

- Keep domain code free of Laravel and HTTP concerns.
- Use commands and queries for every use case.
- Keep controllers thin.
- Keep integrations behind contracts and adapters.
- Keep files small and within documented limits.
- Do not invent material business behavior when the docs are silent. Record an ADR first.
- Treat tenant isolation, auditability, idempotency, and webhook verification as mandatory.

## Quality Rules

- No task is complete without tests.
- No API change is complete without OpenAPI updates.
- No architecture change is complete without an ADR update.
- No merge-ready change may leave docs behind implementation.
- Use the Git hooks and scripts in this repository. Do not bypass them unless you are fixing the governance tooling itself.

## When Answering Questions

- Prefer the project documentation over memory.
- Cite the relevant repo document in the response.
- If the docs do not answer a material decision, say so and create or request an ADR before implementation.
