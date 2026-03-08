# Caching

This document defines the shared cache infrastructure used across MedFlow.

## Core Rules

- Redis is the production cache store.
- Cache keys are tenant-prefixed.
- Cache access follows cache-aside behavior.
- Invalidations must be explicit and must not rely on global cache flushes.
- Shared cache infrastructure must work in non-Redis test environments through Laravel cache contracts.

## Shared Contracts

- `CacheKeyBuilder` builds tenant-prefixed namespace and item keys.
- `TenantCache` handles remember, item-level forget, and namespace invalidation behavior.
- Cache domains use stable names such as `permissions`, `availability`, `settings`, and `reference-data`.

## Key Structure

- Namespace keys use `medflow:tenant:{tenant|global}:{domain}:namespace`.
- Item keys use `medflow:tenant:{tenant|global}:{domain}:v{namespaceVersion}:{segments...}`.
- Key segments are URL-encoded to keep separators unambiguous.

## Invalidation Rules

- `forget()` invalidates a single current-version cache entry.
- `invalidate()` bumps the namespace version for a cache domain and tenant scope.
- Permission invalidation remains event-driven and uses the shared cache helpers instead of raw cache keys.
- Future availability, settings, and token caches must use the same invalidation primitives.

## Testing Requirements

- Tests must prove cache entries do not leak across tenant scopes.
- Tests must prove namespace invalidation forces a reload.
- Tests must prove item-level invalidation leaves sibling keys intact.
