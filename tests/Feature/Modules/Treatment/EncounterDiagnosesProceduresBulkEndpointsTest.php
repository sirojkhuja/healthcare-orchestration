<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/TreatmentTestSupport.php';

uses(RefreshDatabase::class);

it('manages encounter diagnoses procedures and bulk updates with tenant safety', function (): void {
    $admin = User::factory()->create([
        'email' => 'encounters.subresources.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'encounters.subresources.viewer@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'encounters.subresources.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = treatmentIssueBearerToken($this, 'encounters.subresources.admin@openai.com');
    $viewerToken = treatmentIssueBearerToken($this, 'encounters.subresources.viewer@openai.com');
    $blockedToken = treatmentIssueBearerToken($this, 'encounters.subresources.blocked@openai.com');
    $tenantId = treatmentCreateTenant($this, $adminToken, 'Encounter Subresource Tenant')->json('data.id');

    treatmentGrantPermissions($admin, $tenantId, [
        'treatments.view',
        'treatments.manage',
        'patients.manage',
        'providers.manage',
    ]);
    treatmentGrantPermissions($viewer, $tenantId, ['treatments.view']);
    treatmentEnsureMembership($blocked, $tenantId);

    $patientId = treatmentCreatePatient($this, $adminToken, $tenantId)->json('data.id');
    $providerId = treatmentCreateProvider($this, $adminToken, $tenantId)->json('data.id');
    $otherProviderId = treatmentCreateProvider($this, $adminToken, $tenantId, [
        'first_name' => 'Sherzod',
        'last_name' => 'Aliyev',
        'provider_type' => 'doctor',
    ])->json('data.id');
    $planId = treatmentCreatePlan($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'title' => 'Orthopedic rehab plan',
    ])->json('data.id');

    $procedureItemId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$planId.'/items', [
            'item_type' => 'procedure',
            'title' => 'Manual therapy session',
        ])
        ->assertCreated()
        ->json('data.id');

    $nonProcedureItemId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$planId.'/items', [
            'item_type' => 'assessment',
            'title' => 'Pain score review',
        ])
        ->assertCreated()
        ->json('data.id');

    $encounterId = treatmentCreateEncounter($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'treatment_plan_id' => $planId,
        'chief_complaint' => 'Knee pain after exercise',
    ])->assertCreated()->json('data.id');

    $otherEncounterId = treatmentCreateEncounter($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'encountered_at' => '2026-03-11T15:00:00+05:00',
        'timezone' => 'Asia/Tashkent',
    ])->assertCreated()->json('data.id');

    $primaryDiagnosisId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/encounters/'.$encounterId.'/diagnoses', [
            'code' => 'M25.56',
            'display_name' => 'Pain in knee',
            'diagnosis_type' => 'primary',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'encounter_diagnosis_added')
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/encounters/'.$encounterId.'/diagnoses', [
            'display_name' => 'Duplicate primary diagnosis',
            'diagnosis_type' => 'primary',
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    $secondaryDiagnosisId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/encounters/'.$encounterId.'/diagnoses', [
            'code' => 'R26.2',
            'display_name' => 'Difficulty in walking',
            'diagnosis_type' => 'secondary',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/encounters/'.$encounterId.'/diagnoses')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $primaryDiagnosisId)
        ->assertJsonPath('data.1.id', $secondaryDiagnosisId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/encounters/'.$encounterId.'/diagnoses', [
            'display_name' => 'Forbidden mutation',
            'diagnosis_type' => 'secondary',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/encounters/'.$encounterId.'/procedures', [
            'treatment_item_id' => $nonProcedureItemId,
            'display_name' => 'Should fail',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $procedureId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/encounters/'.$encounterId.'/procedures', [
            'treatment_item_id' => $procedureItemId,
            'code' => '97140',
            'display_name' => 'Manual therapy',
            'performed_at' => '2026-03-10T10:00:00+05:00',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'encounter_procedure_added')
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/encounters/'.$encounterId.'/procedures', [
            'treatment_item_id' => $procedureItemId,
            'code' => '97140',
            'display_name' => 'Manual therapy',
            'performed_at' => '2026-03-10T10:00:00+05:00',
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/encounters/'.$encounterId.'/procedures')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $procedureId)
        ->assertJsonPath('data.0.treatment_item_id', $procedureItemId);

    $bulkResponse = $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'encounters-bulk-update-1',
        ])
        ->postJson('/api/v1/encounters/bulk', [
            'encounter_ids' => [$encounterId, $otherEncounterId],
            'changes' => [
                'status' => 'completed',
                'provider_id' => $otherProviderId,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'encounters_bulk_updated')
        ->assertJsonPath('data.affected_count', 2)
        ->assertJsonPath('data.updated_fields', ['status', 'provider_id']);

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'encounters-bulk-update-1',
        ])
        ->postJson('/api/v1/encounters/bulk', [
            'encounter_ids' => [$encounterId, $otherEncounterId],
            'changes' => [
                'status' => 'completed',
                'provider_id' => $otherProviderId,
            ],
        ])
        ->assertOk()
        ->assertHeader('X-Idempotent-Replay', 'true')
        ->assertJsonPath('data.operation_id', $bulkResponse->json('data.operation_id'));

    $this->withToken($blockedToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'encounters-bulk-update-2',
        ])
        ->postJson('/api/v1/encounters/bulk', [
            'encounter_ids' => [$encounterId],
            'changes' => [
                'status' => 'open',
            ],
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $otherTenantId = treatmentCreateTenant($this, $adminToken, 'Encounter Subresource Other Tenant')->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->getJson('/api/v1/encounters/'.$encounterId.'/procedures')
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/encounters/'.$encounterId.'/diagnoses/'.$secondaryDiagnosisId)
        ->assertOk()
        ->assertJsonPath('status', 'encounter_diagnosis_removed');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/encounters/'.$encounterId.'/procedures/'.$procedureId)
        ->assertOk()
        ->assertJsonPath('status', 'encounter_procedure_removed');

    expect(AuditEventRecord::query()->where('action', 'encounter_diagnoses.added')->where('object_id', $primaryDiagnosisId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'encounter_diagnoses.removed')->where('object_id', $secondaryDiagnosisId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'encounter_procedures.added')->where('object_id', $procedureId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'encounter_procedures.removed')->where('object_id', $procedureId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'encounters.bulk_updated')->exists())->toBeTrue();
});
