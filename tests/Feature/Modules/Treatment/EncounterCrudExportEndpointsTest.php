<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/TreatmentTestSupport.php';

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('creates lists exports updates and soft deletes encounters in tenant scope', function (): void {
    Storage::fake('exports');

    $admin = User::factory()->create([
        'email' => 'encounters.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'encounters.viewer@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'encounters.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = treatmentIssueBearerToken($this, 'encounters.admin@openai.com');
    $viewerToken = treatmentIssueBearerToken($this, 'encounters.viewer@openai.com');
    $blockedToken = treatmentIssueBearerToken($this, 'encounters.blocked@openai.com');
    $tenantId = treatmentCreateTenant($this, $adminToken, 'Encounter CRUD Tenant')->json('data.id');

    treatmentGrantPermissions($admin, $tenantId, [
        'tenants.manage',
        'appointments.manage',
        'treatments.view',
        'treatments.manage',
        'patients.manage',
        'providers.manage',
    ]);
    treatmentGrantPermissions($viewer, $tenantId, ['treatments.view']);
    treatmentEnsureMembership($blocked, $tenantId);

    $patientId = treatmentCreatePatient($this, $adminToken, $tenantId, [
        'first_name' => 'Aziza',
        'last_name' => 'Karimova',
    ])->json('data.id');
    $otherPatientId = treatmentCreatePatient($this, $adminToken, $tenantId, [
        'first_name' => 'Bekzod',
        'last_name' => 'Nazarov',
        'sex' => 'male',
        'birth_date' => '1988-11-04',
    ])->json('data.id');
    $providerId = treatmentCreateProvider($this, $adminToken, $tenantId, [
        'first_name' => 'Kamola',
        'last_name' => 'Rasulova',
        'provider_type' => 'doctor',
    ])->json('data.id');
    $otherProviderId = treatmentCreateProvider($this, $adminToken, $tenantId, [
        'first_name' => 'Sherzod',
        'last_name' => 'Aliyev',
        'provider_type' => 'doctor',
    ])->json('data.id');
    $planId = treatmentCreatePlan($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'title' => 'Hypertension treatment plan',
    ])->json('data.id');

    $appointmentId = schedulingCreateAppointment(
        $this,
        $adminToken,
        $tenantId,
        [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'scheduled_start_at' => '2026-03-11T09:00:00+05:00',
            'scheduled_end_at' => '2026-03-11T09:30:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ],
        'encounter-appointment-create-1',
    )->assertCreated()->json('data.id');

    $encounterId = treatmentCreateEncounter($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'treatment_plan_id' => $planId,
        'appointment_id' => $appointmentId,
        'encountered_at' => '2026-03-11T09:10:00+05:00',
        'timezone' => 'Asia/Tashkent',
        'chief_complaint' => 'Persistent headache',
        'summary' => 'Initial evaluation for elevated blood pressure.',
        'follow_up_instructions' => 'Return in one week with home readings.',
    ])->assertCreated()
        ->assertJsonPath('status', 'encounter_created')
        ->json('data.id');

    treatmentCreateEncounter($this, $adminToken, $tenantId, [
        'patient_id' => $otherPatientId,
        'provider_id' => $otherProviderId,
        'encountered_at' => '2026-03-12T10:00:00+05:00',
        'timezone' => 'Asia/Tashkent',
        'chief_complaint' => 'Low back pain',
    ])->assertCreated();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/encounters?q=Aziza&patient_id='.$patientId.'&status=open')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $encounterId)
        ->assertJsonPath('data.0.treatment_plan_id', $planId)
        ->assertJsonPath('meta.filters.patient_id', $patientId)
        ->assertJsonPath('meta.filters.status', 'open');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/encounters/'.$encounterId)
        ->assertOk()
        ->assertJsonPath('data.id', $encounterId)
        ->assertJsonPath('data.diagnosis_count', 0)
        ->assertJsonPath('data.procedure_count', 0)
        ->assertJsonPath('data.appointment_id', $appointmentId);

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/encounters')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/encounters/'.$encounterId, [
            'status' => 'completed',
            'summary' => 'Blood pressure stabilized after medication review.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'encounter_updated')
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.summary', 'Blood pressure stabilized after medication review.');

    $exportResponse = $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/encounters/export?patient_id='.$patientId.'&format=csv')
        ->assertOk()
        ->assertJsonPath('status', 'encounter_export_created')
        ->assertJsonPath('data.row_count', 1)
        ->assertJsonPath('data.filters.patient_id', $patientId);

    $path = $exportResponse->json('data.storage.path');
    Storage::disk('exports')->assertExists($path);
    $contents = Storage::disk('exports')->get($path);

    expect($contents)->toContain('chief_complaint');
    expect($contents)->toContain('Persistent headache');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/encounters/'.$encounterId)
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/encounters/'.$encounterId, [
            'status' => 'entered_in_error',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'entered_in_error');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/encounters/'.$encounterId)
        ->assertOk()
        ->assertJsonPath('status', 'encounter_deleted')
        ->assertJsonPath('data.deleted_at', fn (mixed $value): bool => is_string($value) && $value !== '');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/encounters')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $otherTenantId = treatmentCreateTenant($this, $adminToken, 'Encounter CRUD Other Tenant')->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->getJson('/api/v1/encounters/'.$encounterId)
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

    expect(AuditEventRecord::query()->where('action', 'encounters.created')->where('object_id', $encounterId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'encounters.updated')->where('object_id', $encounterId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'encounters.deleted')->where('object_id', $encounterId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'encounters.exported')->exists())->toBeTrue();
});
