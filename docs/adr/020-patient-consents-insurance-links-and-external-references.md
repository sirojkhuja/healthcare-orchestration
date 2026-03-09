# ADR 020: Patient Consents, Insurance Links, and External References

## Status

Accepted

## Date

2026-03-10

## Context

The canonical route inventory defines:

- `GET /patients/{patientId}/consents`
- `POST /patients/{patientId}/consents`
- `POST /patients/{patientId}/consents/{consentId}:revoke`
- `GET /patients/{patientId}/insurance`
- `POST /patients/{patientId}/insurance`
- `DELETE /patients/{patientId}/insurance/{policyId}`
- `GET /patients/{patientId}/external-refs`
- `POST /patients/{patientId}/external-refs`
- `DELETE /patients/{patientId}/external-refs/{refId}`

After `T031`, the patient module has no contract for:

- what fields a patient consent contains
- how active, expired, and revoked consents are represented
- whether duplicate active consents of the same type are allowed
- what fields define a patient insurance link before the payer catalog exists
- how primary insurance assignment behaves
- what an external reference mapping contains
- whether patient external references are tied to a future integrations registry or may exist independently

`T032` requires those decisions before implementation.

## Decision

Use tenant-scoped patient consent records, lightweight patient insurance policy links, and integration-keyed patient external-reference mappings.

- All three subresources belong to the active tenant through the parent patient.
- `patients.view` protects read endpoints for consents, insurance links, and external references.
- `patients.manage` protects create, revoke, attach, and delete mutations for these patient-scoped resources.
- Mutation audit records use `object_type = patient` and `object_id = {patientId}` so patient timelines can include subresource activity through metadata.

### Patient Consents

- `GET /patients/{patientId}/consents` returns patient consent records ordered by:
  - active consents first
  - then `granted_at` descending
  - then `created_at` descending
- `POST /patients/{patientId}/consents` creates a patient consent with:
  - `consent_type`
  - `granted_by_name`
  - optional `granted_by_relationship`
  - optional `granted_at`
  - optional `expires_at`
  - optional `notes`
- `consent_type` is a machine-readable string, normalized to lowercase snake case.
- `granted_by_name` is required.
- `granted_at` defaults to the current timestamp when omitted.
- `expires_at` is optional and must be later than `granted_at` when present.
- Consent status is derived:
  - `active` when not revoked and not expired
  - `expired` when `expires_at` is in the past and `revoked_at` is null
  - `revoked` when `revoked_at` is present
- At most one active consent of the same `consent_type` may exist for the same patient at a time.
- `POST /patients/{patientId}/consents/{consentId}:revoke` sets `revoked_at` and stores optional `reason`.
- Revoking an already revoked consent is a conflict.
- Consent records are retained for audit and compliance history and are never hard-deleted in normal flows.

### Patient Insurance Links

- `GET /patients/{patientId}/insurance` returns attached patient insurance policies ordered by:
  - `is_primary` descending
  - `effective_from` descending with nulls last
  - `created_at` descending
- `POST /patients/{patientId}/insurance` attaches a patient insurance link with:
  - `insurance_code`
  - `policy_number`
  - optional `member_number`
  - optional `group_number`
  - optional `plan_name`
  - optional `effective_from`
  - optional `effective_to`
  - optional `is_primary`
  - optional `notes`
- `insurance_code` is a stable string intended to align with the future shared insurance-code catalog, but `T032` does not require the shared reference endpoint to exist yet.
- `policy_number` is required.
- `effective_to` must not be earlier than `effective_from`.
- A patient may have multiple insurance links.
- Only one insurance link may be primary for a patient at a time. Attaching a new primary policy clears the previous primary flag.
- A duplicate link for the same patient, `insurance_code`, and `policy_number` is a conflict.
- `DELETE /patients/{patientId}/insurance/{policyId}` hard-deletes the insurance link and records an audit event.

### Patient External References

- `GET /patients/{patientId}/external-refs` returns external reference mappings ordered by:
  - `integration_key` ascending
  - `external_type` ascending
  - `created_at` ascending
- `POST /patients/{patientId}/external-refs` attaches an external reference with:
  - `integration_key`
  - `external_id`
  - optional `external_type`
  - optional `display_name`
  - optional `metadata`
- `integration_key` is a lowercase integration identifier and does not require a corresponding enabled integration record during `T032`.
- `external_id` is required.
- `external_type` defaults to `patient` when omitted.
- `metadata` is an optional JSON object for provider-specific reference hints and must remain safe to expose to internal API clients.
- A patient may have multiple external references, but the tuple `{patient_id, integration_key, external_type, external_id}` must be unique.
- `DELETE /patients/{patientId}/external-refs/{refId}` hard-deletes the mapping and records an audit event.

### Audit

- Consent creation and revocation write patient audit actions:
  - `patients.consent_created`
  - `patients.consent_revoked`
- Insurance link attach and delete write patient audit actions:
  - `patients.insurance_attached`
  - `patients.insurance_detached`
- External reference attach and delete write patient audit actions:
  - `patients.external_ref_attached`
  - `patients.external_ref_detached`

## Alternatives Considered

- defer patient consent storage until the later compliance work
- require the payer catalog before attaching patient insurance
- require the integrations registry before mapping external references
- hard-delete revoked consent history
- make insurance links clinic-owned instead of patient-owned

## Consequences

- Patient consent history becomes explicit and queryable before the broader compliance views are implemented.
- Insurance links can be attached now without blocking on the later payer and claims catalog work.
- External identifiers can be mapped to patients early while keeping future integrations-hub governance independent.
- Patient audit timelines can include consent, insurance, and external-reference activity without redefining the timeline contract.

## Migration Plan

- add tenant-scoped persistence for patient consents, insurance links, and patient external references
- implement patient consent, insurance-link, and external-reference endpoints
- update the patient API documents, canonical source, OpenAPI fragment, and tests
- let later compliance and insurance work reuse these persisted records instead of redefining them
