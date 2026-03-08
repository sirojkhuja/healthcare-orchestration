# Tenancy

This document operationalizes the tenancy rules defined in the canonical SSoT.

## Resolution Contract

- Tenant-owned HTTP requests resolve tenant context from the `X-Tenant-Id` header by default.
- If the header is absent, the request may resolve tenant context from a route parameter such as `{tenantId}` or `{tenant}`.
- If both a header and route parameter are present, they must match exactly.
- Tenant identifiers are UUID strings.

## Middleware Contract

- `ResolveTenantContext` runs for every API request.
- `tenant.require` must be applied to every tenant-owned endpoint.
- Missing tenant context returns `400`.
- Conflicting route and header tenant context returns `403`.

## Infrastructure Scoping Rules

- Tenant scoping stays in infrastructure, never in domain entities.
- Tenant-owned Eloquent models implement `TenantScopedModel`.
- Tenant-owned Eloquent models use the `BelongsToTenant` trait.
- The tenant global scope filters reads by `tenant_id`.
- Tenant-owned writes inherit `tenant_id` from the active tenant context when the attribute is absent.
- Tenant-owned writes must not override the active tenant context with a different `tenant_id`.

## Implementation Notes

- The request-scoped tenant service is `TenantContext`.
- The resolved tenant identifier is stored on the request attributes as `tenant_id`.
- The resolution source is stored as `tenant_context_source`.
- Background jobs and console flows that operate across tenants must set or bypass tenant scope explicitly and document that behavior.

## Testing Requirements

- Feature tests must prove that one tenant cannot read another tenant's records.
- Feature tests must cover missing tenant context and conflicting tenant context.
- Unit or integration tests must cover tenant-owned write behavior.
