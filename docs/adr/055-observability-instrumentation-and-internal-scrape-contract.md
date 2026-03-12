# ADR 055: Observability Instrumentation and Internal Scrape Contract

Date: `2026-03-12`

## Status

Accepted

## Context

`T065` established the public operational API and explicitly kept `/api/v1/metrics` authenticated and tenant-scoped. `T067` now needs to deliver the actual observability stack promised by the canonical source:

- structured JSON logs
- Sentry error aggregation
- OpenTelemetry traces
- Prometheus metrics and alerts
- Grafana dashboards
- Elastic log shipping

The repository still lacked two material decisions:

1. how the local Prometheus instance can scrape runtime metrics without weakening the documented public `/api/v1/metrics` authorization contract
2. which metric families and local stack paths are mandatory in this phase

## Decision

Implement `T067` with application-level structured logging, request-root OpenTelemetry spans, cache-backed runtime counters and histograms, and a local observability stack that stays behind the single nginx gateway port.

### 1. Structured logging

- Application logs must be emitted as newline-delimited JSON.
- The default log stack writes to:
  - stderr for container logs
  - `storage/logs/medflow.json` for log shipping
- Every record is enriched with:
  - `request_id`
  - `correlation_id`
  - `causation_id`
  - `tenant_id` when available
  - `actor_id`, `actor_name`, and `actor_email` when an authenticated API actor is present
  - `trace_id` and `span_id` when tracing is enabled
- Integration failures and HTTP request lifecycle events must be logged as structured events.

### 2. Tracing

- OpenTelemetry request-root spans are created for API traffic.
- Request spans must include at least:
  - HTTP method
  - resolved route label
  - request path
  - tenant identifier when available
  - response status code
  - measured request duration
- Request, correlation, and causation identifiers remain the primary lineage bridge for payloads, audit, and error responses.
- Deeper DB and async auto-instrumentation remain compatible future work; `T067` does not require the PHP `ext-opentelemetry` extension.

### 3. Metrics families

`GET /api/v1/metrics` continues to be the authenticated public operational endpoint, but its exposition expands to include:

- existing health, outbox, queue, and Kafka gauges
- `medflow_http_requests_total`
- `medflow_http_request_duration_seconds` histogram
- `medflow_cache_hits_total`
- `medflow_cache_misses_total`
- `medflow_cache_hit_ratio`
- `medflow_integration_errors_total`
- `medflow_payment_reconciliation_failures_total`
- `medflow_webhook_verification_failures_total`

Instrumentation sources in this phase are:

- request middleware for HTTP totals and latency
- tenant cache reads for hit/miss accounting
- payment reconciliation flows
- SMS and email send paths
- inbound payment, lab, and Telegram webhook verification paths

### 4. Prometheus scrape path

- The public `/api/v1/metrics` route remains authenticated and tenant-scoped exactly as defined in ADR `053`.
- Local Prometheus scrapes the internal nginx path `GET /internal/metrics`.
- `GET /internal/metrics` is not part of the public API inventory.
- The internal path is protected by `OPS_PROMETHEUS_SCRAPE_KEY`, accepted either as:
  - `Authorization: Bearer <key>`
  - `X-Prometheus-Scrape-Key: <key>`

This preserves the public operational contract while allowing local and CI Prometheus validation.

### 5. Local observability stack

When the `observability` Compose profile is enabled:

- nginx remains the only exposed port
- Grafana is available under `/grafana/`
- Prometheus is available under `/prometheus/`
- Kibana is available under `/kibana/`
- Prometheus scrapes nginx `/internal/metrics`
- Grafana is provisioned from repository files
- Fluent Bit tails `storage/logs/medflow.json` and ships records to Elasticsearch
- the OpenTelemetry collector receives OTLP traces and exports them to its debug pipeline for local validation

### 6. Logging pipeline catalog

The operator-visible logging pipeline projection is:

- `app-json`
- `sentry`
- `otel-traces`
- `prometheus-scrape`
- `elastic-shipper`

Reload behavior remains an audited operator intent record only; no shell-level process reload is attempted by the API.

## Consequences

- The public ops contract stays stable while Prometheus becomes usable locally and in CI.
- Logs, metrics, and traces now share request and tenant context.
- The stack remains container-local and reproducible from repository files.
- High-cardinality metric labels must stay tightly controlled; route labels must use route names or stable normalized paths only.
