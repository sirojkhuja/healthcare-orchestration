# Authentication

This document defines the MedFlow API authentication contract.

## Token Model

- API authentication uses short-lived JWT access tokens and persisted refresh-backed auth sessions.
- Access tokens are bearer tokens signed with `HS256`.
- Refresh tokens are opaque random secrets and are stored only as SHA-256 hashes in `auth_sessions`.
- JWTs carry `sub`, `sid`, and `jti` claims for user, session, and access-token identity.

## Session Rules

- Every successful login creates one `auth_sessions` record.
- Every refresh rotates both the refresh token hash and the JWT `jti`.
- Old access tokens become invalid immediately after refresh because `jti` must match the current session record.
- Logout revokes the current auth session and invalidates all access tokens bound to that session.
- Password reset completion revokes every non-revoked auth session owned by the user.
- Session listing returns all sessions for the authenticated user and must place the current session first.
- Session revocation endpoints may revoke only sessions owned by the authenticated user.

## Endpoint Contract

- `POST /api/v1/auth/login` accepts email and password, then returns user, session, and token payloads.
- `POST /api/v1/auth/password/forgot` accepts an email address and always returns `202 Accepted` with `password_reset_requested`.
- `POST /api/v1/auth/password/reset` accepts email, reset token, password, and password confirmation, then revokes all active sessions after a successful reset.
- `POST /api/v1/auth/refresh` accepts a refresh token and returns a rotated access and refresh token pair.
- `POST /api/v1/auth/logout` requires a valid bearer token and revokes the current session.
- `GET /api/v1/auth/me` requires a valid bearer token and returns the authenticated user plus current session id.
- `POST /api/v1/auth/sessions` requires a valid bearer token and returns the authenticated user's auth sessions.
- `DELETE /api/v1/auth/sessions/{sessionId}` requires a valid bearer token and revokes one owned session.
- `POST /api/v1/security/sessions:revoke-all` requires a valid bearer token and revokes every active session owned by the current user, including the current one.

## Guard Rules

- Protected API routes use the `api` guard backed by the shared JWT request authenticator.
- Guard resolution must re-check the bearer token on every request.
- Request handling must fail with `401` and `UNAUTHENTICATED` when the JWT is invalid, expired, revoked, or mismatched with the active session row.

## Security Rules

- `AUTH_JWT_SECRET` may override the signing key; otherwise JWT signing falls back to `APP_KEY`.
- JWT issuer and audience are validated on decode.
- Refresh tokens must never be logged, stored in plaintext, or returned after logout.
- Password reset requests must not disclose whether the submitted email exists.
- Password reset tokens are handled through Laravel's password broker and must never be logged or returned in API responses.
- Future MFA, API key, and Google OAuth work must preserve the same audit and session guarantees.

## Audit Rules

- Login writes `auth.login`.
- Password reset request writes `auth.password_reset_requested`.
- Password reset completion writes `auth.password_reset_completed`.
- Refresh writes `auth.refresh`.
- Logout writes `auth.logout`.
- Single-session revocation writes `auth.session_revoked`.
- Revoke-all writes `auth.sessions_revoked_all`.
- Login, refresh, logout, and single-session revocation use `auth_session` as the object type and the auth session id as the object id.
- Password reset request uses `password_reset_request` as the object type and a hashed lowercase email as the object id.
- Password reset completion and revoke-all use `user` as the object type and the user id as the object id.

## Testing Requirements

- Feature tests must cover valid login, invalid credentials, `me`, refresh rotation, logout revocation, password reset request, password reset completion, and auth-session administration.
- Tests must prove old access tokens stop working after refresh.
- Tests must prove revoked sessions reject subsequent bearer token use.
- Tests must prove password reset requests do not leak whether a user exists.
- Tests must prove users cannot revoke sessions owned by another account.
