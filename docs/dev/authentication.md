# Authentication

This document defines the MedFlow API authentication contract.

## Token Model

- API authentication uses short-lived JWT access tokens and persisted refresh-backed auth sessions.
- Managed API keys are separate machine credentials presented through `X-API-Key`.
- Access tokens are bearer tokens signed with `HS256`.
- Refresh tokens are opaque random secrets and are stored only as SHA-256 hashes in `auth_sessions`.
- API keys use the plaintext format `mfk_<api-key-uuid>.<random-secret>`.
- JWTs carry `sub`, `sid`, and `jti` claims for user, session, and access-token identity.
- MFA uses authenticator-app TOTP secrets plus one-time recovery codes.
- MFA-enabled logins create a short-lived challenge instead of issuing tokens immediately.

## Session Rules

- Every successful login creates one `auth_sessions` record.
- API key creation and use do not create `auth_sessions`.
- Every refresh rotates both the refresh token hash and the JWT `jti`.
- Old access tokens become invalid immediately after refresh because `jti` must match the current session record.
- Logout revokes the current auth session and invalidates all access tokens bound to that session.
- Password reset completion revokes every non-revoked auth session owned by the user.
- Session listing returns all sessions for the authenticated user and must place the current session first.
- Session revocation endpoints may revoke only sessions owned by the authenticated user.

## Endpoint Contract

- `POST /api/v1/auth/login` accepts email and password, then returns user, session, and token payloads.
- MFA-enabled logins return `401 MFA_REQUIRED` with a `challenge_id` and `expires_at` instead of a token pair.
- `POST /api/v1/auth/password/forgot` accepts an email address and always returns `202 Accepted` with `password_reset_requested`.
- `POST /api/v1/auth/password/reset` accepts email, reset token, password, and password confirmation, then revokes all active sessions after a successful reset.
- `POST /api/v1/auth/mfa/setup` requires a valid bearer token and returns a TOTP secret, an `otpauth://` URI, and one-time recovery codes with `mfa_setup_pending`.
- `POST /api/v1/auth/mfa/verify` accepts either an authenticated setup-confirmation code or an MFA login challenge plus a TOTP or recovery code.
- Setup verification returns `200` with `mfa_enabled`.
- Login-challenge verification returns the same `user`, `session`, and `tokens` payload shape as a normal login.
- `POST /api/v1/auth/mfa/disable` requires a valid bearer token and a valid TOTP or recovery code before disabling MFA.
- `POST /api/v1/auth/refresh` accepts a refresh token and returns a rotated access and refresh token pair.
- `POST /api/v1/auth/logout` requires a valid bearer token and revokes the current session.
- `GET /api/v1/auth/me` requires a valid bearer token and returns the authenticated user plus current session id.
- `POST /api/v1/auth/sessions` requires a valid bearer token and returns the authenticated user's auth sessions.
- `DELETE /api/v1/auth/sessions/{sessionId}` requires a valid bearer token and revokes one owned session.
- `POST /api/v1/auth/api-keys` requires a valid bearer token, accepts a name plus optional `expires_at`, and returns the plaintext API key exactly once.
- `GET /api/v1/auth/api-keys` requires a valid bearer token and lists API keys owned by the authenticated user without returning plaintext key material.
- `DELETE /api/v1/auth/api-keys/{keyId}` requires a valid bearer token and revokes one owned API key.
- `GET /api/v1/devices` requires a valid bearer token and lists devices registered by the authenticated user.
- `POST /api/v1/devices` requires a valid bearer token and upserts a registered device by `installation_id`.
- `DELETE /api/v1/devices/{deviceId}` requires a valid bearer token and deletes one owned registered device.
- `GET /api/v1/security/ip-allowlist` requires a valid bearer token plus tenant context and returns the active tenant CIDR allowlist.
- `POST /api/v1/security/ip-allowlist` requires a valid bearer token plus tenant context and replaces the active tenant CIDR allowlist.
- `POST /api/v1/security/sessions:revoke-all` requires a valid bearer token and revokes every active session owned by the current user, including the current one.

## Guard Rules

- Protected API routes use the `api` guard backed by the shared JWT request authenticator.
- Machine-to-machine routes may use the dedicated `api-key` guard backed by the `X-API-Key` header.
- Guard resolution must re-check the bearer token on every request.
- API-key-authenticated routes must not assume the presence of an `auth_session` id.
- Request handling must fail with `401` and `UNAUTHENTICATED` when the JWT is invalid, expired, revoked, or mismatched with the active session row.
- Request handling must fail with `401` and `API_KEY_REVOKED` when a presented API key matches a revoked key record.

## Security Rules

- `AUTH_JWT_SECRET` may override the signing key; otherwise JWT signing falls back to `APP_KEY`.
- JWT issuer and audience are validated on decode.
- Refresh tokens must never be logged, stored in plaintext, or returned after logout.
- API keys must never be logged, must be returned only once at creation, and must be stored only as SHA-256 hashes plus non-secret display metadata.
- MFA secrets must be encrypted at rest.
- Recovery codes must be returned only once and persisted only as hashes.
- Tenant IP allowlists are expressed as CIDR entries and apply to API-key-authenticated requests when the request carries tenant scope.
- MFA setup may be restarted while still pending, but setup must fail closed once MFA is already enabled.
- Password reset requests must not disclose whether the submitted email exists.
- Password reset tokens are handled through Laravel's password broker and must never be logged or returned in API responses.
- Future MFA, API key, and Google OAuth work must preserve the same audit and session guarantees.

## Audit Rules

- Login writes `auth.login`.
- MFA setup start writes `auth.mfa.setup_started`.
- MFA challenge creation writes `auth.mfa.challenge_required`.
- MFA enablement writes `auth.mfa.enabled`.
- MFA disablement writes `auth.mfa.disabled`.
- Password reset request writes `auth.password_reset_requested`.
- Password reset completion writes `auth.password_reset_completed`.
- Refresh writes `auth.refresh`.
- Logout writes `auth.logout`.
- Single-session revocation writes `auth.session_revoked`.
- Revoke-all writes `auth.sessions_revoked_all`.
- API key creation writes `auth.api_key_created`.
- API key revocation writes `auth.api_key_revoked`.
- Device registration writes `auth.device_registered`.
- Device deregistration writes `auth.device_deregistered`.
- Tenant IP allowlist replacement writes `security.ip_allowlist_updated`.
- Login, refresh, logout, and single-session revocation use `auth_session` as the object type and the auth session id as the object id.
- MFA challenge creation uses `mfa_challenge` as the object type and the MFA challenge id as the object id.
- MFA enablement and disablement use `mfa_credential` as the object type and the MFA credential id as the object id.
- API key creation and revocation use `api_key` as the object type and the API key id as the object id.
- Device registration and deregistration use `device` as the object type and the device id as the object id.
- Password reset request uses `password_reset_request` as the object type and a hashed lowercase email as the object id.
- Password reset completion and revoke-all use `user` as the object type and the user id as the object id.
- Tenant IP allowlist replacement uses `tenant` as the object type and the tenant id as the object id.

## Security Event Rules

- MFA setup start writes `mfa.setup_started`.
- MFA challenge creation writes `mfa.challenge_required`.
- MFA challenge success writes `mfa.challenge_verified`.
- MFA challenge failure writes `mfa.challenge_failed`.
- Recovery code consumption writes `mfa.recovery_code_used`.
- MFA disablement writes `mfa.disabled`.

## Testing Requirements

- Feature tests must cover valid login, invalid credentials, MFA challenge responses, MFA setup, MFA verification with TOTP and recovery codes, MFA disable, `me`, refresh rotation, logout revocation, password reset request, password reset completion, and auth-session administration.
- Feature tests must cover API key creation, listing, revocation, revoked-key rejection, tenant IP allowlist enforcement for API-key-authenticated requests, and device registration, listing, update, and deregistration.
- Tests must prove old access tokens stop working after refresh.
- Tests must prove revoked sessions reject subsequent bearer token use.
- Tests must prove password reset requests do not leak whether a user exists.
- Tests must prove users cannot revoke sessions owned by another account.
