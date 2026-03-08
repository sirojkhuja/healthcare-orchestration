# ADR 008: Kafka Client Selection

## Status

Accepted

## Date

2026-03-08

## Context

The MedFlow runtime includes sockets and native Redis support but does not include `ext-rdkafka`. The outbox relay and consumer foundation still need a real Kafka transport implementation.

## Decision

Use `longlang/phpkafka` as the Kafka client behind shared producer and consumer infrastructure. It is a pure-PHP client that works with the current container runtime and allows the platform to implement relay and consumer foundations without introducing a native extension requirement.

## Alternatives Considered

- require `ext-rdkafka` and use an extension-backed client
- defer all Kafka transport work until native extension support exists
- implement a fake or log-only publisher in place of Kafka

## Consequences

- The platform can publish and consume Kafka messages in the current Docker runtime.
- Composer now carries a pure-PHP Kafka client and its transitive dependencies.
- Future runtime hardening should review the client dependency tree and operational behavior under load.

## Migration Plan

- add the Kafka client dependency and lock it
- implement shared Kafka producer and consumer integration points
- route outbox relay and consumer framework through the shared contracts
