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
- `audit:prune` is the operational hook for deleting expired audit records.

## Testing Requirements

- Tests must prove audit records persist before and after values.
- Tests must prove immutable records cannot be updated or deleted through normal model operations.
- Tests must cover retention pruning behavior.
