# ADR 045: Appointment Reminder and Confirmation Notification Contract

Date: `2026-03-12`

## Status

Accepted

## Context

The canonical source and the split scheduling document already define two appointment notification routes:

- `POST /appointments/{appointmentId}:send-reminder`
- `POST /appointments/{appointmentId}:send-confirmation`

Before `T041`, the repository did not define:

- which appointment states can dispatch reminders or confirmation requests
- how appointment-linked templates are selected
- which channels are supported for appointment-linked sends
- how recipients are resolved from current patient data
- how reminder dispatch stays idempotent across reminder windows
- how appointment-linked notification history is recorded

Those decisions are material because later SMS, email, and integration-provider tasks need a stable queue contract and schedulers need a deterministic way to avoid duplicate reminder sends.

## Decision

Implement appointment reminder and confirmation orchestration as scheduling-owned use cases that resolve appointment context, select documented tenant templates, queue notifications through the notification module, and persist an appointment-to-notification linkage record.

### 1. Supported Appointment Notification Types

Two appointment-linked notification types exist in this phase:

- `reminder`
- `confirmation`

The scheduling module owns the orchestration rules and link history. The notifications module continues to own rendered delivery records and delivery lifecycle state.

### 2. Template Selection Contract

Appointment-linked sends use tenant-owned active templates selected by exact template `code`.

Reminder templates:

- `APPOINTMENT-REMINDER-SMS`
- `APPOINTMENT-REMINDER-EMAIL`

Confirmation templates:

- `APPOINTMENT-CONFIRMATION-SMS`
- `APPOINTMENT-CONFIRMATION-EMAIL`

Rules:

- template `code` matching remains case-insensitive through the existing uppercase normalization
- only active templates may be used
- only `sms` and `email` are supported for appointment-linked sends in `T041`
- appointment-linked `telegram` delivery is deferred until the product has tenant-safe patient chat identifiers
- when no supported active template can resolve a recipient in the current tenant, the command fails with `422`

### 3. Recipient Resolution Contract

Appointment-linked sends resolve recipients from patient-owned tenant data.

Per channel:

- `sms`: use `patient.phone` first, otherwise the first available patient contact phone ordered by `is_primary desc`, `is_emergency desc`, `name asc`, `created_at asc`
- `email`: use `patient.email` first, otherwise the first available patient contact email using the same contact ordering

Recipient display name uses:

- patient `preferred_name` when present
- otherwise `first_name + last_name`

The resolved recipient snapshot is copied into the queued notification record through the existing notification queue flow.

### 4. Appointment State and Clinic Guards

Reminder dispatch is allowed only when:

- the appointment is in `scheduled|confirmed`
- the scheduled slot has not started yet

Confirmation dispatch is allowed only when:

- the appointment is in `scheduled`
- the scheduled slot has not started yet
- the linked clinic exists in the current tenant
- the clinic has `require_appointment_confirmation = true`

Operational notes:

- confirmation requests are rejected once the appointment is already `confirmed`
- reminder and confirmation dispatch are rejected for `draft`, `checked_in`, `in_progress`, `completed`, `canceled`, `no_show`, and `rescheduled`

### 5. Reminder Window Idempotency

Reminder dispatch is idempotent per appointment, channel, and reminder window.

The reminder `window_key` is computed in the appointment local timezone:

- `advance`: appointment local start date is more than one calendar day ahead of the current local date
- `day_before`: appointment local start date is exactly one calendar day ahead of the current local date
- `same_day`: appointment local start date equals the current local date and the slot has not started

Rules:

- only one active reminder notification may exist per `appointment_id + channel + window_key`
- repeated `send-reminder` calls in the same window return the existing linked notification records instead of creating duplicates
- a new reminder window may create new notifications for the same appointment

### 6. Confirmation Idempotency

Confirmation dispatch is idempotent per appointment and channel.

Rules:

- only one active confirmation notification may exist per `appointment_id + channel`
- repeated `send-confirmation` calls return the existing linked notification records instead of creating duplicates
- canceling the linked notification allows a later confirmation request to create a replacement record

### 7. Appointment Notification Link Record

Each link record owns:

- `appointment_notification_id`
- `tenant_id`
- `appointment_id`
- `notification_id`
- `notification_type`
- `channel`
- `template_id`
- `template_code`
- `recipient_value`
- optional `window_key`
- `requested_at`
- `created_at`
- `updated_at`

Rules:

- link records are tenant-scoped and never soft-deleted
- `window_key` is required for `reminder` and `null` for `confirmation`
- link rows store queue-time linkage only; delivery lifecycle remains on the notification record

### 8. Response Contract

Both action routes return:

- the appointment projection
- the notification type
- the effective reminder `window_key` for reminder sends
- the linked notification projections in channel order `sms`, then `email`

Response statuses:

- `appointment_reminder_sent`
- `appointment_confirmation_sent`

### 9. Audit Behavior

The system records scheduling audit events for:

- `appointments.reminder_sent`
- `appointments.confirmation_sent`

Each audit record captures:

- the appointment projection
- the linked notification ids
- the notification type
- the reminder `window_key` when applicable

The existing notification queue audit and outbox behavior from ADR `044` remains unchanged.

## Consequences

- appointment reminders now have a deterministic window-based dedupe contract for future scheduler usage
- reminder and confirmation routes can be implemented without coupling scheduling to external delivery providers
- appointment-linked notifications remain auditable because the repository stores both queue records and explicit linkage rows
- later SMS and email adapter tasks can deliver queued notifications without redesigning scheduling behavior
