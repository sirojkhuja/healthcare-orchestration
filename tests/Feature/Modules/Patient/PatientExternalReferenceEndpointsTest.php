<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/PatientTestSupport.php';

uses(RefreshDatabase::class);

it('manages patient external references with metadata roundtrip uniqueness and audit coverage', function (): void {
    User::factory()->create([
        'email' => 'patient.external-refs.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'patient.external-refs.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'patient.external-refs.admin@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'patient.external-refs.viewer@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Patient External Refs Tenant')->json('data.id');

    patientGrantPermissions($viewer, $tenantId, ['patients.view']);

    $patientId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Nodira',
            'last_name' => 'Islomova',
            'sex' => 'female',
            'birth_date' => '1993-12-10',
        ])
        ->assertCreated()
        ->json('data.id');

    $referenceId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/external-refs', [
            'integration_key' => 'lab_portal',
            'external_id' => 'LP-100',
            'display_name' => 'Lab Portal Patient',
            'metadata' => [
                'source' => 'lab',
                'priority' => 'high',
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'patient_external_ref_attached')
        ->assertJsonPath('data.external_type', 'patient')
        ->assertJsonPath('data.metadata.source', 'lab')
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/external-refs', [
            'integration_key' => 'lab_portal',
            'external_id' => 'LP-100',
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/external-refs')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $referenceId)
        ->assertJsonPath('data.0.metadata.priority', 'high');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/patients/'.$patientId.'/external-refs/'.$referenceId)
        ->assertOk()
        ->assertJsonPath('status', 'patient_external_ref_detached')
        ->assertJsonPath('data.id', $referenceId);

    expect(AuditEventRecord::query()->where('action', 'patients.external_ref_attached')->where('object_id', $patientId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'patients.external_ref_detached')->where('object_id', $patientId)->exists())->toBeTrue();
});

it('enforces external reference permissions tenant scope and validation', function (): void {
    User::factory()->create([
        'email' => 'patient.external-refs.admin+2@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'patient.external-refs.viewer+2@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'patient.external-refs.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'patient.external-refs.admin+2@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'patient.external-refs.viewer+2@openai.com');
    $blockedToken = patientIssueBearerToken($this, 'patient.external-refs.blocked@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Patient External Refs Tenant Two')->json('data.id');

    patientGrantPermissions($viewer, $tenantId, ['patients.view']);
    patientEnsureMembership($blocked, $tenantId);

    $patientId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Abbos',
            'last_name' => 'Toshev',
            'sex' => 'male',
            'birth_date' => '1994-03-17',
        ])
        ->assertCreated()
        ->json('data.id');

    $referenceId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/external-refs', [
            'integration_key' => 'erp_sync',
            'external_id' => 'ERP-77',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/external-refs')
        ->assertOk();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/external-refs', [
            'integration_key' => 'denied',
            'external_id' => 'EXT-1',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/patients/'.$patientId.'/external-refs/'.$referenceId)
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/external-refs')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/external-refs', [
            'integration_key' => 'invalid_metadata',
            'external_id' => 'EXT-2',
            'metadata' => 'not-an-object',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED');

    $otherTenantId = patientCreateTenant($this, $adminToken, 'Patient External Refs Tenant Three')->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/external-refs')
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});
