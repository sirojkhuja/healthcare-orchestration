# ADR 004: Integrations Framework

## Status

Accepted

## Date

2026-03-08

## Context

The platform must integrate with many external providers across payments, messaging, identity, and optional Uzbekistan-specific services. Provider behavior and credentials differ, but the internal programming model must stay consistent.

## Decision

Use a standard adapter-based integration framework with:

- application contracts
- infrastructure adapters
- HTTP client wrappers
- authenticators
- retry and circuit-breaker policies
- webhook verifiers
- payload mappers

## Alternatives Considered

- provider-specific service classes with no shared pattern
- direct SDK usage from controllers
- a generic catch-all integration service with dynamic behavior

## Consequences

- Consistent testability and replacement cost across providers
- More up-front framework code
- Better safety for retries, logging, and tenant credential handling

## Migration Plan

- build the shared integration abstractions
- add one provider adapter end to end
- use it as the template for the remaining providers
