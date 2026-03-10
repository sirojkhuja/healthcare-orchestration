# ADR 025: Appointment Aggregate and State Machine

## Status

Accepted

## Date

2026-03-10

## Context

The canonical source already defined the appointment route inventory and the high-level state machine:

- `draft`
- `scheduled`
- `confirmed`
- `checked_in`
- `in_progress`
- `completed`
- side branches `canceled`, `no_show`, and `rescheduled`

It also established only three guards:

- appointments scheduled in the past cannot be confirmed
- check-in requires confirmation unless an admin override is recorded
- completion requires `in_progress`

That was not enough for `T037` because the implementation still needed:

- the aggregate fields owned by Scheduling
- the full transition matrix
- the recovery path from recoverable terminal states
- the event names and payload shape produced by successful transitions
- a clear boundary between this task and later appointment CRUD and action APIs

## Decision

`T037` introduces a pure Scheduling-domain appointment aggregate. Persistence, HTTP endpoints, search, export, and audit read APIs remain deferred to later tasks.

## Aggregate Boundary

The appointment aggregate owns:

- `appointment_id`
- `tenant_id`
- `patient_id`
- `provider_id`
- optional `clinic_id`
- optional `room_id`
- scheduled slot:
  - `scheduled_start_at`
  - `scheduled_end_at`
  - `timezone`
- `status`
- the latest transition metadata:
  - `from_status`
  - `to_status`
  - `occurred_at`
  - `actor`
  - optional `reason`
  - `admin_override`
  - optional `restored_from_status`
  - optional `replacement_appointment_id`
  - optional replacement slot reference

The aggregate is tenant-aware because appointments are tenant-owned business records. The aggregate remains framework-free and does not depend on Laravel, Eloquent, HTTP, or Kafka adapters.

## Status Semantics

### Active States

- `draft`
- `scheduled`
- `confirmed`
- `checked_in`
- `in_progress`

### Terminal States

- `completed`
- `canceled`
- `no_show`
- `rescheduled`

### Recoverable Terminal States

- `canceled`
- `no_show`
- `rescheduled`

`completed` is terminal with no recovery path.

## Transition Matrix

The allowed transitions are:

- `draft -> scheduled` via `schedule`
- `scheduled -> confirmed` via `confirm`
- `confirmed -> checked_in` via `check_in`
- `scheduled -> checked_in` via `check_in` only when `admin_override = true`
- `checked_in -> in_progress` via `start`
- `in_progress -> completed` via `complete`
- `scheduled -> canceled` via `cancel`
- `confirmed -> canceled` via `cancel`
- `scheduled -> no_show` via `mark_no_show`
- `confirmed -> no_show` via `mark_no_show`
- `scheduled -> rescheduled` via `reschedule`
- `confirmed -> rescheduled` via `reschedule`
- `canceled -> scheduled` via `restore`
- `no_show -> scheduled` via `restore`
- `rescheduled -> scheduled` via `restore`

All other transitions are invalid.

## Guard Rules

The following rules apply in addition to the matrix above:

- `schedule` is valid only from `draft`
- `confirm` is valid only from `scheduled`
- `confirm` is rejected when `scheduled_start_at <= occurred_at`
- `check_in` is rejected unless:
  - current status is `confirmed`, or
  - current status is `scheduled` and `admin_override = true`
- `start` is valid only from `checked_in`
- `complete` is valid only from `in_progress`
- `cancel` is valid only from `scheduled` or `confirmed`
- `mark_no_show` is valid only from `scheduled` or `confirmed`
- `mark_no_show` requires `scheduled_start_at <= occurred_at`
- `reschedule` is valid only from `scheduled` or `confirmed`
- `reschedule` requires:
  - a non-empty reason
  - a replacement slot with `end_at > start_at`
- `restore` is valid only from `canceled`, `no_show`, or `rescheduled`
- `restore` is rejected when `scheduled_end_at <= occurred_at`
- terminal states cannot transition anywhere except through the explicit `restore` path above

## Reason and Override Rules

- `cancel` requires a non-empty reason
- `mark_no_show` requires a non-empty reason
- `reschedule` requires a non-empty reason
- `check_in` records `admin_override = true` only when the appointment was not already confirmed
- `restore` records the prior terminal status in `restored_from_status`

## Reschedule Semantics

`reschedule` closes the current appointment as `rescheduled`. It does not mutate the original appointment slot.

Instead, the transition records:

- the original slot already stored on the appointment
- `replacement_appointment_id` when known
- the replacement slot reference

This preserves the original appointment history and supports later `T040` work where replacement appointments, recurrence, and bulk reschedule flows are introduced.

## Domain Events

Each successful transition records exactly one domain event:

- `appointment.scheduled`
- `appointment.confirmed`
- `appointment.checked_in`
- `appointment.started`
- `appointment.completed`
- `appointment.canceled`
- `appointment.no_show`
- `appointment.rescheduled`
- `appointment.restored`

The event topic remains `medflow.appointments.v1` when later published through the outbox.

Each event payload must contain:

- `appointment_id`
- `tenant_id`
- `patient_id`
- `provider_id`
- optional `clinic_id`
- optional `room_id`
- `status`
- `transition`
- `scheduled_slot`

The `transition` object contains:

- `from_status`
- `to_status`
- `occurred_at`
- `actor`
- optional `reason`
- `admin_override`
- optional `restored_from_status`
- optional `replacement_appointment_id`
- optional `replacement_slot`

## Implementation Boundary for `T037`

`T037` delivers:

- the pure aggregate
- pure value objects for slot, actor, status, and transition metadata
- transition guard enforcement
- recorded domain events
- full unit coverage of the state machine

`T037` does not yet deliver:

- database tables
- repositories
- application command handlers
- HTTP routes or controllers
- export or audit query endpoints
- slot occupancy subtraction from the availability cache

Those concerns belong to later tasks, starting with `T038`.

## Consequences

- Scheduling now has a single documented source for appointment lifecycle behavior.
- Later appointment APIs can reuse the same aggregate and event contract instead of re-deriving rules in controllers or services.
- The reschedule and restore flows stay compatible with the canonical side-branch state model without losing original slot history.
- Appointment occupancy remains out of the calendar and slot cache until persistence-backed appointment booking is added.
