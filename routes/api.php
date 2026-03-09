<?php

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
use App\Modules\Integrations\Presentation\Http\Controllers\PatientExternalReferenceController;
use App\Modules\Patient\Presentation\Http\Controllers\PatientConsentController;
use App\Modules\Patient\Presentation\Http\Controllers\PatientContactController;
use App\Modules\Patient\Presentation\Http\Controllers\PatientController;
use App\Modules\Patient\Presentation\Http\Controllers\PatientDocumentController;
use App\Modules\Patient\Presentation\Http\Controllers\PatientTagController;
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
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/ping', function () {
        return response()->json([
            'service' => config('app.name'),
            'version' => config('medflow.version'),
            'status' => 'ok',
        ]);
    });

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
