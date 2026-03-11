# ADR 041: Uzum Merchant API Adapter and Payment Reconciliation Contract

## Status

Accepted

## Context

The canonical source of truth required an Uzum payment adapter, inbound webhook handling, verification, and payment reconciliation endpoints, but it did not yet define the exact Uzum callback contract or how reconciliation should work for providers that do not expose a partner-initiated status-polling API.

The repository already had:

- the shared payment aggregate and gateway abstraction from ADR `037`
- the payment HTTP surface from ADR `038`
- shared webhook-delivery persistence from ADR `039`
- Click and Payme provider adapters with provider-specific webhook contracts in ADR `039` and ADR `040`

The next step needed to preserve the documented architecture rules:

- keep third-party behavior behind a payment-gateway adapter and webhook service
- keep provider replay safety and auditability mandatory
- avoid inventing undocumented business behavior in code without first recording the contract

## Decision

Implement Uzum behind the existing payment gateway registry as a Merchant API adapter with a documented single-route webhook contract and an explicit reconciliation run surface.

### 1. Payment initiation

- `provider_key = uzum` creates the local payment in `initiated`
- Uzum initiation does not return a checkout URL in this phase
- initiation stores provider status `awaiting_uzum_webhook`
- generic `capture`, `cancel`, and `refund` routes are not supported for `provider_key = uzum` in this phase and return `409`

### 2. Webhook transport

- MedFlow exposes one public route: `POST /webhooks/uzum`
- because the canonical route inventory requires a single path while Uzum documents multiple Merchant API callbacks, the operation selector is carried as query parameter `operation`
- supported operations are:
  - `check`
  - `create`
  - `confirm`
  - `reverse`
  - `status`
- MedFlow exposes one authenticated diagnostics helper: `POST /webhooks/uzum:verify`

### 3. Verification

- Uzum verification uses the `Authorization` header with HTTP Basic auth
- configured credentials are `UZUM_MERCHANT_LOGIN` and `UZUM_MERCHANT_PASSWORD`
- every request must also match the configured `UZUM_SERVICE_ID` through payload field `serviceId`
- verification failures return the provider error envelope and do not mutate payment state

### 4. Request linkage and replay safety

- local payment lookup uses `params.payment_id`
- MedFlow also accepts `params.account.value` as a compatibility alias for diagnostics and future provider flexibility
- provider replay and transaction identity use `transId`
- replay uniqueness is `provider_key = uzum + operation + transId`
- delivery metadata is stored in the shared `payment_webhook_deliveries` table

### 5. Local state mapping

- `check` is read-only and validates authentication, service id, payment existence, and amount
- `create` is replay-safe by `transId` and maps local `initiated -> pending` with provider status `CREATED`
- `confirm` is replay-safe by `transId` and maps local `pending -> captured` with provider status `CONFIRMED`
- `reverse` is replay-safe by `transId`
  - local `initiated|pending -> canceled` with provider status `CANCELED`
  - local `captured -> refunded` with provider status `REFUNDED`
- `status` is read-only and returns the current normalized provider state derived from the local payment

### 6. Reconciliation

- the billing module now owns:
  - `POST /payments:reconcile`
  - `GET /payments/reconciliation-runs`
  - `GET /payments/reconciliation-runs/{runId}`
- reconciliation is tenant-scoped and provider-scoped
- request body requires `provider_key`
- request body accepts optional `payment_ids[]` and optional `limit`
- each run stores:
  - tenant id
  - provider key
  - requested payment ids
  - scanned count
  - changed count
  - result count
  - result payloads for each payment
- reconciliation records an audit event `payments.reconciled`

### 7. Uzum reconciliation behavior

- the Uzum adapter does not poll a remote status API in this phase
- instead, reconciliation uses the gateway snapshot abstraction to normalize stale local Uzum payments
- a Uzum payment in local `pending` that remains unconfirmed beyond the configured timeout is mapped to local `failed`
- the timeout is configured through `UZUM_CONFIRMATION_TIMEOUT_MINUTES` and defaults to `30`
- the resulting provider status is `FAILED` with local failure code `uzum_timeout`

## Consequences

### Positive

- the missing payment reconciliation routes are now explicitly documented and implemented
- Uzum follows the same adapter, webhook, audit, and delivery-store architecture as Payme and Click
- the single-route webhook inventory from the canonical documentation is preserved without hiding provider behavior in route sprawl
- reconciliation is useful immediately even without an outbound provider polling API

### Negative

- the single-route `operation` query parameter is a MedFlow normalization choice, not a provider-native path layout
- Uzum initiation does not provide a redirect or hosted checkout artifact in this phase
- partner-initiated outbound status polling remains out of scope until the provider contract requires and documents it

## Follow-up Rules

- any future Uzum outbound polling, hosted checkout, or settlement-specific behavior must update this ADR and the canonical source docs before code changes
- if the provider introduces a stable signed-body mechanism or a different authentication contract, the webhook verification section must be updated in the same change
