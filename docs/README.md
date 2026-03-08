# MedFlow Documentation Map

This repository uses the markdown file below as the canonical product source:

- [healthcare_orchestration_platform_laravel_single_source_of_truth.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/healthcare_orchestration_platform_laravel_single_source_of_truth.md)

The split documents in this repository are derived from that source and exist to make day-to-day implementation easier. When there is any ambiguity or drift, the canonical source wins and the derived documents must be updated immediately.

## Reading Order

1. Start with [source-of-truth-policy.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/project/source-of-truth-policy.md).
2. Review [overview.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/product/overview.md) and [modules-and-domain.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/product/modules-and-domain.md).
3. Read [architecture.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/architecture.md) and [coding-standards.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/coding-standards.md).
4. Read [tenancy.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/tenancy.md) before touching tenant-owned data or routes.
5. Read [authorization.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/authorization.md) before touching policies, permissions, RBAC, or protected routes.
6. Read [request-context.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/request-context.md) before touching request metadata, jobs, or emitted events.
7. Read [audit.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/audit.md) before implementing regulated data changes or retention logic.
8. Read the relevant API module document in [docs/api/modules](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/api/modules).
9. Open [tasklist.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/project/tasklist.md) before making any code or documentation change.

## Documentation Areas

### Project Governance

- [source-of-truth-policy.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/project/source-of-truth-policy.md): authority order, conflict resolution, and sync rules.
- [progress-workflow.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/project/progress-workflow.md): mandatory task lifecycle for Codex and humans.
- [roadmap.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/project/roadmap.md): implementation sequence.
- [tasklist.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/project/tasklist.md): executable project backlog with status tracking.

### Product

- [overview.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/product/overview.md): business goals, user roles, and platform scope.
- [modules-and-domain.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/product/modules-and-domain.md): modules, aggregates, and bounded contexts.
- [state-machines-and-events.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/product/state-machines-and-events.md): workflow states, Kafka events, and delivery guarantees.
- [integrations-catalog.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/product/integrations-catalog.md): external provider strategy and Uzbekistan-specific integrations.

### Engineering

- [architecture.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/architecture.md)
- [tenancy.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/tenancy.md)
- [authorization.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/authorization.md)
- [request-context.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/request-context.md)
- [audit.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/audit.md)
- [coding-standards.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/coding-standards.md)
- [testing.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/testing.md)
- [security.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/security.md)
- [observability.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/observability.md)
- [ci-cd.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/ci-cd.md)
- [local-dev.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/local-dev.md)
- [ai-agent-playbook.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/ai-agent-playbook.md)
- [definition-of-done.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/definition-of-done.md)

### API

- [openapi-guidelines.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/api/openapi-guidelines.md)
- [error-catalog.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/api/error-catalog.md)
- [webhooks.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/api/webhooks.md)
- [endpoint-matrix.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/api/endpoint-matrix.md)
- Module route sets under [docs/api/modules](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/api/modules)

### ADRs

- [000-template.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/adr/000-template.md)
- [001-clean-architecture.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/adr/001-clean-architecture.md)
- [002-kafka-outbox.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/adr/002-kafka-outbox.md)
- [003-redis-caching.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/adr/003-redis-caching.md)
- [004-integrations-framework.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/adr/004-integrations-framework.md)
- [005-observability-stack.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/adr/005-observability-stack.md)

## Maintenance Rule

Any change to platform behavior, API shape, architecture, workflow, or operational policy must update the relevant split document in the same change set.
