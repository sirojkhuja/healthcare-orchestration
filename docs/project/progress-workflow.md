# Delivery Workflow

This workflow is mandatory for Codex and for human contributors working in this repository.

## Required Task Lifecycle

1. Open [tasklist.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/project/tasklist.md).
2. Select the highest-priority task whose dependencies are already done.
3. Mark the task `In Progress`.
4. Update the active task and progress fields in the task list.
5. Implement the change.
6. Update documentation, OpenAPI, ADRs, and tests required by the change.
7. Run formatting, linting, static analysis, tests, build, and verification commands.
8. Fix every failure before moving on.
9. Commit with the task ID in the message.
10. Push to the configured remote.
11. Mark the task `Done`.
12. Recompute and display project progress.
13. Continue with the next available task.

## Mandatory Verification Sequence

Use this order unless a task explicitly states otherwise:

1. `make format`
2. `make lint`
3. `make analyse`
4. `make test`
5. `make build`
6. `make verify`

If the Laravel project has not yet been bootstrapped, run the documentation and governance checks only:

1. `bash scripts/check-tasklist.sh`
2. `bash scripts/quality-gate.sh`

## Commit Policy

- One coherent task per commit whenever possible.
- Commit message format: `TASK-ID: imperative summary`
- Example: `T012: add tenant request context middleware`
- Do not commit failing tests or failing quality gates.
- Do not push without updating task status and progress.

## Progress Accounting

- Progress is measured as `Done tasks / Total tasks`.
- Only tasks marked `Done` count toward progress.
- `In Progress` tasks count as zero until verified, committed, and pushed.
- If scope changes, update total task count and recompute percentage in the same edit.

## Blocking Rules

Stop and document the blocker when:

- A decision is not covered by the canonical source or an ADR.
- Required credentials or external systems are unavailable.
- A dependency task is still `Todo` or `In Progress`.
- Quality gates fail for reasons that require broader architectural change.

When blocked:

1. Record the blocker under the task.
2. Keep the task `In Progress` only if active work continues immediately.
3. Otherwise return it to `Todo`, document the blocker, and pick the next unblocked task.
