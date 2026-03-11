# Webhooks

## Inbound Webhook Standards

Every inbound webhook must:

- terminate at a dedicated `/webhooks/{provider}` route
- verify a provider-specific signature or authentication mechanism
- persist delivery metadata for replay and audit
- be idempotent
- emit structured logs, traces, and audit entries
- map external payloads into internal DTOs through an adapter

## Processing Sequence

1. receive raw request
2. resolve provider configuration for tenant or global provider context
3. verify signature or shared secret
4. check idempotency against provider delivery identifier
5. map payload into an application command
6. execute the command
7. record audit and delivery outcome
8. return the provider-expected response

## Supported Webhook Endpoints

- `POST /webhooks/payme`
- `POST /webhooks/click`
- `POST /webhooks/uzum`
- `POST /webhooks/lab/{provider}`
- `POST /webhooks/telegram`
- `POST /webhooks/myid`
- `POST /webhooks/eimzo`

Verification or diagnostics helpers:

- `POST /webhooks/payme:verify`
- `POST /webhooks/click:verify`
- `POST /webhooks/uzum:verify`
- `POST /webhooks/lab/{provider}:verify`

## Security Requirements

- Never trust route-only identity for provider verification.
- Never process state changes before signature verification succeeds.
- Log verification failures without logging raw secrets.
- Support secret rotation without breaking active deliveries.

## Operational Requirements

- Store provider delivery identifiers where available.
- Expose retry and failure metrics.
- Make replay safe for idempotent handlers.
- Alert on verification failure spikes and dead-letter growth.

Provider-initiated routes that cannot supply `Idempotency-Key` must satisfy the same protection through provider-native replay identifiers plus persisted delivery records.

## Lab Webhook Notes

- `POST /webhooks/lab/{provider}` requires `Idempotency-Key` and `X-Lab-Signature`.
- Lab webhook payloads must include `delivery_id`, `external_order_id`, `status`, `occurred_at`, and optional normalized `results`.
- Successful lab webhook processing must persist a delivery record with provider key, delivery id, payload hash, signature hash, resolved lab order, tenant linkage, and processing outcome.
- `POST /webhooks/lab/{provider}:verify` is the authenticated diagnostics helper and must not mutate business state.

## Payme Webhook Notes

- `POST /webhooks/payme` is a public JSON-RPC 2.0 route and always returns HTTP `200` with a Payme `result` or `error` payload.
- Payme verification uses the `Authorization` header with the configured Merchant API Basic-auth secret.
- Payme request linkage uses `account.payment_id` for local payment lookup and Payme transaction id replay keys for mutating methods.
- `CheckPerformTransaction`, `CreateTransaction`, `PerformTransaction`, `CancelTransaction`, `CheckTransaction`, and `GetStatement` are supported.
- `CreateTransaction`, `PerformTransaction`, and `CancelTransaction` must be duplicate-safe by provider transaction id without requiring `Idempotency-Key`.
- `POST /webhooks/payme:verify` is the authenticated diagnostics helper and must not mutate business state.

## Click Webhook Notes

- `POST /webhooks/click` is a public Shop API callback route and returns the provider-specific JSON response body expected by Click.
- Click verification uses the documented `sign_string` MD5 digest built from callback fields plus the configured secret key.
- Click request linkage uses `merchant_trans_id` for local payment lookup and `click_trans_id` as the replay-safe delivery key.
- `action = 0` is `Prepare` and `action = 1` is `Complete`.
- `Prepare` and `Complete` must be duplicate-safe by `click_trans_id` without requiring `Idempotency-Key`.
- `POST /webhooks/click:verify` is the authenticated diagnostics helper and must not mutate business state.

## Uzum Webhook Notes

- `POST /webhooks/uzum` is a public Merchant API callback route and uses query parameter `operation` to distinguish `check`, `create`, `confirm`, `reverse`, and `status`.
- Uzum verification uses the `Authorization` header with configured Basic auth and payload field `serviceId`.
- Uzum request linkage uses `params.payment_id`, with `params.account.value` accepted as a compatibility alias.
- Uzum replay safety uses `transId` plus the selected `operation`.
- `check` and `status` are read-only but still persist delivery metadata for audit and diagnostics.
- `create`, `confirm`, and `reverse` must be duplicate-safe by `transId` without requiring `Idempotency-Key`.
- `POST /webhooks/uzum:verify` is the authenticated diagnostics helper and must not mutate business state.

## Telegram Webhook Notes

- `POST /webhooks/telegram` is a public Bot API update route and returns JSON `{ "ok": true }` after successful verification.
- Telegram verification uses header `X-Telegram-Bot-Api-Secret-Token`.
- replay safety uses Telegram `update_id`.
- supported updates in this phase are `message` and `edited_message`.
- tenant resolution uses the inbound `chat.id` against tenant-configured Telegram support and broadcast chat ids.
- only mapped support-chat messages with non-empty text record support workflow audit activity; other verified updates are stored as ignored.
