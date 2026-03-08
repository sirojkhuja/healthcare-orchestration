# Architecture

## Pinned Technology Stack

### Core Runtime

- PHP `8.5.3`
- Laravel `12.53.0`
- Composer `2.x`

### Data and Messaging

- PostgreSQL `18.3`
- Redis `8.6.0` with TLS enabled
- Apache Kafka `4.2.0`

### API and Developer Experience

- OpenAPI `3.1.1`
- Swagger UI `5.31.2`
- Pest `4.2.0`
- PHPStan at max level
- Psalm in strict mode
- Laravel Pint with project configuration

### Observability

- Sentry PHP SDK `4.21.0`
- OpenTelemetry Collector `0.146.1`
- Prometheus `3.10.0`
- Grafana `12.3.4`
- Elastic Stack `9.3.1`

## Architectural Style

The platform uses a modular clean architecture:

- `Presentation`: controllers, requests, responses, and API transport details
- `Application`: commands, queries, handlers, DTOs, policies, and interfaces
- `Domain`: entities, value objects, domain services, specifications, and state machines
- `Infrastructure`: persistence, messaging, external integrations, caches, and framework glue

## Layer Dependency Rules

- Presentation may depend on application contracts and DTOs.
- Application may depend on domain abstractions and pure domain logic.
- Domain depends on nothing framework-specific.
- Infrastructure implements interfaces defined above it.

Forbidden:

- Domain classes importing Laravel
- Controllers running business logic
- Controllers calling third-party APIs directly
- Cross-module imports that bypass public contracts

## Module Layout

The intended application layout is:

```text
app/
  Modules/
    Scheduling/
      Domain/
      Application/
      Infrastructure/
      Presentation/
    Billing/
    ...
  Shared/
    Domain/
    Application/
    Infrastructure/
bootstrap/
config/
database/
docs/
```

## Request Flow

1. HTTP request enters Laravel edge.
2. Tenant and correlation context are resolved.
3. Authorization is checked.
4. Presentation layer maps transport payload into an application command or query.
5. Application handler coordinates domain logic and infrastructure boundaries.
6. Domain logic applies policies, invariants, and state transitions.
7. Infrastructure persists data, writes outbox records, and resolves integrations.
8. Response DTO is mapped back to transport output.
9. Logs, traces, and audit data are emitted.

## Tenancy Model

- Multi-tenancy is enforced via `tenant_id`.
- Tenant scoping lives in infrastructure and shared request context, not in domain entities.
- Every query against tenant-owned data must include tenant filtering.
- Shared reference data may be global only when explicitly documented.

## Persistence Rules

- PostgreSQL schema is authoritative and migration-driven.
- Public identifiers use UUIDs.
- Foreign keys and indexes are mandatory.
- Partial indexes should optimize tenant, status, and date filters.
- Full-text search is required for patient and provider directories.

## Caching Model

- Redis uses cache-aside with explicit invalidation.
- Cache keys are tenant-prefixed.
- Cached domains include permissions, availability, settings, token metadata, rate limits, and reference data.

## Messaging Model

- Kafka is the event backbone.
- Domain events are persisted through an outbox table inside the same transaction as business changes.
- Consumers must be idempotent and operationally observable.

## Integration Model

- Every provider integration is hidden behind an application contract and infrastructure adapter.
- Retry, circuit breaker, and webhook verification are not optional.
- Mapping between external payloads and internal DTOs must stay explicit and testable.

## Architectural Constraints for Future Code

- Each use case gets exactly one command or query handler.
- State transitions must be explicit action use cases.
- Domain models remain pure and test-friendly.
- Shared abstractions must not become a dumping ground for module behavior.
