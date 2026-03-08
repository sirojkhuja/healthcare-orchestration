# Testing Strategy

## Coverage Targets

- Domain: `100 percent`
- Application: `100 percent`
- Infrastructure critical paths: full coverage for high-risk behavior
- Presentation: feature coverage for all public API behavior

## Required Test Types

### Unit Tests

- domain entities
- value objects
- specifications
- state machines
- pure application services

### Feature Tests

- HTTP endpoints
- auth and authorization behavior
- tenant scoping
- tenant context resolution from headers and route parameters
- protected route permission enforcement and invalidation behavior
- idempotent command replay, payload mismatch protection, and scope isolation
- request metadata headers and propagation across queue boundaries
- validation and error handling
- serialization and pagination

### Integration Tests

- PostgreSQL repositories
- migration suite freshness, schema conventions, and index creation
- Redis cache behavior
- Kafka outbox relay and consumers
- provider adapters and webhook verification

### Contract Tests

- OpenAPI schema validation
- request and response example drift detection
- provider adapter request and response mapping

## Test Data Rules

- Use builders and mother objects for domain setup.
- Freeze time in tests.
- Avoid randomness unless seeded and repeatable.
- Keep fixtures module-local and minimal.

## Test Naming

- Name tests by business behavior, not by method name.
- One business rule per test when practical.
- State machine tests must describe current state, action, and outcome.

## Required Coverage by Module

- IAM: auth, MFA, token lifecycle, RBAC, rate limits
- IAM: managed API keys, registered devices, and tenant IP allowlist enforcement
- Tenancy: tenant scope leakage tests, settings, limits
- Tenancy: missing tenant context, conflicting tenant context, and write-guard behavior
- Scheduling: slot conflicts, transition guards, reminder rules
- Billing: invoice lifecycle, idempotent payments, reconciliation
- Claims: adjudication transitions and attachment handling
- Integrations: retries, signature verification, mapper correctness
- Shared platform: request metadata propagation, tenant context hydration, and event metadata helpers
- Shared platform: authorization cache hits, invalidation, and tenant-aware permission checks
- Shared platform: tenant-scoped role CRUD, user-role assignment, role-permission replacement, permission catalog reads, and RBAC audit queries
- Shared platform: tenant user create, update, activate, deactivate, lock, unlock, delete, admin password reset, bulk import, and bulk action flows
- Shared platform: JWT auth login, MFA challenge flows, recovery-code handling, security-event writes, refresh rotation, logout revocation, current-user retrieval, password reset flows, and auth-session administration
- Shared platform: API key create/list/revoke flows, revoked-key rejection, tenant allowlist enforcement, and device registration lifecycle
- Shared platform: idempotency replay, in-flight duplicate rejection, and tenant-aware scope separation
- Shared platform: migration freshness, UUID primary keys, and partial index presence
- Shared platform: tenant-prefixed cache keys, item invalidation, and namespace invalidation behavior
- Shared platform: outbox delivery, retry scheduling, and consumer replay suppression
- Audit: immutable persistence, actor capture, before and after values, and retention pruning

## Release Gate

No feature is complete until all required test layers for that feature pass locally and in CI.
