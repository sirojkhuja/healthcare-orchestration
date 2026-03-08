# Tenancy and Clinics API

## Tenants

- `GET /tenants` -> `ListTenantsQuery` -> Tenancy
- `POST /tenants` -> `CreateTenantCommand` -> Tenancy
- `GET /tenants/{tenantId}` -> `GetTenantQuery` -> Tenancy
- `PATCH /tenants/{tenantId}` -> `UpdateTenantCommand` -> Tenancy
- `DELETE /tenants/{tenantId}` -> `DeleteTenantCommand` -> Tenancy
- `POST /tenants/{tenantId}:activate` -> `ActivateTenantCommand` -> Tenancy
- `POST /tenants/{tenantId}:suspend` -> `SuspendTenantCommand` -> Tenancy
- `GET /tenants/{tenantId}/usage` -> `GetTenantUsageQuery` -> Tenancy
- `GET /tenants/{tenantId}/limits` -> `GetTenantLimitsQuery` -> Tenancy
- `PUT /tenants/{tenantId}/limits` -> `UpdateTenantLimitsCommand` -> Tenancy
- `GET /tenants/{tenantId}/settings` -> `GetTenantSettingsQuery` -> Tenancy
- `PUT /tenants/{tenantId}/settings` -> `UpdateTenantSettingsCommand` -> Tenancy

## Clinics and Locations

- `GET /clinics` -> `ListClinicsQuery` -> Clinics
- `POST /clinics` -> `CreateClinicCommand` -> Clinics
- `GET /clinics/{clinicId}` -> `GetClinicQuery` -> Clinics
- `PATCH /clinics/{clinicId}` -> `UpdateClinicCommand` -> Clinics
- `DELETE /clinics/{clinicId}` -> `DeleteClinicCommand` -> Clinics
- `POST /clinics/{clinicId}:activate` -> `ActivateClinicCommand` -> Clinics
- `POST /clinics/{clinicId}:deactivate` -> `DeactivateClinicCommand` -> Clinics
- `GET /clinics/{clinicId}/settings` -> `GetClinicSettingsQuery` -> Clinics
- `PUT /clinics/{clinicId}/settings` -> `UpdateClinicSettingsCommand` -> Clinics
- `GET /clinics/{clinicId}/departments` -> `ListDepartmentsQuery` -> Clinics
- `POST /clinics/{clinicId}/departments` -> `CreateDepartmentCommand` -> Clinics
- `GET /clinics/{clinicId}/departments/{deptId}` -> `GetDepartmentQuery` -> Clinics
- `PATCH /clinics/{clinicId}/departments/{deptId}` -> `UpdateDepartmentCommand` -> Clinics
- `DELETE /clinics/{clinicId}/departments/{deptId}` -> `DeleteDepartmentCommand` -> Clinics
- `GET /clinics/{clinicId}/rooms` -> `ListRoomsQuery` -> Clinics
- `POST /clinics/{clinicId}/rooms` -> `CreateRoomCommand` -> Clinics
- `PATCH /clinics/{clinicId}/rooms/{roomId}` -> `UpdateRoomCommand` -> Clinics
- `DELETE /clinics/{clinicId}/rooms/{roomId}` -> `DeleteRoomCommand` -> Clinics
- `GET /clinics/{clinicId}/work-hours` -> `GetClinicWorkHoursQuery` -> Clinics
- `PUT /clinics/{clinicId}/work-hours` -> `UpdateClinicWorkHoursCommand` -> Clinics
- `GET /clinics/{clinicId}/holidays` -> `ListClinicHolidaysQuery` -> Clinics
- `POST /clinics/{clinicId}/holidays` -> `CreateClinicHolidayCommand` -> Clinics
- `DELETE /clinics/{clinicId}/holidays/{holidayId}` -> `DeleteClinicHolidayCommand` -> Clinics
- `GET /locations/cities` -> `ListCitiesQuery` -> Clinics
- `GET /locations/districts` -> `ListDistrictsQuery` -> Clinics
- `GET /locations/search` -> `SearchLocationsQuery` -> Clinics

## API Notes

- `GET /tenants` is an authenticated tenant-discovery route and returns only tenants where the actor has a membership.
- `POST /tenants` is an authenticated bootstrap route that creates a tenant, attaches the creator as an active member, and assigns a bootstrap `Tenant Administrator` role with the full permission catalog.
- Tenant activation and suspension are administrative lifecycle transitions.
- Tenant-specific routes use `{tenantId}` as a documented tenant context source and may also accept `X-Tenant-Id`; mismatches fail with tenant-scope violations.
- Tenant suspension is exposed as a lifecycle state without removing tenant-administration access, so authorized actors can inspect and reactivate a suspended tenant.
- Tenant settings currently contain `locale`, `timezone`, and `currency`.
- Tenant limits currently contain `users`, `clinics`, `providers`, `patients`, `storage_gb`, and `monthly_notifications`.
- Tenant usage returns `used`, `limit`, and `remaining` for each documented limit key. Not-yet-implemented resources report `0` usage until their modules exist.
- Clinic settings, schedules, and room inventories remain tenant-owned data.
- Location endpoints may expose approved global reference data.
