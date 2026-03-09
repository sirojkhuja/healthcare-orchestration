# ADR 021: Provider Master Records and Soft Delete

## Status

Accepted

## Date

2026-03-10

## Context

The canonical route inventory defines provider CRUD routes:

- `GET /providers`
- `POST /providers`
- `GET /providers/{providerId}`
- `PATCH /providers/{providerId}`
- `DELETE /providers/{providerId}`

The current source documents identify the Provider module broadly as:

- provider master records
- credentials
- specialties
- work hours
- time off
- grouping

After `T032`, the repository has no provider aggregate or CRUD implementation, and the documentation does not define:

- which fields belong to the base provider record before profile, specialty, and license work
- whether provider deletion is hard delete or soft delete
- whether providers must be assigned to a clinic at creation time
- how provider type is represented in the base record
- which permission pair protects provider CRUD

`T033` requires those decisions before implementation.

## Decision

Use a tenant-owned provider master record with a clinic-aware optional assignment and soft delete semantics.

- Provider CRUD routes are tenant-owned and require `X-Tenant-Id`.
- `providers.view` protects provider reads.
- `providers.manage` protects provider writes.
- Provider mutation audit records use `object_type = provider` and `object_id = {providerId}`.

### Base Provider Record

The base provider master record contains:

- `first_name`
- `last_name`
- optional `middle_name`
- optional `preferred_name`
- `provider_type`
- optional `email`
- optional `phone`
- optional `clinic_id`
- optional `notes`

### Validation and Normalization

- `first_name`, `last_name`, and `provider_type` are required on create.
- `provider_type` uses enum values:
  - `doctor`
  - `nurse`
  - `other`
- `provider_type` is normalized to lowercase.
- `email`, when present, is normalized to lowercase.
- `phone`, when present, is trimmed.
- `clinic_id` is optional, but when present it must reference an existing clinic in the same tenant scope.

### Read and Lifecycle Behavior

- `GET /providers` returns active provider records only.
- Provider listing is ordered by:
  - `last_name` ascending
  - `first_name` ascending
  - `created_at` ascending
- `GET /providers/{providerId}` returns one active provider in tenant scope.
- `DELETE /providers/{providerId}` is a soft delete.
- Soft-deleted providers are excluded from active directory reads but retained for auditability and for future scheduling and clinical references.

### Separation From Later Tasks

- `T033` covers only the base provider master record and CRUD behavior.
- `T034` adds provider profile, specialties, licenses, and grouping.
- `T035` and `T036` add availability, work hours, time off, calendar, and export behavior.
- Search remains part of the documented future route inventory and is not required to complete `T033`.

### Audit

Provider CRUD writes tenant-scoped audit actions:

- `providers.created`
- `providers.updated`
- `providers.deleted`

## Alternatives Considered

- make clinic assignment mandatory on every provider
- hard-delete providers on `DELETE /providers/{providerId}`
- place contact fields only in the later provider profile route
- defer provider CRUD until specialties and licenses are designed

## Consequences

- The platform can start building tenant-scoped provider directories before the richer provider profile surface exists.
- Later provider profile and specialty work can extend the provider aggregate without redefining the base record.
- Future scheduling and appointment work can reference a stable provider identifier even after soft deletion.

## Migration Plan

- add a tenant-owned `providers` table with soft delete support
- implement provider CRUD routes, commands, queries, handlers, and persistence
- update provider route documentation, the OpenAPI fragment, and the canonical source
- reuse the provider master record in later profile, availability, and scheduling tasks
