# ADR 011: API Keys, Registered Devices, and Tenant IP Allowlists

## Status

Accepted

## Date

2026-03-09

## Context

The canonical route inventory defines API key, device, and IP allowlist endpoints, but it does not choose the credential format, the authentication header, the device registration model, or how tenant IP allowlists should be enforced. `T023` requires those behaviors to be implemented without inventing undocumented runtime rules in code.

## Decision

Use user-owned managed API keys, user-owned registered devices, and tenant-scoped CIDR allowlists.

- `POST /auth/api-keys` creates a user-owned API key from an authenticated bearer session and returns the plaintext key once.
- Plaintext API keys use the format `mfk_<api-key-uuid>.<random-secret>` and are presented through the `X-API-Key` header.
- API keys are stored only as SHA-256 hashes plus a display prefix, last-used timestamp, expiry timestamp, and revocation timestamp.
- A valid but revoked API key must fail with `401 API_KEY_REVOKED`. Missing, malformed, unknown, or expired API keys continue to fail with the generic unauthenticated response.
- API key authentication uses a dedicated `api-key` guard and does not create `auth_sessions`. Session-oriented endpoints continue to require bearer-session authentication.
- `POST /devices` registers or updates a user-owned device by `installation_id`, allowing the same installation to refresh its metadata without creating duplicates.
- Registered devices store human-readable name, platform, optional push token, optional app version, and last-seen request metadata.
- `POST /security/ip-allowlist` replaces the full active allowlist for the current tenant using CIDR entries. `GET /security/ip-allowlist` returns the active list for the current tenant.
- Tenant IP allowlists are enforced on API-key-authenticated requests when a tenant context is present. If a tenant has one or more allowlist entries, the request IP must match at least one entry or fail with `403 IP_ADDRESS_NOT_ALLOWED`.
- API key creation and revocation, device registration and deregistration, and IP allowlist replacement all write audit records.

## Alternatives Considered

- reuse JWT bearer tokens for machine-to-machine access instead of managed API keys
- authenticate API keys through the `Authorization` bearer header
- make device registration append-only instead of upsert by installation
- enforce tenant IP allowlists for every bearer-session request

## Consequences

- Machine credentials now have a clear lifecycle and can be revoked without touching interactive auth sessions.
- Tenant IP allowlists protect machine-to-machine traffic without blocking normal user login and session flows.
- Device registration becomes stable for future push and session-notification work without creating duplicate rows for the same installation.
- The repository now has two authenticated API guard types with different guarantees, so handlers must continue to choose the correct context abstraction.

## Migration Plan

- add persistence for API keys, registered devices, and tenant IP allowlist entries
- add the dedicated API key guard and allowlist enforcement
- implement the API key, device, and allowlist endpoints with audit coverage
- document the new auth contract in the canonical source, split docs, error catalog, and OpenAPI
