# ADR 024: Provider Work Hours, Time-Off, and Calendar Views

## Status

Accepted

## Date

2026-03-10

## Context

The canonical route inventory defines these provider-facing and scheduling-facing routes after `T035`:

- `GET /providers/{providerId}/calendar`
- `GET /providers/{providerId}/calendar/export`
- `GET /providers/{providerId}/work-hours`
- `PUT /providers/{providerId}/work-hours`
- `GET /providers/{providerId}/time-off`
- `POST /providers/{providerId}/time-off`
- `PATCH /providers/{providerId}/time-off/{timeOffId}`
- `DELETE /providers/{providerId}/time-off/{timeOffId}`

`ADR 023` already established that scheduling-owned availability rules are the canonical low-level schedule source and that `T036` must project provider work-hours and time-off onto that same model instead of introducing a second schedule store.

What remained undefined before `T036`:

- how provider work-hours map onto weekly availability rules
- how provider time-off maps onto date-scoped unavailability
- what the provider calendar response contains before appointments exist
- how calendar export is generated and stored
- which audit actions and idempotency rules apply to the new routes

## Decision

Use the existing `provider_availability_rules` table as the sole persisted schedule model for provider work-hours and time-off.

- Provider work-hours are a provider-facing projection over weekly availability rules.
- Provider time-off is a provider-facing projection over date-scoped `unavailable` rules.
- Provider calendar is a scheduling read model built from:
  - provider weekly work-hours
  - provider time-off
  - clinic work-hours and closed holidays when the provider is assigned to a clinic
  - the existing slot-generation rules and cache behavior from `ADR 023`

No new schedule persistence table is introduced in `T036`.

## Authorization and Idempotency

- All routes remain tenant-owned and require `X-Tenant-Id`.
- `providers.view` protects:
  - `GET /providers/{providerId}/calendar`
  - `GET /providers/{providerId}/calendar/export`
  - `GET /providers/{providerId}/work-hours`
  - `GET /providers/{providerId}/time-off`
- `providers.manage` protects:
  - `PUT /providers/{providerId}/work-hours`
  - `POST /providers/{providerId}/time-off`
  - `PATCH /providers/{providerId}/time-off/{timeOffId}`
  - `DELETE /providers/{providerId}/time-off/{timeOffId}`
- Provider schedule mutations require `Idempotency-Key`.

## Provider Work-Hours Contract

### Read Model

`GET /providers/{providerId}/work-hours` returns a seven-day weekly template keyed by:

- `monday`
- `tuesday`
- `wednesday`
- `thursday`
- `friday`
- `saturday`
- `sunday`

Each day contains an ordered list of intervals:

- `start_time`
- `end_time`

Days with no intervals return an empty list.

The response also returns:

- `provider_id`
- `timezone`
- `updated_at`

`timezone` resolves in the same order as slot generation:

1. provider clinic settings timezone
2. tenant settings timezone
3. application timezone

### Projection Rules

Work-hours are derived only from weekly availability rules for the provider:

1. collect all `weekly available` intervals for the weekday
2. subtract any overlapping `weekly unavailable` intervals for the same weekday
3. sort the remaining intervals by `start_time asc`

Clinic work-hours and clinic holidays do not change the stored provider work-hours template. They only affect the calendar and slot read models.

### Update Model

`PUT /providers/{providerId}/work-hours` is replacement-based and uses:

- `days`

`days` must be an object keyed by weekday names. Unknown weekday keys are rejected.

Each interval must:

- be an object
- provide `start_time` and `end_time`
- use `HH:MM` 24-hour format
- satisfy `start_time < end_time`
- not overlap another interval on the same weekday

On write:

1. normalize all seven weekdays so omitted days become empty lists
2. compare the normalized payload against the current projected work-hours
3. if unchanged, return the current projection without rewriting rules
4. otherwise delete all existing weekly rules for the provider
5. create a canonical set of `weekly available` rules that exactly matches the submitted intervals

The work-hours endpoint therefore canonicalizes weekly schedules into non-overlapping available intervals. Low-level `weekly unavailable` rules remain supported through the direct availability-rule routes, but the first successful work-hours replacement rewrites the weekly template into canonical available intervals only.

## Provider Time-Off Contract

### Data Model

Provider time-off is a provider-facing wrapper over date-scoped `unavailable` availability rules.

Each time-off record contains:

- `id`
- `provider_id`
- `specific_date`
- `start_time`
- `end_time`
- `notes`
- `created_at`
- `updated_at`

Time-off records are single-date intervals. Multi-day leave is represented as multiple records.

### Read Ordering

`GET /providers/{providerId}/time-off` returns time-off records ordered by:

- `specific_date asc`
- `start_time asc`
- `created_at asc`

### Create and Update Rules

`POST /providers/{providerId}/time-off` requires:

- `specific_date`
- `start_time`
- `end_time`
- optional `notes`

`PATCH /providers/{providerId}/time-off/{timeOffId}` allows partial updates of the same fields.

Validation rules:

- `specific_date` uses `Y-m-d`
- `start_time` and `end_time` use `HH:MM`
- `start_time < end_time`
- the record is always persisted as:
  - `scope_type = date`
  - `availability_type = unavailable`

Conflict detection follows `ADR 023`. Two time-off records conflict only when they target the same provider, the same date, and overlapping unavailable intervals.

## Provider Calendar Contract

### Request Rules

`GET /providers/{providerId}/calendar` accepts:

- `date_from`
- `date_to`
- optional `limit`

The rules match the slot-generation contract:

- `date_from` and `date_to` are required
- `date_to` must not be earlier than `date_from`
- the window may span at most `31` calendar days
- `limit` defaults to `200`
- `limit` may not exceed `1000`

### Read Model

The provider calendar is a day-by-day operational view before appointment booking exists.

The response returns:

- `provider_id`
- `timezone`
- `date_from`
- `date_to`
- `slot_duration_minutes`
- `slot_interval_minutes`
- `generated_at`
- `days`

Each day contains:

- `date`
- `weekday`
- `is_clinic_closed`
- `work_hours`
- `time_off`
- `slot_count`
- `slots`

`work_hours` uses the same interval shape as the work-hours endpoint.

`time_off` contains the time-off records for that date.

`slots` contains the generated availability slots for that date using the existing slot shape:

- `start_at`
- `end_at`
- `date`
- `source_rule_ids`

### Computation Rules

For each date in range:

1. resolve provider work-hours from weekly rules
2. resolve time-off from matching date-scoped unavailable rules
3. detect whether the provider clinic is closed for that date because of a closed clinic holiday
4. fetch effective availability slots from the `ADR 023` slot engine
5. group slots by day

Consequences:

- work-hours show the provider template for that weekday
- time-off shows the date-specific provider exceptions
- slots show the effective schedule after applying time-off, clinic work-hours, clinic holiday closures, timezone, slot duration, and slot interval
- appointment occupancy is still out of scope until `T037` and later tasks

## Calendar Export Contract

`GET /providers/{providerId}/calendar/export` accepts the same query parameters as the calendar view plus optional:

- `format`

Only `csv` is supported in `T036`.

The export is stored through `FileStorageManager::storeExport()` on the private exports disk and returns:

- `export_id`
- `format`
- `file_name`
- `row_count`
- `generated_at`
- `filters`
- `storage`

The CSV is flattened to one row per date and contains:

- `date`
- `weekday`
- `is_clinic_closed`
- `work_hours`
- `time_off`
- `slot_count`
- `slot_start_times`
- `slot_end_times`

Interval and slot list columns are serialized as pipe-delimited strings so the file remains spreadsheet-friendly without introducing nested structures.

## Cache Behavior

- Provider work-hours writes invalidate the tenant-scoped `availability` cache namespace because they rewrite weekly rules.
- Provider time-off writes invalidate the tenant-scoped `availability` cache namespace because they rewrite date-scoped unavailable rules.
- Calendar reads do not maintain a second cache namespace in `T036`; they reuse the slot engine and its existing cache-aside behavior.

## Audit

`T036` adds these audit actions:

- `providers.work_hours_updated`
- `providers.time_off_created`
- `providers.time_off_updated`
- `providers.time_off_deleted`
- `providers.calendar_exported`

Audit object types:

- work-hours update: `provider`
- time-off actions: `provider_time_off`
- calendar export: `provider_calendar_export`

## Alternatives Considered

- store provider work-hours in a dedicated weekly JSON document
- store time-off in a dedicated provider-leave table
- make work-hours effective after clinic intersection instead of representing the provider-owned weekly template
- make the calendar endpoint return only slot data and omit work-hours and time-off
- create PDF or ICS export formats before the booking and appointment model exists

## Consequences

- Provider-facing schedule routes stay readable while preserving a single canonical rule store.
- Scheduling continues to own effective slot generation and cache behavior.
- The calendar view is useful before appointment booking exists because it exposes the provider template, date exceptions, and effective slots together.
- The work-hours route becomes the canonical high-level way to manage weekly schedules, while the low-level availability-rule routes remain available for advanced cases and direct troubleshooting.

## Migration Plan

- implement provider work-hours queries and replacement command on top of weekly availability rules
- implement provider time-off CRUD on top of date-scoped unavailable rules
- implement scheduling-owned provider calendar and calendar export views
- update source documents, OpenAPI, tests, and task tracking to match the contract
