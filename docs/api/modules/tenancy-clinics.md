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

- Tenant activation and suspension are administrative lifecycle transitions.
- Clinic settings, schedules, and room inventories remain tenant-owned data.
- Location endpoints may expose approved global reference data.
