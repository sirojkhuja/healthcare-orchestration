# ADR 007: PostgreSQL Schema Conventions

## Status

Accepted

## Date

2026-03-08

## Context

The canonical source requires migration-driven PostgreSQL schema management, UUID public identifiers, and partial indexes for common tenant and lifecycle filters, but the repository did not yet define shared helpers or apply those conventions consistently to base tables.

## Decision

Adopt shared schema helpers for UUID primary keys, indexed tenant UUID columns, request-context UUID columns, and partial index creation. Convert first-party user records to UUID primary keys, reference users from sessions through UUID foreign keys, and enable `pgcrypto` in PostgreSQL environments during migrations.

## Alternatives Considered

- keep Laravel default integer user identifiers and postpone UUID conversion
- hand-author every module migration without shared schema helpers
- avoid partial indexes until later module tables exist

## Consequences

- Base schema conventions become consistent before module growth increases migration drift.
- Future module migrations can reuse shared helpers instead of reimplementing UUID and index rules.
- Existing local databases should use `migrate:fresh` while the project is still pre-release.

## Migration Plan

- add shared schema and index helper classes
- convert the base user and session schema to UUID conventions
- update current shared migrations to use the helpers and partial indexes
- document persistence rules for future module work
