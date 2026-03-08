# Persistence

This document defines the shared PostgreSQL schema conventions for MedFlow.

## Core Rules

- PostgreSQL schema is authoritative and migration-driven.
- Public identifiers use UUIDs.
- Internal numeric identifiers are allowed only when they are never exposed outside internal storage concerns.
- Foreign keys are required when the referenced aggregate already exists.
- Timestamps use timezone-aware columns.

## Shared Schema Primitives

- `SharedSchema::uuidPrimary()` defines UUID primary keys.
- `SharedSchema::tenantColumn()` defines indexed tenant ownership columns.
- `SharedSchema::uuidColumn()` defines reusable indexed UUID columns such as `actor_id`.
- `SharedSchema::requestContextColumns()` defines indexed request and correlation identifier columns.
- `HasUuidPrimaryKey` provides application-side UUID assignment for Eloquent models.

## Indexing Rules

- Every tenant-owned table needs a tenant index at minimum.
- Composite indexes should follow common query filters instead of broad catch-all indexes.
- Partial indexes are preferred for tenant plus lifecycle or date filters when null-heavy datasets would otherwise waste index space.
- Full-text indexes are required later for patient and provider directories.

## Base Table Conventions

- `users.id` is a UUID primary key.
- Session tables reference users through UUID foreign keys.
- Audit and idempotency tables use shared UUID and tenant column helpers.
- PostgreSQL environments enable `pgcrypto` during migrations so the platform is ready for database-side UUID defaults where needed.

## Testing Requirements

- The migration suite must run cleanly on a fresh database.
- Tests must prove first-party models generate UUID primary keys.
- Tests must prove required partial indexes exist after migration.
