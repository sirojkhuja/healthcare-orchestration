# ADR 023: Provider Availability Rules, Slot Generation, and Cache Rebuild

## Status

Accepted

## Date

2026-03-10

## Context

The canonical route inventory defines scheduling-owned availability routes under provider scope:

- `GET /providers/{providerId}/availability/rules`
- `POST /providers/{providerId}/availability/rules`
- `PATCH /providers/{providerId}/availability/rules/{ruleId}`
- `DELETE /providers/{providerId}/availability/rules/{ruleId}`
- `GET /providers/{providerId}/availability/slots`
- `POST /providers/{providerId}/availability:rebuild-cache`

The source documents also require:

- tenant-aware Redis caching for provider availability
- clinic settings with `timezone`, `default_appointment_duration_minutes`, and `slot_interval_minutes`
- clinic weekly work hours and clinic holidays
- provider master records with optional `clinic_id`

After `T034`, the repository still lacks:

- a canonical availability-rule data model
- slot-generation behavior
- cache-warming and invalidation behavior for provider availability
- an explicit relationship between scheduling availability rules and the later provider work-hours and time-off routes in `T036`

`T035` requires those decisions before implementation.

## Decision

Use scheduling-owned availability rules as the canonical low-level scheduling model for provider availability. `T036` provider work-hours and time-off routes will become provider-facing projections and adapters built on top of this rule model rather than introducing a second competing schedule source.

- Availability routes are tenant-owned and require `X-Tenant-Id`.
- `providers.view` protects availability reads.
- `providers.manage` protects availability mutations and cache rebuilds.
- Scheduling mutation routes use idempotency protection. The availability mutation routes and rebuild route require the `Idempotency-Key` header.

## Availability Rule Model

An availability rule is tenant-owned and provider-owned.

Each rule contains:

- `id`
- `tenant_id`
- `provider_id`
- `scope_type`
- `availability_type`
- optional `weekday`
- optional `specific_date`
- `start_time`
- `end_time`
- optional `notes`
- timestamps

### Scope Types

- `weekly`
- `date`

### Availability Types

- `available`
- `unavailable`

### Scope Validation

- `scope_type = weekly` requires `weekday` and forbids `specific_date`.
- `scope_type = date` requires `specific_date` and forbids `weekday`.
- `weekday` uses lowercase values `monday` through `sunday`.
- `start_time` and `end_time` use 24-hour `HH:MM` format and require `start_time < end_time`.

### Conflict Rules

- Rules are checked only within the same tenant and provider.
- Rules conflict when they have the same `scope_type`, the same `availability_type`, the same weekday or specific date, and overlapping time ranges.
- `weekly available` rules may overlap `date unavailable` rules because the date-scoped unavailability is intended to subtract from the weekly schedule.
- `weekly available` rules may overlap `date available` rules because date-scoped availability may extend a specific day without changing the weekly template.
- `weekly unavailable` rules may overlap `date available` rules because the date-scoped availability may reopen a specific day.

## Read Ordering

- `GET /providers/{providerId}/availability/rules` returns rules ordered by:
  - `scope_type asc`
  - `weekday asc nulls last`
  - `specific_date asc nulls last`
  - `start_time asc`
  - `created_at asc`

## Slot Generation

`GET /providers/{providerId}/availability/slots` is a scheduling view, not an appointment-booking view.

The request accepts:

- `date_from`
- `date_to`
- optional `limit`

`POST /providers/{providerId}/availability:rebuild-cache` accepts the same date window and limit in the JSON request body so the cache can be warmed deterministically for the target range.

### Slot Generation Rules

- `date_from` and `date_to` are required dates.
- `date_to` must not be earlier than `date_from`.
- The maximum slot-generation window is `31` calendar days.
- `limit` defaults to `200` and may not exceed `1000`.
- Only active providers in tenant scope may generate slots.
- If the provider has a `clinic_id` and that clinic is `inactive`, the slot result is empty.
- The effective timezone is:
  - clinic settings `timezone` when provider clinic exists and the clinic override is present
  - otherwise tenant settings `timezone`
  - otherwise application timezone
- The effective slot duration is:
  - clinic settings `default_appointment_duration_minutes` when provider clinic exists
  - otherwise `30`
- The effective slot interval is:
  - clinic settings `slot_interval_minutes` when provider clinic exists
  - otherwise `15`

### Effective Availability Computation

For each date in range:

1. Collect weekly rules whose `weekday` matches the date.
2. Collect date rules whose `specific_date` matches the date.
3. Union all `available` intervals for that date.
4. Subtract all `unavailable` intervals for that date.
5. If the provider has a clinic, intersect the remaining intervals with clinic work hours for that weekday.
6. If the provider has a clinic and the date falls inside a closed clinic holiday (`is_closed = true`), the result is empty for that date.
7. Split the remaining intervals into slots using the effective slot duration and slot interval.
8. Emit only slots where `start_at + duration <= interval_end`.

### Slot Response

The slot response returns:

- `provider_id`
- `timezone`
- `date_from`
- `date_to`
- `slot_duration_minutes`
- `slot_interval_minutes`
- `generated_at`
- `slots`

Each slot contains:

- `start_at`
- `end_at`
- `date`
- `source_rule_ids`

### Scope of T035

- Slot generation in `T035` reflects availability rules, clinic constraints, and configuration only.
- It does not subtract booked appointments yet because appointment scheduling is introduced in `T037` and later tasks.

## Cache Behavior

- Availability slots use the shared `availability` cache domain with tenant-prefixed keys.
- The slot query is cache-aside.
- The cache key segments are:
  - `provider`
  - `{providerId}`
  - `{date_from}`
  - `{date_to}`
  - `{limit}`
- Availability mutations invalidate the tenant-scoped `availability` namespace.
- Rebuild-cache invalidates the tenant-scoped `availability` namespace and then warms the requested provider slot range immediately.
- Provider lifecycle changes that affect `clinic_id`, soft delete state, or active visibility invalidate the tenant-scoped `availability` namespace.
- Clinic settings, clinic work hours, clinic holidays, and clinic lifecycle changes invalidate the tenant-scoped `availability` namespace because they change slot generation.

## Audit

Availability actions write these audit records:

- `availability.rules.created`
- `availability.rules.updated`
- `availability.rules.deleted`
- `availability.cache_rebuilt`

Rule records use `object_type = availability_rule` and the rule identifier. Cache rebuild uses `object_type = provider` and the provider identifier.

## Alternatives Considered

- make provider work-hours and time-off the canonical scheduling store before slot generation exists
- generate slots without clinic settings, clinic work hours, or clinic holidays
- use an unstructured JSON document for provider availability rules
- skip idempotency for scheduling availability mutations
- cache provider rules instead of cacheing slot views

## Consequences

- Scheduling gets a stable low-level availability engine before appointments exist.
- `T036` can provide work-hours and time-off management as a provider-facing layer over the same schedule source.
- Slot caching remains tenant-safe and explicitly invalidated when scheduling inputs change.
- Availability views stay deterministic and testable because their inputs and precedence rules are explicit.

## Migration Plan

- add scheduling availability-rule persistence
- implement rule CRUD, slot generation, and rebuild-cache endpoints
- invalidate availability cache from scheduling, provider, and clinic changes that affect slot generation
- update the canonical source, split docs, OpenAPI, tests, and task tracking to match the availability contract
