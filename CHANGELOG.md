# Changelog

All notable changes to this project are generated from repository history and published through the release workflow.

The canonical release note generator is `bash scripts/release/build-changelog.sh --version <semver> --output <path>`.

## Unreleased

- Use `make release-dry-run RELEASE_VERSION=<semver>` to validate the next release candidate, regenerate release notes, and produce the release manifest artifact.

## Changelog Rules

- Versions follow semantic versioning and use Git tags in the form `v<semver>`.
- Release notes are generated from merged commit history since the previous reachable semantic version tag.
- The generated changelog artifact is the source for the GitHub release body.
- Release dry runs write artifacts under `build/release/<semver>/`.
