# Endpoint Matrix

The original SSoT carries the full route inventory. This document is the split navigation layer for that inventory. Each module file below contains the route set, target use case, and owning module.

## Module Route Documents

- [identity-access.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/api/modules/identity-access.md)
- [tenancy-clinics.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/api/modules/tenancy-clinics.md)
- [patients-providers.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/api/modules/patients-providers.md)
- [scheduling-clinical.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/api/modules/scheduling-clinical.md)
- [revenue-insurance.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/api/modules/revenue-insurance.md)
- [platform-integrations-ops.md](/var/www/personal/said-team/portfolio/healthcare-orchestration/docs/api/modules/platform-integrations-ops.md)

## Route Conventions

- Base path: `/api/v1`
- Explicit state transitions use `:action` routes.
- Bulk operations use `/bulk` or `:bulk-*`.
- Export endpoints use `/export`.
- Webhooks live under `/webhooks/*`.
- Admin operations live under `/admin/*`.

Revenue and insurance route inventory includes the Uzbek payment callback surface under:

- `/payments:reconcile`
- `/payments/reconciliation-runs`
- `/payments/reconciliation-runs/{runId}`
- `/webhooks/payme`
- `/webhooks/click`
- `/webhooks/uzum`
- `/webhooks/payme:verify`
- `/webhooks/click:verify`
- `/webhooks/uzum:verify`

Platform integrations route inventory includes the tenant-scoped integrations hub surface under:

- `/integrations`
- `/integrations/{integrationKey}`
- `/integrations/{integrationKey}:enable`
- `/integrations/{integrationKey}:disable`
- `/integrations/{integrationKey}/credentials`
- `/integrations/{integrationKey}/health`
- `/integrations/{integrationKey}:test-connection`
- `/integrations/{integrationKey}/logs`
- `/integrations/{integrationKey}/webhooks`
- `/integrations/{integrationKey}/webhooks/{webhookId}:rotate-secret`
- `/integrations/{integrationKey}/tokens`
- `/integrations/{integrationKey}/tokens:refresh`
- `/integrations/myid:verify`
- `/webhooks/myid`
- `/integrations/eimzo:sign`
- `/webhooks/eimzo`

Observability and admin ops route inventory includes:

- `/ping`
- `/health`
- `/ready`
- `/live`
- `/metrics`
- `/version`
- `/admin/cache:flush`
- `/admin/cache:rebuild`
- `/admin/jobs`
- `/admin/jobs/{jobId}:retry`
- `/admin/kafka/lag`
- `/admin/kafka:replay`
- `/admin/outbox`
- `/admin/outbox:drain`
- `/admin/outbox/{outboxId}:retry`
- `/admin/logging/pipelines`
- `/admin/logging:pipeline-reload`
- `/admin/feature-flags`
- `/admin/rate-limits`
- `/admin/config`
- `/admin/config:reload`

Internal infrastructure paths outside the public API inventory include:

- `/internal/metrics` for Prometheus scraping through nginx with `OPS_PROMETHEUS_SCRAPE_KEY`

Audit and compliance route inventory includes the tenant-scoped governance surface under:

- `/audit/events`
- `/audit/events/{eventId}`
- `/audit/export`
- `/audit/retention`
- `/audit/object/{objectType}/{objectId}`
- `/consents`
- `/consents/{consentId}`
- `/data-access-requests`
- `/data-access-requests/{requestId}`
- `/data-access-requests/{requestId}:approve`
- `/data-access-requests/{requestId}:deny`
- `/compliance/pii-fields`
- `/compliance/pii:rotate-keys`
- `/compliance/pii:re-encrypt`
- `/compliance/reports`

Reference data, shared search, and reporting route inventory includes:

- `/reference/currencies`
- `/reference/countries`
- `/reference/languages`
- `/reference/diagnosis-codes`
- `/reference/procedure-codes`
- `/reference/insurance-codes`
- `/search/global`
- `/search/patients`
- `/search/providers`
- `/search/appointments`
- `/search/invoices`
- `/search/claims`
- `/reports`
- `/reports/{reportId}`
- `/reports/{reportId}:run`
- `/reports/{reportId}/download`

## Inventory Summary

| Area | Approximate endpoints |
| --- | --- |
| Auth and Identity | `16`, including password reset and session administration |
| Tenants | `12` |
| Clinics and Locations | `26` |
| Users, Roles, Permissions | `38` |
| Patients | `30` |
| Providers and Availability | `34` |
| Scheduling and Appointments | `44` |
| Treatment and Encounters | `32` |
| Labs | `28` |
| Prescriptions and Pharmacy | `22` |
| Billing and Payments | `40` |
| Insurance Claims | `28` |
| Notifications | `34` |
| Integrations Hub | `40` |
| Audit and Compliance | `18` |
| Observability and Admin Ops | `22` |
| Reference Data and Search | `18` |

Total planned inventory is roughly `280` to `320` distinct endpoints depending on optional integrations and reporting scope.

The generated production OpenAPI bundle under `docs/api/openapi/openapi.yaml` is the exact runtime surface artifact. `T068` adds contract tests so the live public route table and the bundled OpenAPI operations must stay in sync.

## Matrix Rule

Every route must map to exactly one application command or query handler.
