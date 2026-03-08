<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use App\Modules\IdentityAccess\Infrastructure\Auth\Persistence\AuthSessionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs in with valid credentials and returns tokens plus the current user payload', function (): void {
    $user = User::factory()->create([
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('user.id', (string) $user->getAuthIdentifier())
        ->assertJsonPath('user.email', 'doctor@example.test')
        ->assertJsonPath('tokens.token_type', 'Bearer');

    $sessionId = $response->json('session.id');
    $accessToken = $response->json('tokens.access_token');

    expect($sessionId)->toBeString()->not->toBe('');
    expect($accessToken)->toBeString()->not->toBe('');
    expect(AuthSessionRecord::query()->whereKey($sessionId)->exists())->toBeTrue();

    $this->withToken($accessToken)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('user.id', (string) $user->getAuthIdentifier())
        ->assertJsonPath('session.id', $sessionId);

    $auditEvent = AuditEventRecord::query()
        ->where('action', 'auth.login')
        ->where('object_id', $sessionId)
        ->first();

    expect($auditEvent)->not->toBeNull();
});

it('rejects invalid login credentials', function (): void {
    User::factory()->create([
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'wrong-password',
    ])
        ->assertStatus(401)
        ->assertJsonPath('code', 'UNAUTHENTICATED');
});

it('refreshes tokens, rotates the bearer token, and invalidates the previous access token', function (): void {
    User::factory()->create([
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $oldAccessToken = $loginResponse->json('tokens.access_token');
    $refreshToken = $loginResponse->json('tokens.refresh_token');
    $sessionId = $loginResponse->json('session.id');

    $refreshResponse = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $refreshToken,
    ]);

    $refreshResponse
        ->assertOk()
        ->assertJsonPath('session.id', $sessionId);

    $newAccessToken = $refreshResponse->json('tokens.access_token');
    $newRefreshToken = $refreshResponse->json('tokens.refresh_token');

    expect($newAccessToken)->not->toBe($oldAccessToken);
    expect($newRefreshToken)->not->toBe($refreshToken);

    $this->withToken($newAccessToken)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('session.id', $sessionId);

    $this->withToken($oldAccessToken)
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('code', 'UNAUTHENTICATED');

    $auditEvent = AuditEventRecord::query()
        ->where('action', 'auth.refresh')
        ->where('object_id', $sessionId)
        ->first();

    expect($auditEvent)->not->toBeNull();
});

it('logs out the current session and revokes subsequent bearer token use', function (): void {
    User::factory()->create([
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $accessToken = $loginResponse->json('tokens.access_token');
    $sessionId = $loginResponse->json('session.id');

    $this->withToken($accessToken)
        ->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('status', 'logged_out');

    $this->withToken($accessToken)
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('code', 'UNAUTHENTICATED');

    $session = AuthSessionRecord::query()->findOrFail($sessionId);

    expect($session->revoked_at)->not->toBeNull();
    expect(AuditEventRecord::query()->where('action', 'auth.logout')->where('object_id', $sessionId)->exists())->toBeTrue();
});
