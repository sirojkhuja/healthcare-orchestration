# Release Management

## Purpose

This document defines the repository-owned release workflow for MedFlow. It complements the CI/CD contract in `docs/dev/ci-cd.md` and is the operational source for dry runs, changelog generation, tag publication, and release artifacts.

## Release Rules

- Versioning Strategy: Semantic Versioning
- Git tag format: `v<semver>`
- Release branch: `main`
- Release notes source: generated from Git history through `scripts/release/build-changelog.sh`
- Release gate: a release is blocked when any mandatory quality gate, hardening check, or readiness review fails

## Required Inputs

- a semantic version without the `v` prefix
- synchronized project docs, OpenAPI bundle, ADRs, and tasklist
- an approved production readiness review
- an approved cutover checklist
- an approved rollback plan
- a passing full repository verification run

## Repository Commands

- Local dry run: `make release-dry-run RELEASE_VERSION=<semver>`
- Direct script path: `bash scripts/release/dry-run.sh --version <semver>`
- Changelog generation only: `bash scripts/release/build-changelog.sh --version <semver> --output build/release/<semver>/CHANGELOG-<semver>.md`

`make release-dry-run RELEASE_VERSION=<semver>` is the canonical local command and must remain stable.

## Dry-Run Workflow

1. Validate the tasklist and governance docs.
2. Validate release readiness documents.
3. Run the full repository `make verify` gate.
4. Generate release notes into `build/release/<semver>/CHANGELOG-<semver>.md`.
5. Generate `build/release/<semver>/release-manifest.json`.
6. Report success only when every prior step passes.

## GitHub Workflow Contract

`.github/workflows/release.yml` owns release automation.

- `workflow_dispatch` runs a dry run for the provided semantic version.
- `push` on `v*.*.*` tags reruns the same dry-run checks and publishes a GitHub release when they pass.
- Release artifacts are uploaded as GitHub workflow artifacts for auditability.

## Generated Artifacts

For release version `<semver>`, the workflow must create:

- `build/release/<semver>/CHANGELOG-<semver>.md`
- `build/release/<semver>/release-manifest.json`

The manifest records the version, tag, commit SHA, generation timestamp, and changelog path used for the release.

## Release Sequence

1. Merge the release-ready state to `main`.
2. Run `make release-dry-run RELEASE_VERSION=<semver>`.
3. Review the generated changelog and manifest artifacts.
4. Create and push tag `v<semver>`.
5. Allow the release workflow to publish the GitHub release from the generated changelog artifact.
6. Execute the approved cutover checklist.
7. Verify the post-cutover checks.
8. If any blocking issue appears, execute the rollback plan.

## Blocking Conditions

Do not create or publish a release when any of the following are true:

- the tasklist is invalid or out of sync
- the production readiness review is not approved
- the cutover checklist is not approved
- the rollback plan is not approved
- `make verify` fails
- the target tag already exists unexpectedly for a dry run
- critical security, availability, or observability issues remain open
