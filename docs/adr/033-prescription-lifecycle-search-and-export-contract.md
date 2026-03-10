# ADR 033: Prescription Lifecycle, Search, and Export Contract

Date: `2026-03-10`

## Status

Accepted

## Context

The canonical source already defines:

- prescription CRUD routes
- explicit prescription action routes for `issue`, `cancel`, and `dispense`
- search and export routes for prescriptions
- `T046` as the task that introduces the prescription aggregate and lifecycle
- `T047` as the later task for medication catalog, allergies, and patient medication views

Before `T046`, the docs do not define:

- the prescription field set
- the prescription status catalog and transition guards
- whether prescriptions require the medication catalog to exist first
- which CRUD operations stay valid after issue
- whether delete is hard-delete or soft-delete
- the search and export contract for prescription directory reads

These decisions are required before implementation.

## Decision

Implement a tenant-scoped prescription aggregate with explicit lifecycle actions, soft-delete retention, free-text medication snapshot fields, and prescription directory search/export behavior. The medication catalog remains deferred to `T047`, so `T046` must not depend on `medications` existing.

### Aggregate Ownership

Each prescription owns:

- `prescription_id`
- `tenant_id`
- `patient_id`
- `provider_id`
- optional `encounter_id`
- optional `treatment_item_id`
- `medication_name`
- optional `medication_code`
- `dosage`
- `route`
- `frequency`
- `quantity`
- optional `quantity_unit`
- `authorized_refills`
- optional `instructions`
- optional `notes`
- optional `starts_on`
- optional `ends_on`
- `status`
- latest transition metadata
- lifecycle timestamps:
  - `issued_at`
  - `dispensed_at`
  - `canceled_at`
- optional `cancel_reason`
- soft-delete timestamp `deleted_at`
- `created_at`
- `updated_at`

The read model also exposes:

- patient display reference
- provider display reference
- optional encounter summary reference
- optional treatment item reference

### Status Catalog

`T046` defines these prescription states:

- `draft`
- `issued`
- `dispensed`
- `canceled`

### Allowed Transitions

- `draft -> issued`
- `issued -> dispensed`
- `draft|issued -> canceled`

### Transition Guards

- only draft prescriptions may be issued
- only issued prescriptions may be dispensed
- canceling requires a non-empty reason
- dispensed and canceled prescriptions are terminal

### CRUD Scope

`POST /prescriptions` creates prescriptions only in `draft`.

Generic `PATCH /prescriptions/{prescriptionId}` is allowed only while the prescription is:

- `draft`

Generic update may change:

- patient linkage
- provider linkage
- encounter linkage
- treatment item linkage
- medication snapshot fields
- dosage instructions
- quantity and refill authorization
- prescription notes
- planned start and end dates

Generic `DELETE /prescriptions/{prescriptionId}` is a soft delete and is allowed only while the prescription is:

- `draft`
- `canceled`

Soft-deleted prescriptions leave active list, search, export, and detail reads but remain available for audit history.

### Validation Rules

Prescriptions must resolve active tenant-scoped:

- `patient_id`
- `provider_id`

Required business fields:

- `medication_name`
- `dosage`
- `route`
- `frequency`
- `quantity`
- `authorized_refills`

Validation rules:

- `quantity` must be numeric and greater than `0`
- `authorized_refills` must be an integer in `0..99`
- `starts_on` and `ends_on` use `YYYY-MM-DD`
- `ends_on` must be on or after `starts_on` when both are present
- `medication_code` is optional free-text metadata until `T047` adds the medication catalog

Reference rules:

- when `encounter_id` is present:
  - it must reference an active encounter in the current tenant
  - `patient_id` and `provider_id` must match the encounter
- when `treatment_item_id` is present:
  - `encounter_id` is required
  - the encounter must have `treatment_plan_id`
  - the treatment item must belong to that treatment plan
  - the treatment item must use `item_type = medication`

### Read Contract

`GET /prescriptions` and `GET /prescriptions/search` use the same filter contract in `T046`.

Supported filters:

- `q`
- `status`
- `patient_id`
- `provider_id`
- `encounter_id`
- `issued_from`
- `issued_to`
- `start_from`
- `start_to`
- `created_from`
- `created_to`
- `limit`

Rules:

- default `limit` is `25`
- maximum `limit` is `100`
- export may raise the maximum to `1000`
- `q` matches `prescription_id`, `medication_name`, `medication_code`, patient display fields, provider display fields, `instructions`, and `notes`
- list and search order by:
  - effective issue time desc where available
  - `created_at desc`
  - `prescription_id desc`

### Export Contract

`GET /prescriptions/export` exports the active search result set to CSV only.

Rules:

- `format=csv` is the only supported export format in `T046`
- exports use the shared private export storage contract
- export audit uses `object_type = prescription_export`
- export audit action is `prescriptions.exported`

### Lifecycle Endpoints

- `POST /prescriptions/{prescriptionId}:issue` records `issued_at = now`
- `POST /prescriptions/{prescriptionId}:dispense` records `dispensed_at = now`
- `POST /prescriptions/{prescriptionId}:cancel` requires `reason` and records `canceled_at = now`

### Audit Contract

`T046` writes immutable audit actions:

- `prescriptions.created`
- `prescriptions.updated`
- `prescriptions.deleted`
- `prescriptions.issued`
- `prescriptions.dispensed`
- `prescriptions.canceled`
- `prescriptions.exported`

All prescription audit events use `object_type = prescription` unless the action targets an export artifact.

## Consequences

- `T046` can ship prescription CRUD, search, export, and lifecycle behavior without waiting for medication catalog work.
- `T047` must preserve the existing free-text medication snapshot fields even if it later introduces optional `medication_id` linkage.
- Patient medication views in `T047` must reuse the prescription status and lifecycle defined here instead of redefining pharmacy workflow rules.
