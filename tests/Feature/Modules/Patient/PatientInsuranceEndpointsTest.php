<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/PatientTestSupport.php';

uses(RefreshDatabase::class);

it('manages patient insurance links with primary replacement ordering uniqueness and audit coverage', function (): void {
    User::factory()->create([
        'email' => 'patient.insurance.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'patient.insurance.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'patient.insurance.admin@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'patient.insurance.viewer@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Patient Insurance Tenant')->json('data.id');

    patientGrantPermissions($viewer, $tenantId, ['patients.view']);

    $patientId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Murod',
            'last_name' => 'Saidov',
            'sex' => 'male',
            'birth_date' => '1985-02-18',
        ])
        ->assertCreated()
        ->json('data.id');

    $firstPolicyId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/insurance', [
            'insurance_code' => 'UHC-PPO',
            'policy_number' => 'POL-001',
            'effective_from' => '2026-01-01',
            'is_primary' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'patient_insurance_attached')
        ->assertJsonPath('data.insurance_code', 'uhc-ppo')
        ->assertJsonPath('data.is_primary', true)
        ->json('data.id');

    $secondPolicyId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/insurance', [
            'insurance_code' => 'Aetna-HMO',
            'policy_number' => 'POL-002',
            'plan_name' => 'Family HMO',
            'effective_from' => '2026-02-01',
            'effective_to' => '2026-12-31',
            'is_primary' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.insurance_code', 'aetna-hmo')
        ->assertJsonPath('data.is_primary', true)
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/insurance', [
            'insurance_code' => 'UHC-PPO',
            'policy_number' => 'POL-001',
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/insurance')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $secondPolicyId)
        ->assertJsonPath('data.0.is_primary', true)
        ->assertJsonPath('data.1.id', $firstPolicyId)
        ->assertJsonPath('data.1.is_primary', false);

    expect((bool) DB::table('patient_insurance_policies')->where('id', $firstPolicyId)->value('is_primary'))->toBeFalse();

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/patients/'.$patientId.'/insurance/'.$firstPolicyId)
        ->assertOk()
        ->assertJsonPath('status', 'patient_insurance_detached')
        ->assertJsonPath('data.id', $firstPolicyId);

    expect(AuditEventRecord::query()->where('action', 'patients.insurance_attached')->where('object_id', $patientId)->count())->toBe(2);
    expect(AuditEventRecord::query()->where('action', 'patients.insurance_detached')->where('object_id', $patientId)->exists())->toBeTrue();
});

it('enforces insurance permissions tenant scope and validation', function (): void {
    User::factory()->create([
        'email' => 'patient.insurance.admin+2@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'patient.insurance.viewer+2@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'patient.insurance.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'patient.insurance.admin+2@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'patient.insurance.viewer+2@openai.com');
    $blockedToken = patientIssueBearerToken($this, 'patient.insurance.blocked@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Patient Insurance Tenant Two')->json('data.id');

    patientGrantPermissions($viewer, $tenantId, ['patients.view']);
    patientEnsureMembership($blocked, $tenantId);

    $patientId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Laylo',
            'last_name' => 'Hakimova',
            'sex' => 'female',
            'birth_date' => '1996-06-21',
        ])
        ->assertCreated()
        ->json('data.id');

    $policyId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/insurance', [
            'insurance_code' => 'medihelp',
            'policy_number' => 'POL-900',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/insurance')
        ->assertOk();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/insurance', [
            'insurance_code' => 'denied',
            'policy_number' => 'POL-901',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/patients/'.$patientId.'/insurance/'.$policyId)
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/insurance')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/insurance', [
            'insurance_code' => 'invalid-dates',
            'policy_number' => 'POL-902',
            'effective_from' => '2026-05-02',
            'effective_to' => '2026-05-01',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED');

    $otherTenantId = patientCreateTenant($this, $adminToken, 'Patient Insurance Tenant Three')->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/insurance')
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});
