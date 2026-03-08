# ADR 012: Tenant-Scoped RBAC and Static Permission Catalog

## Status

Accepted

## Date

2026-03-09

## Context

The canonical route catalog defines RBAC administration endpoints for roles, permissions, permission groups, user-role assignment, and RBAC audit history, but it does not define the storage model for roles, how permissions are authored, or how permission projection invalidation should work once administrators change RBAC state. `T024` requires those decisions before implementation.

## Decision

Use tenant-scoped custom roles, tenant-scoped user-role assignments, and a static permission catalog defined in configuration.

- Roles are tenant-owned records. Role CRUD operates inside the active tenant scope.
- User-role assignments are tenant-scoped. The same user may hold different roles in different tenants.
- Permissions and permission groups are fixed reference data from configuration, not tenant-editable database rows.
- Effective permissions are the union of catalog permissions assigned to the roles held by a user inside the active tenant.
- `GET /permissions` returns the flat catalog. `GET /permissions/groups` returns the catalog grouped by group key.
- `PUT /roles/{roleId}/permissions` replaces the full permission set attached to one role.
- `PUT /users/{userId}/roles` replaces the full role set assigned to one user in the active tenant.
- Deleting a role is rejected with a conflict while the role is still assigned to one or more users.
- RBAC administration routes require tenant context and the existing permission middleware contract. Read routes require `rbac.view`; mutating routes require `rbac.manage`.
- Every RBAC mutation writes audit records and invalidates cached permission projections for all impacted users in the affected tenant scope.
- `GET /rbac/audit` returns recent tenant-scoped audit events whose action prefix is `rbac.`.

## Alternatives Considered

- store permissions and permission groups as tenant-editable database records
- make roles global instead of tenant-scoped
- store direct per-user permissions in addition to role-based permissions during the first RBAC iteration
- allow deleting roles by cascading away existing user assignments

## Consequences

- RBAC behavior stays deterministic and permission names remain stable for middleware and future OpenAPI contracts.
- Tenant isolation is preserved for both role definitions and effective permission projections.
- Permission catalog changes remain code-reviewed configuration changes rather than runtime admin mutations.
- Administrative bootstrap still depends on creating at least one tenant-scoped actor with `rbac.manage` through fixtures, seeds, or future tenant/user lifecycle flows.

## Migration Plan

- add tenant-scoped role and user-role-assignment persistence plus role-permission join storage
- replace the null permission projection repository with a database-backed implementation
- expose RBAC administration, permission catalog, and RBAC audit endpoints
- update authorization, testing, API, and OpenAPI documentation to reflect the tenant-scoped RBAC model
