# ADR 053: Observability, Health, and Admin Ops Contract

Date: `2026-03-12`

## Status

Accepted

## Context

The canonical source and split route catalog already define the ops and observability inventory for `T065`:

- `GET /health`
- `GET /ready`
- `GET /live`
- `GET /metrics`
- `GET /version`
- `POST /admin/cache:flush`
- `POST /admin/cache:rebuild`
- `GET /admin/jobs`
- `POST /admin/jobs/{jobId}:retry`
- `GET /admin/kafka/lag`
- `POST /admin/kafka:replay`
- `GET /admin/outbox`
- `POST /admin/outbox:drain`
- `POST /admin/outbox/{outboxId}:retry`
- `GET /admin/logging/pipelines`
- `POST /admin/logging:pipeline-reload`
- `GET /admin/feature-flags`
- `PUT /admin/feature-flags`
- `GET /admin/rate-limits`
- `PUT /admin/rate-limits`
- `GET /admin/config`
- `POST /admin/config:reload`

Earlier tasks already delivered:

- tenant-scoped RBAC with `admin.view` and `admin.manage`
- shared cache infrastructure and explicit namespace invalidation
- Kafka outbox relay, consumer receipt persistence, and retry logic
- integrations catalog feature flags backed by static config only

The repository still lacked documented behavior for:

- which ops endpoints are authenticated and tenant-gated
- how health and readiness status are computed
- what the metrics endpoint exposes before the full Prometheus stack lands in `T067`
- how admin cache, jobs, Kafka, and outbox actions behave
- how feature flags and rate limits are stored and updated in this phase
- what “logging pipeline reload” and “runtime config reload” mean before the full observability stack is implemented

Those decisions are material and must be recorded before implementation.

## Decision

Implement `T065` as a strongly gated operational surface with tenant-scoped admin authorization, explicit runtime probes, tenant-scoped feature-flag and rate-limit settings, and operator actions over caches, failed jobs, Kafka replay receipts, and outbox delivery.

### 1. Authorization and scope

- All `T065` routes require authenticated API access.
- All `T065` routes require active tenant context through `X-Tenant-Id`.
- `admin.view` protects:
  - `GET /health`
  - `GET /ready`
  - `GET /live`
  - `GET /metrics`
  - `GET /version`
  - `GET /admin/jobs`
  - `GET /admin/kafka/lag`
  - `GET /admin/outbox`
  - `GET /admin/logging/pipelines`
  - `GET /admin/feature-flags`
  - `GET /admin/rate-limits`
  - `GET /admin/config`
- `admin.manage` protects:
  - `POST /admin/cache:flush`
  - `POST /admin/cache:rebuild`
  - `POST /admin/jobs/{jobId}:retry`
  - `POST /admin/kafka:replay`
  - `POST /admin/outbox:drain`
  - `POST /admin/outbox/{outboxId}:retry`
  - `POST /admin/logging:pipeline-reload`
  - `PUT /admin/feature-flags`
  - `PUT /admin/rate-limits`
  - `POST /admin/config:reload`

Ops routes are platform operations, not tenant-owned business records. In this phase, tenant context exists to bind authorization and audit scope because the platform does not yet have global RBAC. Responses may therefore include platform-wide runtime state where tenant ownership does not apply.

### 2. Health, readiness, liveness, and version

`GET /live` returns `200 OK` when the application process can answer requests.

Payload:

- `status = alive`
- `service`
- `version`
- `checked_at`

`GET /ready` returns:

- `200 OK` with `status = ready` when all critical probes pass
- `503 Service Unavailable` with `status = not_ready` when any critical probe fails

Readiness checks in this phase:

- database connectivity
- cache store round trip
- queue storage availability
- Kafka configuration presence

`GET /health` returns one of:

- `healthy`
- `degraded`
- `failing`

Status rules:

- `failing` when any critical readiness check fails
- `degraded` when critical checks pass but operational warnings exist
- `healthy` when critical checks pass and no warnings exist

Operational warnings in this phase:

- failed jobs exist
- ready or failed outbox backlog is non-zero
- oldest ready outbox item age exceeds the configured warning threshold

Health payload includes ordered `checks[]` plus a summary block for outbox lag and failed jobs.

`GET /version` returns a safe runtime version projection with:

- `service`
- `environment`
- `version`
- `php_version`
- `laravel_version`
- `modules`
- optional `git_sha`

### 3. Metrics endpoint

`GET /metrics` returns Prometheus-compatible text exposition for the current runtime.

This phase exposes deterministic application gauges and counters derived from local runtime state:

- app info
- ready outbox count
- oldest ready outbox age
- queued jobs count
- failed jobs count
- Kafka consumer receipt totals by consumer
- Kafka consumer receipt age by consumer
- health status code gauge

This route does not yet provide full HTTP latency histograms, OpenTelemetry spans, or external scrape pipeline metadata. `T067` expands the observability surface without changing the route.

### 4. Cache administration

`POST /admin/cache:flush` accepts:

- `domains[]` optional list of cache domains
- `include_global_reference_data` optional boolean

Supported domains in this phase:

- `permissions`
- `availability`
- `settings`
- `reference-data`
- `integrations`
- `feature-flags`
- `rate-limits`
- `ops`

Rules:

- omitted `domains[]` means all supported domains
- tenant-scoped invalidation uses shared namespace invalidation
- `reference-data` invalidation is tenant-scoped unless `include_global_reference_data = true`
- no raw store-level `flush()` is allowed

`POST /admin/cache:rebuild` accepts the same payload and:

1. performs the same namespace invalidation
2. eagerly warms ops-managed projections for the active tenant:
   - feature flags
   - rate limits
   - runtime config

Availability and permission caches remain lazy-loaded after invalidation unless later tasks add targeted warmers.

### 5. Jobs administration

`GET /admin/jobs` returns queue summary plus failed-job inventory.

Supported filters:

- `queue`
- `limit` default `50`, max `100`

Response summary includes:

- `ready_jobs`
- `reserved_jobs`
- `failed_jobs`
- `pending_batches`

Returned items represent rows from Laravel `failed_jobs` and include:

- `id`
- `uuid`
- `connection`
- `queue`
- `display_name`
- `failed_at`
- `error_summary`

`POST /admin/jobs/{jobId}:retry` retries one failed job by:

1. reading the failed-job row
2. reinserting the stored payload into the active queue backend
3. deleting the failed-job row when reinsertion succeeds

In this phase `jobId` refers to the `failed_jobs.id` primary key.

### 6. Kafka lag and replay

`GET /admin/kafka/lag` returns a phase-one Kafka operational projection.

Because broker offset introspection is not yet implemented in the repository runtime, this endpoint uses consumer receipt persistence as the lag source of truth for now.

Per consumer, the response includes:

- `consumer_name`
- `topics[]`
- `processed_total`
- `last_processed_at`
- `receipt_lag_seconds`

This endpoint also returns configured `brokers` and `consumer_group`.

`POST /admin/kafka:replay` enables replay by clearing consumer receipt rows.

Accepted payload:

- `consumer_name` required
- `event_ids[]` optional
- `processed_before` optional ISO-8601 timestamp
- `limit` optional integer, default `100`, max `1000`

Rules:

- at least one of `event_ids[]` or `processed_before` must be provided
- matching receipt rows are deleted for the selected consumer only
- deleting receipts does not itself move Kafka offsets
- the action prepares replay-safe reprocessing after operator-managed republish or offset reset

### 7. Outbox administration

`GET /admin/outbox` returns outbox records in the active tenant scope plus any global system rows with `tenant_id = null`.

Supported filters:

- `status` with `pending|processing|failed|delivered`
- `topic`
- `event_type`
- `limit` default `50`, max `100`

Ordering is:

1. `pending`
2. `failed`
3. `processing`
4. `delivered`
5. `created_at desc`

Each row includes:

- `id`
- `event_id`
- `event_type`
- `topic`
- `tenant_id`
- `status`
- `attempts`
- `next_attempt_at`
- `claimed_at`
- `delivered_at`
- `last_error`
- `created_at`

`POST /admin/outbox:drain` accepts optional `limit` and triggers one synchronous relay pass through the existing outbox relay service.

`POST /admin/outbox/{outboxId}:retry`:

- works only for rows in `failed`
- resets the row to `pending`
- clears `next_attempt_at`, `claimed_at`, and `last_error`
- preserves `attempts`

Retrying a non-failed outbox row returns `409 Conflict`.

### 8. Logging pipelines

Logging pipelines are configuration-backed operational projections in this phase.

`GET /admin/logging/pipelines` returns configured pipeline rows with:

- `key`
- `name`
- `enabled`
- `destination`
- `status`
- `last_reloaded_at`

`status` is:

- `active` when the configured pipeline is enabled
- `disabled` when the configured pipeline is disabled

`POST /admin/logging:pipeline-reload` accepts optional `pipelines[]`.

Rules:

- omitted `pipelines[]` means all configured pipelines
- unknown pipeline keys fail validation
- this phase records an operator reload action and returns updated timestamps
- it does not shell out or signal external log shippers directly

### 9. Feature flags

Feature flags are tenant-scoped overrides with configuration-backed defaults.

`GET /admin/feature-flags` returns one row per configured feature flag with:

- `key`
- `name`
- `description`
- `module`
- `enabled`
- `default_enabled`
- `source = default|tenant_override`
- `updated_at`

`PUT /admin/feature-flags` accepts:

- `flags[]`
  - `key`
  - `enabled`

Rules:

- only configured keys are allowed
- upsert is partial; omitted keys remain unchanged
- setting a flag writes or replaces the tenant override row
- optional-integration availability must resolve through these tenant overrides before falling back to static config defaults

### 10. Rate limits

Rate limits are tenant-scoped named-bucket settings with configuration-backed defaults.

`GET /admin/rate-limits` returns rows with:

- `bucket_key`
- `name`
- `description`
- `requests_per_minute`
- `burst`
- `source = default|tenant_override`
- `updated_at`

`PUT /admin/rate-limits` accepts:

- `limits[]`
  - `bucket_key`
  - `requests_per_minute`
  - `burst`

Rules:

- only configured bucket keys are allowed
- values must be positive integers
- updates are partial and replace only provided bucket keys

This phase establishes the administrative source of truth for rate limits. Broader enforcement rollout remains future hardening work.

### 11. Runtime config view and reload

`GET /admin/config` returns a safe operational config projection with:

- app environment and version
- queue connection name
- cache store name
- Kafka brokers and consumer group
- outbox batch and retry settings
- enabled module list
- last config reload timestamp

`POST /admin/config:reload`:

- invalidates ops-managed runtime-config caches
- records the reload as an audit event
- returns the refreshed safe config projection

This phase does not hot-reload environment variables, restart workers, or rebuild Laravel’s compiled config cache.

### 12. Audit actions

Mutating admin routes write immutable audit actions:

- `admin.cache_flushed`
- `admin.caches_rebuilt`
- `admin.job_retried`
- `admin.kafka_replay_enabled`
- `admin.outbox_drained`
- `admin.outbox_retried`
- `admin.logging_pipelines_reloaded`
- `admin.feature_flags_updated`
- `admin.rate_limits_updated`
- `admin.runtime_config_reloaded`

## Consequences

Positive:

- the full ops/admin route inventory becomes implementable without undocumented behavior
- optional integration availability gains tenant-scoped feature-flag overrides
- operators get explicit controls for cache invalidation, failed-job retry, Kafka replay preparation, and outbox retry
- the future observability stack can expand the same endpoints instead of replacing them

Trade-offs:

- Kafka lag is receipt-age-based in this phase rather than broker-offset-based
- job administration is limited to failed-job retry, not full queue orchestration
- runtime config reload is a projection refresh, not a process-level live reload
