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

function appointmentWorkflowFixture(
    $testCase,
    string $email,
    string $tenantName,
    int $duration = 30,
    string $weekday = 'monday',
): array {
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

    $clinicId = providerCreateClinic($testCase, $token, $tenantId, 'workflow-clinic', 'Workflow Clinic')->json('data.id');
    schedulingUpdateClinicSettings($testCase, $token, $tenantId, $clinicId, [
        'timezone' => 'Asia/Tashkent',
        'default_appointment_duration_minutes' => $duration,
        'slot_interval_minutes' => 15,
        'allow_walk_ins' => true,
        'require_appointment_confirmation' => false,
        'telemedicine_enabled' => false,
    ]);
    schedulingUpdateClinicWorkHours($testCase, $token, $tenantId, $clinicId, [
        $weekday => [
            ['start_time' => '06:00', 'end_time' => '15:00'],
        ],
    ]);

    $patientId = schedulingCreatePatient($testCase, $token, $tenantId)->json('data.id');
    $providerId = schedulingCreateProvider($testCase, $token, $tenantId, [
        'clinic_id' => $clinicId,
    ])->json('data.id');
    schedulingCreateRule($testCase, $token, $tenantId, $providerId, [
        'scope_type' => 'weekly',
        'availability_type' => 'available',
        'weekday' => $weekday,
        'start_time' => '06:00',
        'end_time' => '15:00',
    ], 'workflow-rule-'.$tenantId)->assertCreated();

    return [
        'token' => $token,
        'tenant_id' => $tenantId,
        'clinic_id' => $clinicId,
        'patient_id' => $patientId,
        'provider_id' => $providerId,
    ];
}

it('runs appointment workflow actions and blocks booked slots in availability and calendar views', function (): void {
    $fixture = appointmentWorkflowFixture($this, 'appointments.workflow@openai.com', 'Appointment Workflow Tenant');
    $appointmentId = schedulingCreateAppointment(
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
        'workflow-create-1',
    )->assertCreated()->json('data.id');

    $initialSlots = $this->withToken($fixture['token'])
        ->withHeader('X-Tenant-Id', $fixture['tenant_id'])
        ->getJson('/api/v1/providers/'.$fixture['provider_id'].'/availability/slots?date_from=2026-03-16&date_to=2026-03-16&limit=20')
        ->assertOk();

    expect(array_column($initialSlots->json('data.slots'), 'start_at'))
        ->toContain('2026-03-16T09:00:00+05:00')
        ->toContain('2026-03-16T09:15:00+05:00');

    schedulingAppointmentAction(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        $appointmentId,
        'schedule',
        'workflow-schedule-1',
    )
        ->assertOk()
        ->assertJsonPath('status', 'appointment_scheduled')
        ->assertJsonPath('data.status', 'scheduled');

    schedulingAppointmentAction(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        $appointmentId,
        'confirm',
        'workflow-confirm-1',
    )
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    $bookedSlots = $this->withToken($fixture['token'])
        ->withHeader('X-Tenant-Id', $fixture['tenant_id'])
        ->getJson('/api/v1/providers/'.$fixture['provider_id'].'/availability/slots?date_from=2026-03-16&date_to=2026-03-16&limit=20')
        ->assertOk();

    expect(array_column($bookedSlots->json('data.slots'), 'start_at'))
        ->not->toContain('2026-03-16T08:45:00+05:00')
        ->not->toContain('2026-03-16T09:00:00+05:00')
        ->not->toContain('2026-03-16T09:15:00+05:00');

    $calendarResponse = $this->withToken($fixture['token'])
        ->withHeader('X-Tenant-Id', $fixture['tenant_id'])
        ->getJson('/api/v1/providers/'.$fixture['provider_id'].'/calendar?date_from=2026-03-16&date_to=2026-03-16&limit=40')
        ->assertOk();

    expect($calendarResponse->json('data.days.0.slot_count'))->toBe(32);
    expect(array_column($calendarResponse->json('data.days.0.slots'), 'start_at'))
        ->not->toContain('2026-03-16T08:45:00+05:00')
        ->not->toContain('2026-03-16T09:00:00+05:00')
        ->not->toContain('2026-03-16T09:15:00+05:00');

    schedulingAppointmentAction(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        $appointmentId,
        'check-in',
        'workflow-check-in-1',
    )
        ->assertOk()
        ->assertJsonPath('data.status', 'checked_in');
    schedulingAppointmentAction(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        $appointmentId,
        'start',
        'workflow-start-1',
    )
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');
    schedulingAppointmentAction(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        $appointmentId,
        'complete',
        'workflow-complete-1',
    )
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $restoredSlots = $this->withToken($fixture['token'])
        ->withHeader('X-Tenant-Id', $fixture['tenant_id'])
        ->getJson('/api/v1/providers/'.$fixture['provider_id'].'/availability/slots?date_from=2026-03-16&date_to=2026-03-16&limit=20')
        ->assertOk();

    expect(array_column($restoredSlots->json('data.slots'), 'start_at'))
        ->toContain('2026-03-16T08:45:00+05:00')
        ->toContain('2026-03-16T09:00:00+05:00')
        ->toContain('2026-03-16T09:15:00+05:00');

    expect(AuditEventRecord::query()->where('action', 'appointments.completed')->where('object_id', $appointmentId)->exists())->toBeTrue();
});

it('supports no-show and restore while the original slot is still active', function (): void {
    $fixture = appointmentWorkflowFixture(
        $this,
        'appointments.workflow.restore@openai.com',
        'Appointment Workflow Restore Tenant',
        60,
        'tuesday',
    );

    $appointmentId = schedulingCreateAppointment(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        [
            'patient_id' => $fixture['patient_id'],
            'provider_id' => $fixture['provider_id'],
            'clinic_id' => $fixture['clinic_id'],
            'scheduled_start_at' => '2026-03-10T07:00:00+05:00',
            'scheduled_end_at' => '2026-03-10T08:00:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ],
        'workflow-no-show-create',
    )->assertCreated()->json('data.id');

    schedulingAppointmentAction($this, $fixture['token'], $fixture['tenant_id'], $appointmentId, 'schedule', 'workflow-no-show-schedule')
        ->assertOk();

    schedulingAppointmentAction(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        $appointmentId,
        'no-show',
        'workflow-no-show-mark',
        ['reason' => 'Patient did not arrive.'],
    )
        ->assertOk()
        ->assertJsonPath('data.status', 'no_show');

    schedulingAppointmentAction($this, $fixture['token'], $fixture['tenant_id'], $appointmentId, 'restore', 'workflow-no-show-restore')
        ->assertOk()
        ->assertJsonPath('data.status', 'scheduled');
});

it('supports bulk cancel plus bulk reschedule workflows', function (): void {
    $fixture = appointmentWorkflowFixture($this, 'appointments.workflow.bulk@openai.com', 'Appointment Workflow Bulk Tenant');

    $cancelOne = schedulingCreateAppointment(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        [
            'patient_id' => $fixture['patient_id'],
            'provider_id' => $fixture['provider_id'],
            'clinic_id' => $fixture['clinic_id'],
            'scheduled_start_at' => '2026-03-23T09:00:00+05:00',
            'scheduled_end_at' => '2026-03-23T09:30:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ],
        'workflow-bulk-cancel-create-1',
    )->assertCreated()->json('data.id');
    $cancelTwo = schedulingCreateAppointment(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        [
            'patient_id' => $fixture['patient_id'],
            'provider_id' => $fixture['provider_id'],
            'clinic_id' => $fixture['clinic_id'],
            'scheduled_start_at' => '2026-03-23T10:00:00+05:00',
            'scheduled_end_at' => '2026-03-23T10:30:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ],
        'workflow-bulk-cancel-create-2',
    )->assertCreated()->json('data.id');
    $rescheduleOne = schedulingCreateAppointment(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        [
            'patient_id' => $fixture['patient_id'],
            'provider_id' => $fixture['provider_id'],
            'clinic_id' => $fixture['clinic_id'],
            'scheduled_start_at' => '2026-03-23T11:00:00+05:00',
            'scheduled_end_at' => '2026-03-23T11:30:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ],
        'workflow-bulk-reschedule-create-1',
    )->assertCreated()->json('data.id');
    $rescheduleTwo = schedulingCreateAppointment(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        [
            'patient_id' => $fixture['patient_id'],
            'provider_id' => $fixture['provider_id'],
            'clinic_id' => $fixture['clinic_id'],
            'scheduled_start_at' => '2026-03-23T12:00:00+05:00',
            'scheduled_end_at' => '2026-03-23T12:30:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ],
        'workflow-bulk-reschedule-create-2',
    )->assertCreated()->json('data.id');

    foreach ([
        [$cancelOne, 'workflow-bulk-cancel-schedule-1'],
        [$cancelTwo, 'workflow-bulk-cancel-schedule-2'],
        [$rescheduleOne, 'workflow-bulk-reschedule-schedule-1'],
        [$rescheduleTwo, 'workflow-bulk-reschedule-schedule-2'],
    ] as [$scheduledId, $key]) {
        schedulingAppointmentAction($this, $fixture['token'], $fixture['tenant_id'], $scheduledId, 'schedule', $key)->assertOk();
    }

    $this->withToken($fixture['token'])
        ->withHeaders([
            'X-Tenant-Id' => $fixture['tenant_id'],
            'Idempotency-Key' => 'workflow-bulk-cancel',
        ])
        ->postJson('/api/v1/appointments:bulk-cancel', [
            'appointment_ids' => [$cancelOne, $cancelTwo],
            'reason' => 'Clinic closed unexpectedly.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'appointments_bulk_canceled')
        ->assertJsonPath('data.affected_count', 2)
        ->assertJsonPath('data.appointments.0.status', 'canceled')
        ->assertJsonPath('data.appointments.1.status', 'canceled');

    $bulkReschedule = $this->withToken($fixture['token'])
        ->withHeaders([
            'X-Tenant-Id' => $fixture['tenant_id'],
            'Idempotency-Key' => 'workflow-bulk-reschedule',
        ])
        ->postJson('/api/v1/appointments:bulk-reschedule', [
            'items' => [
                [
                    'appointment_id' => $rescheduleOne,
                    'reason' => 'Provider moved clinic.',
                    'replacement_start_at' => '2026-03-23T13:00:00+05:00',
                    'replacement_end_at' => '2026-03-23T13:30:00+05:00',
                    'timezone' => 'Asia/Tashkent',
                    'clinic_id' => $fixture['clinic_id'],
                ],
                [
                    'appointment_id' => $rescheduleTwo,
                    'reason' => 'Provider moved clinic.',
                    'replacement_start_at' => '2026-03-23T13:30:00+05:00',
                    'replacement_end_at' => '2026-03-23T14:00:00+05:00',
                    'timezone' => 'Asia/Tashkent',
                    'clinic_id' => $fixture['clinic_id'],
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'appointments_bulk_rescheduled')
        ->assertJsonPath('data.affected_count', 2)
        ->assertJsonCount(2, 'data.replacement_appointments');

    expect($bulkReschedule->json('data.appointments.0.status'))->toBe('rescheduled');
    expect($bulkReschedule->json('data.replacement_appointments.0.status'))->toBe('scheduled');

    $slotStarts = array_column(
        $this->withToken($fixture['token'])
            ->withHeader('X-Tenant-Id', $fixture['tenant_id'])
            ->getJson('/api/v1/providers/'.$fixture['provider_id'].'/availability/slots?date_from=2026-03-23&date_to=2026-03-23&limit=40')
            ->assertOk()
            ->json('data.slots'),
        'start_at',
    );

    expect($slotStarts)
        ->toContain('2026-03-23T09:00:00+05:00')
        ->toContain('2026-03-23T10:00:00+05:00')
        ->toContain('2026-03-23T11:00:00+05:00')
        ->toContain('2026-03-23T12:00:00+05:00')
        ->not->toContain('2026-03-23T13:00:00+05:00');

    expect(AuditEventRecord::query()->where('action', 'appointments.bulk_canceled')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'appointments.bulk_rescheduled')->exists())->toBeTrue();
});
