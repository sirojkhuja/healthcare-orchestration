<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/TreatmentTestSupport.php';

uses(RefreshDatabase::class);

it('searches treatment plans and manages ordered treatment items', function (): void {
    User::factory()->create([
        'email' => 'treatment.items.admin@openai.com',
        'password' => 'secret-password',
    ]);

    $token = treatmentIssueBearerToken($this, 'treatment.items.admin@openai.com');
    $tenantId = treatmentCreateTenant($this, $token, 'Treatment Search Tenant')->json('data.id');
    $patientId = treatmentCreatePatient($this, $token, $tenantId, [
        'first_name' => 'Aziza',
        'last_name' => 'Karimova',
    ])->json('data.id');
    $providerId = treatmentCreateProvider($this, $token, $tenantId, [
        'first_name' => 'Kamola',
        'last_name' => 'Rasulova',
        'provider_type' => 'doctor',
    ])->json('data.id');
    $otherPatientId = treatmentCreatePatient($this, $token, $tenantId, [
        'first_name' => 'Bekzod',
        'last_name' => 'Nazarov',
        'sex' => 'male',
        'birth_date' => '1988-11-04',
    ])->json('data.id');
    $otherProviderId = treatmentCreateProvider($this, $token, $tenantId, [
        'first_name' => 'Sherzod',
        'last_name' => 'Aliyev',
        'provider_type' => 'doctor',
    ])->json('data.id');

    $planId = treatmentCreatePlan($this, $token, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'title' => 'Cardiac rehabilitation pathway',
        'summary' => 'Track recovery milestones.',
        'planned_start_date' => '2026-03-20',
        'planned_end_date' => '2026-04-30',
    ])->assertCreated()->json('data.id');

    treatmentCreatePlan($this, $token, $tenantId, [
        'patient_id' => $otherPatientId,
        'provider_id' => $otherProviderId,
        'title' => 'Diabetes monitoring pathway',
        'planned_start_date' => '2026-03-22',
        'planned_end_date' => '2026-05-02',
    ])->assertCreated();

    $assessmentItemId = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$planId.'/items', [
            'item_type' => 'assessment',
            'title' => 'Baseline pain assessment',
            'description' => 'Record the patient pain score before therapy.',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'treatment_plan_item_created')
        ->assertJsonPath('data.sort_order', 1)
        ->json('data.id');

    $exerciseItemId = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$planId.'/items', [
            'item_type' => 'procedure',
            'title' => 'Mobility exercise block',
            'instructions' => 'Supervised session three times per week.',
            'sort_order' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('data.sort_order', 1)
        ->json('data.id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/treatment-plans/'.$planId.'/items')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $exerciseItemId)
        ->assertJsonPath('data.0.sort_order', 1)
        ->assertJsonPath('data.1.id', $assessmentItemId)
        ->assertJsonPath('data.1.sort_order', 2);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/treatment-plans/'.$planId.'/items/'.$assessmentItemId, [
            'sort_order' => 1,
            'instructions' => 'Capture pain score before and after the session.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'treatment_plan_item_updated')
        ->assertJsonPath('data.sort_order', 1)
        ->assertJsonPath('data.instructions', 'Capture pain score before and after the session.');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/treatment-plans/'.$planId.'/items/'.$exerciseItemId)
        ->assertOk()
        ->assertJsonPath('status', 'treatment_plan_item_deleted')
        ->assertJsonPath('data.id', $exerciseItemId);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/treatment-plans/'.$planId.'/items')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $assessmentItemId)
        ->assertJsonPath('data.0.sort_order', 1);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/treatment-plans')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/treatment-plans/'.$planId)
        ->assertOk()
        ->assertJsonPath('data.item_count', 1);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/treatment-plans/search?q=Pain assessment&patient_id='.$patientId.'&status=draft')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $planId)
        ->assertJsonPath('data.0.item_count', 1)
        ->assertJsonPath('meta.filters.patient_id', $patientId)
        ->assertJsonPath('meta.filters.status', 'draft');

    expect(AuditEventRecord::query()->where('action', 'treatment_plan_items.created')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'treatment_plan_items.updated')->where('object_id', $assessmentItemId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'treatment_plan_items.deleted')->where('object_id', $exerciseItemId)->exists())->toBeTrue();
});

it('enforces treatment search and item permissions tenant isolation and plan lifecycle guards', function (): void {
    $admin = User::factory()->create([
        'email' => 'treatment.items.admin-guard@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'treatment.items.viewer@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'treatment.items.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = treatmentIssueBearerToken($this, 'treatment.items.admin-guard@openai.com');
    $viewerToken = treatmentIssueBearerToken($this, 'treatment.items.viewer@openai.com');
    $blockedToken = treatmentIssueBearerToken($this, 'treatment.items.blocked@openai.com');
    $tenantId = treatmentCreateTenant($this, $adminToken, 'Treatment Guard Tenant')->json('data.id');

    treatmentGrantPermissions($viewer, $tenantId, ['treatments.view']);
    treatmentEnsureMembership($blocked, $tenantId);

    $patientId = treatmentCreatePatient($this, $adminToken, $tenantId)->json('data.id');
    $providerId = treatmentCreateProvider($this, $adminToken, $tenantId)->json('data.id');
    $planId = treatmentCreatePlan($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'title' => 'Pulmonary rehab pathway',
    ])->assertCreated()->json('data.id');

    $itemId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$planId.'/items', [
            'item_type' => 'therapy',
            'title' => 'Breathing exercise session',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/treatment-plans/search?q=Pulmonary')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $planId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/treatment-plans/'.$planId.'/items')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $itemId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$planId.'/items', [
            'item_type' => 'procedure',
            'title' => 'Denied edit',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/treatment-plans/search?q=Pulmonary')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $otherTenantId = treatmentCreateTenant($this, $adminToken, 'Treatment Guard Tenant Other')->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->getJson('/api/v1/treatment-plans/'.$planId.'/items')
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$planId.':approve')
        ->assertOk();

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$planId.':start')
        ->assertOk();

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$planId.'/items', [
            'item_type' => 'lab',
            'title' => 'Should fail after start',
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/treatment-plans/'.$planId.'/items/'.$itemId, [
            'title' => 'Should also fail after start',
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/treatment-plans/'.$planId.'/items/'.$itemId)
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');
});
