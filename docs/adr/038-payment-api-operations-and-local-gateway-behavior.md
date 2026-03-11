# ADR 038: Payment API Operations and Local Gateway Behavior

## Status

Accepted

## Date

2026-03-11

## Context

`T050` defined the payment aggregate, local initiation rules, state machine, and normalized gateway contract in ADR `037`, but it did not define the HTTP-level behavior required by `T051`.

Before this ADR, the repo docs did not explicitly define:

- the filter contract for `GET /payments`
- the response shape distinction between `GET /payments/{paymentId}` and `GET /payments/{paymentId}/status`
- which payment routes require idempotency protection
- how the local development gateway behaves when API endpoints call into the gateway abstraction

`T051` requires those decisions before external adapters in `T052` through `T054` can reuse the same HTTP contract.

## Decision

Expose payments through tenant-scoped read and action routes that reuse the `T050` state machine, require idempotency on every payment command route, and ship with deterministic local manual gateways for development and automated tests.

## Route Contract

`T051` implements these routes:

- `GET /payments`
- `POST /payments:initiate`
- `GET /payments/{paymentId}`
- `GET /payments/{paymentId}/status`
- `POST /payments/{paymentId}:capture`
- `POST /payments/{paymentId}:cancel`
- `POST /payments/{paymentId}:refund`

Authorization:

- `billing.view` is required for the `GET` routes
- `billing.manage` is required for the `POST` routes

Tenant scope:

- every route requires `X-Tenant-Id`
- every lookup stays inside the current tenant scope

Idempotency:

- `POST /payments:initiate` requires `Idempotency-Key`
- `POST /payments/{paymentId}:capture` requires `Idempotency-Key`
- `POST /payments/{paymentId}:cancel` requires `Idempotency-Key`
- `POST /payments/{paymentId}:refund` requires `Idempotency-Key`

## Read Behavior

`GET /payments` is the filterable payment directory route.

Supported filters:

- `q`
- `status`
- `invoice_id`
- `provider_key`
- `created_from`
- `created_to`
- `limit`

Rules:

- default `limit` is `25`
- maximum `limit` is `100`
- `q` searches `invoice_number`, `provider_key`, `provider_payment_id`, and `description`
- results are ordered by the latest lifecycle timestamp, then `created_at`, then `id`, all descending
- the response returns `meta.filters`

`GET /payments/{paymentId}` returns the full payment projection.

`GET /payments/{paymentId}/status` returns a status-focused projection containing:

- `id`
- current `status`
- provider summary
- `last_transition`
- failure metadata
- cancel and refund reasons
- lifecycle timestamps

`T051` does not perform live gateway polling on the status route. Remote polling and reconciliation remain reserved for later payment-adapter and reconciliation tasks.

## Command Behavior

`POST /payments:initiate` accepts:

- `invoice_id`
- `provider_key`
- `amount`
- optional `currency`
- optional `description`

Behavior:

- the route validates the request envelope
- the selected gateway is resolved through `provider_key`
- the local payment record is created first in `initiated`
- the normalized gateway snapshot is then applied through the `T050` state machine

`POST /payments/{paymentId}:capture`:

- resolves the gateway from the stored `provider_key`
- maps the provider result through the local state machine

`POST /payments/{paymentId}:cancel`:

- accepts optional `reason`
- resolves the gateway from the stored `provider_key`
- maps the provider result through the local state machine

`POST /payments/{paymentId}:refund`:

- accepts optional `reason`
- resolves the gateway from the stored `provider_key`
- maps the provider result through the local state machine
- still respects the `captured -> refunded` guard and the gateway refund-support guard from ADR `037`

## Local Manual Gateways

`T051` introduces two local gateways through repository configuration:

- `manual`
- `manual_no_refund`

Both are deterministic infrastructure adapters intended for local development, CI, and feature tests.

`manual` behavior:

- initiation returns normalized status `pending`
- `provider_payment_id` is generated as `manual-{payment_id}`
- capture returns `captured`
- cancel returns `canceled`
- refund returns `refunded`
- `supportsRefunds()` is `true`

`manual_no_refund` behavior:

- uses the same deterministic local behavior
- reports `supportsRefunds()` as `false`

These adapters let the payment HTTP contract be exercised before Payme, Click, and Uzum integrations are implemented.

## Consequences

- `T051` can ship end-to-end payment APIs without hard-coding any provider-specific logic in controllers
- the billing HTTP layer stays aligned with the `T050` aggregate and transition rules
- future adapters can reuse the same routes and gateway operation service without redefining payment lifecycle behavior
- gateway polling, webhook verification, and reconciliation remain deferred to later billing tasks instead of being improvised inside `T051`
