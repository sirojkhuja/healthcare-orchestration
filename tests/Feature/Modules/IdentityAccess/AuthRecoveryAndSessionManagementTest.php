<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

it('requests a password reset token without exposing whether the email exists', function (): void {
    User::factory()->create([
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);

    $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'doctor@example.test',
    ])
        ->assertStatus(202)
        ->assertJsonPath('status', 'password_reset_requested');

    expect(DB::table('password_reset_tokens')->where('email', 'doctor@example.test')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'auth.password_reset_requested')->exists())->toBeTrue();
});

it('returns the same password reset response for an unknown email', function (): void {
    $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'missing@example.test',
    ])
        ->assertStatus(202)
        ->assertJsonPath('status', 'password_reset_requested');

    expect(DB::table('password_reset_tokens')->where('email', 'missing@example.test')->exists())->toBeFalse();
    expect(AuditEventRecord::query()->where('action', 'auth.password_reset_requested')->exists())->toBeTrue();
});

it('resets the password and revokes existing sessions', function (): void {
    $user = User::factory()->create([
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $accessToken = $loginResponse->json('tokens.access_token');
    $token = Password::broker('users')->createToken($user);

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'doctor@example.test',
        'token' => $token,
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'password_reset');

    $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertStatus(401);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'new-secret-password',
    ])->assertOk();

    $this->withToken($accessToken)
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('code', 'UNAUTHENTICATED');

    expect(AuditEventRecord::query()->where('action', 'auth.password_reset_completed')->exists())->toBeTrue();
});

it('lists the current user auth sessions', function (): void {
    User::factory()->create([
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);

    $firstSession = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $secondSession = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $currentSessionId = $secondSession->json('session.id');

    $this->withToken($secondSession->json('tokens.access_token'))
        ->postJson('/api/v1/auth/sessions')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $currentSessionId)
        ->assertJsonPath('data.0.current', true)
        ->assertJsonPath('data.1.current', false);
});

it('revokes an individual session for the authenticated user', function (): void {
    User::factory()->create([
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);

    $firstSession = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $secondSession = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $this->withToken($secondSession->json('tokens.access_token'))
        ->deleteJson('/api/v1/auth/sessions/'.$firstSession->json('session.id'))
        ->assertOk()
        ->assertJsonPath('status', 'session_revoked');

    $this->withToken($firstSession->json('tokens.access_token'))
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401);

    $this->withToken($secondSession->json('tokens.access_token'))
        ->getJson('/api/v1/auth/me')
        ->assertOk();
});

it('does not allow revoking another user session', function (): void {
    User::factory()->create([
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);
    User::factory()->create([
        'email' => 'nurse@example.test',
        'password' => 'secret-password',
    ]);

    $doctorSession = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $nurseSession = $this->postJson('/api/v1/auth/login', [
        'email' => 'nurse@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $this->withToken($doctorSession->json('tokens.access_token'))
        ->deleteJson('/api/v1/auth/sessions/'.$nurseSession->json('session.id'))
        ->assertStatus(404);

    $this->withToken($nurseSession->json('tokens.access_token'))
        ->getJson('/api/v1/auth/me')
        ->assertOk();
});

it('revokes all sessions for the authenticated user', function (): void {
    User::factory()->create([
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);

    $firstSession = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $secondSession = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $this->withToken($secondSession->json('tokens.access_token'))
        ->postJson('/api/v1/security/sessions:revoke-all')
        ->assertOk()
        ->assertJsonPath('status', 'all_sessions_revoked')
        ->assertJsonPath('revoked_sessions', 2);

    $this->withToken($firstSession->json('tokens.access_token'))
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401);

    $this->withToken($secondSession->json('tokens.access_token'))
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401);
});
