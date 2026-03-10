# ADR 034: Medication Catalog, Allergies, and Patient Medication Views

Date: `2026-03-10`

## Status

Accepted

## Context

The canonical source already defines these routes for `T047`:

- `GET /medications`
- `POST /medications`
- `GET /medications/{medId}`
- `PATCH /medications/{medId}`
- `DELETE /medications/{medId}`
- `GET /medications/search`
- `GET /patients/{patientId}/allergies`
- `POST /patients/{patientId}/allergies`
- `DELETE /patients/{patientId}/allergies/{allergyId}`
- `GET /patients/{patientId}/medications`

The current source set does not define:

- the medication catalog field set
- the medication search contract
- whether medication delete is hard delete or soft delete
- the allergy field set and whether allergies must link to the medication catalog
- the patient medication projection contract
- whether `T047` adds a direct `medication_id` linkage to prescriptions

Those decisions are required before implementation.

## Decision

Implement `T047` as a tenant-scoped medication catalog plus patient-scoped allergy records and patient medication read models. `T047` does not change the prescription aggregate shape introduced in ADR `033`; prescriptions keep free-text medication snapshot fields and do not gain a required `medication_id` foreign key in this task.

### Medication Catalog Ownership

Each medication catalog record owns:

- `medication_id`
- `tenant_id`
- `code`
- `name`
- optional `generic_name`
- optional `form`
- optional `strength`
- optional `description`
- `is_active`
- `created_at`
- `updated_at`

Rules:

- medication catalog records are tenant-scoped
- `code` is required
- `name` is required
- `code` is normalized by trimming and uppercasing
- `name`, `generic_name`, `form`, `strength`, and `description` are trimmed
- `code` must be unique case-insensitively per tenant
- `is_active` defaults to `true`

### Medication Catalog CRUD and Search

`GET /medications` and `GET /medications/search` use the same filter contract:

- `q`
- `is_active`
- `limit`

Rules:

- default `limit` is `25`
- maximum `limit` is `100`
- `q` matches `code`, `name`, `generic_name`, `form`, and `strength`
- list and search order by `name asc`, `code asc`, `created_at asc`, and `medication_id asc`

`DELETE /medications/{medId}` hard-deletes the catalog row.

Delete consequences:

- allergy records keep their stored `allergen_name` snapshot even if the linked catalog record is deleted
- allergy `medication_id` references become `null` on medication delete
- patient medication views stop matching deleted catalog rows because the catalog row no longer exists

### Allergy Ownership

Each allergy record owns:

- `allergy_id`
- `tenant_id`
- `patient_id`
- optional `medication_id`
- `allergen_name`
- optional `reaction`
- optional `severity`
- optional `noted_at`
- optional `notes`
- `created_at`
- `updated_at`

Rules:

- allergy records are tenant-scoped and patient-owned
- the parent patient must exist in the active tenant scope
- `allergen_name` is a required stored snapshot value
- `medication_id` is optional and may point to a medication catalog record in the same tenant
- if `medication_id` is present and `allergen_name` is omitted, `allergen_name` defaults to the medication catalog `name`
- `reaction` and `notes` are trimmed and optional
- `severity` is optional and uses `mild`, `moderate`, `severe`, and `life_threatening`
- `noted_at` is optional and uses an ISO 8601 timestamp
- duplicate allergies are rejected when the same patient already has the same normalized `allergen_name` in the active tenant scope

`DELETE /patients/{patientId}/allergies/{allergyId}` hard-deletes the allergy row.

### Allergy Read Contract

`GET /patients/{patientId}/allergies` returns allergy history for that patient in the current tenant.

Order:

- higher severity first using `life_threatening`, `severe`, `moderate`, `mild`, then `null`
- `noted_at desc nulls last`
- `allergen_name asc`
- `created_at asc`

### Patient Medication View Contract

`GET /patients/{patientId}/medications` is a patient-centric read model built from prescriptions.

The view:

- requires an active patient in the current tenant scope
- excludes soft-deleted prescriptions
- excludes `draft` prescriptions because drafts are not yet patient medication history
- supports optional filters:
  - `status`
  - `limit`

Rules:

- allowed `status` values are `issued`, `dispensed`, and `canceled`
- default `limit` is `25`
- maximum `limit` is `100`
- order is `COALESCE(issued_at, created_at) desc`, then `created_at desc`, then `prescription_id desc`

Each patient medication item exposes:

- `prescription_id`
- `status`
- medication snapshot:
  - `name`
  - optional `code`
- optional matched medication catalog reference when the prescription `medication_code` exactly matches a catalog `code` in the same tenant
- provider reference
- optional encounter reference
- optional treatment item reference
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
- `issued_at`
- optional `dispensed_at`
- optional `canceled_at`
- optional `cancel_reason`
- `created_at`
- `updated_at`

### Prescription Compatibility Rules

`T047` preserves the contract from ADR `033`:

- prescriptions still own `medication_name` and optional `medication_code`
- prescription create and update payloads do not require catalog linkage
- patient medication views reuse prescription lifecycle states and timestamps rather than defining a separate pharmacy workflow

### Permissions and Audit

Permissions:

- medication catalog reads use `prescriptions.view`
- medication catalog writes use `prescriptions.manage`
- allergy reads use `prescriptions.view`
- allergy writes use `prescriptions.manage`
- patient medication views use `prescriptions.view`

Audit actions:

- `medications.created`
- `medications.updated`
- `medications.deleted`
- `patient_allergies.created`
- `patient_allergies.deleted`

Object types:

- medication catalog records use `object_type = medication`
- allergy records use `object_type = patient_allergy`

## Consequences

- `T047` adds a usable tenant medication catalog without forcing prescriptions to migrate away from snapshot fields.
- Patient allergy history can outlive catalog churn because allergy rows keep `allergen_name` snapshots.
- Patient medication views remain consistent with prescription workflow because they project directly from prescriptions.
- A future task may add optional prescription-to-medication linkage, but it must preserve the snapshot contract established in ADR `033`.
