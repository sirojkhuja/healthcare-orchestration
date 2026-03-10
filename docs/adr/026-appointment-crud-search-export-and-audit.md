# ADR 026: Appointment CRUD, Search, Export, and Audit

## Status

Accepted

## Date

2026-03-10

## Context

The canonical route inventory already defines:

- `GET /appointments`
- `POST /appointments`
- `GET /appointments/{appointmentId}`
- `PATCH /appointments/{appointmentId}`
- `DELETE /appointments/{appointmentId}`
- `GET /appointments/search`
- `GET /appointments/export`
- `GET /appointments/{appointmentId}/audit`

After `T037`, the Scheduling module has only the pure appointment aggregate and state machine. The source documents still did not define:

- whether `POST /appointments` creates a draft or an already scheduled appointment
- which fields belong to the first appointment CRUD payload
- whether `PATCH` and `DELETE` remain available after workflow transitions start
- whether delete is hard-delete or soft-delete
- which search filters exist and how matching is ordered
- which export format and columns are required
- how the audit route behaves for soft-deleted appointments
- whether draft appointments should already consume availability capacity

`T038` requires those decisions before implementation.

## Decision

Use the appointment aggregate from `ADR 025` as the persisted scheduling record and deliver a first persistence-backed appointment directory with draft-only CRUD behavior.

## Create and CRUD Semantics

- `POST /appointments` creates an appointment in `draft` status.
- `PATCH /appointments/{appointmentId}` updates only draft appointments.
- `DELETE /appointments/{appointmentId}` soft-deletes only draft appointments.
- Once an appointment leaves `draft`, workflow changes must happen through explicit action endpoints, not generic patch or delete operations.

This keeps the later state-action routes authoritative for lifecycle behavior and avoids using generic CRUD endpoints to bypass documented transition rules.

## Persistence Contract

Appointments are tenant-owned rows with these persisted fields:

- `id`
- `tenant_id`
- `patient_id`
- `provider_id`
- optional `clinic_id`
- optional `room_id`
- `status`
- `scheduled_start_at`
- `scheduled_end_at`
- `timezone`
- optional JSON `last_transition`
- soft-delete timestamp `deleted_at`
- `created_at`
- `updated_at`

The first CRUD implementation does not persist transition history separately. The latest transition metadata is stored as a JSON snapshot so later transition tasks can extend the same table without redefining the aggregate boundary.

## Validation and Reference Rules

Create and update payloads use:

- required `patient_id`
- required `provider_id`
- required `scheduled_start_at`
- required `scheduled_end_at`
- required `timezone`
- optional `clinic_id`
- optional `room_id`

Validation rules:

- `patient_id` must reference an active patient in the current tenant
- `provider_id` must reference an active provider in the current tenant
- when `clinic_id` is provided, it must reference a clinic in the current tenant
- when `room_id` is provided:
  - `clinic_id` is required
  - the room must exist inside that clinic in the current tenant
- when the provider already has an assigned clinic and `clinic_id` is provided, the values must match
- `scheduled_end_at` must be later than `scheduled_start_at`
- `timezone` must be a valid timezone identifier

No additional slot-conflict enforcement is introduced in `T038`.

## Availability Interaction

Draft appointments do not consume scheduling capacity yet.

Consequences:

- availability slot reads still do not subtract draft appointments
- provider calendar and slot cache behavior from `ADR 023` and `ADR 024` remain unchanged in `T038`
- occupancy is introduced only when explicit appointment workflow actions are implemented in later tasks

## Read Contract

### List

`GET /appointments` returns active tenant-scoped appointments ordered by:

1. `scheduled_start_at asc`
2. `created_at asc`
3. `id asc`

Deleted appointments are excluded.

### Detail

`GET /appointments/{appointmentId}` returns one active tenant-scoped appointment.

The read model includes:

- the core appointment fields
- related display references for:
  - patient
  - provider
  - clinic when present
  - room when present

### Search

`GET /appointments/search` returns active tenant-scoped appointments with:

- `q`
- `status`
- `patient_id`
- `provider_id`
- `clinic_id`
- `room_id`
- `scheduled_from`
- `scheduled_to`
- `created_from`
- `created_to`
- `limit`

Search semantics:

- `q` matches appointment ID plus patient and provider display names
- query token matching uses AND semantics across whitespace-separated tokens
- structured filters combine with `q`
- relevance prefers:
  - exact appointment ID matches
  - prefix matches on patient and provider names
  - substring matches
  - then the list ordering rules above
- `limit` defaults to `25` and may not exceed `100`

## Export Contract

`GET /appointments/export` exports the active search result set to CSV only.

Export accepts the same filter set as search plus:

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
- `clinic_id`
- `clinic_name`
- `room_id`
- `room_name`
- `scheduled_start_at`
- `scheduled_end_at`
- `timezone`
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

Export creation writes audit action `appointments.exported` with object type `appointment_export`.

## Audit Contract

`GET /appointments/{appointmentId}/audit` uses immutable audit events with:

- `object_type = appointment`
- `object_id = {appointmentId}`

Rules:

- tenant scope is mandatory
- results are returned newest first
- `limit` defaults to `50` and may not exceed `100`
- the endpoint may return audit history for a soft-deleted appointment as long as the appointment belongs to the active tenant

## Audit Actions

`T038` adds these audit actions:

- `appointments.created`
- `appointments.updated`
- `appointments.deleted`
- `appointments.exported`

Mutation records use object type `appointment` and object ID `{appointmentId}`.

Delete is audited as a soft delete with before and after values.

## Authorization and Idempotency

- `appointments.view` protects:
  - list
  - show
  - search
  - export
  - audit
- `appointments.manage` protects:
  - create
  - update
  - delete

Scheduling mutation routes require idempotency in `T038`:

- `POST /appointments`
- `PATCH /appointments/{appointmentId}`
- `DELETE /appointments/{appointmentId}`

## Consequences

- Scheduling now has a stable first-pass appointment directory without pre-empting the later workflow action routes.
- Generic update and delete cannot bypass state transitions because only draft appointments remain mutable through CRUD.
- Soft delete preserves auditability while keeping deleted drafts out of active reads.
- Availability behavior remains truthful to the current implementation because draft appointments do not yet represent booked occupancy.
