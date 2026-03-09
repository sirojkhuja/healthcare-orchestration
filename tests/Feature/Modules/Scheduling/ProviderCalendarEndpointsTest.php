<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/SchedulingTestSupport.php';

uses(RefreshDatabase::class);

it('returns provider calendar day views and stores calendar exports', function (): void {
    Storage::fake('exports');

    User::factory()->create([
        'email' => 'provider.calendar.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'provider.calendar.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = providerIssueBearerToken($this, 'provider.calendar.admin@openai.com');
    $viewerToken = providerIssueBearerToken($this, 'provider.calendar.viewer@openai.com');
    $tenantId = providerCreateTenant($this, $adminToken, 'Provider Calendar Alpha')->json('data.id');
    $clinicId = providerCreateClinic($this, $adminToken, $tenantId, 'provider-calendar-alpha', 'Provider Calendar Clinic')->json('data.id');
    providerGrantPermissions($viewer, $tenantId, ['providers.view']);

    schedulingUpdateClinicSettings($this, $adminToken, $tenantId, $clinicId, [
        'timezone' => 'Asia/Tashkent',
        'default_appointment_duration_minutes' => 30,
        'slot_interval_minutes' => 15,
        'allow_walk_ins' => true,
        'require_appointment_confirmation' => false,
        'telemedicine_enabled' => false,
    ]);
    schedulingUpdateClinicWorkHours($this, $adminToken, $tenantId, $clinicId, [
        'monday' => [
            ['start_time' => '09:00', 'end_time' => '12:00'],
        ],
        'tuesday' => [
            ['start_time' => '09:00', 'end_time' => '12:00'],
        ],
    ]);

    $providerId = schedulingCreateProvider($this, $adminToken, $tenantId, [
        'clinic_id' => $clinicId,
    ])->json('data.id');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'provider-calendar-workhours',
        ])
        ->putJson('/api/v1/providers/'.$providerId.'/work-hours', [
            'days' => [
                'monday' => [
                    ['start_time' => '09:00', 'end_time' => '12:00'],
                ],
                'tuesday' => [
                    ['start_time' => '09:00', 'end_time' => '12:00'],
                ],
            ],
        ])
        ->assertOk();

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'provider-calendar-timeoff',
        ])
        ->postJson('/api/v1/providers/'.$providerId.'/time-off', [
            'specific_date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '10:30',
            'notes' => 'Morning handoff',
        ])
        ->assertCreated();

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/clinics/'.$clinicId.'/holidays', [
            'name' => 'Closed Tuesday',
            'start_date' => '2026-03-17',
            'end_date' => '2026-03-17',
            'is_closed' => true,
        ])
        ->assertCreated();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/calendar?date_from=2026-03-16&date_to=2026-03-17&limit=50')
        ->assertOk()
        ->assertJsonPath('data.provider_id', $providerId)
        ->assertJsonPath('data.timezone', 'Asia/Tashkent')
        ->assertJsonPath('data.slot_duration_minutes', 30)
        ->assertJsonPath('data.slot_interval_minutes', 15)
        ->assertJsonCount(2, 'data.days')
        ->assertJsonPath('data.days.0.date', '2026-03-16')
        ->assertJsonPath('data.days.0.weekday', 'monday')
        ->assertJsonPath('data.days.0.is_clinic_closed', false)
        ->assertJsonPath('data.days.0.work_hours.0.start_time', '09:00')
        ->assertJsonPath('data.days.0.time_off.0.start_time', '10:00')
        ->assertJsonPath('data.days.0.slot_count', 8)
        ->assertJsonPath('data.days.0.slots.0.start_at', '2026-03-16T09:00:00+05:00')
        ->assertJsonPath('data.days.0.slots.3.start_at', '2026-03-16T10:30:00+05:00')
        ->assertJsonPath('data.days.1.date', '2026-03-17')
        ->assertJsonPath('data.days.1.is_clinic_closed', true)
        ->assertJsonPath('data.days.1.slot_count', 0);

    $exportResponse = $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/calendar/export?date_from=2026-03-16&date_to=2026-03-17&limit=50&format=csv')
        ->assertOk()
        ->assertJsonPath('status', 'provider_calendar_export_created')
        ->assertJsonPath('data.provider_id', $providerId)
        ->assertJsonPath('data.row_count', 2)
        ->assertJsonPath('data.filters.limit', 50);

    $path = $exportResponse->json('data.storage.path');
    Storage::disk('exports')->assertExists($path);

    $contents = Storage::disk('exports')->get($path);

    expect($contents)->toContain('date,weekday,is_clinic_closed');
    expect($contents)->toContain('2026-03-16');
    expect($contents)->toContain('09:00-12:00');
    expect($contents)->toContain('2026-03-17');
    expect(AuditEventRecord::query()->where('action', 'providers.calendar_exported')->exists())->toBeTrue();
});

it('enforces provider calendar permissions tenant scope and window validation', function (): void {
    Storage::fake('exports');

    User::factory()->create([
        'email' => 'provider.calendar.admin+2@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'provider.calendar.viewer+2@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'provider.calendar.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = providerIssueBearerToken($this, 'provider.calendar.admin+2@openai.com');
    $viewerToken = providerIssueBearerToken($this, 'provider.calendar.viewer+2@openai.com');
    $blockedToken = providerIssueBearerToken($this, 'provider.calendar.blocked@openai.com');
    $tenantId = providerCreateTenant($this, $adminToken, 'Provider Calendar Beta')->json('data.id');
    providerGrantPermissions($viewer, $tenantId, ['providers.view']);
    providerEnsureMembership($blocked, $tenantId);

    $providerId = schedulingCreateProvider($this, $adminToken, $tenantId)->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/calendar?date_from=2026-03-16&date_to=2026-03-16')
        ->assertOk()
        ->assertJsonPath('data.provider_id', $providerId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/calendar/export?date_from=2026-03-16&date_to=2026-03-16')
        ->assertOk()
        ->assertJsonPath('status', 'provider_calendar_export_created');

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/calendar?date_from=2026-03-16&date_to=2026-03-16')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/calendar?date_from=2026-03-01&date_to=2026-04-02')
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $otherTenantId = providerCreateTenant($this, $adminToken, 'Provider Calendar Gamma')->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/calendar?date_from=2026-03-16&date_to=2026-03-16')
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});
