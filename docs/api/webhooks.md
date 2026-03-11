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
