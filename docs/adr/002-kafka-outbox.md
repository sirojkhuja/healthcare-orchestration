# ADR 002: Kafka Outbox

## Status

Accepted

## Date

2026-03-08

## Context

The platform must publish domain events reliably while preserving transactional integrity between database state and emitted events.

## Decision

Use the outbox pattern. Business transactions write both domain data and an outbox record in one database transaction. A relay process publishes outbox records to Kafka and marks them delivered.

## Alternatives Considered

- direct Kafka publication from controllers
- direct Kafka publication from domain services
- best-effort asynchronous publication without persistence

## Consequences

- Improved delivery safety and replayability
- Additional infrastructure for relay, retries, and monitoring
- Clear operational surface for lag and failure tracking

## Migration Plan

- add outbox schema and repository
- add relay worker with retry support
- add operational dashboards for lag and failures
