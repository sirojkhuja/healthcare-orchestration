<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/InsuranceTestSupport.php';

uses(RefreshDatabase::class);

it('manages insurance payers and rules with search update delete and permission coverage', function (): void {
    $manager = User::factory()->create([
        'email' => 'claims.catalog.manager@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'claims.catalog.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = insuranceIssueBearerToken($this, 'claims.catalog.manager@openai.com');
    $viewerToken = insuranceIssueBearerToken($this, 'claims.catalog.viewer@openai.com');
    $tenantId = insuranceCreateTenant($this, $managerToken, 'Claims Catalog Tenant')->json('data.id');

    insuranceGrantPermissions($manager, $tenantId, ['claims.view', 'claims.manage']);
    insuranceGrantPermissions($viewer, $tenantId, ['claims.view']);

    $payerId = insuranceCreatePayer($this, $managerToken, $tenantId, [
        'code' => 'uhc',
        'name' => 'United Health',
        'insurance_code' => 'uhc-ppo',
        'contact_email' => 'claims@uhc.test',
    ], 'claims-payer-create-1')
        ->assertCreated()
        ->assertJsonPath('status', 'payer_created')
        ->assertJsonPath('data.code', 'UHC')
        ->assertJsonPath('data.insurance_code', 'uhc-ppo')
        ->json('data.id');

    insuranceCreatePayer($this, $managerToken, $tenantId, [
        'code' => 'uhc',
        'name' => 'Duplicate Payer',
        'insurance_code' => 'uhc-alt',
    ], 'claims-payer-create-duplicate')
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/insurance/payers?q=health&is_active=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.filters.q', 'health')
        ->assertJsonPath('data.0.id', $payerId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/insurance/payers', [
            'code' => 'viewer',
            'name' => 'Viewer Payer',
            'insurance_code' => 'viewer-plan',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'claims-payer-update-1',
        ])
        ->patchJson('/api/v1/insurance/payers/'.$payerId, [
            'name' => 'United Health Updated',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'payer_updated')
        ->assertJsonPath('data.name', 'United Health Updated')
        ->assertJsonPath('data.is_active', false);

    $ruleId = insuranceCreateRule($this, $managerToken, $tenantId, [
        'payer_id' => $payerId,
        'code' => 'attachment-required',
        'name' => 'Require Attachment',
        'service_category' => 'consultation',
        'requires_attachment' => true,
        'submission_window_days' => 30,
    ], 'claims-rule-create-1')
        ->assertCreated()
        ->assertJsonPath('status', 'insurance_rule_created')
        ->assertJsonPath('data.code', 'ATTACHMENT-REQUIRED')
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/insurance/rules?payer_id='.$payerId)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ruleId);

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'claims-rule-update-1',
        ])
        ->patchJson('/api/v1/insurance/rules/'.$ruleId, [
            'requires_primary_policy' => true,
            'max_claim_amount' => '150000',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'insurance_rule_updated')
        ->assertJsonPath('data.requires_primary_policy', true)
        ->assertJsonPath('data.max_claim_amount', '150000.00');

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'claims-rule-delete-1',
        ])
        ->deleteJson('/api/v1/insurance/rules/'.$ruleId)
        ->assertOk()
        ->assertJsonPath('status', 'insurance_rule_deleted')
        ->assertJsonPath('data.id', $ruleId);

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'claims-payer-delete-1',
        ])
        ->deleteJson('/api/v1/insurance/payers/'.$payerId)
        ->assertOk()
        ->assertJsonPath('status', 'payer_deleted')
        ->assertJsonPath('data.id', $payerId);

    expect(AuditEventRecord::query()->where('action', 'payers.created')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'payers.updated')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'payers.deleted')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'insurance_rules.created')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'insurance_rules.updated')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'insurance_rules.deleted')->exists())->toBeTrue();
});
