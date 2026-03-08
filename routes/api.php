<?php

use App\Modules\IdentityAccess\Presentation\Http\Controllers\AuthController;
use App\Modules\IdentityAccess\Presentation\Http\Controllers\SecurityController;
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
        });
    });

    Route::middleware('auth:api')->group(function (): void {
        Route::post('/security/sessions:revoke-all', [SecurityController::class, 'revokeAllSessions'])->name('security.sessions.revoke-all');
    });
});
