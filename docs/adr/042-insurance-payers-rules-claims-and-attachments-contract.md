# ADR 042: Insurance Payers, Rules, Claims, and Attachments Contract

Date: `2026-03-11`

## Status

Accepted

## Context

The canonical source of truth already defined the insurance route surface:

- payer CRUD under `/insurance/payers`
- rule CRUD under `/insurance/rules`
- claim CRUD, search, export, attachments, and lifecycle actions under `/claims`

Before `T055`, the repository did not define:

- the payer field set
- the rule field set and enforcement model
- the claim aggregate fields
- claim numbering
- generic claim edit and delete guards
- attachment metadata and storage rules
- search and export filters
- exact reopen behavior for adjudicated claims

Those decisions are required before implementation so claim code does not invent undocumented behavior.

## Decision

Implement insurance as tenant-scoped payer and rule catalogs plus a draft-first claim aggregate with explicit adjudication actions, attachment-backed evidence, and CSV export.

### 1. Payers

Each payer owns:

- `payer_id`
- `tenant_id`
- `code`
- `name`
- `insurance_code`
- optional `contact_name`
- optional `contact_email`
- optional `contact_phone`
- `is_active`
- optional `notes`
- `created_at`
- `updated_at`

Rules:

- payers are tenant-scoped
- `code` is required, normalized to uppercase, and unique per tenant
- `insurance_code` is required, normalized to lowercase, and unique per tenant
- `insurance_code` is the stable bridge to patient insurance links created in ADR `020`
- inactive payers remain readable but cannot be used for new or updated claims
- `DELETE /insurance/payers/{payerId}` hard-deletes only unreferenced payers
- a payer is referenced when any non-deleted claim points at it

`GET /insurance/payers` supports:

- `q`
- `insurance_code`
- `is_active`
- `limit`

`q` matches payer `code`, `name`, and `insurance_code`.

### 2. Insurance Rules

Each insurance rule owns:

- `rule_id`
- `tenant_id`
- `payer_id`
- `code`
- `name`
- optional `service_category`
- `requires_primary_policy`
- `requires_attachment`
- optional `max_claim_amount`
- optional `submission_window_days`
- `is_active`
- optional `notes`
- `created_at`
- `updated_at`

Read models also expose:

- `payer.id`
- `payer.code`
- `payer.name`
- `payer.insurance_code`

Rules:

- insurance rules are tenant-scoped and always belong to one payer
- `code` is required, normalized to uppercase, and unique per tenant
- `payer_id` must reference an existing tenant payer
- `service_category` is optional; when present, the rule applies only when the claim invoice snapshot contains at least one matching service category
- `max_claim_amount` is an optional positive decimal cap
- `submission_window_days` is an optional positive integer
- inactive rules remain readable but are ignored during claim submission checks
- delete is hard-delete

`GET /insurance/rules` supports:

- `q`
- `payer_id`
- `service_category`
- `is_active`
- `limit`

`q` matches rule `code`, `name`, and payer `name`.

### 3. Claim Aggregate

Each claim owns:

- `claim_id`
- `tenant_id`
- `claim_number`
- `payer_id`
- `payer_code`
- `payer_name`
- `payer_insurance_code`
- `patient_id`
- `patient_display_name`
- `invoice_id`
- `invoice_number`
- optional `patient_policy_id`
- optional `patient_policy_number`
- optional `patient_member_number`
- optional `patient_group_number`
- optional `patient_plan_name`
- `currency`
- `service_date`
- `billed_amount`
- optional `approved_amount`
- optional `paid_amount`
- optional `notes`
- `status`
- `attachment_count`
- `service_categories[]`
- optional `submitted_at`
- optional `review_started_at`
- optional `approved_at`
- optional `denied_at`
- optional `paid_at`
- optional `denial_reason`
- optional `last_transition`
- `adjudication_history[]`
- optional `deleted_at`
- `created_at`
- `updated_at`

Claim numbering rules:

- each tenant owns a monotonic claim counter
- format is `CLM-000001`, `CLM-000002`, and so on
- numbers are allocated on create and never reused

Create rules:

- `POST /claims` requires `invoice_id` and `payer_id`
- optional create fields are:
  - `patient_policy_id`
  - `service_date`
  - `billed_amount`
  - `notes`
- claims are tenant-scoped
- claims are created in `draft`
- the referenced invoice must exist in the current tenant and be in `issued|finalized`
- `service_date` defaults to the linked invoice date when omitted
- `billed_amount` defaults to the linked invoice total when omitted
- `billed_amount` must be positive and may not exceed the invoice total
- when `patient_policy_id` is supplied, it must reference a patient insurance link for the same invoice patient
- when `patient_policy_id` is supplied, the policy `insurance_code` must match the payer `insurance_code`
- claim create snapshots payer, patient, invoice, policy, and service-category data so later edits to reference records do not rewrite historical claims

CRUD rules:

- generic `PATCH /claims/{claimId}` is limited to claims in `draft`
- draft patches may update:
  - `payer_id`
  - `patient_policy_id`
  - `service_date`
  - `billed_amount`
  - `notes`
- generic `DELETE /claims/{claimId}` is a soft delete limited to `draft`
- deleted claims are excluded from list, search, and export

### 4. Claim Status and Lifecycle

Claim status values are:

- `draft`
- `submitted`
- `under_review`
- `approved`
- `denied`
- `paid`

Allowed transitions:

- `draft -> submitted`
- `submitted -> under_review`
- `under_review -> approved`
- `under_review -> denied`
- `approved -> paid`
- `approved|denied|paid -> submitted` through `reopen`

Required guards:

- only `draft` claims may use generic CRUD write routes
- only `submitted` claims may enter review
- only `under_review` claims may be approved or denied
- only `approved` claims may be marked paid
- `approve` requires a positive `approved_amount` not greater than `billed_amount`
- `mark-paid` requires a positive `paid_amount` not greater than `approved_amount`
- `start-review`, `approve`, `deny`, `mark-paid`, and `reopen` each require:
  - `reason`
  - `source_evidence`
- `reopen` preserves prior adjudication data in `adjudication_history` and clears current decision-only fields so the claim can be reviewed again

Operational notes:

- `submit` records `submitted_at`
- `start-review` records `review_started_at`
- `approve` records `approved_at` and appends an adjudication history entry
- `deny` records `denied_at`, `denial_reason`, and appends an adjudication history entry
- `mark-paid` records `paid_at` and appends an adjudication history entry
- `reopen` records a new `submitted_at`, clears `review_started_at`, `approved_at`, `denied_at`, `paid_at`, `approved_amount`, `paid_amount`, and `denial_reason`, and appends a `reopened` adjudication history entry

### 5. Rule Enforcement

Rules are enforced at `POST /claims/{claimId}:submit`.

Only active rules for the claim payer are considered.

A rule applies when:

- the rule payer matches the claim payer, and
- `service_category` is null or matches at least one value in the claim invoice `service_categories[]` snapshot

Applicable rules enforce:

- `requires_attachment`: the claim must have at least one stored attachment
- `requires_primary_policy`: the claim must reference a patient policy that is currently marked primary
- `max_claim_amount`: `billed_amount` must not exceed the configured amount
- `submission_window_days`: the claim `service_date` must fall within the configured age window measured at submit time

Submission fails closed with `422` when any applicable rule is violated.

### 6. Attachments

Each claim attachment owns:

- `attachment_id`
- `tenant_id`
- `claim_id`
- optional `attachment_type`
- optional `notes`
- `file_name`
- `mime_type`
- `size_bytes`
- `disk`
- `path`
- `uploaded_at`
- `created_at`
- `updated_at`

Rules:

- attachments are tenant-scoped and claim-scoped
- upload uses the shared attachment storage abstraction from `T010`
- file metadata is stored in the claim module, while binary storage remains on the configured attachments disk
- `GET /claims/{claimId}/attachments` returns attachments ordered by `uploaded_at DESC`, then `attachment_id DESC`
- deleting an attachment removes metadata and performs best-effort blob deletion
- attachments remain writable for any non-deleted claim so reopened or denied claims can gather more evidence

### 7. Read, Search, and Export Contract

`GET /claims` and `GET /claims/search` support:

- `q`
- `status`
- `payer_id`
- `patient_id`
- `invoice_id`
- `service_date_from`
- `service_date_to`
- `created_from`
- `created_to`
- `limit`

Rules:

- default `limit` is `25`
- maximum `limit` is `100`
- export maximum `limit` is `1000`
- `q` matches claim number, invoice number, patient display name, payer code, payer name, policy number, and notes
- list and search return the same tenant-scoped read model and differ only by route intent
- results order by `COALESCE(paid_at, approved_at, denied_at, review_started_at, submitted_at, created_at) DESC`, then `claim_number DESC`
- `GET /claims/export` supports only `format=csv` in `T055`

### 8. Audit and Event Contract

`T055` writes immutable audit actions:

- `payers.created`
- `payers.updated`
- `payers.deleted`
- `insurance_rules.created`
- `insurance_rules.updated`
- `insurance_rules.deleted`
- `claims.created`
- `claims.updated`
- `claims.deleted`
- `claims.submitted`
- `claims.review_started`
- `claims.approved`
- `claims.denied`
- `claims.paid`
- `claims.reopened`
- `claims.exported`
- `claim_attachments.uploaded`
- `claim_attachments.deleted`

All claim lifecycle events use `object_type = claim`.

Claim attachment events use `object_type = claim_attachment`.

`T055` publishes outbox-backed claim events on `medflow.claims.v1`:

- `claim.created`
- `claim.submitted`
- `claim.review_started`
- `claim.approved`
- `claim.denied`
- `claim.paid`
- `claim.reopened`

## Consequences

### Positive

- the insurance surface now has a documented, testable contract before implementation
- patient insurance links from ADR `020` can be reused without redefining payer linkage
- claim lifecycle behavior is explicit and suitable for audit and outbox publication
- attachments and rule enforcement stay within the shared storage and clean-architecture constraints

### Negative

- payer linkage currently depends on matching `insurance_code` instead of a foreign key from patient insurance links
- rules are intentionally limited to payer, service category, evidence, amount, and submission-window checks in this phase
- remittance files, ERA ingestion, and balance allocation remain out of scope

## Follow-up Rules

- future remittance, EDI, ERA, or settlement behavior must update this ADR before implementation
- if patient insurance links later become foreign-key-backed to payers, this ADR and ADR `020` must be updated together
- any new claim transition or rule type must update the state-machine and rule-enforcement sections in the same change
