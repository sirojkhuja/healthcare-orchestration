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

Each command delegates through Docker Compose and Composer scripts in the current foundation setup, and the interface must stay stable even if the internals change later.

## Current Workflow Files

- `.github/workflows/governance.yml` validates tasklist and governance artifacts.
- `.github/workflows/ci.yml` validates Docker Compose, runs `make bootstrap`, and then runs lint, analysis, tests, and build through the stable `make` contract.

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
