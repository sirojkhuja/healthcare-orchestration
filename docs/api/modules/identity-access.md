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
