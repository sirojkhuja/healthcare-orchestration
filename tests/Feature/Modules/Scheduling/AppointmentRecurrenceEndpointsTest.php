<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use App\Modules\Scheduling\Application\Contracts\AppointmentRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/SchedulingTestSupport.php';

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

function appointmentRecurrenceFixture($testCase, string $email, string $tenantName): array
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

    $clinicId = providerCreateClinic($testCase, $token, $tenantId, 'recurrence-clinic', 'Recurrence Clinic')->json('data.id');
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
    ], 'recurrence-rule-'.$tenantId)->assertCreated();

    return [
        'token' => $token,
        'tenant_id' => $tenantId,
        'clinic_id' => $clinicId,
        'patient_id' => $patientId,
        'provider_id' => $providerId,
    ];
}

it('materializes recurring appointments and blocks future provider slots', function (): void {
    $fixture = appointmentRecurrenceFixture($this, 'appointments.recurrence@openai.com', 'Appointment Recurrence Tenant');
    $sourceAppointmentId = schedulingCreateAppointment(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        [
            'patient_id' => $fixture['patient_id'],
            'provider_id' => $fixture['provider_id'],
            'clinic_id' => $fixture['clinic_id'],
            'scheduled_start_at' => '2026-03-16T09:00:00+05:00',
            'scheduled_end_at' => '2026-03-16T09:30:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ],
        'recurrence-create-source',
    )->assertCreated()->json('data.id');

    schedulingAppointmentAction(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        $sourceAppointmentId,
        'schedule',
        'recurrence-schedule-source',
    )->assertOk();

    $response = $this->withToken($fixture['token'])
        ->withHeaders([
            'X-Tenant-Id' => $fixture['tenant_id'],
            'Idempotency-Key' => 'recurrence-create-series',
        ])
        ->postJson('/api/v1/appointments/'.$sourceAppointmentId.':make-recurring', [
            'frequency' => 'weekly',
            'interval' => 1,
            'occurrence_count' => 3,
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'appointment_recurrence_created')
        ->assertJsonPath('data.recurrence.status', 'active')
        ->assertJsonCount(2, 'data.appointments');

    $recurrenceId = $response->json('data.recurrence.id');

    /** @var AppointmentRepository $appointmentRepository */
    $appointmentRepository = app(AppointmentRepository::class);
    $generatedAppointments = $appointmentRepository->listForRecurrence($fixture['tenant_id'], $recurrenceId);

    expect(count($generatedAppointments))->toBe(2);
    expect($generatedAppointments[0]->status)->toBe('scheduled');

    $futureSlots = $this->withToken($fixture['token'])
        ->withHeader('X-Tenant-Id', $fixture['tenant_id'])
        ->getJson('/api/v1/providers/'.$fixture['provider_id'].'/availability/slots?date_from=2026-03-23&date_to=2026-03-23&limit=20')
        ->assertOk();

    expect(array_column($futureSlots->json('data.slots'), 'start_at'))
        ->not->toContain('2026-03-23T09:00:00+05:00')
        ->not->toContain('2026-03-23T09:15:00+05:00');

    expect(AuditEventRecord::query()->where('action', 'appointments.recurrence_created')->where('object_id', $recurrenceId)->exists())->toBeTrue();
});

it('cancels active recurrences and frees future generated slots without changing the source appointment', function (): void {
    $fixture = appointmentRecurrenceFixture($this, 'appointments.recurrence.cancel@openai.com', 'Appointment Recurrence Cancel Tenant');
    $sourceAppointmentId = schedulingCreateAppointment(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        [
            'patient_id' => $fixture['patient_id'],
            'provider_id' => $fixture['provider_id'],
            'clinic_id' => $fixture['clinic_id'],
            'scheduled_start_at' => '2026-03-16T09:00:00+05:00',
            'scheduled_end_at' => '2026-03-16T09:30:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ],
        'recurrence-cancel-create-source',
    )->assertCreated()->json('data.id');

    schedulingAppointmentAction(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        $sourceAppointmentId,
        'schedule',
        'recurrence-cancel-schedule-source',
    )->assertOk();

    $recurrenceId = $this->withToken($fixture['token'])
        ->withHeaders([
            'X-Tenant-Id' => $fixture['tenant_id'],
            'Idempotency-Key' => 'recurrence-cancel-create-series',
        ])
        ->postJson('/api/v1/appointments/'.$sourceAppointmentId.':make-recurring', [
            'frequency' => 'weekly',
            'interval' => 1,
            'occurrence_count' => 4,
        ])
        ->assertCreated()
        ->json('data.recurrence.id');

    $this->withToken($fixture['token'])
        ->withHeaders([
            'X-Tenant-Id' => $fixture['tenant_id'],
            'Idempotency-Key' => 'recurrence-cancel-series',
        ])
        ->postJson('/api/v1/appointments/recurrences/'.$recurrenceId.':cancel', [
            'reason' => 'Provider requested a new recurring pattern.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'appointment_recurrence_canceled')
        ->assertJsonPath('data.status', 'canceled')
        ->assertJsonPath('data.canceled_reason', 'Provider requested a new recurring pattern.');

    /** @var AppointmentRepository $appointmentRepository */
    $appointmentRepository = app(AppointmentRepository::class);
    $generatedAppointments = $appointmentRepository->listForRecurrence($fixture['tenant_id'], $recurrenceId);

    expect(count($generatedAppointments))->toBe(3);
    expect(array_unique(array_map(static fn ($appointment): string => $appointment->status, $generatedAppointments)))
        ->toBe(['canceled']);

    $this->withToken($fixture['token'])
        ->withHeader('X-Tenant-Id', $fixture['tenant_id'])
        ->getJson('/api/v1/appointments/'.$sourceAppointmentId)
        ->assertOk()
        ->assertJsonPath('data.status', 'scheduled');

    $restoredSlots = $this->withToken($fixture['token'])
        ->withHeader('X-Tenant-Id', $fixture['tenant_id'])
        ->getJson('/api/v1/providers/'.$fixture['provider_id'].'/availability/slots?date_from=2026-03-23&date_to=2026-03-23&limit=20')
        ->assertOk();

    expect(array_column($restoredSlots->json('data.slots'), 'start_at'))
        ->toContain('2026-03-23T09:00:00+05:00')
        ->toContain('2026-03-23T09:15:00+05:00');

    expect(AuditEventRecord::query()->where('action', 'appointments.recurrence_canceled')->where('object_id', $recurrenceId)->exists())->toBeTrue();
});
