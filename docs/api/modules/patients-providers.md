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
- `GET /patients/search` returns active patient records ordered by tenant-scoped relevance. It supports `q`, `sex`, `city_code`, `district_code`, `birth_date_from`, `birth_date_to`, `created_from`, `created_to`, `has_email`, `has_phone`, and `limit`.
- Search token matching uses AND semantics across `first_name`, `last_name`, `middle_name`, `preferred_name`, `national_id`, `email`, and `phone`. Exact identifier matches sort ahead of prefix matches and substring matches.
- `GET /patients/{patientId}/summary` returns the active patient record plus derived summary fields: `display_name`, `initials`, `age_years`, `directory_status`, contact summary, location summary, `timeline_event_count`, and `last_activity_at`.
- `display_name` uses `preferred_name + last_name` when `preferred_name` exists; otherwise it uses `first_name + last_name`. `initials` use the first and last words of that display name.
- `GET /patients/{patientId}/timeline` returns immutable patient audit events newest first and supports `limit` up to `100`.
- `GET /patients/export` exports the active patient search result set to CSV on the private shared exports disk. It accepts the same filters as patient search plus `format=csv` and an export `limit` with a maximum of `1000`.
- Export responses return an export reference containing `export_id`, `format`, `file_name`, `row_count`, `generated_at`, the applied filters, and the private storage `disk` and `path`.
- The CSV export columns are `id`, `tenant_id`, `display_name`, `first_name`, `last_name`, `middle_name`, `preferred_name`, `sex`, `birth_date`, `age_years`, `national_id`, `email`, `phone`, `city_code`, `district_code`, `address_line_1`, `address_line_2`, `postal_code`, `notes`, `created_at`, `updated_at`, and `exported_at`.
- Export creation writes audit action `patients.exported` with object type `patient_export`.
- Patient contacts use `name`, `relationship`, `phone`, `email`, `is_primary`, `is_emergency`, and `notes`. `name` is required and at least one of `phone` or `email` must be present after create or update.
- Only one active primary contact may exist per patient. Contacts list ordered by `is_primary desc`, `is_emergency desc`, `name asc`, and `created_at asc`.
- `GET /patients/{patientId}/tags` returns the full normalized active tag set. `PUT /patients/{patientId}/tags` replaces the entire set.
- Tags are normalized by trimming, collapsing repeated internal whitespace, lowercasing, discarding empty values, deduplicating case-insensitively, and sorting alphabetically.
- `GET /patients/{patientId}/documents` returns document metadata newest first. `GET /patients/{patientId}/documents/{docId}` returns metadata only and never exposes storage disk, storage path, or a public URL.
- `POST /patients/{patientId}/documents` accepts multipart uploads with `document`, optional `title`, and optional `document_type`. Allowed upload types are `pdf`, `jpg`, `jpeg`, `png`, and `webp` with a maximum size of `10 MiB`.
- Patient documents are stored on the private shared attachments disk. If `title` is omitted it defaults to the uploaded filename.
- Contact, tag, and document mutations emit patient audit actions and appear in the patient timeline through patient-scoped audit metadata.
- `GET /patients/{patientId}/consents` returns consent history with active consents first, then `granted_at desc`, then `created_at desc`.
- `POST /patients/{patientId}/consents` accepts `consent_type`, `granted_by_name`, optional `granted_by_relationship`, optional `granted_at`, optional `expires_at`, and optional `notes`.
- Consent type is normalized to lowercase snake case. `granted_by_name` is required. `granted_at` defaults to the current timestamp when omitted.
- Consent status is derived as `active`, `expired`, or `revoked`. At most one active consent of the same type may exist per patient at a time.
- `POST /patients/{patientId}/consents/{consentId}:revoke` sets `revoked_at` and optional `reason`. Revoked consent history is retained and is never hard-deleted in normal flows.
- `GET /patients/{patientId}/insurance` returns patient insurance links ordered by `is_primary desc`, `effective_from desc nulls last`, and `created_at desc`.
- `POST /patients/{patientId}/insurance` accepts `insurance_code`, `policy_number`, optional `member_number`, optional `group_number`, optional `plan_name`, optional `effective_from`, optional `effective_to`, optional `is_primary`, and optional `notes`.
- `insurance_code` is normalized to lowercase. `policy_number` is required. Duplicate `{patient_id, insurance_code, policy_number}` links are rejected as conflicts.
- Only one patient insurance link may be primary at a time. Attaching a new primary policy clears the previous primary flag.
- `DELETE /patients/{patientId}/insurance/{policyId}` hard-deletes the insurance link and writes patient audit action `patients.insurance_detached`.
- `GET /patients/{patientId}/external-refs` returns patient external references ordered by `integration_key asc`, `external_type asc`, and `created_at asc`.
- `POST /patients/{patientId}/external-refs` accepts `integration_key`, `external_id`, optional `external_type`, optional `display_name`, and optional JSON `metadata`.
- `external_type` defaults to `patient`. The tuple `{patient_id, integration_key, external_type, external_id}` must be unique per patient.
- External-reference metadata must remain safe to expose to internal API clients. `DELETE /patients/{patientId}/external-refs/{refId}` hard-deletes the mapping and writes patient audit action `patients.external_ref_detached`.
- Consent, insurance-link, and external-reference mutations emit patient audit actions and appear in the patient timeline through patient-scoped audit metadata.
- `T029` implemented the base CRUD surface. `T030` added search, summary, timeline, and export. `T031` added contacts, tags, and document management. `T032` adds consents, insurance links, and external references. Bulk flows remain in later tasks.

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
