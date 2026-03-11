# ADR 047: Telegram Bot Broadcast, Webhook, and Sync Contract

Date: `2026-03-12`

## Status

Accepted

## Context

The canonical source already defined:

- `POST /notifications:test/telegram`
- `GET /notification-providers/telegram`
- `PUT /notification-providers/telegram`
- `POST /webhooks/telegram`
- `POST /telegram/bot:broadcast`
- `POST /telegram/bot:sync`

The repository already had:

- template and recipient support for the `telegram` notification channel from ADR `043`
- queue-first notification records from ADR `044`
- appointment reminder orchestration that explicitly defers patient-linked Telegram reminders until tenant-safe chat identifiers exist in ADR `045`

Before `T059`, the docs still did not define:

- what tenant-scoped Telegram provider settings are configurable before the integrations hub exists
- whether Telegram diagnostic sends and queued notifications persist notification rows
- how inbound Telegram webhook verification and replay safety work
- how tenant broadcasts select recipient chats
- what `bot sync` means before tenant-managed credentials, health, and webhook inventories arrive in `T061`

Those decisions are material because the Telegram route inventory already exists and implementing it without a written contract would violate the repository governance rules.

## Decision

Implement Telegram as a single configured bot adapter with tenant-scoped delivery settings, queue-consumer-backed notification delivery, replay-safe inbound webhook handling, explicit broadcast audiences, and a sync route that reconciles the public webhook plus bot metadata.

### 1. Provider shape and tenant-scoped settings

Telegram uses one configured provider key: `telegram`.

Bot credentials stay configuration-backed for this phase:

- bot token comes from environment or config
- webhook secret token comes from environment or config
- tenant-managed credential storage is deferred to `T061`

`GET /notification-providers/telegram` returns the tenant-scoped Telegram settings plus the last synced bot snapshot.

`PUT /notification-providers/telegram` replaces the tenant-scoped settings:

- `enabled`: boolean
- `parse_mode`: `HTML` or `MarkdownV2`
- `broadcast_chat_ids[]`: ordered unique chat ids used by tenant broadcast flows
- `support_chat_ids[]`: ordered unique chat ids used to resolve inbound support messages

Rules:

- chat ids are stored as strings
- duplicates are removed within each list
- one chat id may not be assigned to multiple tenants because webhook tenant resolution must remain deterministic
- settings are tenant-scoped and do not mutate historical notification rows
- bot credentials are never returned

### 2. Diagnostic and queued Telegram delivery

`POST /notifications:test/telegram` is a tenant-scoped diagnostic route:

- requires tenant scope and `notifications.manage`
- validates `recipient.chat_id`, `message`, and optional `metadata`
- uses the same Telegram adapter and parse-mode resolution as queued delivery
- does not create a `notifications` row
- does not publish notification outbox events

Queued Telegram notifications extend ADR `044`:

- the delivery consumer reacts to `notification.queued|notification.retried`
- only notifications whose channel is `telegram` are processed
- one provider delivery attempt increments `attempts` by `1`
- success moves `queued -> sent`
- failure moves `queued -> failed`
- successful rows store `provider_key = telegram` and the Telegram message id in `provider_message_id`
- failure stores `provider_key = telegram`, `last_error_code`, and `last_error_message`

Telegram delivery writes the same audit and outbox events as operational SMS delivery:

- audit: `notifications.sent`, `notifications.failed`
- outbox: `notification.sent`, `notification.failed`

### 3. Broadcast contract

`POST /telegram/bot:broadcast` is a tenant-scoped administrative send route:

- requires tenant scope and `notifications.manage`
- request requires `message`
- request accepts either explicit `chat_ids[]` or `audience`
- supported audience values are:
  - `configured_broadcast`
  - `configured_support`
  - `all_configured`
- request may optionally override `parse_mode`

Resolution rules:

- explicit `chat_ids[]` wins when present
- `configured_broadcast` uses the tenant `broadcast_chat_ids`
- `configured_support` uses the tenant `support_chat_ids`
- `all_configured` uses the union of both configured lists
- the final recipient set must be non-empty after de-duplication

Broadcast rules:

- broadcasts do not create notification rows
- delivery continues per chat even if a previous chat fails
- the response returns `sent_count`, `failed_count`, and ordered per-chat results
- the audit trail records `telegram.broadcast_sent` with the resolved audience and result summary

### 4. Inbound webhook contract

`POST /webhooks/telegram` is the public Telegram update route.

Verification:

- the request must include `X-Telegram-Bot-Api-Secret-Token`
- the header must match the configured webhook secret token
- verification failures return HTTP `401` and do not mutate business state

Replay safety and persistence:

- replay identity is Telegram `update_id`
- each update is stored once in a dedicated Telegram webhook-delivery store
- stored delivery metadata includes:
  - `provider_key`
  - `update_id`
  - `event_type`
  - optional `chat_id`
  - optional `message_id`
  - optional resolved `tenant_id`
  - payload hash
  - secret-token hash
  - outcome
  - optional error code and message
  - raw payload
  - normalized response
  - processed timestamp

Supported update handling for this phase:

- `message`
- `edited_message`

Tenant resolution:

- tenant resolution is based on inbound `chat.id`
- the chat id may match either `support_chat_ids` or `broadcast_chat_ids`
- support workflows are active only when the chat id is in `support_chat_ids`

Outcomes:

- a support chat message with non-empty text is stored as processed and records audit action `telegram.support_message_received`
- mapped non-support updates are stored as ignored
- unmapped chats are stored as ignored with `resolved_tenant_id = null`
- duplicate `update_id` returns the stored `ok = true` result without reprocessing

The provider-facing response is always JSON `{ "ok": true }` after successful verification, including ignored and duplicate updates.

### 5. Bot sync contract

`POST /telegram/bot:sync` is a tenant-scoped authenticated route owned by Integrations.

It performs:

- `getMe`
- `getWebhookInfo`
- `setWebhook` when the configured webhook does not match the expected public route

Expected webhook rules:

- expected webhook URL is `APP_URL + /api/v1/webhooks/telegram`
- the configured secret token is sent as Telegram `secret_token`
- sync is rejected with `409` when tenant Telegram settings are disabled

The sync response returns:

- provider key
- bot id
- bot username
- webhook url
- webhook pending update count
- webhook last error date when present
- tenant setting summary
- `configured_chat_counts` for `broadcast` and `support`

The tenant settings store the last synced bot and webhook snapshot plus `last_synced_at`.

## Consequences

- Telegram delivery becomes operational without waiting for the broader integrations hub task
- patient-linked Telegram reminders remain deferred until tenant-safe chat identifiers are modeled explicitly
- provider credentials stay out of tenant-managed storage until `T061`
- webhook verification, replay safety, and auditability are enforced before public Telegram routes are activated
