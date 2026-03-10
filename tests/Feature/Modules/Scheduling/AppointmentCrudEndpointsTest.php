<?php

use App\Models\User;
use App\Modules\Scheduling\Application\Contracts\AppointmentRepository;
use App\Modules\Scheduling\Domain\Appointments\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/SchedulingTestSupport.php';

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('creates lists updates and soft deletes draft appointments in tenant scope', function (): void {
    $admin = User::factory()->create([
        'email' => 'appointments.admin@openai.com',
        'password' => 'secret-password',
    ]);

    $token = schedulingIssueBearerToken($this, 'appointments.admin@openai.com');
    $tenantId = schedulingCreateTenant($this, $token, 'Appointment CRUD Tenant')->json('data.id');
    patientGrantPermissions($admin, $tenantId, [
        'tenants.manage',
        'appointments.view',
        'appointments.manage',
        'patients.manage',
        'providers.manage',
    ]);

    $clinicId = providerCreateClinic($this, $token, $tenantId, 'main-clinic', 'Main Clinic')->json('data.id');
    $departmentId = providerCreateDepartment($this, $token, $tenantId, $clinicId, 'consult', 'Consultation')->json('data.id');
    $roomId = providerCreateRoom($this, $token, $tenantId, $clinicId, $departmentId, 'room-1', 'Room 1')->json('data.id');
    $patientId = schedulingCreatePatient($this, $token, $tenantId)->json('data.id');
    $providerId = schedulingCreateProvider($this, $token, $tenantId, [
        'clinic_id' => $clinicId,
    ])->json('data.id');

    CarbonImmutable::setTestNow('2026-03-09 10:00:00');

    $appointmentId = schedulingCreateAppointment(
        $this,
        $token,
        $tenantId,
        [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'clinic_id' => $clinicId,
            'room_id' => $roomId,
            'scheduled_start_at' => '2026-03-12T09:00:00+05:00',
            'scheduled_end_at' => '2026-03-12T09:30:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ],
        'appointment-create-1',
    )->assertCreated()
        ->assertJsonPath('status', 'appointment_created')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.patient.id', $patientId)
        ->assertJsonPath('data.provider.id', $providerId)
        ->assertJsonPath('data.clinic.id', $clinicId)
        ->assertJsonPath('data.room.id', $roomId)
        ->json('data.id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/appointments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $appointmentId);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/appointments/'.$appointmentId)
        ->assertOk()
        ->assertJsonPath('data.room.name', 'Room 1')
        ->assertJsonPath('data.scheduled_slot.timezone', 'Asia/Tashkent');

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointment-update-1',
        ])
        ->patchJson('/api/v1/appointments/'.$appointmentId, [
            'scheduled_end_at' => '2026-03-12T09:45:00+05:00',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'appointment_updated')
        ->assertJsonPath('data.scheduled_slot.end_at', '2026-03-12T09:45:00+05:00');

    $deleteResponse = $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointment-delete-1',
        ])
        ->deleteJson('/api/v1/appointments/'.$appointmentId)
        ->assertOk()
        ->assertJsonPath('status', 'appointment_deleted');

    expect($deleteResponse->json('data.deleted_at'))->not->toBeNull();

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/appointments')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/appointments/'.$appointmentId)
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});

it('validates appointment references and restricts generic patch and delete to draft records', function (): void {
    $admin = User::factory()->create([
        'email' => 'appointments.admin+2@openai.com',
        'password' => 'secret-password',
    ]);

    $token = schedulingIssueBearerToken($this, 'appointments.admin+2@openai.com');
    $tenantId = schedulingCreateTenant($this, $token, 'Appointment Validation Tenant')->json('data.id');
    patientGrantPermissions($admin, $tenantId, [
        'tenants.manage',
        'appointments.view',
        'appointments.manage',
        'patients.manage',
        'providers.manage',
    ]);

    $clinicId = providerCreateClinic($this, $token, $tenantId, 'clinic-a', 'Clinic A')->json('data.id');
    $departmentId = providerCreateDepartment($this, $token, $tenantId, $clinicId, 'dept-a', 'Department A')->json('data.id');
    $roomId = providerCreateRoom($this, $token, $tenantId, $clinicId, $departmentId, 'room-a', 'Room A')->json('data.id');
    $otherClinicId = providerCreateClinic($this, $token, $tenantId, 'clinic-b', 'Clinic B')->json('data.id');
    $patientId = schedulingCreatePatient($this, $token, $tenantId)->json('data.id');
    $providerId = schedulingCreateProvider($this, $token, $tenantId, [
        'clinic_id' => $clinicId,
    ])->json('data.id');

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointment-invalid-room',
        ])
        ->postJson('/api/v1/appointments', [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'room_id' => $roomId,
            'scheduled_start_at' => '2026-03-12T11:00:00+05:00',
            'scheduled_end_at' => '2026-03-12T11:30:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointment-invalid-clinic',
        ])
        ->postJson('/api/v1/appointments', [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'clinic_id' => $otherClinicId,
            'scheduled_start_at' => '2026-03-12T11:00:00+05:00',
            'scheduled_end_at' => '2026-03-12T11:30:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    /** @var AppointmentRepository $appointmentRepository */
    $appointmentRepository = app(AppointmentRepository::class);
    $scheduledAppointment = $appointmentRepository->create($tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'clinic_id' => $clinicId,
        'room_id' => $roomId,
        'status' => AppointmentStatus::SCHEDULED->value,
        'scheduled_start_at' => CarbonImmutable::parse('2026-03-15T09:00:00+05:00'),
        'scheduled_end_at' => CarbonImmutable::parse('2026-03-15T09:30:00+05:00'),
        'timezone' => 'Asia/Tashkent',
        'last_transition' => [
            'from_status' => 'draft',
            'to_status' => 'scheduled',
        ],
    ]);

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'scheduled-update-denied',
        ])
        ->patchJson('/api/v1/appointments/'.$scheduledAppointment->appointmentId, [
            'timezone' => 'UTC',
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'scheduled-delete-denied',
        ])
        ->deleteJson('/api/v1/appointments/'.$scheduledAppointment->appointmentId)
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');
});
