# 058. Release Automation and Go-Live Governance

Date: 2026-03-13

## Status

Accepted

## Context

The canonical source requires semantic versioning and changelog generation, and `T070` requires final release automation, a signed-off readiness review, and approved cutover and rollback documentation.

Before this ADR, the repository had strong CI and hardening enforcement, but the final release path was still implicit:

- no dedicated GitHub release workflow existed
- no repository-owned dry-run entrypoint existed
- changelog generation was a policy without committed automation
- readiness, cutover, and rollback documents were not versioned as release-blocking artifacts

That left the release phase dependent on ad hoc operator behavior instead of the same repository-governed workflow used for development and verification.

## Decision

Adopt a repository-owned release workflow with the following rules:

1. `scripts/release/dry-run.sh` is the canonical local and CI release validation entrypoint.
2. `scripts/release/build-changelog.sh` generates release notes from Git history for a target semantic version.
3. `scripts/release/check-readiness.sh` validates required release docs and approval metadata.
4. `.github/workflows/release.yml` runs dry runs on manual dispatch and publishes GitHub releases on semantic-version tag pushes.
5. Release artifacts are written to `build/release/<semver>/` and include a changelog and manifest.
6. Release publication is blocked until the readiness review, cutover checklist, rollback plan, and repository verification all pass.

## Consequences

### Positive

- release automation now uses the same documented command contract and repository governance model as development work
- changelog generation is deterministic and source controlled
- the final release phase has explicit, reviewable operational documents
- tag publication reuses the same dry-run checks that local operators use before creating a release

### Negative

- release runs execute the full repository verification path and therefore take longer
- release readiness now requires maintaining additional operational documents in source control

## Follow-Up

- future environment-promotion automation must reuse the same release manifest and readiness artifacts instead of creating a separate process
