# CI/CD

## Mandatory Pipeline Gates

The project pipeline must fail when any of these checks fail:

- formatting
- linting
- static analysis
- unit tests
- feature tests
- integration tests where enabled
- OpenAPI validation
- coverage thresholds
- architecture rule checks
- file size and layering checks
- documentation sync checks

## Required Command Contract

Future Laravel bootstrap work must provide these repository commands:

- `make format`
- `make lint`
- `make analyse`
- `make test`
- `make build`
- `make verify`
- `make harden`

Each command delegates through Docker Compose and Composer scripts in the current foundation setup, and the interface must stay stable even if the internals change later.

OpenAPI contract work also uses these repository-level commands:

- `npm run openapi:build`
- `npm run openapi:validate`
- `bash scripts/openapi/validate-schema.sh`

## Current Workflow Files

- `.github/workflows/governance.yml` validates tasklist and governance artifacts.
- `.github/workflows/ci.yml` validates Docker Compose, runs `make bootstrap`, then runs lint, analysis, tests, build, explicit OpenAPI validation, and hardening checks through the stable repository commands.

OpenAPI validation in CI must include:

- `npm run openapi:build`
- `npm run openapi:validate`
- `bash scripts/openapi/validate-schema.sh`
- the route/spec parity contract test inside the PHP test suite

Hardening validation in CI must include:

- `bash scripts/architecture/check.sh`
- `bash scripts/performance/check.sh`
- `bash scripts/security/check.sh`

Observability validation in the current repository must include:

- `docker compose config`
- Prometheus config validation through the containerized toolchain when observability files change
- Grafana provisioning files committed alongside dashboard changes
- docs and ADR updates whenever metric families, logging pipelines, or scrape behavior change

## Release Rules

- use semantic versioning
- generate changelog entries from merged work
- promote through documented environments
- block release when critical alerts or failing tests exist

## Pull Request Expectations

Every pull request must show:

- task ID and task status update
- linked docs updated
- API impact statement
- test evidence
- quality gate result

## Deployment Expectations

- containerized runtime
- reverse proxy at the edge
- private internal network for stateful services
- separate workers or consumers for async processing
