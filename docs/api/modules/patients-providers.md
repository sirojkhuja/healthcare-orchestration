# Patients and Providers API

## Patients

- `GET /patients` -> `ListPatientsQuery` -> Patient
- `POST /patients` -> `CreatePatientCommand` -> Patient
- `GET /patients/{patientId}` -> `GetPatientQuery` -> Patient
- `PATCH /patients/{patientId}` -> `UpdatePatientCommand` -> Patient
- `DELETE /patients/{patientId}` -> `DeletePatientCommand` -> Patient
- `GET /patients/search` -> `SearchPatientsQuery` -> Patient
- `GET /patients/{patientId}/summary` -> `GetPatientSummaryQuery` -> Patient
- `GET /patients/{patientId}/timeline` -> `GetPatientTimelineQuery` -> Patient
- `GET /patients/{patientId}/contacts` -> `ListPatientContactsQuery` -> Patient
- `POST /patients/{patientId}/contacts` -> `CreatePatientContactCommand` -> Patient
- `PATCH /patients/{patientId}/contacts/{contactId}` -> `UpdatePatientContactCommand` -> Patient
- `DELETE /patients/{patientId}/contacts/{contactId}` -> `DeletePatientContactCommand` -> Patient
- `GET /patients/{patientId}/insurance` -> `ListPatientInsuranceQuery` -> Insurance
- `POST /patients/{patientId}/insurance` -> `AttachPatientInsuranceCommand` -> Insurance
- `DELETE /patients/{patientId}/insurance/{policyId}` -> `DetachPatientInsuranceCommand` -> Insurance
- `GET /patients/{patientId}/documents` -> `ListPatientDocumentsQuery` -> Patient
- `POST /patients/{patientId}/documents` -> `UploadPatientDocumentCommand` -> Patient
- `GET /patients/{patientId}/documents/{docId}` -> `GetPatientDocumentQuery` -> Patient
- `DELETE /patients/{patientId}/documents/{docId}` -> `DeletePatientDocumentCommand` -> Patient
- `GET /patients/{patientId}/consents` -> `ListPatientConsentsQuery` -> Patient
- `POST /patients/{patientId}/consents` -> `CreatePatientConsentCommand` -> Patient
- `POST /patients/{patientId}/consents/{consentId}:revoke` -> `RevokePatientConsentCommand` -> Patient
- `GET /patients/{patientId}/tags` -> `ListPatientTagsQuery` -> Patient
- `PUT /patients/{patientId}/tags` -> `SetPatientTagsCommand` -> Patient
- `POST /patients:bulk-import` -> `BulkImportPatientsCommand` -> Patient
- `POST /patients/bulk` -> `BulkUpdatePatientsCommand` -> Patient
- `GET /patients/export` -> `ExportPatientsQuery` -> Patient
- `GET /patients/{patientId}/external-refs` -> `ListPatientExternalRefsQuery` -> Integrations
- `POST /patients/{patientId}/external-refs` -> `AttachPatientExternalRefCommand` -> Integrations
- `DELETE /patients/{patientId}/external-refs/{refId}` -> `DetachPatientExternalRefCommand` -> Integrations

## Patient Contract Notes

- Patient routes are tenant-owned and require tenant context through `X-Tenant-Id`.
- `patients.view` protects patient reads and `patients.manage` protects patient writes.
- The patient master record currently contains `first_name`, `last_name`, `middle_name`, `preferred_name`, `sex`, `birth_date`, `national_id`, `email`, `phone`, `city_code`, `district_code`, `address_line_1`, `address_line_2`, `postal_code`, and `notes`.
- `first_name`, `last_name`, `sex`, and `birth_date` are required on create.
- `sex` uses the enum values `female`, `male`, `other`, and `unknown`.
- Optional location codes reuse the approved global location catalog. If `district_code` is provided, `city_code` must also be present and the district must belong to that city.
- `DELETE /patients/{patientId}` is a soft delete. Deleted patients are excluded from active directory reads but retained for auditability.
- `T029` implements the base CRUD surface. Search, summary, timeline, contacts, documents, consents, insurance links, tags, bulk flows, exports, and external references are tracked in later tasks.

## Providers and Availability

- `GET /providers` -> `ListProvidersQuery` -> Provider
- `POST /providers` -> `CreateProviderCommand` -> Provider
- `GET /providers/{providerId}` -> `GetProviderQuery` -> Provider
- `PATCH /providers/{providerId}` -> `UpdateProviderCommand` -> Provider
- `DELETE /providers/{providerId}` -> `DeleteProviderCommand` -> Provider
- `GET /providers/search` -> `SearchProvidersQuery` -> Provider
- `GET /providers/{providerId}/profile` -> `GetProviderProfileQuery` -> Provider
- `PATCH /providers/{providerId}/profile` -> `UpdateProviderProfileCommand` -> Provider
- `GET /providers/{providerId}/specialties` -> `ListProviderSpecialtiesQuery` -> Provider
- `PUT /providers/{providerId}/specialties` -> `SetProviderSpecialtiesCommand` -> Provider
- `GET /providers/{providerId}/licenses` -> `ListProviderLicensesQuery` -> Provider
- `POST /providers/{providerId}/licenses` -> `AddProviderLicenseCommand` -> Provider
- `DELETE /providers/{providerId}/licenses/{licenseId}` -> `RemoveProviderLicenseCommand` -> Provider
- `GET /providers/{providerId}/availability/rules` -> `ListAvailabilityRulesQuery` -> Scheduling
- `POST /providers/{providerId}/availability/rules` -> `CreateAvailabilityRuleCommand` -> Scheduling
- `PATCH /providers/{providerId}/availability/rules/{ruleId}` -> `UpdateAvailabilityRuleCommand` -> Scheduling
- `DELETE /providers/{providerId}/availability/rules/{ruleId}` -> `DeleteAvailabilityRuleCommand` -> Scheduling
- `GET /providers/{providerId}/availability/slots` -> `GetAvailabilitySlotsQuery` -> Scheduling
- `POST /providers/{providerId}/availability:rebuild-cache` -> `RebuildAvailabilityCacheCommand` -> Scheduling
- `GET /providers/{providerId}/calendar` -> `GetProviderCalendarQuery` -> Scheduling
- `GET /providers/{providerId}/calendar/export` -> `ExportProviderCalendarQuery` -> Scheduling
- `GET /providers/{providerId}/work-hours` -> `GetProviderWorkHoursQuery` -> Provider
- `PUT /providers/{providerId}/work-hours` -> `UpdateProviderWorkHoursCommand` -> Provider
- `GET /providers/{providerId}/time-off` -> `ListTimeOffQuery` -> Provider
- `POST /providers/{providerId}/time-off` -> `CreateTimeOffCommand` -> Provider
- `PATCH /providers/{providerId}/time-off/{timeOffId}` -> `UpdateTimeOffCommand` -> Provider
- `DELETE /providers/{providerId}/time-off/{timeOffId}` -> `DeleteTimeOffCommand` -> Provider
- `GET /specialties` -> `ListSpecialtiesQuery` -> Provider
- `POST /specialties` -> `CreateSpecialtyCommand` -> Provider
- `PATCH /specialties/{specialtyId}` -> `UpdateSpecialtyCommand` -> Provider
- `DELETE /specialties/{specialtyId}` -> `DeleteSpecialtyCommand` -> Provider
- `GET /provider-groups` -> `ListProviderGroupsQuery` -> Provider
- `POST /provider-groups` -> `CreateProviderGroupCommand` -> Provider
- `PUT /provider-groups/{groupId}/members` -> `SetProviderGroupMembersCommand` -> Provider

## API Notes

- Patient insurance attachment crosses the Patient and Insurance contexts but remains application-layer coordinated.
- Provider availability belongs to scheduling behavior even when accessed through provider-scoped routes.
