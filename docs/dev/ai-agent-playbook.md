# AI Agent Playbook

## Mandatory Reading Before Work

Codex must read, in this order:

1. [source-of-truth-policy.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/project/source-of-truth-policy.md)
2. [tasklist.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/project/tasklist.md)
3. [progress-workflow.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/project/progress-workflow.md)
4. [coding-standards.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/coding-standards.md)
5. [persistence.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/persistence.md) when the task touches migrations, PostgreSQL schema, UUIDs, or indexes
6. [caching.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/caching.md) when the task touches Redis usage, cache keys, invalidation, or cache-aside behavior
7. [messaging.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/messaging.md) when the task touches Kafka, outbox relays, consumers, or async delivery guarantees
8. [authentication.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/authentication.md) when the task touches login, JWTs, refresh tokens, current-user flows, or auth sessions
9. [tenancy.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/tenancy.md) when the task touches tenant-owned data or routes
10. [authorization.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/authorization.md) when the task touches permissions, RBAC, policies, or protected routes
11. [idempotency.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/idempotency.md) when the task touches protected commands, replay safety, payments, scheduling, or webhooks
12. [request-context.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/request-context.md) when the task touches request metadata, jobs, or emitted messages
13. [audit.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/dev/audit.md) when the task touches regulated data changes or retention behavior
14. the most relevant module, API, security, testing, and observability documents

## Golden Path for Features

1. choose the module and verify dependencies are complete
2. mark the task `In Progress`
3. define or extend domain objects
4. add application commands or queries and handlers
5. add contracts
6. implement infrastructure adapters
7. add presentation endpoints
8. update OpenAPI and docs
9. add tests
10. run all required quality gates
11. commit, push, and mark task `Done`

## Behavior Rules

- never implement undocumented material decisions silently
- never bypass the task workflow
- never leave docs behind code
- never treat optional integrations as enabled by default
- never violate file, method, or layering limits

## Escalation Rule

If the documentation does not answer a decision that changes business behavior, create or request an ADR before continuing.
