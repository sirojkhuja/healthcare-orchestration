# ADR 040: Click Shop API Adapter and Webhook Contract

## Status

Accepted

## Date

2026-03-11

## Context

`T053` requires Click support for:

- payment initiation
- inbound webhook handling
- verification
- provider error mapping

The canonical source and split billing docs already require:

- `POST /webhooks/click` -> `HandleClickWebhookCommand` -> Integrations
- `POST /webhooks/click:verify` -> `VerifyClickWebhookCommand` -> Integrations
- idempotent payment and webhook handling
- verification before mutation

Before this ADR, the repository docs did not define:

- whether MedFlow should use Click Shop API, Click Merchant API, or both
- how Click callback stages map into the local payment state machine
- which fields link a Click request to a MedFlow payment
- how duplicate Click `Prepare` and `Complete` requests are handled
- whether generic payment capture, cancel, and refund routes are supported for Click

The official Click documentation defines two different integration styles:

- Shop API: payment-button redirect plus provider-initiated `Prepare` and `Complete` callback requests
- Merchant API: supplier-initiated invoice, token, status, and reversal endpoints

The canonical MedFlow source of truth already requires the Click webhook routes, but it does not define supplier-initiated invoice, phone-based payment, or card-token flows for this phase.

## Decision

Implement Click in this phase through the documented Shop API plus payment-button redirect flow, keep payment initiation inside Billing, and keep the public Click callback transport in the Integrations module.

The separate Click Merchant API invoice, token, status polling, and reversal flows are deferred until the canonical source explicitly requires them.

## Module and Boundary Decision

- Billing owns the Click gateway adapter, payment repository synchronization, and payment lifecycle changes.
- Integrations owns the public Click webhook controller, Click webhook commands and handlers, and stored delivery metadata.
- The public Click route returns the provider-specific JSON body expected by Click instead of the standard MedFlow API envelope.
- The authenticated diagnostics route `POST /webhooks/click:verify` remains a normal MedFlow JSON endpoint.

This keeps provider-facing transport inside Integrations while preserving the payment aggregate and state machine inside Billing.

## Initiation Contract

Click initiation stays on the existing Billing route:

- `POST /payments:initiate`

Provider behavior:

- `provider_key = click`
- local payment creation remains `initiated`
- initiation returns a `checkout_url`
- local `provider_payment_id` stays `null` until the first accepted Click callback

`checkout_url` uses the documented Click payment-button redirect contract:

- base URL: `https://my.click.uz/services/pay`
- required params:
  - `service_id`
  - `merchant_id`
  - `amount`
  - `transaction_param`
- optional params when configured:
  - `merchant_user_id`
  - `return_url`
  - `card_type`

Field mapping:

- `transaction_param` = MedFlow payment UUID
- `amount` = local payment amount formatted with two decimal places in soums

This keeps Click initiation compatible with the documented payment-button flow already implied by the canonical source and avoids inventing Merchant API invoice creation behavior that is not yet part of the product contract.

## Account Mapping

Click links inbound requests to MedFlow through:

- `merchant_trans_id`

Rules:

- `merchant_trans_id` is the MedFlow payment UUID
- it must resolve to an existing local payment
- the payment must belong to `provider_key = click`
- unknown `merchant_trans_id` returns Click error `-5`
- request `amount` must equal the local payment amount in soums within two decimal places
- amount mismatch returns Click error `-2`

No tenant header is required on the public Click route. Tenant scope is resolved from the linked payment.

## Prepare and Complete Correlation

Click requires `merchant_prepare_id` on the `Complete` request, but the MedFlow public identifiers are UUIDs and the canonical source reserves UUIDs for first-party public identifiers.

MedFlow uses this correlation rule:

- successful `Prepare` replies return `merchant_prepare_id = click_trans_id`
- successful `Complete` replies return `merchant_confirm_id = click_trans_id`
- `click_trans_id` therefore becomes the replay-safe supplier correlation key for one Click payment attempt

This keeps the echoed supplier correlation field numeric, matches the provider request lifecycle, and avoids introducing a second externally visible MedFlow identifier just for Click.

## Verification Contract

Click verification is field-based and uses the documented MD5 digest:

- `md5(click_trans_id + service_id + secret_key + merchant_trans_id + merchant_prepare_id? + amount + action + sign_time)`
- `merchant_prepare_id` participates only for `action = 1`

Additional rules:

- the route requires all documented provider fields for the selected action
- `service_id` must match the configured Click service id
- supported actions are `0` for `Prepare` and `1` for `Complete`
- verification happens before any payment state mutation

The diagnostics route accepts an exact Click payload and returns only whether the payload verifies. It never mutates payment state.

## State Mapping

Click callback stages map to the local payment state machine as follows:

- successful `Prepare` -> local `pending`
- successful `Complete` with request `error = 0` -> local `captured`
- `Complete` with request `error < 0` -> local `canceled`

Provider status values stored on the local payment are:

- `prepared`
- `completed`
- `cancelled`

Local sequencing rules:

- `Prepare` advances `initiated -> pending`
- duplicate `Prepare` on a payment already in `pending` returns the stored success response without re-running the transition
- `Complete` with `error = 0` advances `pending -> captured`
- duplicate `Complete` success returns the stored success response without re-running the transition
- `Complete` with provider-side cancellation advances `pending -> canceled`
- duplicate cancellation returns the same Click cancellation response
- terminal local states are never reopened

## Supported Public Click Callback Contract

The public route supports both Shop API stages on the single public endpoint:

- `action = 0` -> `Prepare`
- `action = 1` -> `Complete`

Transport rules:

- `POST /webhooks/click`
- request payload may arrive as standard form fields
- response body is JSON
- successful prepare reply includes:
  - `error = 0`
  - `error_note = "Success"`
  - `click_trans_id`
  - `merchant_trans_id`
  - `merchant_prepare_id`
- successful complete reply includes:
  - `error = 0`
  - `error_note = "Success"`
  - `click_trans_id`
  - `merchant_trans_id`
  - `merchant_confirm_id`
- provider-side canceled completion returns:
  - `error = -9`
  - `error_note = "Transaction cancelled"`

## Delivery Persistence and Replay Safety

The shared payment-webhook delivery store introduced in ADR `039` is reused for Click.

Each stored delivery includes:

- `provider_key = click`
- method derived from action:
  - `prepare`
  - `complete`
- replay key = `click_trans_id`
- provider transaction id = `click_trans_id`
- request id = `click_paydoc_id`
- linked `payment_id` and tenant id when resolved
- payload hash
- signature hash based on `sign_string`
- processed outcome
- provider error code and message when relevant
- normalized request and response bodies

Rules:

- `Prepare` is replay-safe by `click_trans_id`
- `Complete` is replay-safe by `click_trans_id`
- provider-initiated Click callbacks do not require `Idempotency-Key`
- duplicate mutating callbacks must not execute local transitions twice

## Error Mapping

MedFlow maps the following conditions to documented Click Shop API errors:

- invalid signature -> `-1` / `SIGN CHECK FAILED!`
- amount mismatch -> `-2` / `Incorrect parameter amount`
- unsupported action -> `-3` / `Action not found`
- previously confirmed payment -> `-4` / `Already paid`
- unknown `merchant_trans_id` -> `-5` / `User does not exist`
- unknown or unmatched `merchant_prepare_id` on `Complete` -> `-6` / `Transaction does not exist`
- malformed request or wrong service id -> `-8` / `Error in request from click`
- previously canceled payment, or provider-side canceled `Complete` -> `-9` / `Transaction cancelled`

Unexpected internal failures after verification map to:

- `-7` / `Failed to update user`

## Payment Route Support for Click

For `provider_key = click` in this phase:

- `GET /payments/{paymentId}` and `GET /payments/{paymentId}/status` return the stored local projection
- `POST /payments/{paymentId}:capture` is not supported and returns `409`
- `POST /payments/{paymentId}:cancel` is not supported and returns `409`
- `POST /payments/{paymentId}:refund` is not supported and returns `409`
- `fetchPaymentStatus()` returns the stored local snapshot until a future task explicitly documents outbound Merchant API polling or reversal behavior

This is intentional. The canonical source currently requires Click webhook routes, not Click Merchant API invoice, token, or reversal workflows.

## Idempotency Rule Clarification

The canonical source requires idempotency protection for webhook processing. For provider-initiated callbacks that cannot send `Idempotency-Key`, the requirement is satisfied through provider-native replay identifiers plus stored delivery records.

For Click this means:

- no `Idempotency-Key` header is required on `POST /webhooks/click`
- `Prepare` and `Complete` are replay-safe by `click_trans_id`
- the authenticated diagnostics route does not mutate business state

## Consequences

- `T053` can implement Click without introducing undocumented Merchant API flows
- the public Click endpoint stays aligned with the official Shop API contract
- the payment-button redirect contract stays compatible with the existing `checkout_url` response shape
- the shared payment-webhook delivery store is reused instead of inventing provider-specific replay persistence
- future Click Merchant API work can be added in a later ADR without redefining the Shop API callback contract
