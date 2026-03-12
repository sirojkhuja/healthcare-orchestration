# Cutover Checklist

Status: Approved
Reviewed On: 2026-03-13

## Purpose

Use this checklist during the production release window after the release workflow has produced a valid changelog and manifest for the target semantic version.

## Pre-Cutover

1. Confirm the target commit is on `main` and matches the intended release SHA.
2. Confirm `make release-dry-run RELEASE_VERSION=<semver>` succeeded for the target version.
3. Confirm the generated changelog and release manifest artifacts were reviewed.
4. Confirm the production readiness review remains `Approved`.
5. Confirm the rollback plan remains `Approved` and the previous release tag is known.
6. Confirm no critical alerts, unresolved incidents, or blocked dependencies exist.
7. Confirm required secrets, environment variables, and infrastructure credentials are present.
8. Confirm stakeholders know the cutover window and rollback trigger owner.

## Cutover Steps

1. Create and push Git tag `v<semver>` from the approved release commit.
2. Wait for `.github/workflows/release.yml` to finish successfully.
3. Confirm the GitHub release body matches the generated changelog artifact.
4. Deploy the tagged release through the documented containerized runtime path.
5. Run post-deploy smoke checks against `/api/v1/ping`, `/api/v1/health`, `/api/v1/readiness`, `/api/v1/version`, and `/api/v1/metrics`.
6. Confirm background workers, Kafka relay/consumers, and scheduled jobs resume normally.
7. Confirm no release-blocking errors appear in logs, traces, metrics, or alerts.

## Post-Cutover Validation

1. Confirm the running application reports the intended semantic version.
2. Confirm database migrations, caches, and queue consumers are healthy.
3. Confirm observability dashboards show normal latency and error-rate baselines.
4. Confirm payment, notification, and webhook entrypoints remain healthy.
5. Announce release completion only after the smoke checks and operational checks pass.

## Rollback Trigger

Execute the rollback plan immediately when a release-blocking defect affects availability, integrity, tenant isolation, billing correctness, or secure authentication flows.
