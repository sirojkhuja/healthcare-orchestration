# Idempotency

This document defines the shared idempotency contract for protected MedFlow commands.

## Core Rules

- Idempotency is mandatory for payment initiation, appointment scheduling, and webhook processing.
- Client-initiated protected routes opt in through the shared `idempotency:<operation>` middleware contract.
- Idempotency requires a client-supplied request key when the caller controls request headers.
- Duplicate requests must never execute the protected command twice within the active retention window.
- Idempotency scope must stay tenant-aware and actor-aware when an authenticated actor exists.

Provider-initiated webhook routes may satisfy the same guarantee through provider-native replay identifiers and delivery stores when the external provider cannot send `Idempotency-Key`.

Current provider-native replay anchors:

- Payme uses provider transaction id plus method.
- Click uses `click_trans_id` plus Shop API stage (`prepare` or `complete`).

## HTTP Contract

- The request header is `Idempotency-Key`.
- The replay marker response header is `X-Idempotent-Replay`.
- Missing or empty idempotency headers fail with `422`.
- Duplicate requests with the same key and identical payload replay the original response body and status.
- Reusing the same key for a different payload fails with `409` and `IDEMPOTENCY_REPLAY`.

## Scope and Fingerprinting

- Uniqueness scope is `operation + tenant_id + actor_id` where available.
- The protected operation name comes from the middleware parameter, for example `idempotency:appointments.schedule`.
- The request fingerprint includes the operation, HTTP method, route signature, query string, and normalized request payload.
- The same idempotency key may be reused safely in a different tenant scope or by a different actor scope.

## Storage and Retention

- Shared idempotency storage is database-backed through `idempotency_requests`.
- Records store request fingerprint, status, replayable response payload, and expiry.
- The default retention window is `24` hours and is configurable through `IDEMPOTENCY_RETENTION_HOURS`.
- Expired records may be replaced by a fresh request that reuses the same key within the same scope.

## Replay Policy

- Completed responses with status codes below `500` are stored and replayable.
- Requests that fail with server errors are not persisted as completed idempotent results.
- Requests that are still processing reject duplicates with `409` and `IDEMPOTENCY_REPLAY`.
- Replay responses must preserve the original application payload and status code while still emitting current request metadata headers.
- Provider-native replay handling must preserve the provider-expected response contract even when the route does not use the shared idempotency middleware.

## Testing Requirements

- Feature tests must prove duplicate protected requests replay the original response without re-execution.
- Feature tests must prove payload mismatches fail closed.
- Tests must prove tenant-aware scope isolation.
- Store-level tests must prove an in-flight request blocks duplicate execution.
