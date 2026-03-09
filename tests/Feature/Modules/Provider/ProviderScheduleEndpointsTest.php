<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/ProviderTestSupport.php';

uses(RefreshDatabase::class);

it('projects provider work hours and manages time off on top of availability rules', function (): void {
    User::factory()->create([
        'email' => 'provider.schedule.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'provider.schedule.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = providerIssueBearerToken($this, 'provider.schedule.admin@openai.com');
    $viewerToken = providerIssueBearerToken($this, 'provider.schedule.viewer@openai.com');
    $tenantId = providerCreateTenant($this, $adminToken, 'Provider Schedule Alpha')->json('data.id');
    $clinicId = providerCreateClinic($this, $adminToken, $tenantId, 'provider-schedule-alpha', 'Provider Schedule Clinic')->json('data.id');
    providerGrantPermissions($viewer, $tenantId, ['providers.view']);

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/clinics/'.$clinicId.'/settings', [
            'timezone' => 'Asia/Tashkent',
            'default_appointment_duration_minutes' => 30,
            'slot_interval_minutes' => 15,
            'allow_walk_ins' => true,
            'require_appointment_confirmation' => false,
            'telemedicine_enabled' => false,
        ])
        ->assertOk();

    $providerId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/providers', [
            'first_name' => 'Aziza',
            'last_name' => 'Karimova',
            'provider_type' => 'doctor',
            'clinic_id' => $clinicId,
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'provider-workhours-seed-available',
        ])
        ->postJson('/api/v1/providers/'.$providerId.'/availability/rules', [
            'scope_type' => 'weekly',
            'availability_type' => 'available',
            'weekday' => 'monday',
            'start_time' => '09:00',
            'end_time' => '17:00',
        ])
        ->assertCreated();

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'provider-workhours-seed-break',
        ])
        ->postJson('/api/v1/providers/'.$providerId.'/availability/rules', [
            'scope_type' => 'weekly',
            'availability_type' => 'unavailable',
            'weekday' => 'monday',
            'start_time' => '12:00',
            'end_time' => '13:00',
        ])
        ->assertCreated();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/work-hours')
        ->assertOk()
        ->assertJsonPath('data.provider_id', $providerId)
        ->assertJsonPath('data.timezone', 'Asia/Tashkent')
        ->assertJsonPath('data.days.monday.0.start_time', '09:00')
        ->assertJsonPath('data.days.monday.0.end_time', '12:00')
        ->assertJsonPath('data.days.monday.1.start_time', '13:00')
        ->assertJsonPath('data.days.monday.1.end_time', '17:00')
        ->assertJsonPath('data.days.tuesday', []);

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'provider-workhours-replace',
        ])
        ->putJson('/api/v1/providers/'.$providerId.'/work-hours', [
            'days' => [
                'monday' => [
                    ['start_time' => '09:00', 'end_time' => '11:00'],
                    ['start_time' => '12:00', 'end_time' => '16:00'],
                ],
                'tuesday' => [
                    ['start_time' => '10:00', 'end_time' => '14:00'],
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'provider_work_hours_updated')
        ->assertJsonPath('data.days.monday.0.end_time', '11:00')
        ->assertJsonPath('data.days.monday.1.start_time', '12:00')
        ->assertJsonPath('data.days.tuesday.0.start_time', '10:00');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'provider-workhours-replace',
        ])
        ->putJson('/api/v1/providers/'.$providerId.'/work-hours', [
            'days' => [
                'monday' => [
                    ['start_time' => '09:00', 'end_time' => '11:00'],
                    ['start_time' => '12:00', 'end_time' => '16:00'],
                ],
                'tuesday' => [
                    ['start_time' => '10:00', 'end_time' => '14:00'],
                ],
            ],
        ])
        ->assertOk()
        ->assertHeader('X-Idempotent-Replay', 'true');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/availability/rules')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.availability_type', 'available')
        ->assertJsonPath('data.0.weekday', 'monday')
        ->assertJsonPath('data.1.weekday', 'monday')
        ->assertJsonPath('data.2.weekday', 'tuesday');

    $timeOffId = $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'provider-timeoff-create',
        ])
        ->postJson('/api/v1/providers/'.$providerId.'/time-off', [
            'specific_date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '10:30',
            'notes' => 'Morning handoff',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'provider_time_off_created')
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'provider-timeoff-create',
        ])
        ->postJson('/api/v1/providers/'.$providerId.'/time-off', [
            'specific_date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '10:30',
            'notes' => 'Morning handoff',
        ])
        ->assertCreated()
        ->assertHeader('X-Idempotent-Replay', 'true');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/time-off')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $timeOffId)
        ->assertJsonPath('data.0.notes', 'Morning handoff');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'provider-timeoff-update',
        ])
        ->patchJson('/api/v1/providers/'.$providerId.'/time-off/'.$timeOffId, [
            'start_time' => '10:30',
            'end_time' => '11:00',
            'notes' => 'Updated handoff window',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'provider_time_off_updated')
        ->assertJsonPath('data.start_time', '10:30')
        ->assertJsonPath('data.notes', 'Updated handoff window');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'provider-timeoff-delete',
        ])
        ->deleteJson('/api/v1/providers/'.$providerId.'/time-off/'.$timeOffId)
        ->assertOk()
        ->assertJsonPath('status', 'provider_time_off_deleted')
        ->assertJsonPath('data.id', $timeOffId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/time-off')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    expect(AuditEventRecord::query()->where('action', 'providers.work_hours_updated')->where('object_id', $providerId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'providers.time_off_created')->where('object_id', $timeOffId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'providers.time_off_updated')->where('object_id', $timeOffId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'providers.time_off_deleted')->where('object_id', $timeOffId)->exists())->toBeTrue();
});

it('enforces provider schedule permissions idempotency and conflict rules', function (): void {
    User::factory()->create([
        'email' => 'provider.schedule.admin+2@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'provider.schedule.viewer+2@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'provider.schedule.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = providerIssueBearerToken($this, 'provider.schedule.admin+2@openai.com');
    $viewerToken = providerIssueBearerToken($this, 'provider.schedule.viewer+2@openai.com');
    $blockedToken = providerIssueBearerToken($this, 'provider.schedule.blocked@openai.com');
    $tenantId = providerCreateTenant($this, $adminToken, 'Provider Schedule Beta')->json('data.id');
    providerGrantPermissions($viewer, $tenantId, ['providers.view']);
    providerEnsureMembership($blocked, $tenantId);

    $providerId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/providers', [
            'first_name' => 'Bekzod',
            'last_name' => 'Nazarov',
            'provider_type' => 'doctor',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/work-hours')
        ->assertOk()
        ->assertJsonPath('data.days.monday', []);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/time-off')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->withToken($viewerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'viewer-denied-workhours',
        ])
        ->putJson('/api/v1/providers/'.$providerId.'/work-hours', [
            'days' => [
                'monday' => [
                    ['start_time' => '09:00', 'end_time' => '12:00'],
                ],
            ],
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/work-hours')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->withoutHeader('Idempotency-Key')
        ->putJson('/api/v1/providers/'.$providerId.'/work-hours', [
            'days' => [
                'monday' => [
                    ['start_time' => '09:00', 'end_time' => '12:00'],
                ],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonPath('details.errors.Idempotency-Key.0', 'The idempotency header is required for this operation.');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->withoutHeader('Idempotency-Key')
        ->postJson('/api/v1/providers/'.$providerId.'/time-off', [
            'specific_date' => '2026-03-16',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonPath('details.errors.Idempotency-Key.0', 'The idempotency header is required for this operation.');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'invalid-day-schedule',
        ])
        ->putJson('/api/v1/providers/'.$providerId.'/work-hours', [
            'days' => [
                'funday' => [
                    ['start_time' => '09:00', 'end_time' => '12:00'],
                ],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'timeoff-alpha',
        ])
        ->postJson('/api/v1/providers/'.$providerId.'/time-off', [
            'specific_date' => '2026-03-16',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])
        ->assertCreated();

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'timeoff-conflict',
        ])
        ->postJson('/api/v1/providers/'.$providerId.'/time-off', [
            'specific_date' => '2026-03-16',
            'start_time' => '09:30',
            'end_time' => '10:30',
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');
});
