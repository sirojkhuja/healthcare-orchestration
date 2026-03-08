# Local Development

## Compose Rules

- keep PostgreSQL private to the internal Docker network
- keep Redis private to the internal Docker network
- expose only the API gateway and approved UI ports
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
- test environment variables must be isolated from local development values
- provider credentials must use sandbox accounts in non-production environments

## Developer Startup Sequence

1. install dependencies
2. copy environment template
3. start Docker services
4. run migrations
5. seed minimal reference data
6. run quality gates
7. begin with the current `In Progress` task only

## Local Safety Rules

- do not expose database ports
- do not point local development at production credentials
- do not skip webhook verification or audit behavior in local environments
- use fake adapters or sandboxes when credentials are unavailable
