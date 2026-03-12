# ADR 056: OpenAPI Bundle and Contract Governance

Date: `2026-03-13`

## Status

Accepted

## Context

The canonical source requires OpenAPI `3.1.1`, full schemas and examples for every route, and contract tests for the API surface. By the time `T067` completed, the repository had six module-level OpenAPI fragments and a large runtime route surface, but it still lacked four production-grade guarantees:

1. one generated document that exactly represents the public `/api/v1` API surface
2. official schema validation against the OpenAPI `3.1.1` JSON Schema
3. automated drift detection between the live Laravel route table and the bundled document
4. a clear authoring rule for fragment sources versus generated artifacts

Without those guarantees, the split docs could remain individually valid while the generated API contract still drifted from runtime behavior.

## Decision

Treat the module fragments under `docs/api/openapi/*.yaml` as the only hand-authored OpenAPI sources and generate a repository-owned production bundle plus contract enforcement around them.

### 1. Authoring model

- Hand-authored OpenAPI sources are limited to:
  - `docs/api/openapi/identity-access-auth.yaml`
  - `docs/api/openapi/patients-providers.yaml`
  - `docs/api/openapi/platform-integrations-ops.yaml`
  - `docs/api/openapi/revenue-insurance.yaml`
  - `docs/api/openapi/scheduling-clinical.yaml`
  - `docs/api/openapi/tenancy-clinics.yaml`
- Generated artifacts are:
  - `docs/api/openapi/openapi.json`
  - `docs/api/openapi/openapi.yaml`
- Generated artifacts are committed to the repository and must not be edited manually.

### 2. Bundle generation

- `scripts/openapi/build.mjs` is the canonical bundle builder.
- The builder must:
  - load all six fragment files in a fixed order
  - normalize the shared `ApiErrorResponse` schema and `Idempotency-Key` header contract
  - prefix non-security components per fragment to avoid collisions
  - merge security schemes safely
  - preserve explicit operation-level security declarations
  - inject the standard successful-response headers:
    - `X-Request-Id`
    - `X-Correlation-Id`
    - `X-Causation-Id`
- `npm run openapi:build` is the stable bundle-generation command.

### 3. Validation contract

- `npm run openapi:validate` must:
  - rebuild the bundle
  - validate repository-specific conventions such as summary, operation ID, tags, responses, explicit security declarations, unique operation IDs, and successful-response request metadata headers
- `bash scripts/openapi/validate-schema.sh` must validate the generated `openapi.json` bundle against the vendored official OpenAPI `3.1.1` schema stored at `scripts/openapi/schema/openapi-3.1.1.schema.json`
- the Docker-backed application image must include the Python `jsonschema` validator needed for that official schema check

### 4. Contract test coverage

- `tests/Feature/Contracts/OpenApiContractCoverageTest.php` is required and must verify:
  - the live `/api/v1` route table equals the bundled OpenAPI operation set exactly
  - authenticated routes document non-empty security requirements
  - tenant-required routes document either `X-Tenant-Id` or `tenantId`
  - idempotent routes document `Idempotency-Key`
  - successful responses document `X-Request-Id` and `X-Correlation-Id`

### 5. CI and local workflow

- `make test` must build the OpenAPI bundle before running the PHP test suite
- `make verify` must run both:
  - repository OpenAPI convention validation
  - official OpenAPI schema validation
- `.github/workflows/ci.yml` must enforce the same OpenAPI validation steps in CI

## Consequences

- The repository now has one generated, production-grade API document instead of six loosely related fragments.
- Route drift becomes a test failure instead of a documentation surprise.
- OpenAPI schema regressions are caught against the official `3.1.1` schema, not only local conventions.
- Contributors must treat the generated bundle as a build artifact and update fragments, docs, tests, and CI together whenever the API surface changes.
