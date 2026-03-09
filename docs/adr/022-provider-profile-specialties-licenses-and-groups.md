# ADR 022: Provider Profile, Specialties, Licenses, and Groups

## Status

Accepted

## Date

2026-03-10

## Context

The canonical route inventory defines provider profile, specialty, license, and provider-group routes:

- `GET /providers/{providerId}/profile`
- `PATCH /providers/{providerId}/profile`
- `GET /providers/{providerId}/specialties`
- `PUT /providers/{providerId}/specialties`
- `GET /providers/{providerId}/licenses`
- `POST /providers/{providerId}/licenses`
- `DELETE /providers/{providerId}/licenses/{licenseId}`
- `GET /specialties`
- `POST /specialties`
- `PATCH /specialties/{specialtyId}`
- `DELETE /specialties/{specialtyId}`
- `GET /provider-groups`
- `POST /provider-groups`
- `PUT /provider-groups/{groupId}/members`

After `T033`, the repository has only the base provider master record and CRUD surface. The source documents do not define:

- which fields belong to the provider profile versus the base provider record
- whether specialties are global or tenant-owned
- how a provider chooses primary specialties
- which license fields are required and how license validity is derived
- whether provider groups are tenant-owned and how members are replaced
- what validation rules must apply between provider profile clinic assignment and clinic department or room references

`T034` requires those decisions before implementation.

## Decision

Use tenant-owned provider extensions built on top of the base provider master record.

- All routes in this ADR are tenant-owned and require `X-Tenant-Id`.
- `providers.view` protects all provider reads.
- `providers.manage` protects all provider mutations.

## Provider Profile

Provider profile data is a one-to-one extension of the base provider record and is stored separately from the base provider table.

The profile contains:

- optional `professional_title`
- optional `bio`
- optional `years_of_experience`
- optional `department_id`
- optional `room_id`
- `is_accepting_new_patients`
- `languages`

### Profile Validation and Normalization

- `professional_title` is trimmed and nullable.
- `bio` is trimmed and nullable.
- `years_of_experience` is nullable and must be between `0` and `80`.
- `languages` is a normalized list:
  - trim each item
  - collapse repeated internal whitespace
  - discard empty values
  - deduplicate case-insensitively
  - sort alphabetically using case-insensitive comparison
- `is_accepting_new_patients` defaults to `true` when no profile row exists.
- `department_id` and `room_id` are optional.
- If either `department_id` or `room_id` is provided, the provider must have a non-null `clinic_id`.
- `department_id`, when present, must reference a department in the same tenant and the same clinic as the provider.
- `room_id`, when present, must reference a room in the same tenant and the same clinic as the provider.
- If both `department_id` and `room_id` are provided and the room has a department assignment, the room department must match `department_id`.

## Specialty Catalog

Specialties are tenant-owned catalog records.

Each specialty contains:

- `id`
- `tenant_id`
- `name`
- optional `description`
- timestamps

### Specialty Rules

- `name` is required, trimmed, and unique case-insensitively per tenant.
- `description` is trimmed and nullable.
- `GET /specialties` returns specialties ordered by `name asc`, then `created_at asc`.
- `PATCH /specialties/{specialtyId}` updates `name` and `description`.
- `DELETE /specialties/{specialtyId}` hard-deletes the specialty only when it is not assigned to any provider. Otherwise it fails with `409 Conflict`.

## Provider Specialty Assignment

Provider specialty assignment is modeled as a tenant-owned join set between providers and specialties.

- `PUT /providers/{providerId}/specialties` replaces the full active specialty set for the provider.
- The request payload uses `specialties`, an array of objects with:
  - `specialty_id`
  - optional `is_primary`
- Each specialty may appear only once in the replacement payload.
- At most one specialty may be marked primary.
- A provider may have zero specialties.
- `GET /providers/{providerId}/specialties` returns assigned specialties ordered by `is_primary desc`, `name asc`, and `assigned_at asc`.

## Provider Licenses

Provider licenses are tenant-owned provider child records.

Each license contains:

- `id`
- `provider_id`
- `license_type`
- `license_number`
- `issuing_authority`
- optional `jurisdiction`
- optional `issued_on`
- optional `expires_on`
- optional `notes`
- timestamps

### License Rules

- `license_type`, `license_number`, and `issuing_authority` are required.
- `license_type` is normalized to lowercase snake case.
- `license_number`, `issuing_authority`, `jurisdiction`, and `notes` are trimmed.
- `expires_on`, when present, must not be earlier than `issued_on`.
- The tuple `{provider_id, license_type, license_number}` must be unique.
- License status is derived:
  - `active` when `expires_on` is null or today is not later than `expires_on`
  - `expired` when today is later than `expires_on`
- `GET /providers/{providerId}/licenses` returns licenses ordered by `status asc`, `expires_on asc nulls last`, and `created_at asc`.
- `DELETE /providers/{providerId}/licenses/{licenseId}` hard-deletes the license.

## Provider Groups

Provider groups are tenant-owned coordination records for logical provider teams.

Each group contains:

- `id`
- `tenant_id`
- `name`
- optional `description`
- optional `clinic_id`
- timestamps

Each group exposes:

- `member_count`
- `member_ids`
- `members`

### Group Rules

- `name` is required, trimmed, and unique case-insensitively per tenant.
- `description` is trimmed and nullable.
- `clinic_id` is optional.
- When `clinic_id` is present it must reference an existing clinic in the current tenant.
- `PUT /provider-groups/{groupId}/members` replaces the entire membership set with the provided `provider_ids`.
- Every member must reference an active provider in the same tenant.
- `GET /provider-groups` returns groups ordered by `name asc`, then `created_at asc`, with members ordered by provider `last_name asc`, `first_name asc`, then `created_at asc`.

## Audit

The implementation records the following audit actions:

- `providers.profile_updated`
- `provider_specialties.created`
- `provider_specialties.updated`
- `provider_specialties.deleted`
- `providers.specialties_set`
- `providers.license_added`
- `providers.license_removed`
- `provider_groups.created`
- `provider_groups.members_updated`

Provider-specific records use `object_type = provider` with the provider identifier. Specialty catalog records use `object_type = specialty`. Group records use `object_type = provider_group`.

## Alternatives Considered

- store profile fields directly on `providers`
- use a global cross-tenant specialty catalog
- allow multiple primary specialties per provider
- support in-place license updates instead of append and remove semantics
- allow provider groups to mix providers from arbitrary clinics inside the tenant

## Consequences

- The base provider directory remains compact while richer scheduling-facing metadata lives behind the profile route.
- Tenant administrators can manage a local specialty catalog without changing global reference data.
- Provider groups become deterministic replacement-based collections with explicit membership state.
- Future availability and calendar work can reuse department, room, specialty, and group relationships without redefining them.

## Migration Plan

- add profile, specialty, specialty-assignment, license, provider-group, and provider-group-member tables
- implement provider services, commands, queries, handlers, repositories, and controllers
- update provider route documentation and OpenAPI
- cover profile, specialty, license, and group behavior with feature tests
