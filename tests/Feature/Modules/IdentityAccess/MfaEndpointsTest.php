<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use App\Modules\AuditCompliance\Infrastructure\Persistence\SecurityEventRecord;
use App\Modules\IdentityAccess\Application\Contracts\MfaTotpService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('starts and confirms MFA setup for the authenticated user', function (): void {
    User::factory()->create([
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $setupResponse = $this->withToken($loginResponse->json('tokens.access_token'))
        ->postJson('/api/v1/auth/mfa/setup')
        ->assertOk()
        ->assertJsonPath('status', 'mfa_setup_pending')
        ->assertJsonCount((int) config('medflow.auth.mfa.recovery_codes_count'), 'recovery_codes');

    $secret = $setupResponse->json('secret');
    expect($secret)->toBeString();

    $code = app(MfaTotpService::class)->codeAt($secret, CarbonImmutable::now());

    $this->withToken($loginResponse->json('tokens.access_token'))
        ->postJson('/api/v1/auth/mfa/verify', [
            'code' => $code,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'mfa_enabled');

    expect(AuditEventRecord::query()->where('action', 'auth.mfa.setup_started')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'auth.mfa.enabled')->exists())->toBeTrue();
    expect(SecurityEventRecord::query()->where('event_type', 'mfa.setup_started')->exists())->toBeTrue();
    expect(SecurityEventRecord::query()->where('event_type', 'mfa.enabled')->exists())->toBeTrue();
});

it('requires an MFA challenge at login and completes it with a TOTP code', function (): void {
    User::factory()->create([
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);

    $bootstrapLogin = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $setupResponse = $this->withToken($bootstrapLogin->json('tokens.access_token'))
        ->postJson('/api/v1/auth/mfa/setup')
        ->assertOk();

    $secret = $setupResponse->json('secret');

    $this->withToken($bootstrapLogin->json('tokens.access_token'))
        ->postJson('/api/v1/auth/mfa/verify', [
            'code' => app(MfaTotpService::class)->codeAt($secret, CarbonImmutable::now()),
        ])
        ->assertOk();

    $challengeResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])
        ->assertStatus(401)
        ->assertJsonPath('code', 'MFA_REQUIRED');

    $challengeId = $challengeResponse->json('details.challenge_id');

    $this->postJson('/api/v1/auth/mfa/verify', [
        'challenge_id' => $challengeId,
        'code' => app(MfaTotpService::class)->codeAt($secret, CarbonImmutable::now()),
    ])
        ->assertOk()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'session' => ['id'],
            'tokens' => ['token_type', 'access_token', 'access_token_expires_at', 'refresh_token', 'refresh_token_expires_at'],
        ]);

    expect(AuditEventRecord::query()->where('action', 'auth.mfa.challenge_required')->exists())->toBeTrue();
    expect(SecurityEventRecord::query()->where('event_type', 'mfa.challenge_required')->exists())->toBeTrue();
    expect(SecurityEventRecord::query()->where('event_type', 'mfa.challenge_verified')->exists())->toBeTrue();
});

it('allows completing an MFA login challenge with a one-time recovery code', function (): void {
    User::factory()->create([
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);

    $bootstrapLogin = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $setupResponse = $this->withToken($bootstrapLogin->json('tokens.access_token'))
        ->postJson('/api/v1/auth/mfa/setup')
        ->assertOk();

    $secret = $setupResponse->json('secret');
    $recoveryCode = $setupResponse->json('recovery_codes.0');

    $this->withToken($bootstrapLogin->json('tokens.access_token'))
        ->postJson('/api/v1/auth/mfa/verify', [
            'code' => app(MfaTotpService::class)->codeAt($secret, CarbonImmutable::now()),
        ])
        ->assertOk();

    $firstChallenge = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])
        ->assertStatus(401)
        ->json('details.challenge_id');

    $this->postJson('/api/v1/auth/mfa/verify', [
        'challenge_id' => $firstChallenge,
        'recovery_code' => $recoveryCode,
    ])->assertOk();

    $secondChallenge = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])
        ->assertStatus(401)
        ->json('details.challenge_id');

    $this->postJson('/api/v1/auth/mfa/verify', [
        'challenge_id' => $secondChallenge,
        'recovery_code' => $recoveryCode,
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED');

    expect(SecurityEventRecord::query()->where('event_type', 'mfa.recovery_code_used')->exists())->toBeTrue();
    expect(SecurityEventRecord::query()->where('event_type', 'mfa.challenge_failed')->exists())->toBeTrue();
});

it('disables MFA with a valid code and restores direct login', function (): void {
    User::factory()->create([
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);

    $bootstrapLogin = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $setupResponse = $this->withToken($bootstrapLogin->json('tokens.access_token'))
        ->postJson('/api/v1/auth/mfa/setup')
        ->assertOk();

    $secret = $setupResponse->json('secret');
    $currentCode = app(MfaTotpService::class)->codeAt($secret, CarbonImmutable::now());

    $this->withToken($bootstrapLogin->json('tokens.access_token'))
        ->postJson('/api/v1/auth/mfa/verify', [
            'code' => $currentCode,
        ])
        ->assertOk();

    $this->withToken($bootstrapLogin->json('tokens.access_token'))
        ->postJson('/api/v1/auth/mfa/disable', [
            'code' => app(MfaTotpService::class)->codeAt($secret, CarbonImmutable::now()),
        ])
        ->assertOk()
        ->assertJsonPath('status', 'mfa_disabled');

    $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    expect(AuditEventRecord::query()->where('action', 'auth.mfa.disabled')->exists())->toBeTrue();
    expect(SecurityEventRecord::query()->where('event_type', 'mfa.disabled')->exists())->toBeTrue();
});
