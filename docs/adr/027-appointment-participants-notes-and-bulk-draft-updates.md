# ADR 027: Appointment Participants, Notes, and Bulk Draft Updates

## Status

Accepted

## Date

2026-03-10

## Context

The canonical route inventory already defines appointment participant, note, and bulk routes, but before `T039` it does not define:

- what an appointment participant contains
- whether participants may reference internal records or only free text
- what an appointment note contains
- whether notes capture an author
- whether participants and notes are limited to draft appointments
- what `POST /appointments/bulk` updates and whether it is partial or action-based
- whether `POST /appointments:bulk-cancel` and `POST /appointments:bulk-reschedule` belong to `T039` or later transition work

`T039` requires these decisions before implementation.

## Decision

Use tenant-scoped appointment participants, authored appointment notes, and all-or-nothing bulk draft updates.

- All participant, note, and bulk-update flows remain tenant-owned through the parent appointment.
- `appointments.view` protects participant and note reads.
- `appointments.manage` protects participant and note mutations plus `POST /appointments/bulk`.
- Participant and note subresources resolve only through active appointments in the current tenant scope. Soft-deleted draft appointments do not expose participant or note routes.
- `POST /appointments:bulk-cancel` and `POST /appointments:bulk-reschedule` are deferred to `T040` because they depend on explicit appointment transition workflows and reasons from the state-machine work.

## Participants

### Data Model

Each appointment participant contains:

- `id`
- `appointment_id`
- `participant_type`
- `reference_id`
- `display_name`
- `role`
- `required`
- `notes`
- `created_at`
- `updated_at`

`participant_type` is one of:

- `user`
- `provider`
- `external`

### Reference Rules

- `user` participants require `reference_id` and it must resolve to an active tenant user membership.
- `provider` participants require `reference_id` and it must resolve to an active tenant provider.
- `external` participants must not carry `reference_id` and must provide `display_name`.
- `display_name` is stored from the resolved internal record for `user` and `provider` participants and from the request payload for `external` participants.

### Request Contract

`POST /appointments/{appointmentId}/participants` accepts:

- `participant_type`
- optional `reference_id`
- optional `display_name`
- `role`
- optional `required`
- optional `notes`

Rules:

- `role` is required, trimmed free text, and limited to `120` characters.
- `required` defaults to `false`.
- `notes` is optional free text limited to `5000` characters.
- `display_name` is required only for `external`.
- The same appointment may not contain duplicate participants:
  - for `user` and `provider`, duplicates are blocked by `participant_type + reference_id`
  - for `external`, duplicates are blocked by normalized `participant_type + display_name + role`

### Read Ordering and Deletion

- `GET /appointments/{appointmentId}/participants` returns participants ordered by:
  - `required desc`
  - `display_name asc`
  - `created_at asc`
- `DELETE /appointments/{appointmentId}/participants/{participantId}` hard-deletes the participant row.

## Notes

### Data Model

Each appointment note contains:

- `id`
- `appointment_id`
- `body`
- `author`
- `created_at`
- `updated_at`

`author` contains:

- `user_id`
- `name`
- `email`

### Request Contract

`POST /appointments/{appointmentId}/notes` accepts:

- `body`

`PATCH /appointments/{appointmentId}/notes/{noteId}` accepts:

- `body`

Rules:

- `body` is required on create.
- `body` is required when present on update.
- `body` is trimmed free text limited to `10000` characters.
- Notes capture the authenticated API user as the author at creation time.
- Updating a note changes only `body` and `updated_at`. The original author remains unchanged.
- `DELETE /appointments/{appointmentId}/notes/{noteId}` hard-deletes the note row.

### Read Ordering

- `GET /appointments/{appointmentId}/notes` returns notes ordered by:
  - `updated_at desc`
  - `created_at desc`
  - `id desc`

## Bulk Draft Updates

### Scope

`POST /appointments/bulk` performs the generic bulk mutation work for `T039`.

- The route updates only active `draft` appointments.
- It applies one shared change set to every selected appointment.
- It uses the same mutable fields as the single draft `PATCH /appointments/{appointmentId}` route:
  - `patient_id`
  - `provider_id`
  - `clinic_id`
  - `room_id`
  - `scheduled_start_at`
  - `scheduled_end_at`
  - `timezone`

### Request Contract

`POST /appointments/bulk` accepts:

- `appointment_ids`
- `changes`

Rules:

- `appointment_ids` is a required array of distinct UUIDs with `1..100` items.
- `changes` is a required object.
- `changes` must contain at least one supported field.
- Every appointment must exist in the active tenant scope and currently be in `draft` status.
- The same normalization and cross-reference validation rules as the single draft patch route apply to every target appointment.
- The entire request is all-or-nothing. If any target fails validation or state checks, no appointment in the batch is updated.
- The route requires `Idempotency-Key`.

### Response Contract

The response returns:

- `operation_id`
- `affected_count`
- `updated_fields`
- `appointments`

`appointments` returns the updated appointment view payload for every affected appointment ordered by input `appointment_ids`.

## Audit

- Participant creation and deletion write:
  - `appointments.participant_added`
  - `appointments.participant_removed`
- Note creation, update, and deletion write:
  - `appointments.note_added`
  - `appointments.note_updated`
  - `appointments.note_deleted`
- Bulk draft updates write:
  - one summary audit event `appointments.bulk_updated` with `object_type = appointment_bulk_operation`
  - one `appointments.updated` event per affected appointment with metadata linking it to the bulk operation

## Alternatives Considered

- restrict participants to free-text names only
- allow only internal users as participants
- treat notes as append-only and disallow updates
- allow bulk updates on scheduled and later appointment states
- implement bulk cancel and bulk reschedule before the explicit transition routes exist

## Consequences

- Appointment collaboration data now has a stable tenant-scoped subresource model without changing the appointment state machine.
- Notes capture authorship immediately, which supports later clinical and notification workflows without redefining note history.
- Bulk draft updates stay intentionally narrow so explicit state-transition routes remain authoritative for scheduling behavior.
- `T040` can build bulk cancel and reschedule safely on top of the documented transition rules instead of mixing state transitions into the generic bulk patch route.

## Migration Plan

- add appointment participant and appointment note persistence
- expose participant, note, and bulk draft update routes
- update scheduling docs, OpenAPI, tests, and route wiring
- leave bulk cancel and bulk reschedule for `T040`
