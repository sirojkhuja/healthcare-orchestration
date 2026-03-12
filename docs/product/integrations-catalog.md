# Integrations Catalog

## Integration Framework Rules

Every integration must follow the same internal shape:

- application-layer contract
- infrastructure adapter
- HTTP client wrapper
- authenticator
- retry policy
- circuit breaker
- webhook verifier for inbound traffic
- mapper between external payloads and internal DTOs

Controllers and domain objects must never call third-party clients directly.

## Payment Integrations

### Payme

- create payments
- query payment status
- receive and verify callbacks
- reconcile status drift
- support refunds where provider behavior allows

### Click

- create payments
- query payment status
- receive and verify callbacks
- reconcile status drift
- support refunds where provider behavior allows

### Uzum

- create payments
- query payment status
- receive and verify callbacks
- reconcile status drift
- support refunds where provider behavior allows

## Identity Integrations

### Google OAuth

- redirect and callback login flow
- account linking rules
- secure token storage if refresh tokens are retained

### MyID

- optional identity verification
- treat as an external verification provider, not as an internal identity store
- initiation is tenant-scoped through `POST /integrations/myid:verify`
- initiation requires the tenant integration to be enabled, managed credentials to be configured, and at least one active secret-managed webhook registration
- verification sessions are local-first in this phase and complete only through the replay-safe public webhook `POST /webhooks/myid`
- verification session states are `pending`, `verified`, `rejected`, `expired`, and `failed`

### E-IMZO

- optional e-signature workflow
- all signing state must be auditable
- initiation is tenant-scoped through `POST /integrations/eimzo:sign`
- initiation requires the tenant integration to be enabled, managed credentials to be configured, and at least one active secret-managed webhook registration
- sign requests are local-first in this phase and complete only through the replay-safe public webhook `POST /webhooks/eimzo`
- sign request states are `pending`, `signed`, `canceled`, `expired`, and `failed`

## Messaging Integrations

### SMS Providers

- Eskiz
- Play Mobile
- TextUp

SMS delivery uses a strategy interface with tenant-specific routing and failover ordering. Routing decisions may vary by message type, such as OTP, reminder, or bulk announcement.
- The supported routing message types are `otp`, `reminder`, `transactional`, and `bulk`.
- Tenant routing policies store ordered provider priorities per message type and fall back to a documented default route when no tenant override exists.
- Diagnostic sends must reuse the same routing engine as queued notification delivery so provider-specific endpoints and queue consumers do not drift.

### Telegram

- patient reminders
- tenant broadcasts
- support channel workflows
- webhook handling and bot synchronization

### Email

- transactional delivery
- versioned templates
- event tracking for delivery outcomes
- tenant-scoped sender settings with explicit enable and reply-to fields
- queue-consumer delivery for template-backed notification rows
- direct authenticated send flow for operational emails without creating notification rows
- append-only email-event history for `direct` and `notification` sources

## Shared Requirements for Every Integration

- tenant-aware credential storage
- encryption at rest for tokens and secrets
- structured logs with correlation IDs
- health check endpoint
- test connection endpoint
- webhook secret rotation support where applicable
- timeout, retry, and failure classification policy
- explicit mapping between provider errors and the platform error catalog

## Integrations Hub

The shared integrations hub is the tenant-scoped administrative surface for:

- catalog-backed integration registry
- encrypted credential inventory
- readiness and test-connection checks
- append-only integration operation logs
- managed webhook inventory and secret rotation
- managed token inventory and refresh lifecycle

The hub owns the administrative inventory, while provider-specific modules keep their existing runtime API contracts.

## Optional Uzbekistan-Specific Extensions

- local identity aggregators
- tax or receipt systems
- local geocoding or map providers

Optional integrations must remain plug-in style and must not change the core architecture.
- Optional plug-in webhooks must verify a managed secret header, resolve tenant scope from managed webhook inventory, and deduplicate deliveries by provider-specific replay keys.
