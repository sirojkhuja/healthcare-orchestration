## ADR 051: Audit Retention, Export, and PII Governance Contract

Date: `2026-03-12`

## Status

Accepted

## Context

The canonical source and split route catalog define the audit and compliance endpoints for:

- `GET /audit/events`
- `GET /audit/events/{eventId}`
- `GET /audit/export`
- `GET /audit/retention`
- `PUT /audit/retention`
- `GET /audit/object/{objectType}/{objectId}`
- `GET /compliance/pii-fields`
- `PUT /compliance/pii-fields`
- `POST /compliance/pii:rotate-keys`
- `POST /compliance/pii:re-encrypt`
- `GET /compliance/reports`

The repository already has immutable audit persistence plus the `audit:prune` operational command, but it does not define:

- which filters the generic audit queries accept
- how tenant scope applies to generic audit reads
- how retention settings are stored and how they affect pruning
- how PII fields are registered before every business module adopts registry-backed encryptors
- what the rotation and re-encryption commands do in this phase
- which permissions gate the new routes

Implementing `T063` without those decisions would create material undocumented behavior.

## Decision

Implement `T063` as a tenant-scoped audit and compliance control plane backed by three storage areas:

1. immutable audit-event queries and audit export
2. tenant-scoped audit-retention overrides with effective pruning rules
3. tenant-scoped PII field registry plus append-only compliance operation reports

### 1. Permission model

Add a dedicated compliance permission group with these fixed catalog entries:

- `audit.view`: list and inspect tenant-scoped audit events, object history, and audit retention settings
- `audit.manage`: update tenant audit retention settings and run audit export
- `compliance.view`: inspect the tenant PII registry and compliance reports
- `compliance.manage`: replace the PII registry and trigger key-rotation or re-encryption operations

All audit and compliance routes require authenticated tenant context through `X-Tenant-Id`.

### 2. Generic audit query contract

`GET /audit/events` returns immutable audit events in descending `occurred_at` order inside the active tenant scope only.

Supported query parameters:

- `q` optional free-text filter matched against `action`, `object_type`, `object_id`, and `actor_name`
- `action_prefix` optional prefix filter
- `object_type` optional exact filter
- `object_id` optional exact filter
- `actor_type` optional exact filter
- `actor_id` optional exact filter
- `occurred_from` optional inclusive ISO-8601 timestamp
- `occurred_to` optional inclusive ISO-8601 timestamp
- `limit` optional integer, default `50`, max `100`

`GET /audit/events/{eventId}` returns one event only when `tenant_id` matches the active tenant. Audit rows without `tenant_id` are not exposed through this tenant-scoped API in this phase.

`GET /audit/object/{objectType}/{objectId}` returns the same tenant-scoped event shape, newest first, with optional `action_prefix` and `limit` filters. This endpoint is the generic equivalent of module-specific audit timelines such as appointment audit history.

### 3. Audit export contract

`GET /audit/export` uses the same filters as `GET /audit/events` and supports only `format = csv`.

The export is synchronous in this phase and returns:

- `export_id`
- `format`
- `file_name`
- `row_count`
- `generated_at`
- `filters`
- `disk`
- `path`
- `visibility`

Exports are stored on the configured exports disk under:

`tenants/{tenantId}/audit/exports/{Y/m/d}/{fileName}`

Successful export writes immutable audit action:

- `audit.exported`

with:

- `object_type = audit_export`
- `object_id = export_id`
- `metadata.filters` containing the applied query criteria

### 4. Audit retention contract

The environment variable `AUDIT_RETENTION_DAYS` remains the platform default retention window.

`PUT /audit/retention` manages a tenant-specific override record:

- `retention_days` required integer `0..3650`

Rules:

- `retention_days > 0` enables pruning for this tenant using the provided day window
- `retention_days = 0` explicitly disables pruning for this tenant
- tenants without an override fall back to the platform default

`GET /audit/retention` returns:

- `tenant_id`
- `default_retention_days`
- `tenant_retention_days`
- `effective_retention_days`
- `pruning_enabled`
- `updated_at`

The `audit:prune` command must honor effective policy per tenant:

- use tenant override when present
- skip tenants whose override is `0`
- use the platform default for tenants without an override
- use the platform default for audit rows with `tenant_id = null`

Retention updates write immutable audit action:

- `audit.retention_updated`

with `object_type = audit_retention_policy` and `object_id = tenant_id`.

### 5. PII field registry contract

`GET /compliance/pii-fields` returns active and retired registry entries for the current tenant ordered by `object_type`, `field_path`, and creation time.

Each registry entry stores:

- `field_id`
- `tenant_id`
- `object_type`
- `field_path`
- `classification`
- `encryption_profile`
- `key_version`
- `status = active|retired`
- `notes`
- `last_rotated_at`
- `last_reencrypted_at`
- timestamps

Allowed `classification` values:

- `identity`
- `contact`
- `government_id`
- `clinical`
- `financial`
- `biometric`
- `other`

Allowed `encryption_profile` values:

- `encrypted_string`
- `encrypted_json`

`PUT /compliance/pii-fields` performs replacement semantics for the active tenant:

- request body requires `fields[]`
- entries are matched by normalized `object_type + field_path`
- existing matching rows are updated in place
- omitted active rows are marked `retired`
- new rows are inserted as `active`
- `key_version` defaults to `1` for new rows

Replacement writes immutable audit action:

- `compliance.pii_fields_replaced`

with `object_type = pii_registry`.

### 6. Rotation and re-encryption contract

`POST /compliance/pii:rotate-keys` and `POST /compliance/pii:re-encrypt` both accept:

- `field_ids` optional unique array of registry ids

When `field_ids` is omitted, the operation targets all active registry rows in the current tenant.

`POST /compliance/pii:rotate-keys`:

- increments `key_version` for targeted active rows
- sets `last_rotated_at = now`
- creates one append-only compliance report row of type `pii_key_rotation`

`POST /compliance/pii:re-encrypt` in this phase is a control-plane re-encryption cycle:

- it records `last_reencrypted_at = now` for targeted active rows
- it does not rewrite arbitrary business rows yet because registry-backed field encryptors are adopted module by module in later hardening work
- it creates one append-only compliance report row of type `pii_reencryption`

Both operations return the created compliance report summary immediately with `status = completed`.

Both operations write immutable audit actions:

- `compliance.pii_keys_rotated`
- `compliance.pii_fields_reencrypted`

### 7. Compliance reports contract

`GET /compliance/reports` lists append-only compliance operation reports newest first.

Supported filters:

- `type` optional exact filter with `pii_key_rotation|pii_reencryption`
- `status` optional exact filter with `completed`
- `limit` optional integer, default `50`, max `100`

Each report contains:

- `report_id`
- `tenant_id`
- `type`
- `status`
- `requested_field_count`
- `processed_field_count`
- `skipped_field_count`
- `field_ids`
- `summary`
- `created_at`
- `completed_at`

## Consequences

Positive:

- `T063` gains an explicit contract before implementation
- audit reads, export, and retention rules become tenant-safe and testable
- PII governance is operational now without pretending every business module already uses registry-backed encryptors
- later hardening work can evolve the data-plane encryption adapters without changing the audit/compliance HTTP surface

Trade-offs:

- tenant-scoped audit routes do not expose global non-tenant audit rows in this phase
- re-encryption is limited to registry-governance state until later module adoption work lands
- retention pruning becomes more complex because it must honor tenant overrides
