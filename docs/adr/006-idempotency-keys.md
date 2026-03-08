# ADR 006: Shared Idempotency Keys

## Status

Accepted

## Date

2026-03-08

## Context

The canonical source requires idempotency for payment creation, appointment scheduling, and webhook processing, but it does not define the exact request header contract, scope boundaries, or replay behavior.

## Decision

Use a shared database-backed idempotency store with the `Idempotency-Key` request header and the `idempotency:<operation>` middleware contract. Scope uniqueness by operation, tenant, and authenticated actor when present. Persist completed non-5xx responses and replay them on duplicate requests with the same fingerprint. Reject duplicate in-flight requests and key reuse with different payloads using `409 IDEMPOTENCY_REPLAY`.

## Alternatives Considered

- Redis-only storage before the shared cache layer exists
- stateless request fingerprinting with no persisted replay records
- per-module ad hoc idempotency implementations

## Consequences

- Duplicate protected commands are prevented across app instances.
- Replay behavior stays consistent for payments, scheduling, and webhook handlers.
- The platform must maintain idempotency record expiry and storage hygiene.

## Migration Plan

- add shared idempotency storage and middleware
- document header, scope, and replay behavior
- update future payment, scheduling, and webhook endpoints to use the shared middleware
