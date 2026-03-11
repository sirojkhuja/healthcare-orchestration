# ADR 044: Notification Queue Lifecycle and Dispatch Contract

Date: `2026-03-12`

## Status

Accepted

## Context

The canonical source already defined the notification dispatch route surface:

- `POST /notifications`
- `GET /notifications`
- `GET /notifications/{notificationId}`
- `POST /notifications/{notificationId}:retry`
- `POST /notifications/{notificationId}:cancel`

Before `T057`, the repository did not define:

- the notification record shape
- notification statuses
- recipient payload rules per channel
- whether notification sends are synchronous or queue-first
- retry and cancel guards
- notification list filters
- notification event and audit behavior

Those decisions are material because reminder orchestration and future SMS, Telegram, and email adapters need a stable notification record before provider-specific delivery work starts.

## Decision

Implement notifications as tenant-scoped rendered delivery records with a queue-first lifecycle. `POST /notifications` accepts a send request, renders the current template snapshot, persists a notification in `queued`, records audit data, and publishes a `medflow.notifications.v1` outbox event. Provider-specific tasks later consume or update that record to perform external delivery.

### 1. Notification Record

Each notification owns:

- `notification_id`
- `tenant_id`
- `template_id`
- `template_code`
- `template_version`
- `channel`
- `recipient`
- `recipient_value`
- optional `rendered_subject`
- `rendered_body`
- `variables`
- `metadata`
- `status`
- `attempts`
- `max_attempts`
- optional `provider_key`
- optional `provider_message_id`
- optional `last_error_code`
- optional `last_error_message`
- `queued_at`
- optional `sent_at`
- optional `failed_at`
- optional `canceled_at`
- optional `canceled_reason`
- optional `last_attempt_at`
- `created_at`
- `updated_at`

Rules:

- notifications are tenant-scoped and never soft-deleted
- each notification stores a rendered content snapshot so later template edits do not rewrite delivery history
- `attempts` stores actual provider delivery attempts, not manual retry requests
- `max_attempts` is fixed at `3` for this phase
- provider-specific fields stay nullable until channel adapters are implemented

### 2. Queue-First Lifecycle

States:

- `queued`
- `sent`
- `failed`
- `canceled`

Transition rules:

- create starts in `queued`
- `failed -> queued` through `retry`
- `queued|failed -> canceled` through `cancel`
- `sent` is terminal
- `canceled` is terminal for `T057`

Operational notes:

- `T057` does not call external SMS, Telegram, or email providers directly
- queue creation is the dispatch contract for this phase
- later provider tasks may move `queued -> sent|failed`
- manual retry is rejected when the record is not `failed`
- manual retry is rejected when `attempts >= max_attempts`

### 3. Send Request Contract

`POST /notifications` requires:

- `template_id`
- `recipient`
- `variables`

Optional:

- `metadata`

Template rules:

- the referenced template must exist in the current tenant
- the referenced template must be active
- current template content is rendered at send time and copied into the notification record

Recipient rules depend on channel:

- `email`: `recipient.email` is required and must be a valid email address; `recipient.name` is optional
- `sms`: `recipient.phone_number` is required and must be a normalized phone-like string
- `telegram`: `recipient.chat_id` is required and may be integer-like or string-like

### 4. Read Models and Filters

`GET /notifications` supports:

- `q`
- `status`
- `channel`
- `template_id`
- `created_from`
- `created_to`
- `limit`

`q` matches:

- `template_code`
- `recipient_value`
- `rendered_subject`
- `rendered_body`
- `channel`

`GET /notifications/{notificationId}` returns the full stored snapshot plus lifecycle fields.

### 5. Audit and Event Behavior

The system records audit events for:

- `notifications.queued`
- `notifications.retried`
- `notifications.canceled`

The system publishes notification outbox events to `medflow.notifications.v1` for:

- `notification.queued`
- `notification.retried`
- `notification.canceled`

Each outbox payload includes the stored notification projection at the time of the event.

## Consequences

- reminder orchestration can create durable notification records before provider adapters exist
- template rendering remains deterministic and auditable because sends snapshot rendered content
- retry and cancel behavior is explicit before SMS, Telegram, and email integrations are added
- later delivery tasks can extend the lifecycle to `sent` and `failed` without redesigning the public API
