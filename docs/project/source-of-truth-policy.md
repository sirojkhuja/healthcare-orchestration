# Source of Truth Policy

## Canonical Authority Order

1. [healthcare_orchestration_platform_laravel_single_source_of_truth.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/healthcare_orchestration_platform_laravel_single_source_of_truth.md)
2. Split documents in `docs/`
3. ADRs in `docs/adr/`
4. Task execution artifacts such as [tasklist.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/project/tasklist.md)
5. Code comments and commit messages

If lower-priority documentation conflicts with higher-priority documentation, the higher-priority artifact wins immediately and the lower-priority artifact must be corrected in the same working session.

## What the SSoT Controls

The canonical source controls:

- Product scope
- Technology stack and pinned versions
- Clean architecture boundaries
- Security and compliance rules
- State machine behavior
- Messaging and integration contracts
- API standards and endpoint inventory
- Testing, observability, CI/CD, and local development rules
- Codex working model and definition of done

## What the Split Documents Add

The split documents do not replace the SSoT. They make it operational by:

- Grouping related decisions into smaller, implementation-friendly files
- Making reading order explicit
- Capturing workflow details needed for day-to-day execution
- Expanding route catalogs into module-level documents
- Turning roadmap items into executable tasks with dependencies and status tracking

## Update Rules

Every meaningful change must answer all of the questions below:

1. Does it change behavior?
2. Does it change an API schema, route, error, or webhook?
3. Does it change architecture, module boundaries, or infrastructure choices?
4. Does it change testing, observability, security, CI/CD, or developer workflow?
5. Does it change the delivery order or scope tracked in the task list?

If the answer to any question is yes, the relevant document must be updated before the work is considered done.

## Conflict Resolution

Use this order when implementation details are unclear:

1. Read the canonical source section that governs the area.
2. Read the matching split document.
3. Read the relevant ADR.
4. If ambiguity remains, record a new ADR and update both the canonical source and the split documents.

Do not invent behavior in code when the documentation is silent on a material decision. Create the missing decision record first.

## Documentation Sync Checklist

- Update the split document closest to the change.
- Update [endpoint-matrix.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/api/endpoint-matrix.md) when routes change.
- Update OpenAPI documentation when requests, responses, status codes, or auth rules change.
- Update an ADR when architecture or infrastructure decisions change.
- Update [tasklist.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/project/tasklist.md) if scope, sequencing, or dependencies change.
- Update [ai-agent-playbook.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/ai-agent-playbook.md) if Codex workflow changes.
