# ADR 018: Patient Search, Summary, Timeline, and Export

## Status

Accepted

## Date

2026-03-09

## Context

The canonical route inventory defines:

- `GET /patients/search`
- `GET /patients/{patientId}/summary`
- `GET /patients/{patientId}/timeline`
- `GET /patients/export`

After `T029`, the patient module has only the tenant-owned patient master record and patient audit events. The source documents do not yet define:

- which patient fields participate in search
- how patient search relevance should order matches
- what the first patient summary payload contains
- whether the timeline is sourced from patient audits or a separate event stream
- which export format is required and how generated files are referenced

`T030` requires those decisions before implementation.

## Decision

Use the patient master record plus patient audit events to deliver the first search, summary, timeline, and export contract.

- `GET /patients/search` returns active tenant-scoped patient records ordered by a deterministic relevance model.
- Search supports:
  - `q`
  - `sex`
  - `city_code`
  - `district_code`
  - `birth_date_from`
  - `birth_date_to`
  - `created_from`
  - `created_to`
  - `has_email`
  - `has_phone`
  - `limit`
- Search considers only non-deleted patient records.
- `q` matches across `first_name`, `last_name`, `middle_name`, `preferred_name`, `national_id`, `email`, and `phone`.
- Query token matching uses AND semantics across whitespace-separated tokens.
- Relevance ordering prefers:
  - exact identifier matches
  - prefix matches on patient names and identifiers
  - substring matches
  - then deterministic tie-breaking by `last_name`, `first_name`, and `created_at`
- `GET /patients/{patientId}/summary` returns:
  - the active patient master record
  - derived summary fields based on the master record and patient audit activity
- The first summary contract contains:
  - `display_name`
  - `initials`
  - `age_years`
  - `directory_status`
  - `contact`
  - `location`
  - `timeline_event_count`
  - `last_activity_at`
- `display_name` uses `preferred_name + last_name` when `preferred_name` exists; otherwise it uses `first_name + last_name`.
- `initials` use the first and last words of `display_name`.
- `last_activity_at` uses the newest patient audit event time when audit activity exists; otherwise it falls back to the patient record `updated_at`.
- `GET /patients/{patientId}/timeline` uses immutable audit events with `object_type = patient` and `object_id = {patientId}`.
- Timeline results are tenant-scoped and returned newest first.
- Timeline supports a `limit` parameter with a maximum of `100`.
- `GET /patients/export` exports the active patient search result set to CSV only.
- Export accepts the same filter set as patient search plus:
  - `format`, currently limited to `csv`
  - `limit`, default `1000`, maximum `1000`
- Export writes the generated CSV through the shared export storage abstraction on the private `exports` disk.
- The exported CSV columns are:
  - `id`
  - `tenant_id`
  - `display_name`
  - `first_name`
  - `last_name`
  - `middle_name`
  - `preferred_name`
  - `sex`
  - `birth_date`
  - `age_years`
  - `national_id`
  - `email`
  - `phone`
  - `city_code`
  - `district_code`
  - `address_line_1`
  - `address_line_2`
  - `postal_code`
  - `notes`
  - `created_at`
  - `updated_at`
  - `exported_at`
- Export responses return a generated export reference with:
  - `export_id`
  - `format`
  - `file_name`
  - `row_count`
  - `generated_at`
  - `filters`
  - `storage` containing the private disk and relative path reference
- Export creation writes an audit event with action `patients.exported`, object type `patient_export`, and metadata containing the tenant-scoped filter set and export reference.
- `patients.view` protects search, summary, timeline, and export because they are read operations.

## Alternatives Considered

- implement dedicated search infrastructure before the patient directory contract stabilizes
- make timeline a separate event stream unrelated to audit records
- return export files inline without persisting them through the shared storage abstraction
- leave patient summary undefined until contacts, documents, consents, and insurance links exist

## Consequences

- The patient directory now has a stable first-pass search and export behavior without introducing a separate indexing system.
- Patient summary remains narrow and truthful to currently implemented patient data instead of implying future clinical or billing details that do not yet exist.
- Timeline behavior reuses immutable audit infrastructure and preserves regulated change visibility.
- Export storage references are private implementation references, not public download URLs. A later artifact-download task may wrap them in signed retrieval flows without redefining the export payload.

## Migration Plan

- add the patient search, summary, timeline, and export query handlers
- update the patient API document, canonical source, and OpenAPI fragment with the new contract
- add feature tests for search relevance, summary derivation, timeline ordering, export storage, authorization, and tenant scope
