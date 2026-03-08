# Observability

## Logging

- Emit structured JSON logs.
- Include `X-Request-Id` and `X-Correlation-Id` in request context and logs.
- Include `X-Causation-Id` in request context, queued jobs, and emitted message metadata.
- Standardize log levels and event names.
- Log business-significant state transitions and integration failures.

## Tracing

Instrument:

- inbound HTTP requests
- outbound integration calls
- database access where sampling permits
- Kafka produce and consume paths
- long-running background jobs

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

## Error Monitoring

- Send application exceptions to Sentry.
- Group errors by fingerprint where practical.
- Include tenant and correlation context when safe.
- Until OpenTelemetry trace IDs are available, API error payloads use the current request identifier as `trace_id`.

## Centralized Logs and Dashboards

- Forward logs to Elastic.
- Publish operational dashboards in Grafana.
- Keep dashboards for API health, integration health, Kafka lag, and queue health.

## Alerting Priorities

- tenant scope failure
- payment webhook or reconciliation failure
- outbox backlog growth
- Kafka consumer lag
- elevated 5xx rates
- integration authentication failures
- security event spikes
