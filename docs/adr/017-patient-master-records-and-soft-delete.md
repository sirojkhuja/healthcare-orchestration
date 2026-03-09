# ADR 017: Patient Master Records and Soft Delete

## Status

Accepted

## Date

2026-03-09

## Context

The canonical route catalog defines patient CRUD, search, contacts, documents, consents, insurance links, tags, exports, and external references, but it does not define:

- which fields belong to the tenant-owned patient master record
- which patient fields are mandatory on creation
- how location data is represented on the patient record
- whether deleting a patient is a hard delete or a reversible record-hiding operation
- how patient uniqueness and tenant usage counting should treat deleted records

`T029` requires these decisions before implementation.

## Decision

Use a tenant-owned patient master record with explicit demographic, contact, and address fields, plus soft delete semantics for `DELETE /patients/{patientId}`.

- `Patient` is tenant-owned.
- The first patient master-record contract contains:
  - `first_name`
  - `last_name`
  - `middle_name`
  - `preferred_name`
  - `sex`
  - `birth_date`
  - `national_id`
  - `email`
  - `phone`
  - `city_code`
  - `district_code`
  - `address_line_1`
  - `address_line_2`
  - `postal_code`
  - `notes`
- `first_name`, `last_name`, `sex`, and `birth_date` are required on create.
- `sex` uses the enum values `female`, `male`, `other`, and `unknown`.
- `national_id` is optional, normalized to uppercase, and must be unique within a tenant among non-deleted patient records.
- `email` is optional and normalized to lowercase.
- `phone` is optional and stored as a trimmed string.
- `city_code` and `district_code` are optional approved location codes. If `district_code` is provided, `city_code` is also required and the district must belong to the selected city.
- `GET /patients` and `GET /patients/{patientId}` return only non-deleted patient records.
- `PATCH /patients/{patientId}` may update any mutable field above for a non-deleted patient.
- `DELETE /patients/{patientId}` is a soft delete that sets `deleted_at`, removes the patient from active directory reads, and preserves the row for auditability and future compliance work.
- Patient CRUD mutations write audit records with object type `patient`.
- Tenant usage for `patients` counts only non-deleted patient records.
- `patients.view` protects read endpoints and `patients.manage` protects create, update, and delete endpoints.
- `T029` covers only patient CRUD and active-directory listing. Contacts, documents, consents, insurance links, tags, search, summaries, timeline views, exports, and external references remain in later tasks.

## Alternatives Considered

- hard-delete patient rows on `DELETE /patients/{patientId}`
- store patient demographic data in an unstructured JSON column
- make patient records clinic-owned instead of tenant-owned
- allow arbitrary free-text districts without the approved location catalog

## Consequences

- Patient APIs now have a stable first-pass record shape for demographics and communication details.
- Soft delete preserves auditability and future compliance options without keeping deleted patients visible in active workflows.
- Tenant usage, patient uniqueness, and future patient search can all target the active patient directory consistently.
- Later patient sub-resources can attach to a stable patient identity without redefining the master record.

## Migration Plan

- add tenant-owned patient persistence with soft delete support and active-directory indexes
- implement patient list, create, get, update, and delete endpoints
- update the canonical source, split docs, OpenAPI, and tests to match the patient contract
