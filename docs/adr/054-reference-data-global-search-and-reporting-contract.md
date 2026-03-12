# ADR 054: Reference Data, Global Search, and Reporting Contract

Date: `2026-03-12`

## Status

Accepted

## Context

Task `T066` adds the shared reference-data endpoints, the `/search/*` surface, and the tenant-scoped reporting lifecycle. The canonical SSoT enumerates the routes, but the response shapes, permission model, and reporting behavior need explicit implementation rules.

## Decision

### 1. Reference-data catalogs

The following catalogs are global, read-only, and config-backed in this phase:

- `currencies`
- `countries`
- `languages`
- `diagnosis_codes`
- `procedure_codes`
- `insurance_codes`

All six endpoints return the same entry shape:

- `code`
- `name`
- `is_active`
- `metadata`

Shared filters:

- `q`: optional case-insensitive substring match against code and name
- `limit`: optional, default `25`, max `100`

Authorization:

- all `/reference/*` routes require tenant context, authentication, and `reference.view`

Caching:

- catalog responses are cached in the shared `reference-data` cache namespace using global scope (`tenant_id = null`)

### 2. Shared search surface

The shared search routes exist in addition to the module-local search routes.

- `GET /search/patients`
- `GET /search/providers`
- `GET /search/appointments`
- `GET /search/invoices`
- `GET /search/claims`

Rules:

- `patients`, `appointments`, `invoices`, and `claims` reuse the same criteria and response envelope as their existing module-local search endpoints
- `providers` gets a dedicated directory search contract with filters `q`, `provider_type`, `clinic_id`, `has_email`, `has_phone`, and `limit`
- `/search/providers` requires `providers.view`
- the resource-specific shared search routes require the same permission as the underlying resource read flow

### 3. Global search

`GET /search/global` is a grouped federated query across:

- `patient`
- `provider`
- `appointment`
- `invoice`
- `claim`

Request contract:

- `q`: required
- `types[]`: optional subset of the supported types
- `limit_per_type`: optional, default `5`, max `25`

Authorization:

- `/search/global` requires `search.global`
- each source is also filtered by its resource-view permission
- inaccessible types are omitted from the response instead of failing the whole request
- if the caller has no accessible source types, the response is still `200` with empty result groups

Response contract:

- results are grouped by type
- each item contains `type`, `id`, `title`, `subtitle`, `status`, `score`, and `metadata`
- `meta` returns the normalized query, requested types, returned types, `limit_per_type`, and `total_results`

### 4. Reporting lifecycle

Reports are tenant-scoped saved definitions plus append-only run history.

Definition contract:

- `id`
- `code`
- `name`
- `description`
- `source`
- `format`
- `filters`
- `latest_run`
- `created_at`
- `updated_at`
- `deleted_at`

Run contract:

- `id`
- `report_id`
- `status`
- `format`
- `row_count`
- `file_name`
- `storage`
- `generated_at`

Supported report sources in this phase:

- `patients`
- `providers`
- `appointments`
- `invoices`
- `claims`

Reporting rules:

- report `code` is normalized to lowercase snake case and unique per tenant among non-deleted definitions
- only `csv` is supported in this phase
- `filters` are stored as normalized source-specific search criteria
- `POST /reports/{reportId}:run` executes synchronously and records one completed run row
- generated files are stored on the `artifacts` disk
- `GET /reports/{reportId}/download` downloads the latest completed run artifact
- `DELETE /reports/{reportId}` soft-deletes the definition only; historical run rows remain append-only

List filters:

- `q`
- `source`
- `limit`

Authorization:

- `GET /reports`, `GET /reports/{reportId}`, and `GET /reports/{reportId}/download` require `reports.view`
- `POST /reports`, `POST /reports/{reportId}:run`, and `DELETE /reports/{reportId}` require `reports.manage`

Audit:

- `reports.created`
- `reports.ran`
- `reports.deleted`

### 5. CSV serialization

Report CSV files flatten nested values using dot-notation keys. Scalar leaf values are written directly. Arrays of scalars or objects are JSON-encoded into the flattened cell value.

## Consequences

Positive:

- shared search and reporting reuse existing module read services instead of duplicating business logic
- the reference-data contract stays stable even if the underlying config catalog grows
- reporting becomes implementation-ready without introducing asynchronous scheduling before it is needed

Negative:

- reports are synchronous in this phase and should remain small-to-medium tenant datasets
- config-backed reference catalogs require code review for catalog changes until a dedicated admin source exists

## Follow-up

- `T066` implements the endpoints, repositories, tests, docs, and OpenAPI
- future work may add asynchronous report execution, richer reference-data governance, and broader report sources
