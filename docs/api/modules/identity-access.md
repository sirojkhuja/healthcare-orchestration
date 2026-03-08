# Identity and Access API

## Auth and Identity

- `POST /auth/login` -> `LoginCommand` -> IAM
- `POST /auth/logout` -> `LogoutCommand` -> IAM
- `POST /auth/refresh` -> `RefreshTokenCommand` -> IAM
- `GET /auth/me` -> `GetMeQuery` -> IAM
- `POST /auth/password/forgot` -> `RequestPasswordResetCommand` -> IAM
- `POST /auth/password/reset` -> `ResetPasswordCommand` -> IAM
- `POST /auth/mfa/setup` -> `SetupMfaCommand` -> IAM
- `POST /auth/mfa/verify` -> `VerifyMfaCommand` -> IAM
- `POST /auth/mfa/disable` -> `DisableMfaCommand` -> IAM
- `GET /auth/google/redirect` -> `GoogleRedirectQuery` -> IAM
- `GET /auth/google/callback` -> `GoogleCallbackCommand` -> IAM
- `POST /auth/sessions` -> `ListSessionsQuery` -> IAM
- `DELETE /auth/sessions/{sessionId}` -> `RevokeSessionCommand` -> IAM
- `POST /auth/api-keys` -> `CreateApiKeyCommand` -> IAM
- `GET /auth/api-keys` -> `ListApiKeysQuery` -> IAM
- `DELETE /auth/api-keys/{keyId}` -> `RevokeApiKeyCommand` -> IAM

## Users, Roles, Permissions, and Security

- `GET /users` -> `ListUsersQuery` -> IAM
- `POST /users` -> `CreateUserCommand` -> IAM
- `GET /users/{userId}` -> `GetUserQuery` -> IAM
- `PATCH /users/{userId}` -> `UpdateUserCommand` -> IAM
- `DELETE /users/{userId}` -> `DeleteUserCommand` -> IAM
- `POST /users/{userId}:activate` -> `ActivateUserCommand` -> IAM
- `POST /users/{userId}:deactivate` -> `DeactivateUserCommand` -> IAM
- `POST /users/{userId}:lock` -> `LockUserCommand` -> IAM
- `POST /users/{userId}:unlock` -> `UnlockUserCommand` -> IAM
- `POST /users/{userId}:reset-password` -> `AdminResetPasswordCommand` -> IAM
- `GET /users/{userId}/roles` -> `ListUserRolesQuery` -> IAM
- `PUT /users/{userId}/roles` -> `SetUserRolesCommand` -> IAM
- `GET /users/{userId}/permissions` -> `GetUserPermissionsQuery` -> IAM
- `POST /users:bulk-import` -> `BulkImportUsersCommand` -> IAM
- `POST /users/bulk` -> `BulkUpdateUsersCommand` -> IAM
- `GET /roles` -> `ListRolesQuery` -> IAM
- `POST /roles` -> `CreateRoleCommand` -> IAM
- `GET /roles/{roleId}` -> `GetRoleQuery` -> IAM
- `PATCH /roles/{roleId}` -> `UpdateRoleCommand` -> IAM
- `DELETE /roles/{roleId}` -> `DeleteRoleCommand` -> IAM
- `GET /roles/{roleId}/permissions` -> `ListRolePermissionsQuery` -> IAM
- `PUT /roles/{roleId}/permissions` -> `SetRolePermissionsCommand` -> IAM
- `GET /permissions` -> `ListPermissionsQuery` -> IAM
- `GET /permissions/groups` -> `ListPermissionGroupsQuery` -> IAM
- `GET /rbac/audit` -> `GetRbacAuditQuery` -> IAM
- `GET /profiles/me` -> `GetMyProfileQuery` -> IAM
- `PATCH /profiles/me` -> `UpdateMyProfileCommand` -> IAM
- `POST /profiles/me/avatar` -> `UploadMyAvatarCommand` -> IAM
- `GET /profiles/{userId}` -> `GetProfileQuery` -> IAM
- `PATCH /profiles/{userId}` -> `UpdateProfileCommand` -> IAM
- `GET /devices` -> `ListDevicesQuery` -> IAM
- `POST /devices` -> `RegisterDeviceCommand` -> IAM
- `DELETE /devices/{deviceId}` -> `DeregisterDeviceCommand` -> IAM
- `GET /security/events` -> `ListSecurityEventsQuery` -> IAM
- `GET /security/events/{eventId}` -> `GetSecurityEventQuery` -> IAM
- `POST /security/ip-allowlist` -> `UpdateIpAllowlistCommand` -> IAM
- `GET /security/ip-allowlist` -> `GetIpAllowlistQuery` -> IAM
- `POST /security/sessions:revoke-all` -> `RevokeAllSessionsCommand` -> IAM

## API Notes

- MFA, sessions, API keys, and security events are part of the IAM boundary.
- Tenant awareness still applies to IAM resources except for true platform-wide administration.
- All auth and security endpoints must emit audit records.
- Protected tenant-owned routes use the shared `permission:<permission-name>` middleware contract.
- Permission changes must invalidate cached permission projections for the affected user and tenant scope.
- RBAC roles are tenant-scoped custom records and user-role assignments are tenant-scoped.
- Permission definitions and permission groups are fixed catalog data exposed through read-only endpoints.
- RBAC read endpoints require `rbac.view`; RBAC mutation endpoints require `rbac.manage`.
- Login and refresh responses return `user`, `session`, and `tokens` payloads.
- API key creation returns plaintext key material exactly once and list responses return only non-secret metadata.
- API keys authenticate through the `X-API-Key` header on routes that opt into the dedicated API-key guard.
- Revoked API keys fail with `401 API_KEY_REVOKED`.
- MFA setup returns `mfa_setup_pending` plus the TOTP secret, the `otpauth://` URI, and recovery codes exactly once.
- MFA setup verification returns `mfa_enabled`, while MFA login-challenge verification returns the normal auth session payload.
- MFA-enabled password logins fail with `401 MFA_REQUIRED` until `/auth/mfa/verify` completes the login challenge.
- Password reset request responses are intentionally uniform and always acknowledge the request with `202 Accepted`.
- Successful password resets revoke all active sessions owned by the user before new logins occur.
- `GET /auth/me` returns the current user plus the active auth session id.
- `POST /auth/sessions` returns all sessions for the authenticated user with the current session first.
- `DELETE /auth/sessions/{sessionId}` may revoke only an auth session owned by the authenticated user.
- `POST /devices` upserts a user-owned device by `installation_id` and refreshes metadata for the existing installation when present.
- `GET /security/ip-allowlist` and `POST /security/ip-allowlist` are tenant-scoped security endpoints and require tenant context.
- Tenant IP allowlists use CIDR entries and only constrain API-key-authenticated traffic in the active tenant scope.
- `POST /security/sessions:revoke-all` revokes every active session for the authenticated user, including the current session.
- MFA setup, challenge, recovery-code use, and disable all write dedicated security events in addition to audit records.
- Refresh rotates both the JWT `jti` and the opaque refresh token.
