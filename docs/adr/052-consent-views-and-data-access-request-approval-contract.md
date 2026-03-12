## ADR 052: Consent Views and Data Access Request Approval Contract

Date: `2026-03-12`

## Status

Accepted

## Context

The canonical source and split route catalog define the compliance endpoints for:

- `GET /consents`
- `GET /consents/{consentId}`
- `GET /data-access-requests`
- `POST /data-access-requests`
- `POST /data-access-requests/{requestId}:approve`
- `POST /data-access-requests/{requestId}:deny`
- `GET /data-access-requests/{requestId}`

After `T063`, the repository already has:

- tenant-scoped patient consent records from ADR `020`
- tenant-scoped audit and compliance permissions from ADR `051`
- generic audit queries and object history for compliance review

It does not yet define:

- how tenant-wide consent views are projected from patient consent history
- which filters the compliance consent list supports
- what fields a data access request contains
- how approval and denial transitions behave
- which audit actions record data access request workflow changes

`T064` requires those decisions before implementation.

## Decision

Implement `T064` as a compliance read-and-review slice with:

1. read-only tenant-wide consent views backed by patient consent records
2. tenant-scoped data access request workflow records with explicit approval and denial actions

### 1. Permission model

- `compliance.view` protects:
  - `GET /consents`
  - `GET /consents/{consentId}`
  - `GET /data-access-requests`
  - `GET /data-access-requests/{requestId}`
- `compliance.manage` protects:
  - `POST /data-access-requests`
  - `POST /data-access-requests/{requestId}:approve`
  - `POST /data-access-requests/{requestId}:deny`

All routes require authenticated tenant context through `X-Tenant-Id`.

### 2. Consent view contract

`GET /consents` is a read-only tenant-scoped compliance projection over patient consent history from ADR `020`.

Each consent view entry returns:

- `id`
- `patient_id`
- `patient.display_name`
- `consent_type`
- `status`
- `granted_by_name`
- `granted_by_relationship`
- `granted_at`
- `expires_at`
- `revoked_at`
- `revocation_reason`
- `notes`
- `created_at`
- `updated_at`

Consent `status` remains derived exactly as defined in ADR `020`:

- `active`
- `expired`
- `revoked`

Supported `GET /consents` filters:

- `q` optional free-text filter matched against patient display name, `consent_type`, and `granted_by_name`
- `patient_id` optional exact UUID filter
- `consent_type` optional normalized exact filter
- `status` optional exact filter with `active|expired|revoked`
- `granted_from` optional inclusive ISO-8601 timestamp
- `granted_to` optional inclusive ISO-8601 timestamp
- `expires_from` optional inclusive ISO-8601 timestamp
- `expires_to` optional inclusive ISO-8601 timestamp
- `limit` optional integer, default `50`, max `100`

Consent list ordering is:

1. active consents first
2. `granted_at desc`
3. `created_at desc`

`GET /consents/{consentId}` returns one consent only when it belongs to the active tenant.

This compliance slice does not add new consent mutation routes. Patient-owned create and revoke behavior remains in ADR `020`.

### 3. Data access request contract

Data access requests are tenant-scoped workflow records linked to one patient.

Each record stores:

- `request_id`
- `tenant_id`
- `patient_id`
- `patient.display_name`
- `request_type`
- `status`
- `requested_by_name`
- `requested_by_relationship`
- `requested_at`
- `reason`
- `notes`
- `approved_at`
- `approved_by.user_id`
- `approved_by.name`
- `denied_at`
- `denied_by.user_id`
- `denied_by.name`
- `denial_reason`
- `decision_notes`
- `created_at`
- `updated_at`

`request_type` is a machine-readable string normalized to lowercase snake case.

Allowed workflow statuses:

- `submitted`
- `approved`
- `denied`

### 4. Create request behavior

`POST /data-access-requests` accepts:

- `patient_id` required UUID
- `request_type` required string
- `requested_by_name` required string
- `requested_by_relationship` optional string
- `requested_at` optional ISO-8601 timestamp, default `now`
- `reason` optional string
- `notes` optional string

Rules:

- the patient must exist in the active tenant
- empty strings normalize to `null` for optional text fields
- a created record starts in `submitted`

Successful creation writes immutable audit action:

- `compliance.data_access_request_created`

with:

- `object_type = data_access_request`
- `object_id = request_id`

### 5. Review workflow

`POST /data-access-requests/{requestId}:approve` accepts:

- `decision_notes` optional string

Rules:

- only `submitted` requests may be approved
- approval sets `status = approved`
- approval sets `approved_at = now`
- approval stores the acting reviewer identity from the authenticated actor context
- approval does not set denial fields

Successful approval writes immutable audit action:

- `compliance.data_access_request_approved`

`POST /data-access-requests/{requestId}:deny` accepts:

- `reason` required string
- `decision_notes` optional string

Rules:

- only `submitted` requests may be denied
- denial sets `status = denied`
- denial sets `denied_at = now`
- denial stores the acting reviewer identity from the authenticated actor context
- denial persists the required `denial_reason`

Successful denial writes immutable audit action:

- `compliance.data_access_request_denied`

Any approve or deny action against an `approved` or `denied` request returns `409 Conflict`.

### 6. Data access request query contract

`GET /data-access-requests` returns tenant-scoped workflow records ordered by:

1. `submitted` first
2. `requested_at desc`
3. `created_at desc`

Supported filters:

- `q` optional free-text filter matched against patient display name, `request_type`, and `requested_by_name`
- `patient_id` optional exact UUID filter
- `request_type` optional normalized exact filter
- `status` optional exact filter with `submitted|approved|denied`
- `requested_from` optional inclusive ISO-8601 timestamp
- `requested_to` optional inclusive ISO-8601 timestamp
- `limit` optional integer, default `50`, max `100`

`GET /data-access-requests/{requestId}` returns one request only when it belongs to the active tenant.

## Consequences

Positive:

- compliance users can inspect patient consent history without using patient-scoped endpoints
- data access review work gains an explicit tenant-safe approval contract
- the workflow is auditable through explicit compliance audit actions
- later fulfillment or export-delivery work can extend the request record without redefining approval behavior

Trade-offs:

- the workflow currently ends at approval or denial and does not model downstream fulfillment
- request types remain free-form normalized identifiers in this phase instead of a locked enum catalog
- consent views remain projections over patient-owned consent records rather than a separate compliance ledger
