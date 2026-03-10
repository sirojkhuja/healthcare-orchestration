# ADR 031: Encounters, Diagnoses, Procedures, Bulk Updates, and Export

## Status

Accepted

## Date

2026-03-10

## Context

The canonical route inventory already defines:

- `GET /encounters`
- `POST /encounters`
- `GET /encounters/{encounterId}`
- `PATCH /encounters/{encounterId}`
- `DELETE /encounters/{encounterId}`
- `GET /encounters/{encounterId}/diagnoses`
- `POST /encounters/{encounterId}/diagnoses`
- `DELETE /encounters/{encounterId}/diagnoses/{dxId}`
- `GET /encounters/{encounterId}/procedures`
- `POST /encounters/{encounterId}/procedures`
- `DELETE /encounters/{encounterId}/procedures/{procId}`
- `GET /encounters/export`
- `POST /encounters/bulk`

Before `T044`, the source documents do not define:

- the encounter field set
- whether encounters are soft-deleted or hard-deleted
- whether the generic list route also supports filtering
- how encounter exports are filtered and stored
- the diagnosis and procedure subresource payloads
- whether procedures may link back to treatment-plan items
- what the bulk route updates and whether it is all-or-nothing

`T044` requires these decisions before implementation.

## Decision

Implement encounters as tenant-scoped clinical visit records with ordered diagnosis and procedure subresources, filterable list and export behavior, and a narrow all-or-nothing bulk update route.

## Encounter Aggregate Boundary

Each encounter owns:

- `encounter_id`
- `tenant_id`
- `patient_id`
- `provider_id`
- optional `treatment_plan_id`
- optional `appointment_id`
- optional `clinic_id`
- optional `room_id`
- `status`
- `encountered_at`
- `timezone`
- optional `chief_complaint`
- optional `summary`
- optional `notes`
- optional `follow_up_instructions`
- soft-delete timestamp `deleted_at`
- `created_at`
- `updated_at`

The encounter read model also exposes:

- patient display reference
- provider display reference
- optional clinic display reference
- optional room display reference
- `diagnosis_count`
- `procedure_count`

## Encounter Status Catalog

`T044` defines these encounter statuses:

- `open`
- `completed`
- `entered_in_error`

There is no separate encounter action-route state machine in this phase. Generic `PATCH /encounters/{encounterId}` owns status changes.

## Create, Update, and Delete Rules

- `POST /encounters` creates encounters in `open` status.
- `PATCH /encounters/{encounterId}` may update any active encounter in tenant scope.
- Generic patch may change:
  - `patient_id`
  - `provider_id`
  - `treatment_plan_id`
  - `appointment_id`
  - `clinic_id`
  - `room_id`
  - `status`
  - `encountered_at`
  - `timezone`
  - `chief_complaint`
  - `summary`
  - `notes`
  - `follow_up_instructions`
- `DELETE /encounters/{encounterId}` is a soft delete and is allowed only while the encounter status is:
  - `open`
  - `entered_in_error`

Soft-deleted encounters leave active list, detail, diagnosis, and procedure reads but remain available to audit history.

## Validation and Reference Rules

Create and update validation uses:

- required `patient_id`
- required `provider_id`
- required `encountered_at`
- required `timezone`
- optional `treatment_plan_id`
- optional `appointment_id`
- optional `clinic_id`
- optional `room_id`
- optional clinical narrative fields

Reference rules:

- `patient_id` must reference an active patient in the current tenant
- `provider_id` must reference an active provider in the current tenant
- when `treatment_plan_id` is present, it must reference a non-deleted treatment plan in the current tenant
- when `appointment_id` is present, it must reference a non-deleted appointment in the current tenant
- when `appointment_id` is present, `patient_id` and `provider_id` must match the linked appointment
- when the linked appointment already has `clinic_id` or `room_id`, any provided encounter `clinic_id` or `room_id` must match those values
- when `clinic_id` is provided, it must reference a clinic in the current tenant
- when `room_id` is provided:
  - `clinic_id` is required
  - the room must exist in that clinic in the current tenant
- when the provider already has an assigned clinic and `clinic_id` is provided, the values must match
- `timezone` must be a valid timezone identifier

## Encounter List and Filter Contract

`GET /encounters` is the filterable directory route and accepts:

- `q`
- `status`
- `patient_id`
- `provider_id`
- `treatment_plan_id`
- `appointment_id`
- `clinic_id`
- `encounter_from`
- `encounter_to`
- `created_from`
- `created_to`
- `limit`

Rules:

- default `limit` is `25`
- maximum `limit` is `100`
- `encounter_to` must be on or after `encounter_from`
- `created_to` must be on or after `created_from`
- active list results exclude soft-deleted encounters

Matching rules:

- `q` matches encounter id
- `q` matches patient and provider display names
- `q` matches `chief_complaint` and `summary`
- `q` matches diagnosis names and codes linked to the encounter
- `q` matches procedure names and codes linked to the encounter

List ordering is:

1. `encountered_at desc`
2. `created_at desc`
3. `id desc`

Responses include `meta.filters` echoing the effective filter set.

## Diagnosis Contract

Diagnoses are encounter-owned subresources, not standalone aggregates.

Each diagnosis owns:

- `diagnosis_id`
- `tenant_id`
- `encounter_id`
- optional `code`
- `display_name`
- `diagnosis_type`
- optional `notes`
- `created_at`
- `updated_at`

`diagnosis_type` is one of:

- `primary`
- `secondary`

Rules:

- every diagnosis belongs to an active encounter in the current tenant
- each encounter may have at most one `primary` diagnosis
- duplicate diagnoses are rejected when the same encounter already contains the same normalized `code`, `display_name`, and `diagnosis_type`
- `DELETE /encounters/{encounterId}/diagnoses/{dxId}` hard-deletes the diagnosis row

Diagnosis reads order by:

1. `diagnosis_type asc` with `primary` before `secondary`
2. `created_at asc`
3. `id asc`

## Procedure Contract

Procedures are encounter-owned subresources, not standalone aggregates.

Each procedure owns:

- `procedure_id`
- `tenant_id`
- `encounter_id`
- optional `treatment_item_id`
- optional `code`
- `display_name`
- optional `performed_at`
- optional `notes`
- `created_at`
- `updated_at`

Rules:

- every procedure belongs to an active encounter in the current tenant
- when `treatment_item_id` is present:
  - the encounter must also have `treatment_plan_id`
  - the treatment item must belong to the same treatment plan
  - the treatment item must have `item_type = procedure`
- duplicate procedures are rejected when the same encounter already contains the same normalized `code`, `display_name`, `performed_at`, and `treatment_item_id`
- `DELETE /encounters/{encounterId}/procedures/{procId}` hard-deletes the procedure row

Procedure reads order by:

1. `performed_at asc` with null values last
2. `created_at asc`
3. `id asc`

## Export Contract

`GET /encounters/export` exports the active encounter list result set to CSV only.

Export accepts the same filter set as `GET /encounters` plus:

- `format`, currently only `csv`
- `limit`, default `1000`, maximum `1000`

Export stores the generated CSV through `FileStorageManager::storeExport()` on the private exports disk.

CSV columns are:

- `id`
- `tenant_id`
- `status`
- `patient_id`
- `patient_name`
- `provider_id`
- `provider_name`
- `treatment_plan_id`
- `appointment_id`
- `clinic_id`
- `clinic_name`
- `room_id`
- `room_name`
- `encountered_at`
- `timezone`
- `chief_complaint`
- `summary`
- `diagnosis_count`
- `procedure_count`
- `created_at`
- `updated_at`
- `exported_at`

The export response returns:

- `export_id`
- `format`
- `file_name`
- `row_count`
- `generated_at`
- `filters`
- `storage` with disk, path, and visibility

Export creation writes audit action `encounters.exported` with object type `encounter_export`.

## Bulk Update Contract

`POST /encounters/bulk` performs an all-or-nothing shared change set across active encounters.

The request accepts:

- `encounter_ids`
- `changes`

Rules:

- `encounter_ids` is a required array of distinct UUIDs with `1..100` items
- `changes` is a required object
- `changes` must contain at least one supported field
- every encounter must exist in the active tenant scope
- bulk updates may change only:
  - `status`
  - `provider_id`
  - `clinic_id`
  - `room_id`
  - `encountered_at`
  - `timezone`
- the same validation and cross-reference rules as single encounter patch apply to every target record
- the entire request is all-or-nothing
- the route requires `Idempotency-Key`

The response returns:

- `operation_id`
- `affected_count`
- `updated_fields`
- `encounters`

`encounters` returns the updated encounter payloads in input `encounter_ids` order.

## Audit Contract

`T044` adds immutable audit actions:

- `encounters.created`
- `encounters.updated`
- `encounters.deleted`
- `encounters.bulk_updated`
- `encounters.exported`
- `encounter_diagnoses.added`
- `encounter_diagnoses.removed`
- `encounter_procedures.added`
- `encounter_procedures.removed`

Object types are:

- `encounter`
- `encounter_bulk_operation`
- `encounter_export`
- `encounter_diagnosis`
- `encounter_procedure`

## Consequences

- The Treatment module gains a stable encounter anchor for future lab-order and prescription work.
- Encounter exports reuse the same filter semantics as the primary list route instead of introducing a second search contract.
- Procedures may already link back to ordered treatment-plan items without waiting for later treatment-plan redesign.
- Bulk encounter updates stay intentionally narrow so narrative clinical content is not mass-edited accidentally.
