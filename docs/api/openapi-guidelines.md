# OpenAPI Guidelines

## Standard

- OpenAPI version: `3.1.1`
- API base path: `/api/v1`
- Event versioning is independent from API versioning

## Authoring Sources

Hand-authored OpenAPI sources are limited to the module fragments:

- `docs/api/openapi/identity-access-auth.yaml`
- `docs/api/openapi/patients-providers.yaml`
- `docs/api/openapi/platform-integrations-ops.yaml`
- `docs/api/openapi/revenue-insurance.yaml`
- `docs/api/openapi/scheduling-clinical.yaml`
- `docs/api/openapi/tenancy-clinics.yaml`

Do not edit `docs/api/openapi/openapi.yaml` or `docs/api/openapi/openapi.json` manually. Those two files are generated production bundle artifacts.

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
- documented request and correlation response headers where applicable
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
Operation IDs must also be globally unique across the generated repository bundle.

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
- replay response header when the original result is returned again

## Security Documentation

- Document auth requirements on every endpoint.
- Describe tenant scoping behavior where relevant.
- Document `X-Request-Id` and `X-Correlation-Id` response headers for API operations.
- For tenant-owned endpoints, document the `X-Tenant-Id` header unless the route parameter is the documented tenant context source.
- If a tenant-owned endpoint accepts both route tenant scope and `X-Tenant-Id`, document that mismatches fail with `403`.
- Mark admin-only endpoints explicitly.
- Document webhook signature expectations in webhook operations.

## Bundle Contract

- Build the production bundle with `npm run openapi:build`.
- The bundle is assembled from the six module fragments in a fixed order.
- Shared error and idempotency components are normalized during bundle generation.
- Successful `2xx` and `3xx` responses in the generated bundle must document:
  - `X-Request-Id`
  - `X-Correlation-Id`
  - `X-Causation-Id`
- The generated bundle must match the live `/api/v1` Laravel route table exactly.

## Validation Contract

Every API-affecting change must pass all of the following:

- `npm run openapi:validate`
- `bash scripts/openapi/validate-schema.sh`
- `tests/Feature/Contracts/OpenApiContractCoverageTest.php`

The contract suite must prove:

- route-to-spec parity for all public `/api/v1` operations
- documented security on authenticated routes
- documented tenant context for tenant-required routes
- documented `Idempotency-Key` on idempotent routes
- documented request metadata headers on successful responses

## Documentation Sync Rule

OpenAPI must be updated in the same change set as any API-affecting implementation change.
