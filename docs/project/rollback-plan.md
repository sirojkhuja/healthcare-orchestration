# Rollback Plan

Status: Approved
Reviewed On: 2026-03-13

## Rollback Triggers

Rollback is mandatory when any of the following occurs after cutover:

- health or readiness checks fail and do not recover immediately
- tenant isolation, authorization, or audit behavior is incorrect
- billing, payment capture, or claim workflows produce incorrect state
- outbound notifications or inbound webhooks fail in a release-blocking way
- security, data integrity, or availability incidents exceed the approved release window threshold

## Rollback Strategy

1. Freeze further deploys and stop any additional release changes.
2. Identify the last known good semantic version tag.
3. Redeploy the previous tagged release through the same containerized runtime path.
4. Restore routing to the previous release after smoke checks succeed.
5. Revalidate `/api/v1/ping`, `/api/v1/health`, `/api/v1/readiness`, `/api/v1/version`, and `/api/v1/metrics`.
6. Confirm background workers, Kafka consumers, and scheduled jobs are stable on the rolled-back release.
7. Capture an incident timeline and preserve logs, traces, metrics, and release artifacts for follow-up review.

## Data and Schema Posture

- Do not attempt rollback until schema compatibility is understood for the target and previous tags.
- Prefer backward-compatible releases so the previous application version can safely run against the deployed schema.
- If a migration is not backward compatible, stop and execute the documented incident process before any database reversal attempt.

## Communication Requirements

1. Announce rollback start to engineering, product, security, and operations stakeholders.
2. Record the triggering symptom, decision time, owner, and expected recovery target.
3. Announce rollback completion only after post-rollback smoke checks pass.
4. Open a follow-up review before the next release attempt.
