<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use App\Modules\Scheduling\Application\Contracts\AppointmentRepository;
use App\Modules\Scheduling\Domain\Appointments\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/SchedulingTestSupport.php';

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('bulk updates draft appointments with idempotency authorization and all-or-nothing safety', function (): void {
    $admin = User::factory()->create([
        'email' => 'appointments.bulk.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'appointments.bulk.viewer@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'appointments.bulk.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = schedulingIssueBearerToken($this, 'appointments.bulk.admin@openai.com');
    $viewerToken = schedulingIssueBearerToken($this, 'appointments.bulk.viewer@openai.com');
    $blockedToken = schedulingIssueBearerToken($this, 'appointments.bulk.blocked@openai.com');
    $tenantId = schedulingCreateTenant($this, $adminToken, 'Appointment Bulk Tenant')->json('data.id');

    patientGrantPermissions($admin, $tenantId, [
        'tenants.manage',
        'appointments.view',
        'appointments.manage',
        'patients.manage',
        'providers.manage',
    ]);
    patientGrantPermissions($viewer, $tenantId, ['appointments.view']);
    patientEnsureMembership($blocked, $tenantId);

    $patientId = schedulingCreatePatient($this, $adminToken, $tenantId)->json('data.id');
    $providerId = schedulingCreateProvider($this, $adminToken, $tenantId)->json('data.id');
    $appointmentIdOne = schedulingCreateAppointment(
        $this,
        $adminToken,
        $tenantId,
        [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'scheduled_start_at' => '2026-03-18T09:00:00+05:00',
            'scheduled_end_at' => '2026-03-18T09:30:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ],
        'appointments-bulk-create-1',
    )->assertCreated()->json('data.id');
    $appointmentIdTwo = schedulingCreateAppointment(
        $this,
        $adminToken,
        $tenantId,
        [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'scheduled_start_at' => '2026-03-18T10:00:00+05:00',
            'scheduled_end_at' => '2026-03-18T10:30:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ],
        'appointments-bulk-create-2',
    )->assertCreated()->json('data.id');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointments-bulk-update',
        ])
        ->postJson('/api/v1/appointments/bulk', [
            'appointment_ids' => [$appointmentIdTwo, $appointmentIdOne],
            'changes' => [
                'timezone' => 'UTC',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'appointments_bulk_updated')
        ->assertJsonPath('data.affected_count', 2)
        ->assertJsonPath('data.updated_fields.0', 'timezone')
        ->assertJsonPath('data.appointments.0.id', $appointmentIdTwo)
        ->assertJsonPath('data.appointments.0.scheduled_slot.timezone', 'UTC')
        ->assertJsonPath('data.appointments.1.id', $appointmentIdOne)
        ->assertJsonPath('data.appointments.1.scheduled_slot.timezone', 'UTC');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointments-bulk-update',
        ])
        ->postJson('/api/v1/appointments/bulk', [
            'appointment_ids' => [$appointmentIdTwo, $appointmentIdOne],
            'changes' => [
                'timezone' => 'UTC',
            ],
        ])
        ->assertOk()
        ->assertHeader('X-Idempotent-Replay', 'true');

    expect(AuditEventRecord::query()->where('action', 'appointments.bulk_updated')->exists())->toBeTrue();

    $this->withToken($viewerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointments-bulk-viewer-denied',
        ])
        ->postJson('/api/v1/appointments/bulk', [
            'appointment_ids' => [$appointmentIdOne],
            'changes' => [
                'timezone' => 'Asia/Tashkent',
            ],
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($blockedToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointments-bulk-blocked-denied',
        ])
        ->postJson('/api/v1/appointments/bulk', [
            'appointment_ids' => [$appointmentIdOne],
            'changes' => [
                'timezone' => 'Asia/Tashkent',
            ],
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->withoutHeader('Idempotency-Key')
        ->postJson('/api/v1/appointments/bulk', [
            'appointment_ids' => [$appointmentIdOne],
            'changes' => [
                'timezone' => 'Asia/Tashkent',
            ],
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonPath('details.errors.Idempotency-Key.0', 'The idempotency header is required for this operation.');

    /** @var AppointmentRepository $appointmentRepository */
    $appointmentRepository = app(AppointmentRepository::class);
    $scheduledAppointment = $appointmentRepository->create($tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'clinic_id' => null,
        'room_id' => null,
        'status' => AppointmentStatus::SCHEDULED->value,
        'scheduled_start_at' => CarbonImmutable::parse('2026-03-19T09:00:00+05:00'),
        'scheduled_end_at' => CarbonImmutable::parse('2026-03-19T09:30:00+05:00'),
        'timezone' => 'Asia/Tashkent',
        'last_transition' => [
            'from_status' => 'draft',
            'to_status' => 'scheduled',
        ],
    ]);

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointments-bulk-failed-state',
        ])
        ->postJson('/api/v1/appointments/bulk', [
            'appointment_ids' => [$appointmentIdOne, $scheduledAppointment->appointmentId],
            'changes' => [
                'timezone' => 'Asia/Tashkent',
            ],
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/appointments/'.$appointmentIdOne)
        ->assertOk()
        ->assertJsonPath('data.scheduled_slot.timezone', 'UTC');
});
