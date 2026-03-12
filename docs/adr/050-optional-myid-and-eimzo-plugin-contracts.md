## ADR 050: Optional MyID and E-IMZO Plug-in Contracts

Date: `2026-03-12`

## Status

Accepted

## Context

The canonical source defines four optional Uzbekistan plug-in endpoints but does not yet define their request shapes, state models, or webhook verification contract:

- `POST /integrations/myid:verify`
- `POST /webhooks/myid`
- `POST /integrations/eimzo:sign`
- `POST /webhooks/eimzo`

The repository already exposes tenant-scoped integration registry, credential, token, and webhook inventory management through `T061`. Implementing the optional plug-ins without a written contract would leave material decisions undocumented:

- how feature flags gate runtime behavior
- what state is stored for verification and signing requests
- how inbound webhooks resolve tenant scope
- how replay protection and secret verification work
- what deterministic local behavior exists before live provider adapters are available

## Decision

Implement MyID and E-IMZO as optional, feature-flagged plug-ins backed by tenant-scoped request records plus replay-safe webhook delivery records. This phase remains deterministic and local-first: initiation commands do not require live outbound traffic, but they do require the integration hub to be enabled and configured for the tenant.

### 1. Feature-flag and readiness rules

- `myid` and `eimzo` remain catalog entries in the integrations hub.
- A plug-in command returns `409` when the corresponding catalog entry is not `available`.
- Initiation commands require the integration to be tenant-enabled.
- Initiation commands require managed credentials to be configured.
- Initiation commands require at least one active managed webhook registration for the same integration key.
- This phase allows initiation when the integration health is `healthy` or `degraded`, but rejects `disabled` and `failing`.

### 2. MyID verification session contract

`POST /integrations/myid:verify` creates one tenant-scoped verification session.

Request body:

- `external_reference` required string
- `subject` required object with string keys and scalar or null leaf values
- `metadata` optional object with string keys

Stored state:

- `verification_id`
- `tenant_id`
- `external_reference`
- `provider_reference`
- `status = pending|verified|rejected|expired|failed`
- `subject`
- `metadata`
- `result_payload`
- `webhook_id`
- `completed_at`

Response body:

- `verification_id`
- `integration_key = myid`
- `external_reference`
- `provider_reference`
- `status`
- `subject`
- `metadata`
- `result_payload`
- timestamps

### 3. E-IMZO signing session contract

`POST /integrations/eimzo:sign` creates one tenant-scoped signing session.

Request body:

- `external_reference` required string
- `document_hash` required string
- `document_name` required string
- `signer` optional object with string keys and scalar or null leaf values
- `metadata` optional object with string keys

Stored state:

- `sign_request_id`
- `tenant_id`
- `external_reference`
- `provider_reference`
- `status = pending|signed|canceled|expired|failed`
- `document_hash`
- `document_name`
- `signer`
- `metadata`
- `signature_payload`
- `webhook_id`
- `completed_at`

Response body:

- `sign_request_id`
- `integration_key = eimzo`
- `external_reference`
- `provider_reference`
- `status`
- `document_hash`
- `document_name`
- `signer`
- `metadata`
- `signature_payload`
- timestamps

### 4. Webhook verification and replay protection

Both inbound webhook routes are public:

- `POST /webhooks/myid`
- `POST /webhooks/eimzo`

Webhook request rules:

- header `X-Integration-Webhook-Secret` is required
- body `webhook_id` is required
- body `delivery_id` is required and replay-safe per `integration_key + webhook_id`
- body `provider_reference` is required
- body `status` is required
- body `metadata` is optional
- MyID webhooks accept optional `result_payload`
- E-IMZO webhooks accept optional `signature_payload`

Tenant resolution and verification:

- resolve the managed webhook by `integration_key + webhook_id`
- reject inactive or missing webhook records with `404`
- verify the provided secret by comparing its SHA-256 hash to the stored webhook secret hash
- resolve tenant scope from the webhook record, not from the request body
- store each processed delivery in an append-only webhook-delivery table
- duplicate `delivery_id` replays return `{ "ok": true }` without reprocessing

### 5. State transition rules

MyID verification sessions:

- start in `pending`
- terminal transitions allowed to `verified|rejected|expired|failed`
- terminal states are immutable for later webhook deliveries

E-IMZO signing sessions:

- start in `pending`
- terminal transitions allowed to `signed|canceled|expired|failed`
- terminal states are immutable for later webhook deliveries

### 6. Audit and integration logging

The platform must record:

- audit events for initiation and webhook completion
- integration-log entries for initiation and webhook processing
- webhook delivery records containing payload hash, secret hash, outcome, and response payload

### 7. Local-first behavior for this phase

- initiation endpoints generate internal provider references locally
- initiation endpoints do not require live provider communication
- webhook routes are the authoritative mechanism for completing sessions
- future live adapters may replace the local initiation step without changing the HTTP contract recorded here

## Consequences

Positive:

- optional plug-ins now have explicit contracts before implementation
- feature flags, tenant scoping, and webhook verification remain enforced
- future provider adapters can plug into stable initiation and webhook surfaces

Trade-offs:

- this phase does not expose read endpoints for verification or signing sessions
- webhook payloads use a platform-managed `webhook_id` rather than a provider-native route fan-out contract
- live provider redirects or browser flows remain future adapter work
