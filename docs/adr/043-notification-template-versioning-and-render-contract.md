# ADR 043: Notification Template Versioning and Render Contract

Date: `2026-03-12`

## Status

Accepted

## Context

The canonical source of truth already defined the notification template route surface:

- `GET /templates`
- `POST /templates`
- `GET /templates/{templateId}`
- `PATCH /templates/{templateId}`
- `DELETE /templates/{templateId}`
- `POST /templates/{templateId}:test-render`

Before `T056`, the repository did not define:

- the exact template field set
- channel-specific subject rules
- how template versions are stored
- delete behavior
- render placeholder syntax
- render failure behavior
- test-render request and response shape

Those decisions are material because notification content must remain predictable and auditable before later dispatch tasks build on it.

## Decision

Implement tenant-scoped notification templates as soft-deletable current projections plus immutable version rows, with strict curly-brace placeholder rendering and a dedicated test-render endpoint.

### 1. Template Model

Each notification template owns:

- `template_id`
- `tenant_id`
- `code`
- `name`
- `channel`
- optional `description`
- `is_active`
- `current_version`
- optional `subject_template`
- `body_template`
- `placeholders[]`
- optional `deleted_at`
- `created_at`
- `updated_at`

Rules:

- templates are tenant-scoped
- supported channel values are `email`, `sms`, and `telegram`
- `code` is required, normalized to uppercase, and unique per tenant among non-deleted templates
- `name` is required
- `description` is optional
- `is_active` defaults to `true`
- `DELETE /templates/{templateId}` is a soft delete
- a deleted template is excluded from `list`, `show`, and `test-render`
- deleting a template does not delete its stored version history rows

### 2. Channel-Specific Content Rules

- `email` templates require both `subject_template` and `body_template`
- `sms` and `telegram` templates require `body_template`
- `sms` and `telegram` never persist `subject_template`; any provided value is discarded during normalization
- `body_template` must be a non-empty string for every channel

### 3. Version Storage

Two tables define the contract:

- `notification_templates`: current projection
- `notification_template_versions`: immutable history

Versioning rules:

- create stores version `1`
- each successful update writes a new immutable version row and increments `current_version`
- no-op patches do not increment the version
- `GET /templates/{templateId}` returns the current projection plus all versions ordered by descending `version`
- each version row stores the full content snapshot:
  - `code`
  - `name`
  - `channel`
  - `description`
  - `is_active`
  - `subject_template`
  - `body_template`
  - `placeholders[]`

### 4. Render Syntax and Test-Render Contract

Placeholder syntax uses double curly braces with dot-path lookup:

- `{{patient.first_name}}`
- `{{appointment.start_at}}`
- `{{clinic.name}}`

Render rules:

- placeholder discovery is derived from current `subject_template` and `body_template`
- placeholders are deduplicated and sorted lexicographically
- variables are supplied as a JSON object under `variables`
- each placeholder path must resolve through nested JSON object keys
- resolved values may be:
  - string
  - integer
  - float
  - boolean
  - null
- booleans render as `true` or `false`
- `null` renders as an empty string
- arrays or objects at the final placeholder segment are rejected
- missing placeholder paths are rejected

`POST /templates/{templateId}:test-render` returns:

- `template_id`
- `code`
- `channel`
- `current_version`
- `placeholders[]`
- echoed `variables`
- optional `rendered_subject`
- `rendered_body`
- `rendered_at`

### 5. Filtering and Read Models

`GET /templates` supports:

- `q`
- `channel`
- `is_active`
- `limit`

`q` matches:

- `code`
- `name`
- `channel`
- `description`
- `subject_template`
- `body_template`

### 6. Audit Behavior

The system records audit events for:

- `notification_templates.created`
- `notification_templates.updated`
- `notification_templates.deleted`

Test-render requests are not audit-persisted because the payload may contain transient or regulated personal data and the endpoint does not mutate business state.

## Consequences

- later notification dispatch tasks can safely reference stable template versions
- operators can inspect current template content and history without reconstructing snapshots from audit diffs
- rendering failures are explicit and deterministic before any provider adapters are involved
- soft delete preserves auditability while allowing code reuse after archival
