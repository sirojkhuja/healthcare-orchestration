# ADR 009: JWT API Authentication

## Status

Accepted

## Date

2026-03-08

## Context

The canonical source requires authenticated API access through OAuth2 or JWT, but it does not choose one implementation. The Laravel codebase also did not have an authentication package or token-session model in place for the `/auth/login`, `/auth/logout`, `/auth/refresh`, and `/auth/me` endpoints.

## Decision

Use JWT bearer access tokens with persisted auth sessions and rotating opaque refresh tokens.

- Access tokens are signed with `firebase/php-jwt`.
- Refresh tokens are random opaque secrets stored only as SHA-256 hashes.
- Auth sessions persist in `auth_sessions`.
- JWTs must include `sub`, `sid`, and `jti` claims so the guard can verify both the user and the current active access token for the session.
- Refresh rotates both the refresh token and the JWT `jti`.

## Alternatives Considered

- implement OAuth2 authorization server flows immediately
- use Laravel session cookies for API authentication
- use opaque bearer tokens without JWT claims

## Consequences

- API authentication satisfies the documented OAuth2/JWT requirement with a smaller implementation surface than a full OAuth2 server.
- Logout and refresh invalidation become enforceable through session persistence.
- Future MFA, API keys, and session-management work can build on `auth_sessions` instead of introducing a second auth-session store.
- JWT signing now depends on repository-managed secrets and a pinned third-party JWT library.

## Migration Plan

- add the JWT library dependency
- add auth-session persistence and the JWT request guard
- implement login, logout, refresh, and current-user endpoints
- extend the same session model for password reset, MFA, and session administration tasks
