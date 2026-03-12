# Observability

## Logging

- Emit structured JSON logs.
- Write the default stack to stderr and `storage/logs/medflow.json`.
- Include `X-Request-Id` and `X-Correlation-Id` in request context and logs.
- Include `X-Causation-Id` in request context, queued jobs, and emitted message metadata.
- Include `tenant_id`, authenticated actor fields, and `trace_id` / `span_id` when available.
- Standardize log levels and event names.
- Log business-significant state transitions and integration failures.

## Tracing

Instrument:

- inbound HTTP requests
- outbound integration calls
- database access where sampling permits
- Kafka produce and consume paths
- long-running background jobs

Current repository implementation:

- request-root OpenTelemetry spans are created in HTTP middleware
- request, correlation, and causation IDs remain the primary lineage bridge
- deeper DB and async auto-instrumentation remain compatible future work

Use request, correlation, and causation identifiers as the primary lineage bridge until OpenTelemetry propagation is fully implemented.

## Metrics

Track at minimum:

- HTTP latency and error rates
- queue and outbox lag
- Kafka consumer lag
- integration error rates
- cache hit ratio
- payment reconciliation failures
- webhook verification failures

Current `T065` endpoint surface:

- `/api/v1/live` for process liveness
- `/api/v1/ready` for critical runtime readiness
- `/api/v1/health` for ordered health checks and degraded-state warnings
- `/api/v1/metrics` for Prometheus text exposition of app info, health status, outbox lag, queue counts, and Kafka consumer receipt lag
- `/api/v1/admin/*` for strongly gated operational controls over caches, failed jobs, Kafka replay receipts, outbox retry/drain, logging pipeline reload, feature flags, rate limits, and runtime config views

`T067` expands `/api/v1/metrics` with:

- HTTP request totals and duration histogram
- cache hits, misses, and hit ratio
- integration error totals
- payment reconciliation failure totals
- webhook verification failure totals

Prometheus scrapes the internal nginx path `/internal/metrics` with `OPS_PROMETHEUS_SCRAPE_KEY`. That path is infrastructure-only and does not change the public `/api/v1/metrics` contract.

## Error Monitoring

- Send application exceptions to Sentry.
- Group errors by fingerprint where practical.
- Include tenant and correlation context when safe.
- Include request and trace identifiers on the Sentry scope when available.
- Until OpenTelemetry trace IDs are available, API error payloads use the current request identifier as `trace_id`.

## Centralized Logs and Dashboards

- Forward logs to Elastic.
- Publish operational dashboards in Grafana.
- Keep dashboards for API health, integration health, Kafka lag, and queue health.
- Local UI paths are `/grafana/`, `/prometheus/`, and `/kibana/` through nginx.

## Alerting Priorities

- tenant scope failure
- payment webhook or reconciliation failure
- outbox backlog growth
- Kafka consumer lag
- elevated 5xx rates
- integration authentication failures
- security event spikes
