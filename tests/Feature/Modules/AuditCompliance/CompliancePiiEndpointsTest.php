<?php

use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

require_once __DIR__.'/AuditComplianceTestSupport.php';

uses(RefreshDatabase::class);

it('manages pii registry fields rotates keys re-encrypts and lists compliance reports', function (): void {
    [, $token, $tenantId] = auditComplianceCreateContext($this, 'pii-admin', ['compliance.view', 'compliance.manage']);

    $updateResponse = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/compliance/pii-fields', [
            'fields' => [
                [
                    'object_type' => 'patient',
                    'field_path' => 'national_id',
                    'classification' => 'government_id',
                    'encryption_profile' => 'encrypted_string',
                    'notes' => 'Primary national identifier.',
                ],
                [
                    'object_type' => 'patient',
                    'field_path' => 'contacts',
                    'classification' => 'contact',
                    'encryption_profile' => 'encrypted_json',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'pii_fields_updated')
        ->assertJsonCount(2, 'data');

    $fieldId = (string) $updateResponse->json('data.0.field_id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/compliance/pii-fields')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.object_type', 'patient')
        ->assertJsonPath('data.0.key_version', 1);

    $rotateResponse = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/compliance/pii:rotate-keys', [
            'field_ids' => [$fieldId],
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'pii_key_rotation_completed')
        ->assertJsonPath('data.type', 'pii_key_rotation')
        ->assertJsonPath('data.requested_field_count', 1)
        ->assertJsonPath('data.processed_field_count', 1);

    $this->assertDatabaseHas('pii_fields', [
        'id' => $fieldId,
        'tenant_id' => $tenantId,
        'key_version' => 2,
    ]);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/compliance/pii:re-encrypt')
        ->assertOk()
        ->assertJsonPath('status', 'pii_reencryption_completed')
        ->assertJsonPath('data.type', 'pii_reencryption')
        ->assertJsonPath('data.summary.registry_only', true)
        ->assertJsonPath('data.processed_field_count', 2);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/compliance/reports?limit=10')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.type', 'pii_reencryption')
        ->assertJsonPath('data.1.type', 'pii_key_rotation');

    $this->assertDatabaseHas('compliance_reports', [
        'id' => (string) $rotateResponse->json('data.report_id'),
        'tenant_id' => $tenantId,
        'type' => 'pii_key_rotation',
        'status' => 'completed',
    ]);

    expect(AuditEventRecord::query()->where('tenant_id', $tenantId)->where('action', 'compliance.pii_fields_replaced')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('tenant_id', $tenantId)->where('action', 'compliance.pii_keys_rotated')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('tenant_id', $tenantId)->where('action', 'compliance.pii_fields_reencrypted')->exists())->toBeTrue();
});

it('enforces compliance permissions and rejects unknown pii field ids', function (): void {
    [, $viewerToken, $tenantId] = auditComplianceCreateContext($this, 'pii-viewer', ['compliance.view']);
    [, $managerToken] = auditComplianceAttachUser($this, $tenantId, 'pii-manager', ['compliance.view', 'compliance.manage']);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/compliance/pii-fields')
        ->assertOk();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/compliance/pii-fields', ['fields' => []])
        ->assertStatus(403);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/compliance/pii:rotate-keys')
        ->assertStatus(403);

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/compliance/pii:rotate-keys', [
            'field_ids' => [(string) Str::uuid()],
        ])
        ->assertStatus(404)
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});
