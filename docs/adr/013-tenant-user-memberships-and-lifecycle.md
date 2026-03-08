# ADR 013: Tenant User Memberships and User Lifecycle

## Status

Accepted

## Date

2026-03-09

## Context

The canonical route catalog defines user CRUD, explicit lifecycle transitions, bulk import, and bulk update endpoints, but it does not define whether users are tenant-owned or global, how tenant onboarding relates to shared identity accounts, or how lifecycle status should affect permission evaluation across tenants. `T025` requires those decisions before implementation.

## Decision

Use global identity accounts plus tenant-scoped user memberships.

- The `users` table stores global identity data such as `name`, `email`, and password material.
- A new tenant-owned `tenant_user_memberships` table links one user account to one tenant and stores the tenant-scoped lifecycle status.
- Membership statuses are `active`, `inactive`, and `locked`.
- `activate` transitions only from `inactive` to `active`.
- `deactivate` transitions only from `active` to `inactive`.
- `lock` transitions only from `active` to `locked`.
- `unlock` transitions only from `locked` to `active`.
- `DELETE /users/{userId}` removes the user membership from the active tenant and deletes tenant-scoped role assignments for that user in that tenant. It does not delete the shared global user account.
- `POST /users` and `POST /users:bulk-import` create a new global user account when the email does not already exist. When the email already exists, they attach the existing account to the active tenant instead of creating a duplicate.
- Attaching an existing global account through `POST /users` or `POST /users:bulk-import` does not overwrite shared identity fields. Cross-tenant identity changes must go through `PATCH /users/{userId}` explicitly.
- Password input is required only when a new global account must be created. Existing-account attachment keeps the current password unchanged.
- `PATCH /users/{userId}` updates shared global identity fields. Those changes become visible across every tenant membership for that user.
- `POST /users/{userId}:reset-password` sets a new password for the shared account and revokes all active sessions for that user.
- Permission evaluation requires an `active` tenant membership. `inactive`, `locked`, or missing memberships yield an empty effective permission set in that tenant.
- RBAC user-role assignment, permission reads, and user lifecycle endpoints require that the target user has a membership in the active tenant.
- `POST /users/bulk` applies one tenant-scoped action across a validated set of user memberships in a single all-or-nothing request. Supported actions are `activate`, `deactivate`, `lock`, `unlock`, and `delete`.

## Alternatives Considered

- make user records tenant-owned and require tenant context at login
- keep users global and let tenant admins change global lifecycle state directly
- model `locked` as a separate boolean instead of an explicit lifecycle state
- treat `DELETE /users/{userId}` as global account deletion

## Consequences

- The same authenticated user may belong to multiple tenants while holding different roles and different lifecycle states per tenant.
- Tenant-scoped suspension and lock behavior can revoke access in one tenant without breaking other tenant memberships.
- Shared identity fields remain global, so tenant-scoped user updates must be audited clearly because they affect every tenant membership for that user.
- Tenant membership deletion must also clear tenant-scoped role assignments and invalidate cached permission projections in that tenant.

## Migration Plan

- add tenant-scoped user membership persistence and indexes
- require active tenant membership in permission projection resolution
- expose tenant-scoped user lifecycle and bulk administration endpoints
- update user, authorization, testing, and OpenAPI documentation to reflect tenant membership semantics
