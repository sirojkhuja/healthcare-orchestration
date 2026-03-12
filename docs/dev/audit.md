# Audit Foundation

This document defines the shared audit foundation used across MedFlow modules.

## Core Rules

- Audit events are immutable and write-once.
- Audit records must capture actor, request metadata, object identity, action, and before/after state when applicable.
- Audit persistence must not depend on controllers or transport code.
- Modules emit audit events through the audit writer service instead of writing audit tables directly.

## Required Fields

- `event_id`
- `tenant_id` when the audited object is tenant-owned
- `action`
- `object_type`
- `object_id`
- `actor_type`
- `actor_id` when a specific authenticated actor exists
- `actor_name` when available
- `request_id`
- `correlation_id`
- `before`
- `after`
- `metadata`
- `occurred_at`

## Actor Resolution

- Authenticated users resolve to actor type `user`.
- Unauthenticated internal execution resolves to actor type `service`.
- The audit writer enriches caller input with actor, tenant, request, and correlation metadata automatically.

## Retention Hooks

- Retention is configured by `AUDIT_RETENTION_DAYS`.
- `0` means pruning is disabled until an explicit retention period is configured.
- Tenant-scoped audit APIs may override the platform default through a tenant retention record.
- Tenant overrides take precedence during pruning. A tenant override of `0` disables pruning for that tenant only.
- `audit:prune` is the operational hook for deleting expired audit records.

## Query and Export Rules

- Generic audit reads are tenant-scoped in this phase.
- `GET /audit/events` supports free-text, actor, object, action-prefix, and time-range filters.
- `GET /audit/object/{objectType}/{objectId}` is the generic immutable object-history view.
- `GET /audit/export` supports only CSV export and writes audit action `audit.exported`.

## Testing Requirements

- Tests must prove audit records persist before and after values.
- Tests must prove immutable records cannot be updated or deleted through normal model operations.
- Tests must cover retention pruning behavior.
- Tests for generic audit APIs must prove tenant isolation, object-history reads, export behavior, and tenant override retention behavior.
