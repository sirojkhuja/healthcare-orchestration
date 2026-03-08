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
