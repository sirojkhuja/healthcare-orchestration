# ADR 046: SMS Routing, Failover, and Delivery Consumer Contract

Date: `2026-03-12`

## Status

Accepted

## Context

The canonical source already defined:

- `GET /notification-providers/sms`
- `PUT /notification-providers/sms`
- `POST /notifications:test/sms`
- `POST /integrations/eskiz:send`
- `POST /integrations/playmobile:send`
- `POST /integrations/textup:send`
- an `SmsProvider` strategy with failover ordering, tenant-specific routing, and per-message-type routing

Before `T058`, the repository still lacked:

- the supported SMS message-type taxonomy
- how tenant routing preferences are stored and merged with defaults
- whether test and provider-specific SMS endpoints persist notification rows
- how queued SMS notifications move from `queued` to `sent|failed`
- whether delivery attempts count per retry request or per provider attempt
- which audit and outbox events record SMS delivery outcomes

Those decisions are material because `T057` made notification dispatch queue-first and `T041` already queues appointment reminder and confirmation notifications that later SMS work must deliver without redesigning the notification contract.

## Decision

Implement SMS delivery as a queue-consumer-backed extension of the notification lifecycle with tenant-scoped routing priorities, explicit failover, and diagnostic send routes that reuse the same routing engine.

### 1. Supported SMS Message Types

The platform supports these SMS routing message types:

- `otp`
- `reminder`
- `transactional`
- `bulk`

Routing resolution order:

1. `metadata.message_type` when present and valid
2. appointment-linked reminder notifications map to `reminder`
3. appointment-linked confirmations map to `transactional`
4. template codes containing `OTP` map to `otp`
5. bulk-like metadata may map to `bulk`
6. all other sends default to `transactional`

`T041` now writes `metadata.message_type` for appointment-linked SMS notifications so reminder flows do not depend on heuristic template inspection.

### 2. Tenant-Scoped Routing Policy

`GET /notification-providers/sms` returns:

- configured providers
- one effective ordered provider list per message type
- `source = default|custom` for each route

`PUT /notification-providers/sms` accepts a partial replacement set of route entries:

- each entry contains `message_type`
- each entry contains an ordered non-empty `providers` list
- provider keys must be unique inside a route
- provider keys must reference configured SMS providers
- each `message_type` may appear at most once per request

Persistence rules:

- custom routes are stored per `tenant_id + message_type`
- unspecified message types keep their existing custom route or fall back to defaults
- routing changes do not mutate historical notification rows

Default route order for this phase:

- `otp`: `eskiz`, `playmobile`, `textup`
- `reminder`: `playmobile`, `eskiz`, `textup`
- `transactional`: `eskiz`, `playmobile`, `textup`
- `bulk`: `textup`, `playmobile`, `eskiz`

### 3. Diagnostic SMS Endpoints

`POST /notifications:test/sms` is a notification-layer diagnostic route:

- requires tenant scope and `notifications.manage`
- validates `recipient.phone_number`, `message`, optional `message_type`, and optional `metadata`
- uses the same routing and failover engine as queued notification delivery
- does not create a `notifications` row
- does not publish notification outbox events
- returns the attempted providers and the final result

`POST /integrations/eskiz:send`, `POST /integrations/playmobile:send`, and `POST /integrations/textup:send` are provider-specific diagnostics:

- require tenant scope and `integrations.manage`
- validate the same request shape as `POST /notifications:test/sms`
- force exactly one provider instead of routing through tenant failover order
- do not create a `notifications` row
- return the single-provider delivery result

### 4. Queued SMS Notification Delivery

Queued SMS notifications are delivered by a Kafka consumer that listens to `medflow.notifications.v1`.

The consumer reacts only to:

- `notification.queued`
- `notification.retried`

Processing rules:

- non-SMS notifications are ignored
- only notifications still in `queued` are processed
- provider attempts run in the effective route order for the resolved message type
- `attempts` counts actual provider delivery attempts, not manual retry requests
- delivery stops on first successful provider response
- delivery stops when the remaining attempt budget is exhausted

State updates:

- success moves `queued -> sent`
- full route failure moves `queued -> failed`
- `provider_key` stores the successful provider on success or the last attempted provider on failure
- `provider_message_id` is populated only on success
- `last_error_code` and `last_error_message` store the last provider failure on failure

### 5. Audit and Outbox Behavior

SMS delivery adds these audit actions:

- `notifications.sent`
- `notifications.failed`

SMS delivery adds these outbox events on `medflow.notifications.v1`:

- `notification.sent`
- `notification.failed`

Both audit metadata and event payloads include the ordered delivery attempt list used during routing.

## Consequences

- queue-first notification dispatch stays intact while SMS delivery becomes operational
- routing and failover are tenant-visible without requiring direct template changes
- appointment reminder and confirmation notifications can use the same routing engine as ad hoc SMS sends
- future credential, health, and logging work in `T061` can extend the provider adapters without changing the public SMS routing contract
