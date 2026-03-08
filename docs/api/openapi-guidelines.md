# OpenAPI Guidelines

## Standard

- OpenAPI version: `3.1.1`
- API base path: `/api/v1`
- Event versioning is independent from API versioning

## Design Rules

- Prefer resource-oriented routes.
- Use explicit action routes for workflow transitions such as `:confirm` or `:approve`.
- Keep request and response schemas explicit and reusable.
- Expose UUIDs as public identifiers.
- Include example payloads for every endpoint.

## Required Components

Every endpoint definition must include:

- summary
- operation ID
- tags
- authentication requirements
- request body schema if applicable
- response schemas for success and error cases
- pagination schema when list endpoints paginate
- idempotency header requirements where applicable
- example payloads

## Operation ID Convention

Use application use-case names:

- `login`
- `createTenant`
- `scheduleAppointment`
- `approveClaim`

The OpenAPI operation ID should map cleanly to exactly one application command or query.

## Error Model

Every error response must expose:

- `code`
- `message`
- `details`
- `trace_id`
- `correlation_id`

See [error-catalog.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/api/error-catalog.md) for the approved code set.

## Pagination

- Cursor pagination is preferred.
- List responses must document sorting and filtering options.
- Cursor fields must be opaque to clients.

## Idempotency

Idempotency keys are required for:

- payment initiation
- appointment scheduling
- webhook processing

Document:

- header name
- uniqueness scope
- retention window
- replay behavior

## Security Documentation

- Document auth requirements on every endpoint.
- Describe tenant scoping behavior where relevant.
- For tenant-owned endpoints, document the `X-Tenant-Id` header unless the route parameter is the documented tenant context source.
- If a tenant-owned endpoint accepts both route tenant scope and `X-Tenant-Id`, document that mismatches fail with `403`.
- Mark admin-only endpoints explicitly.
- Document webhook signature expectations in webhook operations.

## Documentation Sync Rule

OpenAPI must be updated in the same change set as any API-affecting implementation change.
