<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/SchedulingTestSupport.php';

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

function appointmentWaitlistFixture($testCase, string $email, string $tenantName): array
{
    $admin = User::factory()->create([
        'email' => $email,
        'password' => 'secret-password',
    ]);
    $token = schedulingIssueBearerToken($testCase, $email);
    $tenantId = schedulingCreateTenant($testCase, $token, $tenantName)->json('data.id');

    patientGrantPermissions($admin, $tenantId, [
        'tenants.manage',
        'appointments.view',
        'appointments.manage',
        'patients.manage',
        'providers.view',
        'providers.manage',
    ]);

    $clinicId = providerCreateClinic($testCase, $token, $tenantId, 'waitlist-clinic', 'Waitlist Clinic')->json('data.id');
    schedulingUpdateClinicSettings($testCase, $token, $tenantId, $clinicId, [
        'timezone' => 'Asia/Tashkent',
        'default_appointment_duration_minutes' => 30,
        'slot_interval_minutes' => 15,
        'allow_walk_ins' => true,
        'require_appointment_confirmation' => false,
        'telemedicine_enabled' => false,
    ]);
    schedulingUpdateClinicWorkHours($testCase, $token, $tenantId, $clinicId, [
        'monday' => [
            ['start_time' => '09:00', 'end_time' => '12:00'],
        ],
    ]);

    $patientId = schedulingCreatePatient($testCase, $token, $tenantId)->json('data.id');
    $providerId = schedulingCreateProvider($testCase, $token, $tenantId, [
        'clinic_id' => $clinicId,
    ])->json('data.id');
    schedulingCreateRule($testCase, $token, $tenantId, $providerId, [
        'scope_type' => 'weekly',
        'availability_type' => 'available',
        'weekday' => 'monday',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ], 'waitlist-rule-'.$tenantId)->assertCreated();

    return [
        'token' => $token,
        'tenant_id' => $tenantId,
        'clinic_id' => $clinicId,
        'patient_id' => $patientId,
        'provider_id' => $providerId,
    ];
}

it('creates lists and removes waitlist entries inside tenant scope', function (): void {
    $fixture = appointmentWaitlistFixture($this, 'appointments.waitlist@openai.com', 'Appointment Waitlist Tenant');

    $entryId = schedulingCreateWaitlistEntry(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        [
            'patient_id' => $fixture['patient_id'],
            'provider_id' => $fixture['provider_id'],
            'clinic_id' => $fixture['clinic_id'],
            'desired_date_from' => '2026-03-16',
            'desired_date_to' => '2026-03-17',
            'preferred_start_time' => '09:00',
            'preferred_end_time' => '10:30',
            'notes' => 'Please book as early as possible.',
        ],
        'waitlist-create-1',
    )
        ->assertCreated()
        ->assertJsonPath('status', 'waitlist_entry_created')
        ->assertJsonPath('data.status', 'open')
        ->json('data.id');

    $this->withToken($fixture['token'])
        ->withHeader('X-Tenant-Id', $fixture['tenant_id'])
        ->getJson('/api/v1/waitlist?status=open&limit=10')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $entryId);

    $this->withToken($fixture['token'])
        ->withHeaders([
            'X-Tenant-Id' => $fixture['tenant_id'],
            'Idempotency-Key' => 'waitlist-remove-1',
        ])
        ->deleteJson('/api/v1/waitlist/'.$entryId)
        ->assertOk()
        ->assertJsonPath('status', 'waitlist_entry_removed')
        ->assertJsonPath('data.status', 'removed');

    $this->withToken($fixture['token'])
        ->withHeader('X-Tenant-Id', $fixture['tenant_id'])
        ->getJson('/api/v1/waitlist?status=removed&limit=10')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $entryId);

    expect(AuditEventRecord::query()->where('action', 'appointments.waitlist_added')->where('object_id', $entryId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'appointments.waitlist_removed')->where('object_id', $entryId)->exists())->toBeTrue();
});

it('offers available slots to waitlist entries and rejects offers outside the requested window', function (): void {
    $fixture = appointmentWaitlistFixture($this, 'appointments.waitlist.offer@openai.com', 'Appointment Waitlist Offer Tenant');
    $entryId = schedulingCreateWaitlistEntry(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        [
            'patient_id' => $fixture['patient_id'],
            'provider_id' => $fixture['provider_id'],
            'clinic_id' => $fixture['clinic_id'],
            'desired_date_from' => '2026-03-16',
            'desired_date_to' => '2026-03-16',
            'preferred_start_time' => '09:00',
            'preferred_end_time' => '10:00',
        ],
        'waitlist-offer-create-1',
    )->assertCreated()->json('data.id');

    $offerResponse = $this->withToken($fixture['token'])
        ->withHeaders([
            'X-Tenant-Id' => $fixture['tenant_id'],
            'Idempotency-Key' => 'waitlist-offer-1',
        ])
        ->postJson('/api/v1/waitlist/'.$entryId.':offer-slot', [
            'scheduled_start_at' => '2026-03-16T09:00:00+05:00',
            'scheduled_end_at' => '2026-03-16T09:30:00+05:00',
            'timezone' => 'Asia/Tashkent',
            'clinic_id' => $fixture['clinic_id'],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'waitlist_slot_offered')
        ->assertJsonPath('data.entry.status', 'booked')
        ->assertJsonPath('data.appointment.status', 'scheduled');

    expect($offerResponse->json('data.entry.booked_appointment_id'))
        ->toBeString()
        ->not->toBe('');

    $offeredSlots = $this->withToken($fixture['token'])
        ->withHeader('X-Tenant-Id', $fixture['tenant_id'])
        ->getJson('/api/v1/providers/'.$fixture['provider_id'].'/availability/slots?date_from=2026-03-16&date_to=2026-03-16&limit=20')
        ->assertOk();

    expect(array_column($offeredSlots->json('data.slots'), 'start_at'))
        ->not->toContain('2026-03-16T09:00:00+05:00')
        ->not->toContain('2026-03-16T09:15:00+05:00');

    $outOfWindowEntryId = schedulingCreateWaitlistEntry(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        [
            'patient_id' => $fixture['patient_id'],
            'provider_id' => $fixture['provider_id'],
            'clinic_id' => $fixture['clinic_id'],
            'desired_date_from' => '2026-03-17',
            'desired_date_to' => '2026-03-17',
            'preferred_start_time' => '09:00',
            'preferred_end_time' => '09:30',
        ],
        'waitlist-offer-create-2',
    )->assertCreated()->json('data.id');

    $this->withToken($fixture['token'])
        ->withHeaders([
            'X-Tenant-Id' => $fixture['tenant_id'],
            'Idempotency-Key' => 'waitlist-offer-2',
        ])
        ->postJson('/api/v1/waitlist/'.$outOfWindowEntryId.':offer-slot', [
            'scheduled_start_at' => '2026-03-16T09:30:00+05:00',
            'scheduled_end_at' => '2026-03-16T10:00:00+05:00',
            'timezone' => 'Asia/Tashkent',
            'clinic_id' => $fixture['clinic_id'],
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    expect(AuditEventRecord::query()->where('action', 'appointments.waitlist_booked')->where('object_id', $entryId)->exists())->toBeTrue();
});
