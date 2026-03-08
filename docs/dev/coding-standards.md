# Coding Standards

## Non-Negotiable Limits

- Max file length: `250` lines soft, `400` lines hard
- Max method length: `40` lines
- Max public methods per class: `12`
- Max cyclomatic complexity per method: `10`

These limits are quality gates, not suggestions.

## Clean Architecture Rules

- Use module boundaries consistently.
- Keep business logic out of controllers, requests, and jobs.
- Keep domain classes framework-independent.
- Use interfaces for external services and repositories.
- Keep write use cases in commands and read use cases in queries.

## Dependency Injection

- No direct `new` in business code outside approved factories.
- Inject contracts instead of concrete adapters.
- Use the container only at composition boundaries, not inside domain or application logic.

## Required Patterns

Use the patterns mandated by the SSoT when they fit the problem:

- Repository
- Unit of Work
- Factory
- Builder
- Strategy
- Adapter
- Internal Facade
- Decorator
- Observer
- Mediator or event bus
- Command
- Chain of Responsibility
- Specification
- State
- CQRS
- Outbox

## No Magic Rule

- Do not hide business rules in helpers, traits, global functions, or model events.
- Make use cases explicit and searchable.
- Prefer named DTOs and value objects over loose arrays.

## Naming Rules

- Handlers use verb-noun names such as `CreateAppointmentCommandHandler`.
- Queries describe the answer they return.
- DTOs describe transport or view purpose, not storage concerns.
- Route names and OpenAPI operation IDs must align with application use-case names.

## Documentation Rules

Every change that affects behavior must update:

- the relevant split document,
- OpenAPI if the API changes,
- tests,
- an ADR if architecture changes,
- the task list if scope or sequence changes.

## Review Rules

Before considering a change review-ready, confirm:

- no layer violations exist,
- no file exceeds limits,
- no dead abstractions were introduced,
- nullability and error paths are handled explicitly,
- logging and audit behavior exist where needed,
- integration logic remains behind contracts,
- security implications are addressed.
