# ADR 028: Appointment Workflows, Recurrence, Waitlist, and Booked Slot Blocking

Date: `2026-03-10`

## Status

Accepted

## Context

The canonical source and split scheduling docs already define:

- the appointment state machine and route inventory for action endpoints
- recurrence and waitlist route names
- deferred `POST /appointments:bulk-cancel` and `POST /appointments:bulk-reschedule`
- the rule that draft appointments do not consume slot capacity until later scheduling workflow work

Before `T040`, the docs do not define:

- the concrete request and response contracts for appointment action routes
- whether action routes should persist pure state-machine metadata only or also create replacement bookings
- which appointment states block availability and provider-calendar slots
- whether recurrence stores only a template or also materializes future appointments
- how recurrence cancellation affects generated appointments
- what data a waitlist entry contains and what `offer-slot` does
- the bulk cancel and bulk reschedule request contract

`T040` requires these decisions before implementation.

## Decision

Implement explicit appointment workflow endpoints, materialized recurrence series, actionable waitlist booking, and availability blocking for booked appointments.

- `T040` owns:
  - single-appointment action routes
  - bulk cancel and bulk reschedule
  - recurrence creation and cancellation
  - waitlist entry lifecycle and slot offer booking
  - slot blocking in availability and provider-calendar reads for booked appointments
- All `T040` mutations remain tenant-scoped and must invalidate availability cache for the affected tenant.
- Action mutations use `appointments.manage`.
- Waitlist and recurrence reads use `appointments.view`.
- Waitlist and recurrence mutations use `appointments.manage`.

## Appointment Action Routes

### Supported Routes

`T040` implements these action routes:

- `POST /appointments/{appointmentId}:schedule`
- `POST /appointments/{appointmentId}:confirm`
- `POST /appointments/{appointmentId}:check-in`
- `POST /appointments/{appointmentId}:start`
- `POST /appointments/{appointmentId}:complete`
- `POST /appointments/{appointmentId}:cancel`
- `POST /appointments/{appointmentId}:no-show`
- `POST /appointments/{appointmentId}:reschedule`
- `POST /appointments/{appointmentId}:restore`

All action routes:

- resolve only active appointments in the current tenant scope
- require `Idempotency-Key`
- return the updated appointment payload, except `:reschedule`, which returns both the updated source appointment and the replacement appointment
- write audit events with `object_type = appointment`
- remain all-or-nothing per request

### Single-Action Request Rules

Rules by route:

- `:schedule`
  - request body is empty
  - valid only for `draft`
  - requires the current appointment slot to be currently available for the provider
- `:confirm`
  - request body is empty
  - valid only for `scheduled`
- `:check-in`
  - accepts optional `admin_override`
  - valid for `confirmed`, or `scheduled` only when `admin_override = true`
- `:start`
  - request body is empty
  - valid only for `checked_in`
- `:complete`
  - request body is empty
  - valid only for `in_progress`
- `:cancel`
  - requires `reason`
  - valid only for `scheduled|confirmed`
- `:no-show`
  - requires `reason`
  - valid only for `scheduled|confirmed`
  - valid only after the scheduled start time
- `:restore`
  - request body is empty
  - valid only for `canceled|no_show|rescheduled`
  - rejected once the original slot has fully elapsed
  - requires the original slot to be currently available again

### Reschedule Contract

`POST /appointments/{appointmentId}:reschedule` accepts:

- `reason`
- `replacement_start_at`
- `replacement_end_at`
- `timezone`
- optional `clinic_id`
- optional `room_id`

Rules:

- the source appointment must be `scheduled` or `confirmed`
- the replacement slot must pass the same provider, clinic, room, and chronology validation rules as appointment create/update
- the replacement slot must be currently available for the provider
- the replacement appointment inherits:
  - tenant
  - patient
  - provider
  - participants
  - notes do not copy
- the replacement appointment is created immediately in `scheduled` status
- the source appointment transitions to `rescheduled`
- the source appointment stores replacement metadata in `last_transition`
- the replacement appointment stores `recurrence_id = null` unless it was created through series materialization

### Response and Audit

Single-action routes return:

- `status`
- `data`

`status` values:

- `appointment_scheduled`
- `appointment_confirmed`
- `appointment_checked_in`
- `appointment_started`
- `appointment_completed`
- `appointment_canceled`
- `appointment_no_show`
- `appointment_rescheduled`
- `appointment_restored`

Audit actions:

- `appointments.scheduled`
- `appointments.confirmed`
- `appointments.checked_in`
- `appointments.started`
- `appointments.completed`
- `appointments.canceled`
- `appointments.no_show`
- `appointments.rescheduled`
- `appointments.restored`

Reschedule also writes:

- `appointments.replacement_created`

## Booked Slot Blocking

Availability and provider-calendar reads must exclude slots that overlap appointments in these statuses:

- `scheduled`
- `confirmed`
- `checked_in`
- `in_progress`

The following statuses do not block slots:

- `draft`
- `completed`
- `canceled`
- `no_show`
- `rescheduled`

Blocking is based on overlap with:

- `provider_id`
- appointment `scheduled_start_at`
- appointment `scheduled_end_at`
- active tenant scope only

## Bulk Cancel and Bulk Reschedule

### Bulk Cancel

`POST /appointments:bulk-cancel` accepts:

- `appointment_ids`
- `reason`

Rules:

- `appointment_ids` is a required array of distinct UUIDs with `1..100` items
- every appointment must exist in the current tenant and be `scheduled` or `confirmed`
- the request is all-or-nothing

Response:

- `operation_id`
- `affected_count`
- `appointments`

Audit:

- one `appointments.bulk_canceled`
- one `appointments.canceled` per appointment

### Bulk Reschedule

`POST /appointments:bulk-reschedule` accepts:

- `items`

Each `items[]` object contains:

- `appointment_id`
- `reason`
- `replacement_start_at`
- `replacement_end_at`
- `timezone`
- optional `clinic_id`
- optional `room_id`

Rules:

- `items` contains `1..100` objects
- `appointment_id` values must be distinct
- each source appointment must exist in the current tenant and be `scheduled` or `confirmed`
- each replacement slot must pass the single-reschedule validation rules
- the request is all-or-nothing

Response:

- `operation_id`
- `affected_count`
- `appointments`
- `replacement_appointments`

Audit:

- one `appointments.bulk_rescheduled`
- per source appointment:
  - `appointments.rescheduled`
  - `appointments.replacement_created`

## Recurrence

### Data Model

`appointment_recurrences` stores:

- `id`
- `tenant_id`
- `source_appointment_id`
- `patient_id`
- `provider_id`
- optional `clinic_id`
- optional `room_id`
- `frequency`
- `interval`
- optional `occurrence_count`
- optional `until_date`
- `timezone`
- `status`
- optional `canceled_reason`
- `created_at`
- `updated_at`

`appointments` gains nullable `recurrence_id`.

### Supported Frequencies

Supported recurrence frequencies:

- `daily`
- `weekly`
- `monthly`

Rules:

- `interval` is `1..12`
- the source appointment must be `scheduled` or `confirmed`
- the request must include exactly one of:
  - `occurrence_count`
  - `until_date`
- `occurrence_count` includes the source appointment and must be `2..24`
- `until_date` may not be more than `180` days after the source appointment date

### Materialization Behavior

`POST /appointments/{appointmentId}:make-recurring` materializes future appointments immediately.

- future appointments copy patient, provider, clinic, room, slot duration, and timezone from the source appointment
- future appointments are created in `scheduled` status
- every future slot must be currently available
- the request is all-or-nothing
- the source appointment keeps its current status and becomes the series root through `source_appointment_id`

Response returns:

- `status`
- `data`

`data` contains:

- recurrence metadata
- `appointments`

Monthly materialization keeps the local appointment wall-clock time and uses no-overflow month arithmetic when a later month has fewer calendar days than the source appointment date.

### Recurrence Cancellation

`POST /appointments/recurrences/{recurrenceId}:cancel` accepts:

- `reason`

Rules:

- only `active` recurrences may be canceled
- recurrence cancellation marks the series `canceled`
- future generated appointments in `scheduled` or `confirmed` status are canceled with the provided reason
- past appointments and terminal appointments already in the series remain unchanged

Audit actions:

- `appointments.recurrence_created`
- `appointments.recurrence_canceled`

## Waitlist

### Data Model

`appointment_waitlist_entries` stores:

- `id`
- `tenant_id`
- `patient_id`
- `provider_id`
- optional `clinic_id`
- optional `room_id`
- `desired_date_from`
- `desired_date_to`
- optional `preferred_start_time`
- optional `preferred_end_time`
- optional `notes`
- `status`
- optional `booked_appointment_id`
- optional `offered_slot`
- `created_at`
- `updated_at`

`status` is:

- `open`
- `booked`
- `removed`

### Waitlist Routes

`POST /waitlist` accepts:

- `patient_id`
- `provider_id`
- optional `clinic_id`
- optional `room_id`
- `desired_date_from`
- `desired_date_to`
- optional `preferred_start_time`
- optional `preferred_end_time`
- optional `notes`

Rules:

- patient and provider must exist in the current tenant
- `desired_date_to` must be on or after `desired_date_from`
- preferred start and end time must appear together when present
- the entry starts in `open`

`GET /waitlist` supports filters:

- `status`
- `patient_id`
- `provider_id`
- `clinic_id`
- `desired_from`
- `desired_to`
- `limit`

Default ordering:

- `desired_date_from asc`
- `created_at asc`

`DELETE /waitlist/{entryId}`:

- allowed only for `open`
- changes status to `removed`

### Offer Slot

`POST /waitlist/{entryId}:offer-slot` accepts:

- `scheduled_start_at`
- `scheduled_end_at`
- `timezone`
- optional `clinic_id`
- optional `room_id`

Rules:

- the entry must be `open`
- the offered slot must fall inside the desired date range
- preferred time bounds, when present, must match the offered slot start and end time window
- the slot must be currently available for the waitlisted provider
- the route creates a new `scheduled` appointment for the waitlisted patient and provider
- the waitlist entry becomes `booked`
- the created appointment id is stored in `booked_appointment_id`
- the offered slot snapshot is stored in `offered_slot`

Audit actions:

- `appointments.waitlist_added`
- `appointments.waitlist_removed`
- `appointments.waitlist_booked`

`offer-slot` returns:

- the updated waitlist entry
- the scheduled appointment created from the offered slot

## Consequences

- `T040` makes the appointment workflow operational end to end instead of leaving the state machine isolated in unit tests.
- Availability and provider-calendar reads become consistent with booked scheduling states.
- Recurrence and waitlist behavior are explicit and testable instead of remaining route placeholders.
- Bulk cancel and bulk reschedule stay separate from the generic draft bulk patch route introduced in `T039`.
