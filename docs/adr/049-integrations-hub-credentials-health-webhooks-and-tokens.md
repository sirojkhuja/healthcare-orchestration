# ADR 049: Integrations Hub Credentials, Health, Webhooks, and Tokens

Date: `2026-03-12`

## Status

Accepted

## Context

The canonical source already defined the integrations hub route inventory:

- `GET /integrations`
- `GET /integrations/{integrationKey}`
- `POST /integrations/{integrationKey}:enable`
- `POST /integrations/{integrationKey}:disable`
- `GET /integrations/{integrationKey}/credentials`
- `PUT /integrations/{integrationKey}/credentials`
- `DELETE /integrations/{integrationKey}/credentials`
- `GET /integrations/{integrationKey}/health`
- `POST /integrations/{integrationKey}:test-connection`
- `GET /integrations/{integrationKey}/logs`
- `GET /integrations/{integrationKey}/webhooks`
- `POST /integrations/{integrationKey}/webhooks`
- `DELETE /integrations/{integrationKey}/webhooks/{webhookId}`
- `POST /integrations/{integrationKey}/webhooks/{webhookId}:rotate-secret`
- `GET /integrations/{integrationKey}/tokens`
- `POST /integrations/{integrationKey}/tokens:refresh`
- `DELETE /integrations/{integrationKey}/tokens/{tokenId}`

The repository already had provider-specific payment, lab, SMS, Telegram, and email adapters, but it still lacked the shared hub behavior for:

- the tenant-scoped integration registry
- encrypted credential storage
- health and test-connection results
- append-only integration operation logs
- managed webhook inventory and secret rotation
- token inventory and refresh lifecycle

Those missing decisions are material because earlier ADRs explicitly deferred tenant-managed credential storage to `T061`, and implementing these endpoints without a written contract would violate the governance rules.

## Decision

Implement the integrations hub as a tenant-scoped operational surface backed by a static integration catalog plus encrypted tenant records for state, credentials, webhooks, tokens, and append-only logs.

### 1. Integration catalog and registry shape

The catalog is configuration-backed and defines the supported integration keys for this phase:

- `email`
- `telegram`
- `eskiz`
- `playmobile`
- `textup`
- `payme`
- `click`
- `uzum`
- `mock-lab`
- `myid`
- `eimzo`

Each catalog definition exposes:

- `integration_key`
- `name`
- `category`
- capability flags for `credentials`, `health`, `logs`, `webhooks`, `tokens`, and `test_connection`
- credential field schema
- optional default public webhook path and auth mode
- optional feature flag key for not-yet-enabled plug-ins

Registry rules:

- `GET /integrations` returns one row per known integration key
- `GET /integrations/{integrationKey}` returns the registry row plus summaries for credentials, health, webhooks, and tokens
- registry enablement is tenant-scoped
- catalog entries guarded by disabled feature flags remain visible with `available = false`
- `POST /integrations/{integrationKey}:enable` fails with `409` when the catalog entry is feature-flagged off
- `POST /integrations/{integrationKey}:disable` is always allowed for available entries

Registry enablement controls the integrations hub administrative surface and readiness state in this phase. Existing provider-specific runtime flows may still use documented module-level enable flags or configuration fallbacks until their adapters are explicitly upgraded.

### 2. Credential storage contract

`GET /integrations/{integrationKey}/credentials` returns:

- catalog field schema
- `configured = true|false`
- `source = tenant|none`
- masked value previews for stored fields
- `updated_at`

Security rules:

- raw secrets are never returned after persistence
- secret, token, and password values are encrypted at rest
- non-secret values may be returned as plain stored values
- secret previews use masking and only expose the last four characters when present

`PUT /integrations/{integrationKey}/credentials` fully replaces the stored credential payload for that tenant and integration key.

Validation rules:

- only catalog-declared fields are accepted
- values must be scalar strings or `null`
- required fields are defined by the catalog
- empty strings normalize to `null`

`DELETE /integrations/{integrationKey}/credentials` removes the tenant-stored credential payload and revokes all active hub-managed tokens for that integration key.

### 3. Health and test-connection behavior

`GET /integrations/{integrationKey}/health` returns:

- `status = healthy|degraded|failing|disabled`
- `enabled`
- `available`
- optional `last_test_status`
- optional `last_tested_at`
- ordered `checks[]`

Checks may include:

- `feature_flag`
- `credentials`
- `webhooks`
- `tokens`
- `catalog`

Status rules:

- unavailable or disabled integrations return `disabled`
- any failed check returns `failing`
- no failed checks but at least one warning returns `degraded`
- all checks passing returns `healthy`

`POST /integrations/{integrationKey}:test-connection` performs a deterministic readiness probe for this phase:

- validates catalog support
- validates enabled state
- validates required credentials
- validates required webhook and token preconditions when the catalog demands them
- writes the test result into the integration state record
- appends one integration log entry
- writes one audit event

This phase does not require outbound live traffic for test-connection. When provider adapters are still stubbed or feature-flagged off, the probe remains local and configuration-based.

### 4. Integration logs

Integration logs are stored in an append-only tenant-scoped table.

`GET /integrations/{integrationKey}/logs` supports:

- `level`
- `event`
- `limit`

Rules:

- default `limit` is `50`
- maximum `limit` is `100`
- logs include operation context but never raw secrets or raw tokens
- integrations hub mutations append operational log rows for enable, disable, credential update/delete, test-connection, webhook changes, token refresh, and token revoke

### 5. Webhook inventory and secret rotation

The hub stores tenant-managed webhook inventory records separately from the public Laravel routes.

`GET /integrations/{integrationKey}/webhooks` returns the stored webhook registrations for the current tenant.

`POST /integrations/{integrationKey}/webhooks` creates one webhook inventory record with:

- `name`
- derived `endpoint_url`
- `auth_mode`
- optional `metadata`
- generated secret when the catalog marks the integration as secret-managed

Rules:

- the callback path is catalog-derived
- the webhook secret is returned only once at creation time when generated or provided
- stored webhook secrets are encrypted at rest and hashed for verification-oriented future use
- unsupported integrations return `409` on create

`POST /integrations/{integrationKey}/webhooks/{webhookId}:rotate-secret`:

- is allowed only for secret-managed webhook integrations
- replaces the stored secret with a newly generated or provided secret
- returns the new secret exactly once
- records audit and log entries

`DELETE /integrations/{integrationKey}/webhooks/{webhookId}` removes only the tenant webhook inventory record. It does not remove or disable the underlying Laravel route implementation.

### 6. Token inventory and refresh lifecycle

The hub keeps a tenant-scoped token inventory for integrations that declare token support in the catalog.

Supported token behavior in this phase is generic and inventory-driven. Provider-specific OAuth exchanges remain future adapter work.

Token material enters the store in one of two ways:

- credential upsert includes token-related fields such as `access_token` or `refresh_token`
- a later provider-specific flow writes token rows through the same repository contract

`GET /integrations/{integrationKey}/tokens` returns token metadata only:

- `token_id`
- `label`
- `status = active|revoked|expired`
- `token_type`
- token previews
- scopes
- access and refresh expiry timestamps
- refresh and revoke timestamps

`POST /integrations/{integrationKey}/tokens:refresh`:

- supports an optional `token_id`
- refreshes the requested token or the latest active token
- requires an active row with a refresh token
- rotates the access token locally for this phase
- preserves the refresh token unless a future provider adapter changes that behavior
- updates expiry and refresh timestamps
- appends audit and integration-log records

`DELETE /integrations/{integrationKey}/tokens/{tokenId}` revokes the selected token row without deleting history.

### 7. Earlier provider tasks

This ADR extends earlier provider-specific ADRs as follows:

- email sender settings stay in the Notifications module, while transport credential inventory now lives under `/integrations/email/credentials`
- Telegram tenant delivery settings stay in the Notifications module, while bot credential inventory now lives under `/integrations/telegram/credentials`
- payment, lab, and SMS provider-specific routes keep their existing public contracts; the integrations hub adds the shared administrative inventory without redefining those routes

## Consequences

- the integrations route inventory becomes operational without inventing undocumented behavior
- secrets and tokens gain encrypted tenant-scoped storage plus audit and log coverage
- feature-flagged optional integrations can appear in the hub before their provider adapters arrive in `T062`
- provider-specific modules keep their stable runtime contracts while gaining a shared administrative control plane
