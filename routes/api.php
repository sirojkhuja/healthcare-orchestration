<?php

use App\Modules\Billing\Presentation\Http\Controllers\BillableServiceController;
use App\Modules\Billing\Presentation\Http\Controllers\InvoiceController;
use App\Modules\Billing\Presentation\Http\Controllers\InvoiceItemController;
use App\Modules\Billing\Presentation\Http\Controllers\InvoiceWorkflowController;
use App\Modules\Billing\Presentation\Http\Controllers\PaymentController;
use App\Modules\Billing\Presentation\Http\Controllers\PaymentReconciliationController;
use App\Modules\Billing\Presentation\Http\Controllers\PaymentWorkflowController;
use App\Modules\Billing\Presentation\Http\Controllers\PriceListController;
use App\Modules\IdentityAccess\Presentation\Http\Controllers\ApiKeyController;
use App\Modules\IdentityAccess\Presentation\Http\Controllers\AuthController;
use App\Modules\IdentityAccess\Presentation\Http\Controllers\DeviceController;
use App\Modules\IdentityAccess\Presentation\Http\Controllers\PermissionCatalogController;
use App\Modules\IdentityAccess\Presentation\Http\Controllers\ProfileController;
use App\Modules\IdentityAccess\Presentation\Http\Controllers\RbacAuditController;
use App\Modules\IdentityAccess\Presentation\Http\Controllers\RoleController;
use App\Modules\IdentityAccess\Presentation\Http\Controllers\SecurityController;
use App\Modules\IdentityAccess\Presentation\Http\Controllers\UserController;
use App\Modules\IdentityAccess\Presentation\Http\Controllers\UserRoleController;
use App\Modules\Insurance\Presentation\Http\Controllers\PatientInsuranceController;
use App\Modules\Integrations\Presentation\Http\Controllers\ClickWebhookController;
use App\Modules\Integrations\Presentation\Http\Controllers\PatientExternalReferenceController;
use App\Modules\Integrations\Presentation\Http\Controllers\PaymeWebhookController;
use App\Modules\Integrations\Presentation\Http\Controllers\UzumWebhookController;
use App\Modules\Lab\Presentation\Http\Controllers\LabOrderBulkController;
use App\Modules\Lab\Presentation\Http\Controllers\LabOrderController;
use App\Modules\Lab\Presentation\Http\Controllers\LabOrderWorkflowController;
use App\Modules\Lab\Presentation\Http\Controllers\LabResultController;
use App\Modules\Lab\Presentation\Http\Controllers\LabTestController;
use App\Modules\Lab\Presentation\Http\Controllers\LabWebhookController;
use App\Modules\Patient\Presentation\Http\Controllers\PatientConsentController;
use App\Modules\Patient\Presentation\Http\Controllers\PatientContactController;
use App\Modules\Patient\Presentation\Http\Controllers\PatientController;
use App\Modules\Patient\Presentation\Http\Controllers\PatientDocumentController;
use App\Modules\Patient\Presentation\Http\Controllers\PatientTagController;
use App\Modules\Pharmacy\Presentation\Http\Controllers\MedicationController;
use App\Modules\Pharmacy\Presentation\Http\Controllers\PatientAllergyController;
use App\Modules\Pharmacy\Presentation\Http\Controllers\PatientMedicationController;
use App\Modules\Pharmacy\Presentation\Http\Controllers\PrescriptionController;
use App\Modules\Pharmacy\Presentation\Http\Controllers\PrescriptionWorkflowController;
use App\Modules\Provider\Presentation\Http\Controllers\ProviderController;
use App\Modules\Provider\Presentation\Http\Controllers\ProviderGroupController;
use App\Modules\Provider\Presentation\Http\Controllers\ProviderLicenseController;
use App\Modules\Provider\Presentation\Http\Controllers\ProviderProfileController;
use App\Modules\Provider\Presentation\Http\Controllers\ProviderSpecialtyController;
use App\Modules\Provider\Presentation\Http\Controllers\ProviderTimeOffController;
use App\Modules\Provider\Presentation\Http\Controllers\ProviderWorkHoursController;
use App\Modules\Provider\Presentation\Http\Controllers\SpecialtyController;
use App\Modules\Scheduling\Presentation\Http\Controllers\AppointmentAuditController;
use App\Modules\Scheduling\Presentation\Http\Controllers\AppointmentBulkController;
use App\Modules\Scheduling\Presentation\Http\Controllers\AppointmentBulkWorkflowController;
use App\Modules\Scheduling\Presentation\Http\Controllers\AppointmentController;
use App\Modules\Scheduling\Presentation\Http\Controllers\AppointmentNoteController;
use App\Modules\Scheduling\Presentation\Http\Controllers\AppointmentParticipantController;
use App\Modules\Scheduling\Presentation\Http\Controllers\AppointmentRecurrenceController;
use App\Modules\Scheduling\Presentation\Http\Controllers\AppointmentWorkflowController;
use App\Modules\Scheduling\Presentation\Http\Controllers\AvailabilityController;
use App\Modules\Scheduling\Presentation\Http\Controllers\ProviderCalendarController;
use App\Modules\Scheduling\Presentation\Http\Controllers\WaitlistController;
use App\Modules\TenantManagement\Presentation\Http\Controllers\ClinicController;
use App\Modules\TenantManagement\Presentation\Http\Controllers\ClinicHolidayController;
use App\Modules\TenantManagement\Presentation\Http\Controllers\ClinicLifecycleController;
use App\Modules\TenantManagement\Presentation\Http\Controllers\ClinicSettingsController;
use App\Modules\TenantManagement\Presentation\Http\Controllers\ClinicWorkHoursController;
use App\Modules\TenantManagement\Presentation\Http\Controllers\DepartmentController;
use App\Modules\TenantManagement\Presentation\Http\Controllers\LocationController;
use App\Modules\TenantManagement\Presentation\Http\Controllers\RoomController;
use App\Modules\TenantManagement\Presentation\Http\Controllers\TenantController;
use App\Modules\TenantManagement\Presentation\Http\Controllers\TenantLifecycleController;
use App\Modules\TenantManagement\Presentation\Http\Controllers\TenantLimitsController;
use App\Modules\TenantManagement\Presentation\Http\Controllers\TenantSettingsController;
use App\Modules\TenantManagement\Presentation\Http\Controllers\TenantUsageController;
use App\Modules\Treatment\Presentation\Http\Controllers\EncounterBulkController;
use App\Modules\Treatment\Presentation\Http\Controllers\EncounterController;
use App\Modules\Treatment\Presentation\Http\Controllers\EncounterDiagnosisController;
use App\Modules\Treatment\Presentation\Http\Controllers\EncounterProcedureController;
use App\Modules\Treatment\Presentation\Http\Controllers\TreatmentPlanController;
use App\Modules\Treatment\Presentation\Http\Controllers\TreatmentPlanItemController;
use App\Modules\Treatment\Presentation\Http\Controllers\TreatmentPlanWorkflowController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/ping', function () {
        return response()->json([
            'service' => config('app.name'),
            'version' => config('medflow.version'),
            'status' => 'ok',
        ]);
    });

    Route::post('/webhooks/lab/{provider}', [LabWebhookController::class, 'process'])
        ->where('provider', '[A-Za-z0-9_-]+')
        ->middleware('idempotency:lab.webhooks.process')
        ->name('webhooks.labs.process');
    Route::post('/webhooks/payme', [PaymeWebhookController::class, 'process'])
        ->name('webhooks.payme.process');
    Route::post('/webhooks/click', [ClickWebhookController::class, 'process'])
        ->name('webhooks.click.process');
    Route::post('/webhooks/uzum', [UzumWebhookController::class, 'process'])
        ->name('webhooks.uzum.process');

    Route::prefix('auth')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('/mfa/verify', [AuthController::class, 'verifyMfa'])->name('auth.mfa.verify');
        Route::post('/password/forgot', [AuthController::class, 'requestPasswordReset'])->name('auth.password.forgot');
        Route::post('/password/reset', [AuthController::class, 'resetPassword'])->name('auth.password.reset');
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');

        Route::middleware('auth:api')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::post('/mfa/setup', [AuthController::class, 'setupMfa'])->name('auth.mfa.setup');
            Route::post('/mfa/disable', [AuthController::class, 'disableMfa'])->name('auth.mfa.disable');
            Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
            Route::post('/sessions', [SecurityController::class, 'listSessions'])->name('auth.sessions.list');
            Route::delete('/sessions/{sessionId}', [SecurityController::class, 'revokeSession'])->name('auth.sessions.revoke');
            Route::post('/api-keys', [ApiKeyController::class, 'create'])->name('auth.api-keys.create');
            Route::get('/api-keys', [ApiKeyController::class, 'list'])->name('auth.api-keys.list');
            Route::delete('/api-keys/{keyId}', [ApiKeyController::class, 'revoke'])->name('auth.api-keys.revoke');
        });
    });

    Route::middleware('auth:api')->group(function (): void {
        Route::get('/devices', [DeviceController::class, 'list'])->name('devices.list');
        Route::post('/devices', [DeviceController::class, 'register'])->name('devices.register');
        Route::delete('/devices/{deviceId}', [DeviceController::class, 'deregister'])->name('devices.deregister');
        Route::get('/tenants', [TenantController::class, 'list'])->name('tenants.list');
        Route::post('/tenants', [TenantController::class, 'create'])->name('tenants.create');
        Route::get('/profiles/me', [ProfileController::class, 'me'])->name('profiles.me.show');
        Route::patch('/profiles/me', [ProfileController::class, 'updateMe'])->name('profiles.me.update');
        Route::post('/profiles/me/avatar', [ProfileController::class, 'uploadMyAvatar'])->name('profiles.me.avatar.upload');
        Route::post('/security/sessions:revoke-all', [SecurityController::class, 'revokeAllSessions'])->name('security.sessions.revoke-all');
        Route::middleware('tenant.require')->group(function (): void {
            Route::get('/security/ip-allowlist', [SecurityController::class, 'getIpAllowlist'])->name('security.ip-allowlist.get');
            Route::post('/security/ip-allowlist', [SecurityController::class, 'updateIpAllowlist'])->name('security.ip-allowlist.update');
            Route::middleware('permission:profiles.view')->group(function (): void {
                Route::get('/profiles/{userId}', [ProfileController::class, 'show'])->name('profiles.show');
            });
            Route::middleware('permission:profiles.manage')->group(function (): void {
                Route::patch('/profiles/{userId}', [ProfileController::class, 'update'])->name('profiles.update');
            });
            Route::middleware('permission:users.view')->group(function (): void {
                Route::get('/users', [UserController::class, 'list'])->name('users.list');
                Route::get('/users/{userId}', [UserController::class, 'show'])->name('users.show');
            });
            Route::middleware('permission:users.manage')->group(function (): void {
                Route::post('/users', [UserController::class, 'create'])->name('users.create');
                Route::patch('/users/{userId}', [UserController::class, 'update'])->name('users.update');
                Route::delete('/users/{userId}', [UserController::class, 'delete'])->name('users.delete');
                Route::post('/users/{userId}:activate', [UserController::class, 'activate'])->name('users.activate');
                Route::post('/users/{userId}:deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
                Route::post('/users/{userId}:lock', [UserController::class, 'lock'])->name('users.lock');
                Route::post('/users/{userId}:unlock', [UserController::class, 'unlock'])->name('users.unlock');
                Route::post('/users/{userId}:reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
                Route::post('/users:bulk-import', [UserController::class, 'bulkImport'])->name('users.bulk-import');
                Route::post('/users/bulk', [UserController::class, 'bulkUpdate'])->name('users.bulk-update');
            });
            Route::middleware('permission:tenants.view')->group(function (): void {
                Route::get('/tenants/{tenantId}', [TenantController::class, 'show'])->name('tenants.show');
                Route::get('/tenants/{tenantId}/usage', [TenantUsageController::class, 'show'])->name('tenants.usage.show');
                Route::get('/tenants/{tenantId}/limits', [TenantLimitsController::class, 'show'])->name('tenants.limits.show');
                Route::get('/tenants/{tenantId}/settings', [TenantSettingsController::class, 'show'])->name('tenants.settings.show');
                Route::get('/clinics', [ClinicController::class, 'list'])->name('clinics.list');
                Route::get('/clinics/{clinicId}', [ClinicController::class, 'show'])->name('clinics.show');
                Route::get('/clinics/{clinicId}/settings', [ClinicSettingsController::class, 'show'])->name('clinics.settings.show');
                Route::get('/clinics/{clinicId}/departments', [DepartmentController::class, 'list'])->name('clinics.departments.list');
                Route::get('/clinics/{clinicId}/departments/{departmentId}', [DepartmentController::class, 'show'])->name('clinics.departments.show');
                Route::get('/clinics/{clinicId}/rooms', [RoomController::class, 'list'])->name('clinics.rooms.list');
                Route::get('/clinics/{clinicId}/work-hours', [ClinicWorkHoursController::class, 'show'])->name('clinics.work-hours.show');
                Route::get('/clinics/{clinicId}/holidays', [ClinicHolidayController::class, 'list'])->name('clinics.holidays.list');
                Route::get('/locations/cities', [LocationController::class, 'cities'])->name('locations.cities');
                Route::get('/locations/districts', [LocationController::class, 'districts'])->name('locations.districts');
                Route::get('/locations/search', [LocationController::class, 'search'])->name('locations.search');
            });
            Route::middleware('permission:tenants.manage')->group(function (): void {
                Route::patch('/tenants/{tenantId}', [TenantController::class, 'update'])->name('tenants.update');
                Route::delete('/tenants/{tenantId}', [TenantController::class, 'delete'])->name('tenants.delete');
                Route::post('/tenants/{tenantId}:activate', [TenantLifecycleController::class, 'activate'])->name('tenants.activate');
                Route::post('/tenants/{tenantId}:suspend', [TenantLifecycleController::class, 'suspend'])->name('tenants.suspend');
                Route::put('/tenants/{tenantId}/limits', [TenantLimitsController::class, 'update'])->name('tenants.limits.update');
                Route::put('/tenants/{tenantId}/settings', [TenantSettingsController::class, 'update'])->name('tenants.settings.update');
                Route::post('/clinics', [ClinicController::class, 'create'])->name('clinics.create');
                Route::patch('/clinics/{clinicId}', [ClinicController::class, 'update'])->name('clinics.update');
                Route::delete('/clinics/{clinicId}', [ClinicController::class, 'delete'])->name('clinics.delete');
                Route::post('/clinics/{clinicId}:activate', [ClinicLifecycleController::class, 'activate'])->name('clinics.activate');
                Route::post('/clinics/{clinicId}:deactivate', [ClinicLifecycleController::class, 'deactivate'])->name('clinics.deactivate');
                Route::put('/clinics/{clinicId}/settings', [ClinicSettingsController::class, 'update'])->name('clinics.settings.update');
                Route::post('/clinics/{clinicId}/departments', [DepartmentController::class, 'create'])->name('clinics.departments.create');
                Route::patch('/clinics/{clinicId}/departments/{departmentId}', [DepartmentController::class, 'update'])->name('clinics.departments.update');
                Route::delete('/clinics/{clinicId}/departments/{departmentId}', [DepartmentController::class, 'delete'])->name('clinics.departments.delete');
                Route::post('/clinics/{clinicId}/rooms', [RoomController::class, 'create'])->name('clinics.rooms.create');
                Route::patch('/clinics/{clinicId}/rooms/{roomId}', [RoomController::class, 'update'])->name('clinics.rooms.update');
                Route::delete('/clinics/{clinicId}/rooms/{roomId}', [RoomController::class, 'delete'])->name('clinics.rooms.delete');
                Route::put('/clinics/{clinicId}/work-hours', [ClinicWorkHoursController::class, 'update'])->name('clinics.work-hours.update');
                Route::post('/clinics/{clinicId}/holidays', [ClinicHolidayController::class, 'create'])->name('clinics.holidays.create');
                Route::delete('/clinics/{clinicId}/holidays/{holidayId}', [ClinicHolidayController::class, 'delete'])->name('clinics.holidays.delete');
            });
            Route::middleware('permission:patients.view')->group(function (): void {
                Route::get('/patients', [PatientController::class, 'list'])->name('patients.list');
                Route::get('/patients/search', [PatientController::class, 'search'])->name('patients.search');
                Route::get('/patients/export', [PatientController::class, 'export'])->name('patients.export');
                Route::get('/patients/{patientId}', [PatientController::class, 'show'])->name('patients.show');
                Route::get('/patients/{patientId}/summary', [PatientController::class, 'summary'])->name('patients.summary');
                Route::get('/patients/{patientId}/timeline', [PatientController::class, 'timeline'])->name('patients.timeline');
                Route::get('/patients/{patientId}/contacts', [PatientContactController::class, 'list'])->name('patients.contacts.list');
                Route::get('/patients/{patientId}/consents', [PatientConsentController::class, 'list'])->name('patients.consents.list');
                Route::get('/patients/{patientId}/insurance', [PatientInsuranceController::class, 'list'])->name('patients.insurance.list');
                Route::get('/patients/{patientId}/tags', [PatientTagController::class, 'list'])->name('patients.tags.list');
                Route::get('/patients/{patientId}/documents', [PatientDocumentController::class, 'list'])->name('patients.documents.list');
                Route::get('/patients/{patientId}/documents/{docId}', [PatientDocumentController::class, 'show'])->name('patients.documents.show');
                Route::get('/patients/{patientId}/external-refs', [PatientExternalReferenceController::class, 'list'])->name('patients.external-refs.list');
            });
            Route::middleware('permission:patients.manage')->group(function (): void {
                Route::post('/patients', [PatientController::class, 'create'])->name('patients.create');
                Route::patch('/patients/{patientId}', [PatientController::class, 'update'])->name('patients.update');
                Route::delete('/patients/{patientId}', [PatientController::class, 'delete'])->name('patients.delete');
                Route::post('/patients/{patientId}/contacts', [PatientContactController::class, 'create'])->name('patients.contacts.create');
                Route::patch('/patients/{patientId}/contacts/{contactId}', [PatientContactController::class, 'update'])->name('patients.contacts.update');
                Route::delete('/patients/{patientId}/contacts/{contactId}', [PatientContactController::class, 'delete'])->name('patients.contacts.delete');
                Route::post('/patients/{patientId}/consents', [PatientConsentController::class, 'create'])->name('patients.consents.create');
                Route::post('/patients/{patientId}/consents/{consentId}:revoke', [PatientConsentController::class, 'revoke'])->name('patients.consents.revoke');
                Route::post('/patients/{patientId}/insurance', [PatientInsuranceController::class, 'create'])->name('patients.insurance.create');
                Route::delete('/patients/{patientId}/insurance/{policyId}', [PatientInsuranceController::class, 'delete'])->name('patients.insurance.delete');
                Route::put('/patients/{patientId}/tags', [PatientTagController::class, 'update'])->name('patients.tags.update');
                Route::post('/patients/{patientId}/documents', [PatientDocumentController::class, 'upload'])->name('patients.documents.create');
                Route::delete('/patients/{patientId}/documents/{docId}', [PatientDocumentController::class, 'delete'])->name('patients.documents.delete');
                Route::post('/patients/{patientId}/external-refs', [PatientExternalReferenceController::class, 'create'])->name('patients.external-refs.create');
                Route::delete('/patients/{patientId}/external-refs/{refId}', [PatientExternalReferenceController::class, 'delete'])->name('patients.external-refs.delete');
            });
            Route::middleware('permission:providers.view')->group(function (): void {
                Route::get('/providers', [ProviderController::class, 'list'])->name('providers.list');
                Route::get('/providers/{providerId}', [ProviderController::class, 'show'])->name('providers.show');
                Route::get('/providers/{providerId}/calendar', [ProviderCalendarController::class, 'show'])->name('providers.calendar.show');
                Route::get('/providers/{providerId}/calendar/export', [ProviderCalendarController::class, 'export'])->name('providers.calendar.export');
                Route::get('/providers/{providerId}/profile', [ProviderProfileController::class, 'show'])->name('providers.profile.show');
                Route::get('/providers/{providerId}/specialties', [ProviderSpecialtyController::class, 'list'])->name('providers.specialties.list');
                Route::get('/providers/{providerId}/licenses', [ProviderLicenseController::class, 'list'])->name('providers.licenses.list');
                Route::get('/providers/{providerId}/work-hours', [ProviderWorkHoursController::class, 'show'])->name('providers.work-hours.show');
                Route::get('/providers/{providerId}/time-off', [ProviderTimeOffController::class, 'list'])->name('providers.time-off.list');
                Route::get('/providers/{providerId}/availability/rules', [AvailabilityController::class, 'list'])->name('providers.availability.rules.list');
                Route::get('/providers/{providerId}/availability/slots', [AvailabilityController::class, 'slots'])->name('providers.availability.slots');
                Route::get('/specialties', [SpecialtyController::class, 'list'])->name('specialties.list');
                Route::get('/provider-groups', [ProviderGroupController::class, 'list'])->name('provider-groups.list');
            });
            Route::middleware('permission:appointments.view')->group(function (): void {
                Route::get('/appointments', [AppointmentController::class, 'list'])->name('appointments.list');
                Route::get('/appointments/search', [AppointmentController::class, 'search'])->name('appointments.search');
                Route::get('/appointments/export', [AppointmentController::class, 'export'])->name('appointments.export');
                Route::get('/appointments/{appointmentId}/audit', [AppointmentAuditController::class, 'list'])->name('appointments.audit.list');
                Route::get('/appointments/{appointmentId}/participants', [AppointmentParticipantController::class, 'list'])->name('appointments.participants.list');
                Route::get('/appointments/{appointmentId}/notes', [AppointmentNoteController::class, 'list'])->name('appointments.notes.list');
                Route::get('/appointments/{appointmentId}', [AppointmentController::class, 'show'])->name('appointments.show');
                Route::get('/waitlist', [WaitlistController::class, 'list'])->name('waitlist.list');
            });
            Route::middleware('permission:treatments.view')->group(function (): void {
                Route::get('/treatment-plans', [TreatmentPlanController::class, 'list'])->name('treatment-plans.list');
                Route::get('/treatment-plans/search', [TreatmentPlanController::class, 'search'])->name('treatment-plans.search');
                Route::get('/treatment-plans/{planId}/items', [TreatmentPlanItemController::class, 'list'])->name('treatment-plans.items.list');
                Route::get('/treatment-plans/{planId}', [TreatmentPlanController::class, 'show'])->name('treatment-plans.show');
                Route::get('/encounters', [EncounterController::class, 'list'])->name('encounters.list');
                Route::get('/encounters/export', [EncounterController::class, 'export'])->name('encounters.export');
                Route::get('/encounters/{encounterId}/diagnoses', [EncounterDiagnosisController::class, 'list'])->name('encounters.diagnoses.list');
                Route::get('/encounters/{encounterId}/procedures', [EncounterProcedureController::class, 'list'])->name('encounters.procedures.list');
                Route::get('/encounters/{encounterId}', [EncounterController::class, 'show'])->name('encounters.show');
            });
            Route::middleware('permission:labs.view')->group(function (): void {
                Route::get('/lab-tests', [LabTestController::class, 'list'])->name('lab-tests.list');
                Route::get('/lab-orders', [LabOrderController::class, 'list'])->name('lab-orders.list');
                Route::get('/lab-orders/search', [LabOrderController::class, 'search'])->name('lab-orders.search');
                Route::get('/lab-orders/export', [LabOrderController::class, 'export'])->name('lab-orders.export');
                Route::get('/lab-orders/{orderId}/results', [LabResultController::class, 'list'])->name('lab-orders.results.list');
                Route::get('/lab-orders/{orderId}/results/{resultId}', [LabResultController::class, 'show'])->name('lab-orders.results.show');
                Route::get('/lab-orders/{orderId}', [LabOrderController::class, 'show'])->name('lab-orders.show');
            });
            Route::middleware('permission:prescriptions.view')->group(function (): void {
                Route::get('/medications', [MedicationController::class, 'list'])->name('medications.list');
                Route::get('/medications/search', [MedicationController::class, 'search'])->name('medications.search');
                Route::get('/medications/{medId}', [MedicationController::class, 'show'])->name('medications.show');
                Route::get('/patients/{patientId}/allergies', [PatientAllergyController::class, 'list'])->name('patients.allergies.list');
                Route::get('/patients/{patientId}/medications', [PatientMedicationController::class, 'list'])->name('patients.medications.list');
                Route::get('/prescriptions', [PrescriptionController::class, 'list'])->name('prescriptions.list');
                Route::get('/prescriptions/search', [PrescriptionController::class, 'search'])->name('prescriptions.search');
                Route::get('/prescriptions/export', [PrescriptionController::class, 'export'])->name('prescriptions.export');
                Route::get('/prescriptions/{prescriptionId}', [PrescriptionController::class, 'show'])->name('prescriptions.show');
            });
            Route::middleware('permission:billing.view')->group(function (): void {
                Route::get('/services', [BillableServiceController::class, 'list'])->name('services.list');
                Route::get('/price-lists', [PriceListController::class, 'list'])->name('price-lists.list');
                Route::get('/price-lists/{priceListId}', [PriceListController::class, 'show'])->name('price-lists.show');
                Route::get('/invoices', [InvoiceController::class, 'list'])->name('invoices.list');
                Route::get('/invoices/search', [InvoiceController::class, 'search'])->name('invoices.search');
                Route::get('/invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
                Route::get('/invoices/{invoiceId}/items', [InvoiceItemController::class, 'list'])->name('invoices.items.list');
                Route::get('/invoices/{invoiceId}', [InvoiceController::class, 'show'])->name('invoices.show');
                Route::get('/payments', [PaymentController::class, 'list'])->name('payments.list');
                Route::get('/payments/reconciliation-runs', [PaymentReconciliationController::class, 'list'])->name('payments.reconciliation-runs.list');
                Route::get('/payments/reconciliation-runs/{runId}', [PaymentReconciliationController::class, 'get'])->name('payments.reconciliation-runs.show');
                Route::get('/payments/{paymentId}', [PaymentController::class, 'show'])->name('payments.show');
                Route::get('/payments/{paymentId}/status', [PaymentController::class, 'status'])->name('payments.status');
            });
            Route::middleware('permission:providers.manage')->group(function (): void {
                Route::post('/providers', [ProviderController::class, 'create'])->name('providers.create');
                Route::patch('/providers/{providerId}', [ProviderController::class, 'update'])->name('providers.update');
                Route::delete('/providers/{providerId}', [ProviderController::class, 'delete'])->name('providers.delete');
                Route::patch('/providers/{providerId}/profile', [ProviderProfileController::class, 'update'])->name('providers.profile.update');
                Route::put('/providers/{providerId}/specialties', [ProviderSpecialtyController::class, 'update'])->name('providers.specialties.update');
                Route::post('/providers/{providerId}/licenses', [ProviderLicenseController::class, 'create'])->name('providers.licenses.create');
                Route::delete('/providers/{providerId}/licenses/{licenseId}', [ProviderLicenseController::class, 'delete'])->name('providers.licenses.delete');
                Route::put('/providers/{providerId}/work-hours', [ProviderWorkHoursController::class, 'update'])
                    ->middleware('idempotency:providers.work-hours.update')
                    ->name('providers.work-hours.update');
                Route::post('/providers/{providerId}/time-off', [ProviderTimeOffController::class, 'create'])
                    ->middleware('idempotency:providers.time-off.create')
                    ->name('providers.time-off.create');
                Route::patch('/providers/{providerId}/time-off/{timeOffId}', [ProviderTimeOffController::class, 'update'])
                    ->middleware('idempotency:providers.time-off.update')
                    ->name('providers.time-off.update');
                Route::delete('/providers/{providerId}/time-off/{timeOffId}', [ProviderTimeOffController::class, 'delete'])
                    ->middleware('idempotency:providers.time-off.delete')
                    ->name('providers.time-off.delete');
                Route::post('/providers/{providerId}/availability/rules', [AvailabilityController::class, 'create'])
                    ->middleware('idempotency:availability.rules.create')
                    ->name('providers.availability.rules.create');
                Route::patch('/providers/{providerId}/availability/rules/{ruleId}', [AvailabilityController::class, 'update'])
                    ->middleware('idempotency:availability.rules.update')
                    ->name('providers.availability.rules.update');
                Route::delete('/providers/{providerId}/availability/rules/{ruleId}', [AvailabilityController::class, 'delete'])
                    ->middleware('idempotency:availability.rules.delete')
                    ->name('providers.availability.rules.delete');
                Route::post('/providers/{providerId}/availability:rebuild-cache', [AvailabilityController::class, 'rebuild'])
                    ->middleware('idempotency:availability.cache.rebuild')
                    ->name('providers.availability.rebuild');
                Route::post('/specialties', [SpecialtyController::class, 'create'])->name('specialties.create');
                Route::patch('/specialties/{specialtyId}', [SpecialtyController::class, 'update'])->name('specialties.update');
                Route::delete('/specialties/{specialtyId}', [SpecialtyController::class, 'delete'])->name('specialties.delete');
                Route::post('/provider-groups', [ProviderGroupController::class, 'create'])->name('provider-groups.create');
                Route::put('/provider-groups/{groupId}/members', [ProviderGroupController::class, 'updateMembers'])->name('provider-groups.members.update');
            });
            Route::middleware('permission:appointments.manage')->group(function (): void {
                Route::post('/appointments', [AppointmentController::class, 'create'])
                    ->middleware('idempotency:appointments.create')
                    ->name('appointments.create');
                Route::patch('/appointments/{appointmentId}', [AppointmentController::class, 'update'])
                    ->middleware('idempotency:appointments.update')
                    ->name('appointments.update');
                Route::delete('/appointments/{appointmentId}', [AppointmentController::class, 'delete'])
                    ->middleware('idempotency:appointments.delete')
                    ->name('appointments.delete');
                Route::post('/appointments/bulk', [AppointmentBulkController::class, 'update'])
                    ->middleware('idempotency:appointments.bulk.update')
                    ->name('appointments.bulk.update');
                Route::post('/appointments:bulk-cancel', [AppointmentBulkWorkflowController::class, 'cancel'])
                    ->middleware('idempotency:appointments.bulk.cancel')
                    ->name('appointments.bulk.cancel');
                Route::post('/appointments:bulk-reschedule', [AppointmentBulkWorkflowController::class, 'reschedule'])
                    ->middleware('idempotency:appointments.bulk.reschedule')
                    ->name('appointments.bulk.reschedule');
                Route::post('/appointments/{appointmentId}:make-recurring', [AppointmentRecurrenceController::class, 'create'])
                    ->middleware('idempotency:appointments.recurrence.create')
                    ->name('appointments.recurrence.create');
                Route::post('/appointments/recurrences/{recurrenceId}:cancel', [AppointmentRecurrenceController::class, 'cancel'])
                    ->middleware('idempotency:appointments.recurrence.cancel')
                    ->name('appointments.recurrence.cancel');
                Route::post('/appointments/{appointmentId}/participants', [AppointmentParticipantController::class, 'create'])
                    ->middleware('idempotency:appointments.participants.create')
                    ->name('appointments.participants.create');
                Route::delete('/appointments/{appointmentId}/participants/{participantId}', [AppointmentParticipantController::class, 'delete'])
                    ->middleware('idempotency:appointments.participants.delete')
                    ->name('appointments.participants.delete');
                Route::post('/appointments/{appointmentId}/notes', [AppointmentNoteController::class, 'create'])
                    ->middleware('idempotency:appointments.notes.create')
                    ->name('appointments.notes.create');
                Route::patch('/appointments/{appointmentId}/notes/{noteId}', [AppointmentNoteController::class, 'update'])
                    ->middleware('idempotency:appointments.notes.update')
                    ->name('appointments.notes.update');
                Route::delete('/appointments/{appointmentId}/notes/{noteId}', [AppointmentNoteController::class, 'delete'])
                    ->middleware('idempotency:appointments.notes.delete')
                    ->name('appointments.notes.delete');
                Route::post('/appointments/{appointmentId}:schedule', [AppointmentWorkflowController::class, 'schedule'])
                    ->middleware('idempotency:appointments.schedule')
                    ->name('appointments.schedule');
                Route::post('/appointments/{appointmentId}:confirm', [AppointmentWorkflowController::class, 'confirm'])
                    ->middleware('idempotency:appointments.confirm')
                    ->name('appointments.confirm');
                Route::post('/appointments/{appointmentId}:check-in', [AppointmentWorkflowController::class, 'checkIn'])
                    ->middleware('idempotency:appointments.check-in')
                    ->name('appointments.check-in');
                Route::post('/appointments/{appointmentId}:start', [AppointmentWorkflowController::class, 'start'])
                    ->middleware('idempotency:appointments.start')
                    ->name('appointments.start');
                Route::post('/appointments/{appointmentId}:complete', [AppointmentWorkflowController::class, 'complete'])
                    ->middleware('idempotency:appointments.complete')
                    ->name('appointments.complete');
                Route::post('/appointments/{appointmentId}:cancel', [AppointmentWorkflowController::class, 'cancel'])
                    ->middleware('idempotency:appointments.cancel')
                    ->name('appointments.cancel');
                Route::post('/appointments/{appointmentId}:no-show', [AppointmentWorkflowController::class, 'noShow'])
                    ->middleware('idempotency:appointments.no-show')
                    ->name('appointments.no-show');
                Route::post('/appointments/{appointmentId}:reschedule', [AppointmentWorkflowController::class, 'reschedule'])
                    ->middleware('idempotency:appointments.reschedule')
                    ->name('appointments.reschedule');
                Route::post('/appointments/{appointmentId}:restore', [AppointmentWorkflowController::class, 'restore'])
                    ->middleware('idempotency:appointments.restore')
                    ->name('appointments.restore');
                Route::post('/waitlist', [WaitlistController::class, 'create'])
                    ->middleware('idempotency:waitlist.create')
                    ->name('waitlist.create');
                Route::delete('/waitlist/{entryId}', [WaitlistController::class, 'delete'])
                    ->middleware('idempotency:waitlist.delete')
                    ->name('waitlist.delete');
                Route::post('/waitlist/{entryId}:offer-slot', [WaitlistController::class, 'offer'])
                    ->middleware('idempotency:waitlist.offer')
                    ->name('waitlist.offer');
            });
            Route::middleware('permission:labs.manage')->group(function (): void {
                Route::post('/lab-tests', [LabTestController::class, 'create'])->name('lab-tests.create');
                Route::patch('/lab-tests/{testId}', [LabTestController::class, 'update'])->name('lab-tests.update');
                Route::delete('/lab-tests/{testId}', [LabTestController::class, 'delete'])->name('lab-tests.delete');
                Route::post('/lab-orders', [LabOrderController::class, 'create'])
                    ->middleware('idempotency:lab-orders.create')
                    ->name('lab-orders.create');
                Route::patch('/lab-orders/{orderId}', [LabOrderController::class, 'update'])
                    ->middleware('idempotency:lab-orders.update')
                    ->name('lab-orders.update');
                Route::delete('/lab-orders/{orderId}', [LabOrderController::class, 'delete'])
                    ->middleware('idempotency:lab-orders.delete')
                    ->name('lab-orders.delete');
                Route::post('/lab-orders/bulk', [LabOrderBulkController::class, 'update'])
                    ->middleware('idempotency:lab-orders.bulk.update')
                    ->name('lab-orders.bulk.update');
                Route::post('/lab-orders/{orderId}:cancel', [LabOrderWorkflowController::class, 'cancel'])
                    ->middleware('idempotency:lab-orders.cancel')
                    ->name('lab-orders.cancel');
                Route::post('/lab-orders/{orderId}:mark-collected', [LabOrderWorkflowController::class, 'markCollected'])
                    ->middleware('idempotency:lab-orders.mark-collected')
                    ->name('lab-orders.mark-collected');
                Route::post('/lab-orders/{orderId}:mark-received', [LabOrderWorkflowController::class, 'markReceived'])
                    ->middleware('idempotency:lab-orders.mark-received')
                    ->name('lab-orders.mark-received');
                Route::post('/lab-orders/{orderId}:mark-complete', [LabOrderWorkflowController::class, 'complete'])
                    ->middleware('idempotency:lab-orders.mark-complete')
                    ->name('lab-orders.mark-complete');
                Route::middleware('permission:integrations.manage')->group(function (): void {
                    Route::post('/lab-orders/{orderId}:send', [LabOrderWorkflowController::class, 'send'])
                        ->middleware('idempotency:lab-orders.send')
                        ->name('lab-orders.send');
                    Route::post('/lab-orders:reconcile', [LabOrderWorkflowController::class, 'reconcile'])
                        ->middleware('idempotency:lab-orders.reconcile')
                        ->name('lab-orders.reconcile');
                });
            });
            Route::middleware('permission:prescriptions.manage')->group(function (): void {
                Route::post('/medications', [MedicationController::class, 'create'])
                    ->middleware('idempotency:medications.create')
                    ->name('medications.create');
                Route::patch('/medications/{medId}', [MedicationController::class, 'update'])
                    ->middleware('idempotency:medications.update')
                    ->name('medications.update');
                Route::delete('/medications/{medId}', [MedicationController::class, 'delete'])
                    ->middleware('idempotency:medications.delete')
                    ->name('medications.delete');
                Route::post('/patients/{patientId}/allergies', [PatientAllergyController::class, 'create'])
                    ->middleware('idempotency:patients.allergies.create')
                    ->name('patients.allergies.create');
                Route::delete('/patients/{patientId}/allergies/{allergyId}', [PatientAllergyController::class, 'delete'])
                    ->middleware('idempotency:patients.allergies.delete')
                    ->name('patients.allergies.delete');
                Route::post('/prescriptions', [PrescriptionController::class, 'create'])
                    ->middleware('idempotency:prescriptions.create')
                    ->name('prescriptions.create');
                Route::patch('/prescriptions/{prescriptionId}', [PrescriptionController::class, 'update'])
                    ->middleware('idempotency:prescriptions.update')
                    ->name('prescriptions.update');
                Route::delete('/prescriptions/{prescriptionId}', [PrescriptionController::class, 'delete'])
                    ->middleware('idempotency:prescriptions.delete')
                    ->name('prescriptions.delete');
                Route::post('/prescriptions/{prescriptionId}:issue', [PrescriptionWorkflowController::class, 'issue'])
                    ->middleware('idempotency:prescriptions.issue')
                    ->name('prescriptions.issue');
                Route::post('/prescriptions/{prescriptionId}:cancel', [PrescriptionWorkflowController::class, 'cancel'])
                    ->middleware('idempotency:prescriptions.cancel')
                    ->name('prescriptions.cancel');
                Route::post('/prescriptions/{prescriptionId}:dispense', [PrescriptionWorkflowController::class, 'dispense'])
                    ->middleware('idempotency:prescriptions.dispense')
                    ->name('prescriptions.dispense');
            });
            Route::middleware('permission:billing.manage')->group(function (): void {
                Route::post('/services', [BillableServiceController::class, 'create'])
                    ->middleware('idempotency:services.create')
                    ->name('services.create');
                Route::patch('/services/{serviceId}', [BillableServiceController::class, 'update'])
                    ->middleware('idempotency:services.update')
                    ->name('services.update');
                Route::delete('/services/{serviceId}', [BillableServiceController::class, 'delete'])
                    ->middleware('idempotency:services.delete')
                    ->name('services.delete');
                Route::post('/price-lists', [PriceListController::class, 'create'])
                    ->middleware('idempotency:price-lists.create')
                    ->name('price-lists.create');
                Route::patch('/price-lists/{priceListId}', [PriceListController::class, 'update'])
                    ->middleware('idempotency:price-lists.update')
                    ->name('price-lists.update');
                Route::delete('/price-lists/{priceListId}', [PriceListController::class, 'delete'])
                    ->middleware('idempotency:price-lists.delete')
                    ->name('price-lists.delete');
                Route::put('/price-lists/{priceListId}/items', [PriceListController::class, 'setItems'])
                    ->middleware('idempotency:price-lists.items.replace')
                    ->name('price-lists.items.replace');
                Route::post('/invoices', [InvoiceController::class, 'create'])
                    ->middleware('idempotency:invoices.create')
                    ->name('invoices.create');
                Route::patch('/invoices/{invoiceId}', [InvoiceController::class, 'update'])
                    ->middleware('idempotency:invoices.update')
                    ->name('invoices.update');
                Route::delete('/invoices/{invoiceId}', [InvoiceController::class, 'delete'])
                    ->middleware('idempotency:invoices.delete')
                    ->name('invoices.delete');
                Route::post('/invoices/{invoiceId}/items', [InvoiceItemController::class, 'create'])
                    ->middleware('idempotency:invoices.items.create')
                    ->name('invoices.items.create');
                Route::patch('/invoices/{invoiceId}/items/{itemId}', [InvoiceItemController::class, 'update'])
                    ->middleware('idempotency:invoices.items.update')
                    ->name('invoices.items.update');
                Route::delete('/invoices/{invoiceId}/items/{itemId}', [InvoiceItemController::class, 'delete'])
                    ->middleware('idempotency:invoices.items.delete')
                    ->name('invoices.items.delete');
                Route::post('/invoices/{invoiceId}:issue', [InvoiceWorkflowController::class, 'issue'])
                    ->middleware('idempotency:invoices.issue')
                    ->name('invoices.issue');
                Route::post('/invoices/{invoiceId}:finalize', [InvoiceWorkflowController::class, 'finalize'])
                    ->middleware('idempotency:invoices.finalize')
                    ->name('invoices.finalize');
                Route::post('/invoices/{invoiceId}:void', [InvoiceWorkflowController::class, 'void'])
                    ->middleware('idempotency:invoices.void')
                    ->name('invoices.void');
                Route::post('/payments:initiate', [PaymentController::class, 'initiate'])
                    ->middleware('idempotency:payments.initiate')
                    ->name('payments.initiate');
                Route::post('/payments/{paymentId}:capture', [PaymentWorkflowController::class, 'capture'])
                    ->middleware('idempotency:payments.capture')
                    ->name('payments.capture');
                Route::post('/payments/{paymentId}:cancel', [PaymentWorkflowController::class, 'cancel'])
                    ->middleware('idempotency:payments.cancel')
                    ->name('payments.cancel');
                Route::post('/payments/{paymentId}:refund', [PaymentWorkflowController::class, 'refund'])
                    ->middleware('idempotency:payments.refund')
                    ->name('payments.refund');
                Route::post('/payments:reconcile', [PaymentReconciliationController::class, 'reconcile'])
                    ->name('payments.reconcile');
            });
            Route::middleware('permission:integrations.manage')->group(function (): void {
                Route::post('/webhooks/lab/{provider}:verify', [LabWebhookController::class, 'verify'])
                    ->where('provider', '[A-Za-z0-9_-]+')
                    ->name('webhooks.labs.verify');
                Route::post('/webhooks/payme:verify', [PaymeWebhookController::class, 'verify'])
                    ->name('webhooks.payme.verify');
                Route::post('/webhooks/click:verify', [ClickWebhookController::class, 'verify'])
                    ->name('webhooks.click.verify');
                Route::post('/webhooks/uzum:verify', [UzumWebhookController::class, 'verify'])
                    ->name('webhooks.uzum.verify');
            });
            Route::middleware('permission:treatments.manage')->group(function (): void {
                Route::post('/treatment-plans', [TreatmentPlanController::class, 'create'])->name('treatment-plans.create');
                Route::patch('/treatment-plans/{planId}', [TreatmentPlanController::class, 'update'])->name('treatment-plans.update');
                Route::delete('/treatment-plans/{planId}', [TreatmentPlanController::class, 'delete'])->name('treatment-plans.delete');
                Route::post('/treatment-plans/{planId}/items', [TreatmentPlanItemController::class, 'create'])->name('treatment-plans.items.create');
                Route::patch('/treatment-plans/{planId}/items/{itemId}', [TreatmentPlanItemController::class, 'update'])->name('treatment-plans.items.update');
                Route::delete('/treatment-plans/{planId}/items/{itemId}', [TreatmentPlanItemController::class, 'delete'])->name('treatment-plans.items.delete');
                Route::post('/treatment-plans/{planId}:approve', [TreatmentPlanWorkflowController::class, 'approve'])->name('treatment-plans.approve');
                Route::post('/treatment-plans/{planId}:start', [TreatmentPlanWorkflowController::class, 'start'])->name('treatment-plans.start');
                Route::post('/treatment-plans/{planId}:pause', [TreatmentPlanWorkflowController::class, 'pause'])->name('treatment-plans.pause');
                Route::post('/treatment-plans/{planId}:resume', [TreatmentPlanWorkflowController::class, 'resume'])->name('treatment-plans.resume');
                Route::post('/treatment-plans/{planId}:finish', [TreatmentPlanWorkflowController::class, 'finish'])->name('treatment-plans.finish');
                Route::post('/treatment-plans/{planId}:reject', [TreatmentPlanWorkflowController::class, 'reject'])->name('treatment-plans.reject');
                Route::post('/encounters', [EncounterController::class, 'create'])->name('encounters.create');
                Route::patch('/encounters/{encounterId}', [EncounterController::class, 'update'])->name('encounters.update');
                Route::delete('/encounters/{encounterId}', [EncounterController::class, 'delete'])->name('encounters.delete');
                Route::post('/encounters/bulk', [EncounterBulkController::class, 'update'])
                    ->middleware('idempotency:encounters.bulk.update')
                    ->name('encounters.bulk.update');
                Route::post('/encounters/{encounterId}/diagnoses', [EncounterDiagnosisController::class, 'create'])->name('encounters.diagnoses.create');
                Route::delete('/encounters/{encounterId}/diagnoses/{dxId}', [EncounterDiagnosisController::class, 'delete'])->name('encounters.diagnoses.delete');
                Route::post('/encounters/{encounterId}/procedures', [EncounterProcedureController::class, 'create'])->name('encounters.procedures.create');
                Route::delete('/encounters/{encounterId}/procedures/{procId}', [EncounterProcedureController::class, 'delete'])->name('encounters.procedures.delete');
            });
            Route::middleware('permission:rbac.view')->group(function (): void {
                Route::get('/roles', [RoleController::class, 'list'])->name('roles.list');
                Route::get('/roles/{roleId}', [RoleController::class, 'show'])->name('roles.show');
                Route::get('/roles/{roleId}/permissions', [RoleController::class, 'permissions'])->name('roles.permissions.list');
                Route::get('/permissions', [PermissionCatalogController::class, 'list'])->name('permissions.list');
                Route::get('/permissions/groups', [PermissionCatalogController::class, 'groups'])->name('permissions.groups');
                Route::get('/users/{userId}/roles', [UserRoleController::class, 'list'])->name('users.roles.list');
                Route::get('/users/{userId}/permissions', [UserRoleController::class, 'permissions'])->name('users.permissions.show');
                Route::get('/rbac/audit', [RbacAuditController::class, 'list'])->name('rbac.audit.list');
            });
            Route::middleware('permission:rbac.manage')->group(function (): void {
                Route::post('/roles', [RoleController::class, 'create'])->name('roles.create');
                Route::patch('/roles/{roleId}', [RoleController::class, 'update'])->name('roles.update');
                Route::delete('/roles/{roleId}', [RoleController::class, 'delete'])->name('roles.delete');
                Route::put('/roles/{roleId}/permissions', [RoleController::class, 'setPermissions'])->name('roles.permissions.update');
                Route::put('/users/{userId}/roles', [UserRoleController::class, 'update'])->name('users.roles.update');
            });
        });
    });
});
