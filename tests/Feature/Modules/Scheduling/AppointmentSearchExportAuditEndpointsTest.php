<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/SchedulingTestSupport.php';

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('searches exports and returns audit history for tenant scoped appointments', function (): void {
    Storage::fake('exports');

    $admin = User::factory()->create([
        'email' => 'appointments.read.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'appointments.read.viewer@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'appointments.read.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = schedulingIssueBearerToken($this, 'appointments.read.admin@openai.com');
    $viewerToken = schedulingIssueBearerToken($this, 'appointments.read.viewer@openai.com');
    $blockedToken = schedulingIssueBearerToken($this, 'appointments.read.blocked@openai.com');
    $tenantId = schedulingCreateTenant($this, $adminToken, 'Appointment Read Tenant')->json('data.id');

    patientGrantPermissions($admin, $tenantId, [
        'tenants.manage',
        'appointments.view',
        'appointments.manage',
        'patients.manage',
        'providers.manage',
    ]);
    patientGrantPermissions($viewer, $tenantId, ['appointments.view']);
    patientEnsureMembership($blocked, $tenantId);

    $patientId = schedulingCreatePatient($this, $adminToken, $tenantId, [
        'first_name' => 'Aziza',
        'last_name' => 'Karimova',
    ])->json('data.id');
    $otherPatientId = schedulingCreatePatient($this, $adminToken, $tenantId, [
        'first_name' => 'Bekzod',
        'last_name' => 'Nazarov',
        'sex' => 'male',
        'birth_date' => '1989-06-21',
    ])->json('data.id');
    $providerId = schedulingCreateProvider($this, $adminToken, $tenantId, [
        'first_name' => 'Kamola',
        'last_name' => 'Rasulova',
        'provider_type' => 'doctor',
    ])->json('data.id');
    $otherProviderId = schedulingCreateProvider($this, $adminToken, $tenantId, [
        'first_name' => 'Sherzod',
        'last_name' => 'Aliyev',
        'provider_type' => 'doctor',
    ])->json('data.id');

    CarbonImmutable::setTestNow('2026-03-09 08:00:00');

    $appointmentId = schedulingCreateAppointment(
        $this,
        $adminToken,
        $tenantId,
        [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'scheduled_start_at' => '2026-03-13T09:00:00+05:00',
            'scheduled_end_at' => '2026-03-13T09:30:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ],
        'appointments-read-create-1',
    )->assertCreated()->json('data.id');

    schedulingCreateAppointment(
        $this,
        $adminToken,
        $tenantId,
        [
            'patient_id' => $otherPatientId,
            'provider_id' => $otherProviderId,
            'scheduled_start_at' => '2026-03-14T11:00:00+05:00',
            'scheduled_end_at' => '2026-03-14T11:30:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ],
        'appointments-read-create-2',
    )->assertCreated();

    CarbonImmutable::setTestNow('2026-03-09 08:15:00');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointments-read-update-1',
        ])
        ->patchJson('/api/v1/appointments/'.$appointmentId, [
            'scheduled_end_at' => '2026-03-13T09:45:00+05:00',
        ])
        ->assertOk();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/appointments/search?q=Aziza Kamola&patient_id='.$patientId.'&status=draft')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $appointmentId)
        ->assertJsonPath('meta.filters.patient_id', $patientId)
        ->assertJsonPath('meta.filters.status', 'draft');

    $exportResponse = $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/appointments/export?patient_id='.$patientId.'&format=csv')
        ->assertOk()
        ->assertJsonPath('status', 'appointment_export_created')
        ->assertJsonPath('data.row_count', 1)
        ->assertJsonPath('data.filters.patient_id', $patientId);

    $path = $exportResponse->json('data.storage.path');
    Storage::disk('exports')->assertExists($path);
    $contents = Storage::disk('exports')->get($path);

    expect($contents)->toContain('patient_name');
    expect($contents)->toContain('Aziza Karimova');
    expect(AuditEventRecord::query()->where('action', 'appointments.exported')->exists())->toBeTrue();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/appointments/'.$appointmentId.'/audit?limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.action', 'appointments.updated')
        ->assertJsonPath('data.1.action', 'appointments.created')
        ->assertJsonPath('meta.limit', 2);

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/appointments/'.$appointmentId.'/audit')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    CarbonImmutable::setTestNow('2026-03-09 08:20:00');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointments-read-delete-1',
        ])
        ->deleteJson('/api/v1/appointments/'.$appointmentId)
        ->assertOk();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/appointments/'.$appointmentId.'/audit')
        ->assertOk()
        ->assertJsonPath('data.0.action', 'appointments.deleted');

    $otherTenantId = schedulingCreateTenant($this, $adminToken, 'Appointment Read Other Tenant')->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->getJson('/api/v1/appointments/'.$appointmentId.'/audit')
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});
