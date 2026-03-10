# ADR 037: Payment Aggregate and Initiation Contract

## Status

Accepted

## Date

2026-03-11

## Context

The canonical source already defines:

- `GET /payments`
- `POST /payments:initiate`
- `GET /payments/{paymentId}`
- `GET /payments/{paymentId}/status`
- `POST /payments/{paymentId}:cancel`
- `POST /payments/{paymentId}:refund`
- `POST /payments/{paymentId}:capture`
- `POST /payments:reconcile`
- `GET /payments/reconciliation-runs`
- `GET /payments/reconciliation-runs/{runId}`

The canonical source and product state-machine document also define the local payment status catalog:

- `initiated`
- `pending`
- `captured`
- `failed`
- `canceled`
- `refunded`

Before `T050`, the docs do not define:

- the payment aggregate field set
- how payments relate to invoices
- what the initiation payload validates
- how provider capability is represented
- which transitions are local workflow actions versus remote status synchronization
- what the provider-facing payment gateway contract looks like

`T050` requires those decisions before `T051` can expose payment API operations and before `T052` through `T054` implement provider adapters and webhook handling.

## Decision

Implement payments as tenant-scoped aggregates linked to a single invoice, with explicit domain transitions, a normalized provider-gateway contract, and no invoice-balance mutation in this phase.

## Payment Aggregate Boundary

Each payment owns:

- `payment_id`
- `tenant_id`
- `invoice_id`
- `invoice_number`
- `provider_key`
- `amount`
- `currency`
- optional `description`
- `status`
- optional `provider_payment_id`
- optional `provider_status`
- optional `checkout_url`
- optional `failure_code`
- optional `failure_message`
- optional `cancel_reason`
- optional `refund_reason`
- optional `last_transition`
- `initiated_at`
- optional `pending_at`
- optional `captured_at`
- optional `failed_at`
- optional `canceled_at`
- optional `refunded_at`
- `created_at`
- `updated_at`

The read model may expose nested invoice and provider summaries, but the aggregate itself stores the invoice number and payment provider key as snapshots so later invoice edits do not affect payment history.

## Invoice Linkage Rules

Payments are linked to exactly one invoice.

Rules:

- `invoice_id` is required on initiation
- the invoice must exist in the current tenant scope
- payment initiation is allowed only when the invoice status is:
  - `issued`
  - `finalized`
- payments may not be initiated for void invoices
- payment currency must equal the linked invoice currency
- payment amount must be greater than zero
- payment amount may not exceed the linked invoice `total_amount`

This phase does not allocate payments against invoice balances and does not mutate invoice status. Multiple payment attempts may therefore exist for one invoice as separate historical records.

## Status Catalog and Transition Rules

`T050` defines this state machine:

- `initiated`
- `pending`
- `captured`
- `failed`
- `canceled`
- `refunded`

Allowed transitions:

- `initiated -> pending`
- `pending -> captured`
- `pending -> failed`
- `pending -> canceled`
- `captured -> refunded`

Terminal states:

- `failed`
- `canceled`
- `refunded`

Guards:

- `capture` is allowed only from `pending`
- `fail` is allowed only from `pending`
- `cancel` is allowed only from `pending`
- `refund` is allowed only from `captured`
- `refund` additionally requires the selected payment gateway to support refunds

Operational notes:

- creation always starts in `initiated`
- remote webhooks and reconciliation may advance a local payment forward through valid transitions, but they may not reopen terminal states
- `provider_status` stores the remote provider status string for diagnostics and reconciliation transparency
- `provider_payment_id` is nullable at creation time and becomes required once the external provider acknowledges the payment

## Initiation Contract

The local initiation contract accepts:

- `invoice_id`
- `provider_key`
- `amount`
- optional `currency`
- optional `description`

Rules:

- `provider_key` uses lowercase slug format `[a-z0-9._-]+`
- `currency` is optional and defaults to the linked invoice currency
- when `currency` is provided, it must equal the linked invoice currency
- `description` is optional free text stored for provider-facing or audit-facing reference

The local initiation flow creates the payment record first in `initiated`, writes audit and outbox records, and leaves provider communication to later tasks.

## Provider Gateway Contract

Payments use a provider abstraction that mirrors the integration framework already defined in the canonical source.

`PaymentGateway` must expose:

- `providerKey(): string`
- `supportsRefunds(): bool`
- `initiatePayment(PaymentGatewayInitiationRequestData): PaymentGatewaySnapshotData`
- `fetchPaymentStatus(PaymentData): PaymentGatewaySnapshotData`
- `capturePayment(PaymentData): PaymentGatewaySnapshotData`
- `cancelPayment(PaymentData, ?string $reason = null): PaymentGatewaySnapshotData`
- `refundPayment(PaymentData, ?string $reason = null): PaymentGatewaySnapshotData`
- `verifyWebhookSignature(string $signature, string $payload): bool`

`PaymentGatewaySnapshotData` is the normalized provider response shape and includes:

- normalized local `status`
- optional `provider_payment_id`
- optional `provider_status`
- optional `checkout_url`
- optional `failure_code`
- optional `failure_message`
- optional `reason`
- optional `occurred_at`
- optional `raw_payload`

This contract allows later provider adapters to translate provider-specific payloads into the local payment state machine without moving transition logic into controllers or infrastructure code.

## Auditing and Events

Payment creation and transitions write audit actions:

- `payments.initiated`
- `payments.pending`
- `payments.captured`
- `payments.failed`
- `payments.canceled`
- `payments.refunded`

Successful creation and transitions emit billing-topic outbox events:

- `payment.initiated`
- `payment.pending`
- `payment.captured`
- `payment.failed`
- `payment.canceled`
- `payment.refunded`

`PaymentCaptured` remains part of the domain event catalog required by the canonical source.

## Consequences

- `T050` can implement payment persistence, the pure domain state machine, and lifecycle services without prematurely choosing a provider adapter
- `T051` can add payment API routes on top of this stable local contract
- `T052` through `T054` can implement Payme, Click, and Uzum behind the same gateway surface
- invoice settlement, cumulative balance math, and payment allocation remain deferred until a later billing phase explicitly documents them
