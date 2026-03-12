# Local Development

## Compose Rules

- keep PostgreSQL private to the internal Docker network
- keep Redis private to the internal Docker network
- expose only the API gateway port and route approved observability UIs through nginx
- run app, queue workers, and Kafka consumers as separate services where appropriate

## Required Services

- app
- nginx or caddy
- postgres
- redis
- kafka
- otel-collector
- prometheus
- grafana
- elasticsearch
- kibana

## Local Environment Standards

- `.env.example` must document required variables without secrets
- local runtime uses `SESSION_DRIVER=file` until IAM-backed session persistence is implemented
- test environment variables must be isolated from local development values
- provider credentials must use sandbox accounts in non-production environments

## Developer Startup Sequence

1. run `make bootstrap`
2. start the base runtime with `docker compose up -d nginx postgres redis kafka`
3. start observability services only when needed with `docker compose --profile observability up -d`
4. run `make verify`
5. begin with the current `In Progress` task only

When the observability profile is running:

- Grafana is reachable at `http://localhost:8080/grafana/`
- Prometheus is reachable at `http://localhost:8080/prometheus/`
- Kibana is reachable at `http://localhost:8080/kibana/`
- Prometheus scrapes the internal nginx path `/internal/metrics` using `OPS_PROMETHEUS_SCRAPE_KEY`
- Fluent Bit tails `storage/logs/medflow.json` and ships logs to Elasticsearch

## Local Command Contract

- `make bootstrap` builds the app image, installs Composer and Node dependencies through Docker Compose, and runs the baseline test suite.
- `make format`, `make lint`, `make analyse`, and `make test` execute through the Compose `app` service.
- `make build` executes through the Compose `node` service.
- `make verify` runs the full Composer verification path through Compose and then runs the frontend build through Compose.
- `docker compose config` must validate before any compose change is committed.

## Local Safety Rules

- do not expose database ports
- do not point local development at production credentials
- do not skip webhook verification or audit behavior in local environments
- use fake adapters or sandboxes when credentials are unavailable
