# ADR 016: Clinic Registry, Schedules, and Location Reference Data

## Status

Accepted

## Date

2026-03-09

## Context

The canonical route catalog defines clinic CRUD, clinic settings, departments, rooms, work hours, holidays, and location reference endpoints, but it does not define:

- which fields belong to a clinic record
- which lifecycle states a clinic can enter
- which fields belong to departments and rooms
- how clinic settings differ from tenant settings
- how clinic work hours and holidays are represented
- whether location data is tenant-owned or global

`T028` requires these decisions before implementation.

## Decision

Use a tenant-owned clinic registry with nested department, room, weekly schedule, and holiday records, plus a read-only global location catalog.

- `Clinic` is tenant-owned and contains `code`, `name`, `status`, `contact_email`, `contact_phone`, `city_code`, `district_code`, `address_line_1`, `address_line_2`, `postal_code`, and `notes`.
- `Clinic.code` is normalized to uppercase and must be unique within a tenant.
- Clinic lifecycle states are `active` and `inactive`.
- Clinics are created as `active`.
- `POST /clinics/{clinicId}:deactivate` transitions only from `active` to `inactive`.
- `POST /clinics/{clinicId}:activate` transitions only from `inactive` to `active`.
- `DELETE /clinics/{clinicId}` is allowed only while the clinic is `inactive`.
- Deleting a clinic cascades deletion of clinic settings, departments, rooms, work hours, and holidays owned by that clinic.
- `ClinicSettings` is a dedicated configuration document with the fields:
  - `timezone`
  - `default_appointment_duration_minutes`
  - `slot_interval_minutes`
  - `allow_walk_ins`
  - `require_appointment_confirmation`
  - `telemedicine_enabled`
- `ClinicSettings.timezone` is nullable. When it is `null`, tenant timezone remains the effective default.
- Default clinic settings are:
  - `timezone = null`
  - `default_appointment_duration_minutes = 30`
  - `slot_interval_minutes = 15`
  - `allow_walk_ins = true`
  - `require_appointment_confirmation = false`
  - `telemedicine_enabled = false`
- `Department` is clinic-owned and contains `code`, `name`, `description`, and `phone_extension`.
- `Department.code` is normalized to uppercase and must be unique within a clinic.
- `Room` is clinic-owned and contains `department_id`, `code`, `name`, `type`, `floor`, `capacity`, and `notes`.
- `Room.code` is normalized to uppercase and must be unique within a clinic.
- `Room.department_id` is nullable. When present, it must point to a department in the same clinic.
- `Room.type` is one of `consultation`, `treatment`, `imaging`, `laboratory`, `operating`, `administrative`, `virtual`, or `other`.
- `Room.capacity` is an integer greater than or equal to `1`.
- Clinic work hours are a full weekly schedule replacement keyed by `monday` through `sunday`.
- Each day contains zero or more intervals with `start_time` and `end_time` in 24-hour `HH:MM` format.
- Intervals for the same day must be sorted, non-overlapping, and have `start_time < end_time`.
- `GET /clinics/{clinicId}/work-hours` always returns all seven weekdays. Days without intervals return an empty list.
- Clinic holidays are clinic-owned records with `name`, `start_date`, `end_date`, `is_closed`, and `notes`.
- Holiday date ranges are inclusive, require `start_date <= end_date`, and must not overlap another holiday range in the same clinic.
- Location endpoints expose approved, read-only global reference data. They are not tenant-owned records.
- The first implementation ships an application-owned Uzbekistan reference catalog from configuration, with cities and districts identified by stable codes.
- `GET /locations/cities` may filter by `q`.
- `GET /locations/districts` requires `city_code` and returns districts only for that city.
- `GET /locations/search` requires `q` and returns mixed city and district matches with a `type` discriminator.
- Clinic, clinic settings, department, room, work hours, and holiday mutations all write audit records.
- Tenant usage for `clinics` counts live clinic records in the `clinics` table.

## Alternatives Considered

- store clinic settings, work hours, and holidays in one untyped JSON document
- make departments and rooms global tenant-owned records without a clinic boundary
- store location reference data as tenant-editable rows
- allow clinic deletion while active

## Consequences

- The first clinic-management iteration has a stable contract for address, schedule, and facility inventory behavior.
- Scheduling and provider modules can reuse clinic settings, rooms, holidays, and weekly schedules without redefining them.
- Location data stays governed and read-only, which avoids tenant drift in administrative geography.
- Clinic deletion is safe and deterministic because lifecycle and cascade rules are explicit.

## Migration Plan

- add clinic, clinic settings, department, room, clinic work hours, and clinic holiday persistence
- implement clinic CRUD, lifecycle, nested resources, and location reference query endpoints
- update the canonical source, split API docs, OpenAPI, tests, and tenant usage behavior to match the clinic contract
