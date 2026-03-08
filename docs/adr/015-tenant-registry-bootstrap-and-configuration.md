# ADR 015: Tenant Registry, Bootstrap Administration, and Configuration

## Status

Accepted

## Date

2026-03-09

## Context

The canonical route catalog defines tenant CRUD plus lifecycle, limits, usage, and settings endpoints, but it does not define:

- how tenant visibility works before an active tenant context is selected
- how the first administrator gains access to a newly created tenant
- which tenant lifecycle states exist
- which fields belong to tenant settings and tenant limits
- what the usage endpoint reports
- whether tenant suspension immediately revokes administrative access

`T027` requires these decisions before implementation.

## Decision

Use a global tenant registry with actor-visible listing, bootstrap tenant administration on creation, explicit tenant settings and limits records, and a derived usage snapshot.

- `GET /tenants` is an authenticated discovery endpoint. It returns only tenants where the authenticated actor has a tenant membership.
- `POST /tenants` is an authenticated bootstrap endpoint. It does not require an active tenant context or `tenants.manage`.
- Creating a tenant provisions:
  - the tenant registry record
  - default tenant settings
  - default tenant limits
  - an active tenant membership for the creator
  - a bootstrap `Tenant Administrator` role in the new tenant
  - assignment of that bootstrap role to the creator
- The bootstrap `Tenant Administrator` role receives every permission from the static permission catalog.
- Tenant-specific routes such as `GET /tenants/{tenantId}` and `/tenants/{tenantId}/settings` remain tenant-scoped routes. They accept tenant context from the `{tenantId}` route parameter and may also receive `X-Tenant-Id`. Mismatches fail closed with the existing tenant-scope rules.
- Tenant lifecycle states are `active` and `suspended`.
- `POST /tenants/{tenantId}:suspend` transitions only from `active` to `suspended`.
- `POST /tenants/{tenantId}:activate` transitions only from `suspended` to `active`.
- `DELETE /tenants/{tenantId}` is allowed only while the tenant is `suspended`.
- The first tenant iteration does not automatically strip administrative access when a tenant is suspended. Suspension is an explicit lifecycle state for visibility, governance, and future runtime gating, while tenant administration remains available so an authorized actor can inspect and reactivate the tenant.
- Tenant settings are a dedicated configuration document with the fields `locale`, `timezone`, and `currency`.
- Tenant limits are a dedicated configuration document with the fields `users`, `clinics`, `providers`, `patients`, `storage_gb`, and `monthly_notifications`.
- Limit fields are nullable integers or numbers. `null` means unlimited.
- Tenant usage is a derived read model that reports `used`, `limit`, and `remaining` for each documented limit key. For resources not yet implemented, usage reports `0` until their owning module is delivered.
- Tenant mutations, settings replacement, limits replacement, lifecycle transitions, and deletion all write audit records.

## Alternatives Considered

- require an active tenant and `tenants.manage` permission for tenant creation
- introduce a global super-admin role outside the existing tenant-scoped RBAC model
- leave tenant settings and limits as untyped JSON without an explicit field contract
- make suspension immediately remove effective permissions for the tenant

## Consequences

- Authenticated users can discover their tenant memberships and bootstrap new tenants without inventing a separate platform-admin model.
- Newly created tenants are immediately manageable by the creator through the existing RBAC infrastructure.
- Tenant settings, limits, and usage now have a stable first-pass contract that later modules can extend deliberately.
- Suspension becomes observable immediately without blocking recovery flows for the same tenant administration surface.
- Future modules that want tenant-status enforcement on non-administrative workflows must document and implement that gating explicitly.

## Migration Plan

- add tenant registry, tenant settings, and tenant limits persistence
- implement tenant list and create bootstrap flows
- implement tenant detail, update, delete, activate, suspend, settings, limits, and usage endpoints
- document the tenant bootstrap and configuration contract in the canonical source, split docs, OpenAPI, and tests
