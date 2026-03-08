# Authorization

This document defines the shared authorization foundation for tenant-aware MedFlow APIs.

## Core Rules

- Authorization is policy-driven and permission-based.
- Tenant-owned endpoints must resolve tenant context before authorization runs.
- Authorization defaults to deny when no permission projection exists.
- Permission checks must stay in the application and infrastructure layers, not in controllers or domain entities.
- Permission evaluation must be deterministic for the same user, tenant, and permission tuple.

## Permission Projection Contract

- The shared authorization service is `PermissionAuthorizer`.
- Permission data loads through `PermissionProjectionRepository`.
- A permission projection is scoped by `user_id` and `tenant_id`.
- Tenant-aware permissions must not reuse global cache entries.
- Missing projections return an empty permission set rather than granting access implicitly.

## Cache Rules

- Permission projections are cached with tenant-prefixed keys.
- Cache invalidation is event-driven.
- The shared invalidation event is `PermissionProjectionInvalidated`.
- Any future role, permission, tenant-admin, or bulk user change that affects effective permissions must dispatch an invalidation event for the impacted user and tenant scope.
- Cache invalidation must be narrower than full-cache flushes unless an ADR explicitly allows broader invalidation.

## HTTP Middleware Contract

- The route middleware alias is `permission:<permission-name>`.
- Permission middleware requires an authenticated actor.
- Permission middleware evaluates the active tenant context together with the authenticated actor identifier.
- Missing authentication returns `401`.
- Authenticated requests without the required permission return `403`.

## Implementation Notes

- `CachedPermissionAuthorizer` is the default shared implementation.
- Cache keys use the shape `permissions:{tenant|global}:{userId}`.
- Permission middleware must run after tenant resolution on tenant-owned routes.
- Permission failures flow through the standard API error envelope and use the `FORBIDDEN` error code.

## Testing Requirements

- Feature tests must prove authorized access succeeds.
- Feature tests must prove cached permission lookups do not re-query the repository until invalidation occurs.
- Feature tests must prove invalidation causes the next permission check to reload the projection.
- Future IAM work must add tests for role assignment, permission group changes, and tenant-admin overrides.
