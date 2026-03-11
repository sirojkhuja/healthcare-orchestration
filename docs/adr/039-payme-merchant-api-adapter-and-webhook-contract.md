# ADR 039: Payme Merchant API Adapter and Webhook Contract

## Status

Accepted

## Date

2026-03-11

## Context

`T050` and `T051` defined the local payment aggregate, HTTP routes, and normalized gateway contract, but they intentionally deferred provider-specific behavior.

`T052` requires Payme support for:

- payment initiation
- inbound webhook handling
- verification
- provider error mapping

The canonical source and split billing docs already require:

- `POST /webhooks/payme` -> `HandlePaymeWebhookCommand` -> Integrations
- `POST /webhooks/payme:verify` -> `VerifyPaymeWebhookCommand` -> Integrations
- idempotent payment and webhook handling
- verification before mutation

Before this ADR, the repository docs did not define:

- the exact Payme wire protocol
- how the Payme transaction states map into the local payment state machine
- which request field links a Payme transaction to a MedFlow payment
- how duplicate Payme method calls are handled
- how `GetStatement` is produced
- whether the generic payment capture, cancel, and refund routes are supported for Payme

The official Payme Business Merchant API documentation defines a JSON-RPC 2.0 style inbound protocol over HTTPS, Basic authorization, method-level duplicates for `CreateTransaction`, `PerformTransaction`, and `CancelTransaction`, transaction state codes `1`, `2`, `-1`, and `-2`, and mandatory `GetStatement` support.

## Decision

Implement Payme through the Payme Business Merchant API contract on the public MedFlow webhook route, keep payment initiation inside Billing, and keep the Payme public transport in the Integrations module.

## Module and Boundary Decision

- Billing owns the payment aggregate, gateway adapter, payment repository changes, and payment lifecycle synchronization.
- Integrations owns the public Payme webhook controller, Payme webhook commands and handlers, and payment-webhook delivery persistence.
- The Payme public route does not use the standard API status envelope because Payme expects JSON-RPC responses.
- The authenticated diagnostics route `POST /webhooks/payme:verify` remains a normal MedFlow JSON endpoint.

This keeps provider-facing HTTP concerns inside Integrations while preserving the payment state machine and transition rules inside Billing.

## Payme Authentication and Verification

The Payme public route verifies the `Authorization` header against the configured merchant secret using the Payme Merchant API Basic-auth scheme.

Contract:

- header: `Authorization`
- scheme: `Basic`
- expected credentials: `Paycom:{merchant_key}`
- verification failure returns a Payme JSON-RPC error with code `-32504`
- verification happens before any state mutation

The diagnostics route accepts the raw authorization header plus payload and returns only whether verification succeeds. It never mutates payment state.

## Initiation Contract

Payme initiation stays on the existing Billing route:

- `POST /payments:initiate`

Provider behavior:

- `provider_key = payme`
- local payment creation remains `initiated`
- `provider_payment_id` stays `null` until Payme calls `CreateTransaction`
- initiation returns a `checkout_url`

`checkout_url` for Payme is the documented Payme checkout URL built from the merchant checkout parameters. It includes:

- `merchant`
- `amount` in tiyin
- `account[payment_id]`
- optional return URL and locale fields when configured

This preserves the generic `checkout_url` contract without adding a MedFlow-only checkout bridge route.

## Account Mapping

MedFlow uses a single Payme account field:

- `account.payment_id`

Rules:

- the value is the MedFlow payment UUID
- the field must resolve to an existing payment
- the payment must belong to `provider_key = payme`
- unknown `account.payment_id` returns Payme error `-31050`
- the Payme request `amount` must equal the local payment amount converted to tiyin
- amount mismatch returns Payme error `-31001`

No tenant header is required on the public Payme route. Tenant scope is resolved from the linked payment.

## State Mapping

Payme provider states map to local payments as follows:

- Payme `1` -> local `pending`
- Payme `2` -> local `captured`
- Payme `-1` -> local `canceled`
- Payme `-2` -> local `refunded`

Local sequencing rules:

- `CreateTransaction` advances `initiated -> pending`
- `PerformTransaction` advances `pending -> captured`
- `CancelTransaction` on a pending Payme transaction advances `pending -> canceled`
- `CancelTransaction` on a performed Payme transaction advances `captured -> refunded`

Provider timestamps:

- `params.time` from `CreateTransaction` is stored as the Payme transaction creation time used by `GetStatement`
- local payment transition timestamps use the MedFlow processing time for the accepted callback
- terminal local states are never reopened

## Supported Payme Methods

The public route supports:

- `CheckPerformTransaction`
- `CreateTransaction`
- `PerformTransaction`
- `CancelTransaction`
- `CheckTransaction`
- `GetStatement`

Read behavior:

- `CheckPerformTransaction` validates the payment link and amount and returns `allow = true` when payment creation is permitted
- `CheckTransaction` returns the current transaction view for the linked Payme transaction
- `GetStatement` returns all successfully created Payme transactions whose Payme `time` falls within the requested inclusive range, ordered ascending by that Payme `time`

Mutation behavior:

- `CreateTransaction`, `PerformTransaction`, and `CancelTransaction` must be duplicate-safe
- duplicate-safe behavior is implemented from the provider transaction id, not `Idempotency-Key`
- only one active Payme transaction may exist for one MedFlow payment
- attempting to create a different Payme transaction for a payment that is already waiting for payment or already paid returns `-31008`
- unknown provider transaction ids on `PerformTransaction`, `CancelTransaction`, and `CheckTransaction` return `-31003`
- `PerformTransaction`, `CancelTransaction`, and `CheckTransaction` resolve the local payment from the Payme transaction id established by `CreateTransaction`

`GetStatement` is mandatory and is backed by stored Payme create-delivery records plus the current payment lifecycle snapshot.

## Payment Route Support for Payme

The generic payment routes still exist for all gateways, but Payme support is provider-driven in this phase.

For `provider_key = payme`:

- `GET /payments/{paymentId}` and `GET /payments/{paymentId}/status` return the stored local projection
- `POST /payments/{paymentId}:capture` is not supported and returns `409`
- `POST /payments/{paymentId}:cancel` is not supported and returns `409`
- `POST /payments/{paymentId}:refund` is not supported and returns `409`
- `fetchPaymentStatus()` returns the stored local snapshot until a later reconciliation task documents outbound Payme polling behavior

This avoids inventing merchant-initiated Payme actions that are not part of the documented Merchant API contract.

## Delivery Persistence

Payme webhook processing persists delivery records in a shared payment-webhook delivery store that is reusable for Click and Uzum.

Each stored delivery includes:

- `provider_key`
- method name
- replay key derived from method plus Payme transaction id when available
- payload hash
- authorization hash
- linked `payment_id` when resolved
- linked tenant id when resolved
- processed outcome
- provider error code and message when relevant
- raw request payload
- normalized response payload
- processed timestamp

Rules:

- `CreateTransaction`, `PerformTransaction`, and `CancelTransaction` use the Payme transaction id as the provider replay anchor
- `CheckPerformTransaction`, `CheckTransaction`, and `GetStatement` are read-only and do not rely on shared idempotency middleware
- duplicate mutating Payme requests must not execute local transitions twice

## Error Mapping

MedFlow maps the following conditions to Payme errors:

- invalid authorization -> `-32504`
- invalid JSON-RPC request shape -> `-32600`
- unsupported Payme method -> `-32601`
- invalid method params -> `-32602`
- unexpected internal failure -> `-32400`
- invalid amount -> `-31001`
- invalid `account.payment_id` -> `-31050` with `data = "account.payment_id"`
- transaction not found -> `-31003`
- impossible transition or conflicting provider transaction -> `-31008`

The public Payme route always returns HTTP `200` with a JSON-RPC `result` or `error` body. Transport-level failures are reserved for infrastructure outages before the application can form a valid JSON-RPC response.

## Idempotency Rule Clarification

The canonical source requires idempotency protection for webhook processing. For provider-initiated webhooks that cannot supply `Idempotency-Key`, the required protection is satisfied by provider-native replay identifiers and stored delivery records.

For Payme this means:

- no `Idempotency-Key` header is required on `POST /webhooks/payme`
- `CreateTransaction`, `PerformTransaction`, and `CancelTransaction` are replay-safe by Payme transaction id plus method
- the authenticated diagnostics route remains a normal MedFlow route and does not mutate business state

## Consequences

- `T052` can implement Payme without distorting the local payment state machine
- the public Payme endpoint remains compatible with the official Merchant API contract
- future Click and Uzum tasks can reuse the shared payment-webhook delivery store
- a later payment reconciliation task can add explicit outbound Payme reconciliation behavior without redefining the webhook contract
