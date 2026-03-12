# ADR 057: Hardening Baselines and Governance Checks

Date: `2026-03-13`

## Status

Accepted

## Context

The canonical source requires performance baselines, security review actions, and architecture rule checks before release. By the end of `T068`, the repository had broad functional coverage and strong OpenAPI governance, but the final hardening phase still lacked three explicit release-blocking controls:

1. a stable architecture gate for clean boundaries and file-size governance
2. a repeatable security check for runtime dependency audit and committed-secret review
3. a named performance baseline suite for critical operational endpoints

Without those controls, the final release phase would rely on ad hoc review instead of repository-owned checks.

## Decision

Add one hardening contract that is executable locally, in hooks, and in CI.

### 1. Hardening entrypoints

- `make harden` is the stable hardening command.
- `make verify` must run the hardening command after OpenAPI validation, analysis, tests, and build.
- the hardening command delegates to:
  - `bash scripts/architecture/check.sh`
  - `bash scripts/performance/check.sh`
  - `bash scripts/security/check.sh`

### 2. Architecture enforcement

- `tests/Architecture/CleanArchitectureBoundariesTest.php` is the boundary contract.
- The enforced rules are:
  - domain code may not import Laravel or lower layers
  - application code may not import presentation or infrastructure classes
  - presentation code may not import module infrastructure classes
- `tests/Architecture/FileSizeGovernanceTest.php` enforces the `400` line hard limit across `app/**/*.php`.
- files that still exceed the hard limit must be listed in the reviewed exception register under `config/governance.php`.
- the exception register is self-cleaning: if an exempted file drops to `400` lines or fewer, the test fails until the exemption is removed.

### 3. Boundary cleanup included in this phase

- CIDR matching is moved to the shared domain so the IP allowlist controller no longer depends on an infrastructure class.
- Click and Uzum webhook application services now depend on the billing-layer `ServiceIdAwareWebhookPaymentGateway` contract instead of infrastructure gateway classes.

### 4. Security enforcement

- `bash scripts/security/check.sh` owns release-blocking security automation.
- It must run:
  - Composer runtime dependency audit against the lockfile
  - npm production lockfile audit
  - tracked-file secret scanning
  - tracked environment file review
- Composer abandoned-package notices are warnings for manual review and do not fail the hardening gate by themselves.
- Actual runtime security advisories fail the hardening gate.

### 5. Performance baseline contract

- `tests/Performance/PlatformBaselineTest.php` is the performance smoke suite.
- The suite measures average latency for:
  - `GET /api/v1/ping`
  - authenticated tenant-scoped `GET /api/v1/metrics`
  - internal scrape `GET /internal/metrics`
- Thresholds and iteration counts are stored in `config/governance.php` so they are versioned with the code and docs.

## Consequences

- The hardening phase is now executable instead of advisory.
- Boundary regressions, new oversized application files, runtime dependency advisories, tracked secret material, and basic operational latency regressions fail before release.
- The repository still carries a small reviewed file-size exception register. Those exceptions are explicit technical debt and remain visible until each file is extracted.
