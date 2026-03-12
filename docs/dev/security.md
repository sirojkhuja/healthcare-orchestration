# Security

## Baseline Rules

- Secrets are never committed.
- Database ports are never exposed from local or deployed compose stacks.
- Redis must use TLS and authentication.
- OAuth and provider tokens must be encrypted at rest.
- Webhook signatures must be verified for every inbound callback.

## Tenant and Access Control

- Tenant isolation is mandatory for every tenant-owned record.
- Tenant context conflicts between headers and route scope must fail closed.
- Missing tenant context on tenant-owned endpoints must fail with a client error instead of returning unscoped data.
- Authorization is policy-driven and role-based.
- Permission caches must be invalidated on relevant changes.
- Protected routes must bind permission checks to both the authenticated actor and active tenant context.
- Payment, scheduling, and webhook mutation routes must require idempotency protection.
- Admin overrides must be explicit, auditable, and narrow in scope.

## Data Protection

- Sensitive PII requires field-level encryption where appropriate.
- Sensitive-field governance is tracked in a tenant-scoped PII registry with explicit classification, encryption profile, key version, and rotation timestamps.
- PII key rotation and re-encryption operations must create append-only compliance reports and audit records.
- Audit data must capture actor, timing, before and after values, and request metadata.
- Audit storage is write-once; normal application flows must not mutate existing audit records.
- Retention policies must be explicit for audit and compliance artifacts.

## Integration Security

- Store credentials per tenant and per integration key.
- Support secret rotation and token refresh without downtime.
- Classify provider errors and log them without leaking secrets.
- Use strict outbound timeouts and bounded retries.

## API Security

- OAuth2 or JWT for authenticated API access
- JWT API access uses short-lived bearer tokens plus persisted rotating refresh sessions
- managed API key access uses hashed one-time-issued credentials presented via `X-API-Key`
- authenticator-app MFA uses encrypted TOTP secrets, hashed recovery codes, and a short-lived challenge before tokens are issued
- MFA challenge, recovery-code use, and disable operations must emit dedicated security events in addition to audit records
- tenant IP allowlists use explicit CIDR entries and are enforced for API-key-authenticated traffic inside tenant scope
- password reset requests must not reveal whether an email exists, and successful resets must revoke all active sessions for that user
- session administration endpoints may operate only on sessions owned by the authenticated actor
- per-tenant and per-IP rate limiting
- idempotency keys for payment creation, appointment scheduling, and webhook processing
- standardized error payloads with trace and correlation IDs

## Development Security Rules

- Never bypass authorization for convenience.
- Never log raw secrets, tokens, or highly sensitive payloads.
- Never add debug routes or hidden admin behavior without documentation and gating.
- Run security checks before merging and before release.
