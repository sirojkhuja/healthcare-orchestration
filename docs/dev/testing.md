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
- validation and error handling
- serialization and pagination

### Integration Tests

- PostgreSQL repositories
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
- Tenancy: tenant scope leakage tests, settings, limits
- Scheduling: slot conflicts, transition guards, reminder rules
- Billing: invoice lifecycle, idempotent payments, reconciliation
- Claims: adjudication transitions and attachment handling
- Integrations: retries, signature verification, mapper correctness

## Release Gate

No feature is complete until all required test layers for that feature pass locally and in CI.
