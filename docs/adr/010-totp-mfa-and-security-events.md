# ADR 010: TOTP MFA and Security Event Tracking

## Status

Accepted

## Date

2026-03-08

## Context

The canonical source defines MFA endpoints and a `MFA_REQUIRED` error code, but it does not choose the MFA method, the login challenge flow, or the persistence model for MFA-related security events. `T022` requires MFA setup, verification, disable flow, and security event tracking.

## Decision

Use app-based TOTP MFA with recovery codes and a short-lived login challenge.

- MFA setup issues a Base32 TOTP secret, an `otpauth://` URI for authenticator apps, and one-time recovery codes.
- TOTP codes use `6` digits with a `30` second period and a bounded verification window.
- MFA secrets are encrypted at rest.
- Recovery codes are returned only at setup time and are stored only as SHA-256 hashes.
- Successful password login for an MFA-enabled user does not create an auth session immediately. It creates an MFA challenge and returns `401 MFA_REQUIRED` with a `challenge_id` and `expires_at`.
- `POST /auth/mfa/verify` completes either setup confirmation for the authenticated user or a pending login challenge for an MFA-enabled user.
- `POST /auth/mfa/disable` requires an authenticated session plus a valid TOTP code or recovery code.
- MFA and login-challenge activity writes both audit records and dedicated security events.

## Alternatives Considered

- email or SMS OTP as the primary MFA method
- issuing full auth sessions before MFA completion
- storing recovery codes in plaintext
- using audit events alone without a dedicated security-event store

## Consequences

- MFA works without introducing external delivery dependencies.
- Login flow gains an explicit challenge step for MFA-enabled accounts.
- Sensitive MFA material is protected at rest and recovery codes remain one-time credentials.
- IAM gets a reusable security-event foundation for future API key, device, IP allowlist, and login-anomaly work.

## Migration Plan

- add MFA credential, MFA challenge, and security event persistence
- add TOTP and recovery-code verification services
- update login to emit MFA challenges when MFA is enabled
- implement MFA setup, verify, and disable endpoints
- document the new auth and security-event contracts in the canonical source, split docs, and OpenAPI
