# ADR 005: Observability Stack

## Status

Accepted

## Date

2026-03-08

## Context

The platform operates across async workflows, third-party integrations, and regulated business events. Debugging without strong telemetry would be slow and risky.

## Decision

Adopt a full observability stack:

- structured JSON logs
- Sentry for error aggregation
- OpenTelemetry for tracing
- Prometheus for metrics
- Grafana for dashboards
- Elastic for centralized logs

## Alternatives Considered

- basic application logs only
- vendor-specific all-in-one APM only
- metrics without tracing or centralized logs

## Consequences

- Better operational visibility and incident response
- Additional deployment and maintenance overhead
- Need for disciplined metric and trace naming

## Migration Plan

- add correlation ID propagation first
- instrument HTTP, database, and integration boundaries
- add dashboards and alerting during platform hardening
