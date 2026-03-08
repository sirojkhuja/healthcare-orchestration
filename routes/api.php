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
