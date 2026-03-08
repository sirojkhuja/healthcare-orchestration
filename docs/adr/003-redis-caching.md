# ADR 003: Redis Caching

## Status

Accepted

## Date

2026-03-08

## Context

The platform needs fast access to permission data, availability views, settings, and other frequently read data while preserving tenant isolation and consistency.

## Decision

Use Redis with TLS as a cache-aside store. Keys are tenant-prefixed. Cache invalidation is driven by domain events and explicit application actions.

## Alternatives Considered

- database-only reads with no cache layer
- write-through cache for all reads and writes
- module-specific ad hoc caches

## Consequences

- Better read performance and lower pressure on PostgreSQL
- Need for disciplined invalidation
- Need for cache observability and key hygiene

## Migration Plan

- define shared cache contract
- add tenant-prefixed key builder
- add cache invalidation hooks from domain events
