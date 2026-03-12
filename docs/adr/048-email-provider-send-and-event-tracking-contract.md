# ADR 048: Email Provider, Send, and Event Tracking Contract

Date: `2026-03-12`

## Status

Accepted

## Context

The canonical source already defined the email-facing route inventory:

- `POST /notifications:test/email`
- `GET /notification-providers/email`
- `PUT /notification-providers/email`
- `POST /email:send`
- `GET /email/events`

The repository also already had:

- versioned tenant-scoped templates for the `email` channel from ADR `043`
- queue-first notification records from ADR `044`
- SMS and Telegram channel adapters that consume `medflow.notifications.v1`

Before `T060`, the docs still did not define:

- what tenant-scoped email provider settings exist before the broader integrations hub arrives in `T061`
- whether queued email notifications are delivered through the same notification lifecycle as SMS and Telegram
- whether diagnostic email sends create delivery history
- how direct transactional email sends differ from queued template-backed notifications
- what the email event query model stores and filters

Those are material decisions because the email route surface already exists in the source documents and implementing it without a written contract would violate the project governance rules.

## Decision

Implement email as a configuration-backed transactional adapter with tenant-scoped sender settings, queue-consumer-backed notification delivery, a direct authenticated send route, and a separate email-event query model for actual delivery outcomes.

### 1. Provider shape and tenant-scoped settings

Email uses one configured provider key for this phase: `email`.

Transport credentials remain application-configured for this task:

- the underlying mailer is the Laravel mailer configured by environment
- tenant-managed credential storage is deferred to `T061`

`GET /notification-providers/email` returns tenant-scoped email settings with:

- `enabled`
- `provider_key`
- `from_address`
- `from_name`
- optional `reply_to_address`
- optional `reply_to_name`

`PUT /notification-providers/email` fully replaces the tenant-scoped settings.

Rules:

- `enabled` is required
- `from_address` must be a valid email address
- `from_name` is required
- `reply_to_address` is optional
- `reply_to_name` is optional and defaults to `from_name` when `reply_to_address` is present without a name
- provider credentials are never returned or stored in tenant-managed settings during this task

### 2. Diagnostic email send

`POST /notifications:test/email` is a tenant-scoped diagnostic route:

- requires tenant scope and `notifications.manage`
- validates `recipient`, `subject`, `body`, and optional `metadata`
- uses the same provider settings and adapter as operational delivery
- does not create a `notifications` row
- does not create an `email_events` row
- does not publish notification outbox events

The response always returns the attempted result as either:

- `notification_test_email_sent`
- `notification_test_email_failed`

Provider-disabled tenants receive HTTP `409`.

### 3. Queued email notifications

Queued email notifications extend ADR `044`:

- the delivery consumer reacts to `notification.queued|notification.retried`
- only notifications whose channel is `email` are processed
- one delivery attempt increments `attempts` by `1`
- success moves `queued -> sent`
- failure moves `queued -> failed`
- success stores `provider_key = email`
- success may store a provider message id when the adapter returns one
- failure stores `provider_key = email`, `last_error_code`, and `last_error_message`

Queued email delivery writes:

- notification audit actions: `notifications.sent`, `notifications.failed`
- notification outbox events: `notification.sent`, `notification.failed`
- email event rows described below with `source = notification`

If the tenant email provider is disabled, queued email notifications fail with error code `email_disabled`.

### 4. Direct transactional send

`POST /email:send` is a direct authenticated route owned by Notifications.

It:

- requires tenant scope and `notifications.manage`
- validates `recipient`, `subject`, `body`, and optional `metadata`
- uses the tenant email provider settings and the configured adapter directly
- does not create a `notifications` row
- always writes an email event row

The response returns:

- `email_sent` when the adapter succeeds
- `email_failed` when the adapter rejects or throws a mapped delivery failure

Direct send failures return a normal JSON result payload instead of an exception-driven transport error so operators can inspect the provider outcome deterministically.

### 5. Email event query model

`GET /email/events` returns tenant-scoped delivery outcomes for actual email sends.

An email event stores:

- `event_id`
- `tenant_id`
- optional `notification_id`
- `source` as `notification|direct`
- `event_type` as `sent|failed`
- `recipient_email`
- optional `recipient_name`
- `subject`
- `provider_key`
- optional `message_id`
- optional `error_code`
- optional `error_message`
- `metadata`
- `occurred_at`
- `created_at`

Rules:

- diagnostic sends do not create email events
- queued-notification sends create `source = notification`
- direct sends create `source = direct`
- one row is written per delivery outcome
- retries append new rows rather than mutating historical rows

`GET /email/events` supports:

- `q`
- `source`
- `event_type`
- `notification_id`
- `created_from`
- `created_to`
- `limit`

`q` matches:

- `recipient_email`
- `subject`
- `provider_key`
- `message_id`
- `error_message`

### 6. Provider adapter boundary

The adapter stays behind an application contract and is responsible for:

- applying sender and reply-to settings
- using the configured Laravel mailer
- returning a stable send result
- mapping provider failures into explicit platform error codes

For this phase, a generated message id is acceptable when the underlying transport does not expose a provider-native id.

## Consequences

- email joins SMS and Telegram under the same queue-first notification lifecycle
- transactional direct sends gain audit-friendly event history without overloading the notification table
- tenant-level sender identity is available before the broader integrations hub and credential vault work in `T061`
- delivery history stays append-only and queryable even when sends are retried or provider settings later change
