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

### E-IMZO

- optional e-signature workflow
- all signing state must be auditable

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

## Shared Requirements for Every Integration

- tenant-aware credential storage
- encryption at rest for tokens and secrets
- structured logs with correlation IDs
- health check endpoint
- test connection endpoint
- webhook secret rotation support where applicable
- timeout, retry, and failure classification policy
- explicit mapping between provider errors and the platform error catalog

## Optional Uzbekistan-Specific Extensions

- local identity aggregators
- tax or receipt systems
- local geocoding or map providers

Optional integrations must remain plug-in style and must not change the core architecture.
