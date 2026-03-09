<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/SchedulingTestSupport.php';

uses(RefreshDatabase::class);

it('manages availability rules and generates slots with clinic constraints', function (): void {
    User::factory()->create([
        'email' => 'availability.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'availability.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = providerIssueBearerToken($this, 'availability.admin@openai.com');
    $viewerToken = providerIssueBearerToken($this, 'availability.viewer@openai.com');
    $tenantId = providerCreateTenant($this, $adminToken, 'Availability Tenant Alpha')->json('data.id');
    $clinicId = providerCreateClinic($this, $adminToken, $tenantId, 'avail-alpha', 'Availability Clinic')->json('data.id');
    providerGrantPermissions($viewer, $tenantId, ['providers.view']);

    schedulingUpdateClinicSettings($this, $adminToken, $tenantId, $clinicId);
    schedulingUpdateClinicWorkHours($this, $adminToken, $tenantId, $clinicId, [
        'monday' => [
            ['start_time' => '09:00', 'end_time' => '13:00'],
        ],
    ]);

    $providerId = schedulingCreateProvider($this, $adminToken, $tenantId, [
        'clinic_id' => $clinicId,
    ])->json('data.id');

    $weeklyRuleId = schedulingCreateRule($this, $adminToken, $tenantId, $providerId, [
        'scope_type' => 'weekly',
        'availability_type' => 'available',
        'weekday' => 'monday',
        'start_time' => '08:00',
        'end_time' => '12:00',
    ], 'rule-weekly-alpha')
        ->assertCreated()
        ->assertJsonPath('status', 'availability_rule_created')
        ->json('data.id');

    $dateRuleId = schedulingCreateRule($this, $adminToken, $tenantId, $providerId, [
        'scope_type' => 'date',
        'availability_type' => 'unavailable',
        'specific_date' => '2026-03-16',
        'start_time' => '10:00',
        'end_time' => '10:30',
        'notes' => 'Equipment maintenance',
    ], 'rule-date-alpha')
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/availability/rules')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $dateRuleId)
        ->assertJsonPath('data.1.id', $weeklyRuleId);

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'rule-update-alpha',
        ])
        ->patchJson('/api/v1/providers/'.$providerId.'/availability/rules/'.$weeklyRuleId, [
            'notes' => 'Core weekday availability',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'availability_rule_updated')
        ->assertJsonPath('data.notes', 'Core weekday availability');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/availability/slots?date_from=2026-03-16&date_to=2026-03-16&limit=20')
        ->assertOk()
        ->assertJsonPath('data.timezone', 'Asia/Tashkent')
        ->assertJsonPath('data.slot_duration_minutes', 30)
        ->assertJsonPath('data.slot_interval_minutes', 15)
        ->assertJsonCount(8, 'data.slots')
        ->assertJsonPath('data.slots.0.start_at', '2026-03-16T09:00:00+05:00')
        ->assertJsonPath('data.slots.0.end_at', '2026-03-16T09:30:00+05:00')
        ->assertJsonPath('data.slots.0.source_rule_ids.0', $weeklyRuleId)
        ->assertJsonPath('data.slots.3.start_at', '2026-03-16T10:30:00+05:00');

    $holidayId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/clinics/'.$clinicId.'/holidays', [
            'name' => 'Closed Maintenance',
            'start_date' => '2026-03-16',
            'end_date' => '2026-03-16',
            'is_closed' => true,
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/availability/slots?date_from=2026-03-16&date_to=2026-03-16&limit=20')
        ->assertOk()
        ->assertJsonCount(0, 'data.slots');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'rule-delete-alpha',
        ])
        ->deleteJson('/api/v1/providers/'.$providerId.'/availability/rules/'.$dateRuleId)
        ->assertOk()
        ->assertJsonPath('status', 'availability_rule_deleted')
        ->assertJsonPath('data.id', $dateRuleId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/availability/rules')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $weeklyRuleId);

    expect(AuditEventRecord::query()->where('action', 'availability.rules.created')->where('object_id', $weeklyRuleId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'availability.rules.updated')->where('object_id', $weeklyRuleId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'availability.rules.deleted')->where('object_id', $dateRuleId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'clinics.holiday_created')->where('object_id', $holidayId)->exists())->toBeTrue();
});

it('enforces rule conflicts permissions and idempotency', function (): void {
    User::factory()->create([
        'email' => 'availability.admin+2@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'availability.viewer+2@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'availability.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = providerIssueBearerToken($this, 'availability.admin+2@openai.com');
    $viewerToken = providerIssueBearerToken($this, 'availability.viewer+2@openai.com');
    $blockedToken = providerIssueBearerToken($this, 'availability.blocked@openai.com');
    $tenantId = providerCreateTenant($this, $adminToken, 'Availability Tenant Beta')->json('data.id');
    providerGrantPermissions($viewer, $tenantId, ['providers.view']);
    providerEnsureMembership($blocked, $tenantId);

    $providerId = schedulingCreateProvider($this, $adminToken, $tenantId)->json('data.id');

    schedulingCreateRule($this, $adminToken, $tenantId, $providerId, [
        'scope_type' => 'weekly',
        'availability_type' => 'available',
        'weekday' => 'monday',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ], 'rule-replay-beta')
        ->assertCreated()
        ->assertHeader('Idempotency-Key', 'rule-replay-beta');

    schedulingCreateRule($this, $adminToken, $tenantId, $providerId, [
        'scope_type' => 'weekly',
        'availability_type' => 'available',
        'weekday' => 'monday',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ], 'rule-replay-beta')
        ->assertCreated()
        ->assertHeader('X-Idempotent-Replay', 'true');

    schedulingCreateRule($this, $adminToken, $tenantId, $providerId, [
        'scope_type' => 'weekly',
        'availability_type' => 'available',
        'weekday' => 'monday',
        'start_time' => '11:00',
        'end_time' => '13:00',
    ], 'rule-conflict-beta')
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->withoutHeader('Idempotency-Key')
        ->postJson('/api/v1/providers/'.$providerId.'/availability/rules', [
            'scope_type' => 'weekly',
            'availability_type' => 'available',
            'weekday' => 'tuesday',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonPath('details.errors.Idempotency-Key.0', 'The idempotency header is required for this operation.');

    $this->withToken($viewerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'viewer-denied-beta',
        ])
        ->postJson('/api/v1/providers/'.$providerId.'/availability/rules', [
            'scope_type' => 'weekly',
            'availability_type' => 'available',
            'weekday' => 'tuesday',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/availability/rules')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/availability/slots?date_from=2026-03-16&date_to=2026-03-16')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('rebuilds cached slots and invalidates tenant timezone fallback', function (): void {
    User::factory()->create([
        'email' => 'availability.admin+3@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = providerIssueBearerToken($this, 'availability.admin+3@openai.com');
    $tenantId = providerCreateTenant($this, $adminToken, 'Availability Tenant Gamma')->json('data.id');
    $providerId = schedulingCreateProvider($this, $adminToken, $tenantId)->json('data.id');

    schedulingUpdateTenantSettings($this, $adminToken, $tenantId, [
        'locale' => 'en',
        'timezone' => 'Asia/Tashkent',
        'currency' => 'UZS',
    ]);

    $dateRuleId = schedulingCreateRule($this, $adminToken, $tenantId, $providerId, [
        'scope_type' => 'date',
        'availability_type' => 'available',
        'specific_date' => '2026-03-17',
        'start_time' => '09:00',
        'end_time' => '10:00',
    ], 'rule-date-gamma')
        ->assertCreated()
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/availability/slots?date_from=2026-03-17&date_to=2026-03-17&limit=10')
        ->assertOk()
        ->assertJsonPath('data.timezone', 'Asia/Tashkent')
        ->assertJsonPath('data.slots.0.start_at', '2026-03-17T09:00:00+05:00');

    schedulingUpdateTenantSettings($this, $adminToken, $tenantId, [
        'locale' => 'en',
        'timezone' => 'UTC',
        'currency' => 'UZS',
    ]);

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/availability/slots?date_from=2026-03-17&date_to=2026-03-17&limit=10')
        ->assertOk()
        ->assertJsonPath('data.timezone', 'UTC')
        ->assertJsonPath('data.slots.0.start_at', '2026-03-17T09:00:00+00:00');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'rebuild-gamma',
        ])
        ->postJson('/api/v1/providers/'.$providerId.'/availability:rebuild-cache', [
            'date_from' => '2026-03-17',
            'date_to' => '2026-03-17',
            'limit' => 10,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'availability_cache_rebuilt')
        ->assertJsonPath('data.provider_id', $providerId)
        ->assertJsonPath('data.slots.0.source_rule_ids.0', $dateRuleId);

    expect(AuditEventRecord::query()->where('action', 'availability.cache_rebuilt')->where('object_id', $providerId)->exists())->toBeTrue();
});
