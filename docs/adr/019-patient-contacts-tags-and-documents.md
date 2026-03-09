# ADR 019: Patient Contacts, Tags, and Documents

## Status

Accepted

## Date

2026-03-09

## Context

The canonical route inventory defines patient contact, tag, and document routes, but it does not define:

- what fields a patient contact contains
- whether a contact may omit both phone and email
- how primary and emergency contacts behave
- how patient tags are represented and normalized
- whether patient document routes return files, metadata, or public URLs
- how patient documents integrate with the shared attachment storage abstraction
- whether deleting a patient document removes the stored file

`T031` requires those decisions before implementation.

## Decision

Use tenant-scoped patient contacts, normalized patient tags, and attachment-backed patient document metadata.

- All patient contacts, tags, and documents belong to the active tenant through the parent patient.
- `patients.view` protects contact, tag, and document reads.
- `patients.manage` protects contact, tag, and document mutations.

### Contacts

- `GET /patients/{patientId}/contacts` returns active patient contacts ordered by:
  - `is_primary` descending
  - `is_emergency` descending
  - `name` ascending
  - `created_at` ascending
- `POST /patients/{patientId}/contacts` and `PATCH /patients/{patientId}/contacts/{contactId}` use the fields:
  - `name`
  - `relationship`
  - `phone`
  - `email`
  - `is_primary`
  - `is_emergency`
  - `notes`
- `name` is required.
- At least one of `phone` or `email` is required.
- `relationship` and `notes` are optional free-text fields.
- `email` is normalized to lowercase.
- `phone` is stored as a trimmed string.
- Only one active primary contact may exist per patient. Creating or updating a contact with `is_primary = true` clears the previous primary flag for that patient.
- Contact deletion hard-deletes the contact row and records an audit event.

### Tags

- `GET /patients/{patientId}/tags` returns the full active tag set for the patient.
- `PUT /patients/{patientId}/tags` replaces the entire active tag set.
- Tags are an array of strings.
- Tags are normalized by trimming whitespace, collapsing internal repeated whitespace, and lowercasing.
- Empty tags are discarded.
- Tag uniqueness is case-insensitive after normalization.
- The response returns normalized tags sorted alphabetically.

### Documents

- `GET /patients/{patientId}/documents` returns patient document metadata newest first.
- `POST /patients/{patientId}/documents` accepts multipart upload with:
  - `document` file
  - optional `title`
  - optional `document_type`
- Supported document upload types are:
  - `pdf`
  - `jpg`
  - `jpeg`
  - `png`
  - `webp`
- Maximum upload size is `10 MiB`.
- If `title` is not supplied, it defaults to the uploaded filename.
- `GET /patients/{patientId}/documents/{docId}` returns metadata only. It does not stream the file and does not expose a public URL or storage-internal disk/path values.
- Patient documents use the shared attachment storage abstraction on the private attachments disk.
- Stored patient document metadata contains:
  - `id`
  - `patient_id`
  - `title`
  - `document_type`
  - `file_name`
  - `mime_type`
  - `size_bytes`
  - `uploaded_at`
  - `created_at`
  - `updated_at`
- Deleting a patient document removes the metadata row and performs best-effort deletion of the stored file.
- Document responses expose only metadata that is safe for API clients.

### Audit

- Contact creation, update, and deletion write audit actions:
  - `patients.contact_created`
  - `patients.contact_updated`
  - `patients.contact_deleted`
- Tag replacement writes audit action `patients.tags_updated`.
- Document upload and deletion write audit actions:
  - `patients.document_uploaded`
  - `patients.document_deleted`

## Alternatives Considered

- embed contacts and tags directly on the patient master record as JSON
- allow multiple simultaneous primary contacts
- expose attachment storage paths or direct file URLs in patient document responses
- retain document metadata after deletion while removing the file
- support office document formats before a document-delivery contract exists

## Consequences

- Patient communication and emergency-contact data now has a stable tenant-scoped subresource model.
- Tag replacement stays simple and deterministic for future search and reporting work.
- Patient document APIs remain storage-safe by exposing metadata only, matching the repository pattern already used for avatar uploads.
- A later document-download or signed-artifact task can extend document access without redefining the stored metadata contract.

## Migration Plan

- add patient contact, tag, and document persistence
- integrate patient document uploads with the shared attachment storage abstraction
- expose the patient contact, tag, and document endpoints
- update the split patient API docs, canonical source, OpenAPI fragment, and tests
