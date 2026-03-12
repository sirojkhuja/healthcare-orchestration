# Hardening

## Purpose

`T069` makes performance, security, and architecture hardening explicit release-blocking checks instead of manual review notes.

## Repository Commands

- `make harden`
- `bash scripts/architecture/check.sh`
- `bash scripts/performance/check.sh`
- `bash scripts/security/check.sh`

`make verify` includes all three hardening checks in addition to the existing format, analysis, test, build, and OpenAPI validation flow.

## Architecture Hardening

Architecture hardening is enforced by `tests/Architecture` and covers:

- domain code must not import Laravel or lower layers
- application code must not import presentation or infrastructure classes
- presentation code must not import module infrastructure classes
- application PHP files must stay within the `400` line hard limit unless they are explicitly listed in the reviewed exception register

The reviewed file-size exception register lives in [governance.php](/var/www/personal/said-team/portfolio/healthcare-orchestration/config/governance.php). Each exception is temporary, justified, and automatically flagged for removal once the file falls back under the hard limit.

## Security Hardening

Security hardening is enforced by `bash scripts/security/check.sh` and currently includes:

- Composer runtime dependency audit against `composer.lock`
- npm lockfile audit for production dependencies
- tracked-file secret scan for private-key material and high-risk token formats
- tracked environment file review that allows only `.env.example` and `.env.testing`

Composer abandoned-package notices remain manual review items. Security advisories fail the check.

## Performance Baselines

Performance baselines are enforced by [PlatformBaselineTest.php](/var/www/personal/said-team/portfolio/healthcare-orchestration/tests/Performance/PlatformBaselineTest.php).

The current release-blocking baseline checks average latency for:

- `GET /api/v1/ping` <= `75 ms`
- authenticated tenant-scoped `GET /api/v1/metrics` <= `350 ms`
- internal scrape `GET /internal/metrics` <= `200 ms`

The thresholds and iteration count are owned by [governance.php](/var/www/personal/said-team/portfolio/healthcare-orchestration/config/governance.php) so future tuning happens in one place.
