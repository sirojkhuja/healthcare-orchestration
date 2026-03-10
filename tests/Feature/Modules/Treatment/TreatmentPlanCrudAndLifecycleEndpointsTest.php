<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/TreatmentTestSupport.php';

uses(RefreshDatabase::class);

it('creates updates transitions and soft deletes treatment plans inside tenant scope', function (): void {
    User::factory()->create([
        'email' => 'treatment.admin+1@openai.com',
        'password' => 'secret-password',
    ]);

    $token = treatmentIssueBearerToken($this, 'treatment.admin+1@openai.com');
    $tenantId = treatmentCreateTenant($this, $token, 'Treatment Tenant Alpha')->json('data.id');
    $patientId = treatmentCreatePatient($this, $token, $tenantId)->json('data.id');
    $providerId = treatmentCreateProvider($this, $token, $tenantId)->json('data.id');

    $createResponse = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans', [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'title' => 'Post-op recovery plan',
            'summary' => 'Track wound healing and mobility.',
            'goals' => 'Recover mobility within six weeks.',
            'planned_start_date' => '2026-03-15',
            'planned_end_date' => '2026-04-30',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'treatment_plan_created')
        ->assertJsonPath('data.title', 'Post-op recovery plan')
        ->assertJsonPath('data.status', 'draft');

    $planId = $createResponse->json('data.id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/treatment-plans')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $planId);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/treatment-plans/'.$planId, [
            'title' => 'Post-op rehabilitation plan',
            'goals' => 'Recover mobility and return to work.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'treatment_plan_updated')
        ->assertJsonPath('data.title', 'Post-op rehabilitation plan')
        ->assertJsonPath('data.goals', 'Recover mobility and return to work.');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$planId.':approve')
        ->assertOk()
        ->assertJsonPath('status', 'treatment_plan_approved')
        ->assertJsonPath('data.status', 'approved');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/treatment-plans/'.$planId, [
            'summary' => 'Adjusted after surgical review.',
        ])
        ->assertOk()
        ->assertJsonPath('data.summary', 'Adjusted after surgical review.');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$planId.':start')
        ->assertOk()
        ->assertJsonPath('status', 'treatment_plan_started')
        ->assertJsonPath('data.status', 'active');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/treatment-plans/'.$planId, [
            'title' => 'Should fail',
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$planId.':pause', [
            'reason' => 'Awaiting imaging follow-up',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'treatment_plan_paused')
        ->assertJsonPath('data.status', 'paused')
        ->assertJsonPath('data.last_transition.reason', 'Awaiting imaging follow-up');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$planId.':resume')
        ->assertOk()
        ->assertJsonPath('status', 'treatment_plan_resumed')
        ->assertJsonPath('data.status', 'active');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$planId.':finish')
        ->assertOk()
        ->assertJsonPath('status', 'treatment_plan_finished')
        ->assertJsonPath('data.status', 'finished');

    $secondPlanId = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans', [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'title' => 'Diet review plan',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$secondPlanId.':reject', [
            'reason' => 'Superseded by a combined care pathway',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'treatment_plan_rejected')
        ->assertJsonPath('data.status', 'rejected');

    $deleteResponse = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/treatment-plans/'.$secondPlanId)
        ->assertOk()
        ->assertJsonPath('status', 'treatment_plan_deleted')
        ->assertJsonPath('data.id', $secondPlanId);

    expect($deleteResponse->json('data.deleted_at'))->not->toBeNull();
    expect(DB::table('treatment_plans')->where('id', $secondPlanId)->value('deleted_at'))->not->toBeNull();
    expect(AuditEventRecord::query()->where('action', 'treatment_plans.created')->where('object_id', $planId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'treatment_plans.updated')->where('object_id', $planId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'treatment_plans.approved')->where('object_id', $planId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'treatment_plans.started')->where('object_id', $planId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'treatment_plans.paused')->where('object_id', $planId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'treatment_plans.resumed')->where('object_id', $planId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'treatment_plans.finished')->where('object_id', $planId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'treatment_plans.rejected')->where('object_id', $secondPlanId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'treatment_plans.deleted')->where('object_id', $secondPlanId)->exists())->toBeTrue();
});

it('enforces treatment plan permissions and tenant isolation', function (): void {
    $admin = User::factory()->create([
        'email' => 'treatment.admin+2@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'treatment.viewer@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'treatment.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = treatmentIssueBearerToken($this, 'treatment.admin+2@openai.com');
    $viewerToken = treatmentIssueBearerToken($this, 'treatment.viewer@openai.com');
    $blockedToken = treatmentIssueBearerToken($this, 'treatment.blocked@openai.com');
    $tenantId = treatmentCreateTenant($this, $adminToken, 'Treatment Tenant Beta')->json('data.id');

    treatmentGrantPermissions($viewer, $tenantId, ['treatments.view']);
    treatmentEnsureMembership($blocked, $tenantId);

    $patientId = treatmentCreatePatient($this, $adminToken, $tenantId)->json('data.id');
    $providerId = treatmentCreateProvider($this, $adminToken, $tenantId)->json('data.id');

    $planId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans', [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'title' => 'Diabetes monitoring plan',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/treatment-plans')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/treatment-plans/'.$planId)
        ->assertOk()
        ->assertJsonPath('data.id', $planId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans', [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'title' => 'Denied writer',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$planId.':approve')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/treatment-plans')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $otherTenantId = treatmentCreateTenant($this, $adminToken, 'Treatment Tenant Gamma')->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->getJson('/api/v1/treatment-plans/'.$planId)
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});
