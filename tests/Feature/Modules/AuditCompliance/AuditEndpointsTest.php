<?php

use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require_once __DIR__.'/AuditComplianceTestSupport.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('exports');
    config()->set('medflow.audit.retention_days', 30);
});

it('lists shows filters exports and returns object history for tenant audit events', function (): void {
    [, $token, $tenantId] = auditComplianceCreateContext($this, 'events-admin', ['audit.view', 'audit.manage']);

    $olderEvent = auditComplianceAppendEvent(
        tenantId: $tenantId,
        action: 'patients.updated',
        objectType: 'patient',
        objectId: 'patient-001',
        before: ['status' => 'draft'],
        after: ['status' => 'active'],
        metadata: ['source' => 'front-desk'],
        occurredAt: now()->subMinutes(10)->toImmutable(),
    );
    $newerEvent = auditComplianceAppendEvent(
        tenantId: $tenantId,
        action: 'patients.consented',
        objectType: 'patient',
        objectId: 'patient-001',
        before: [],
        after: ['status' => 'granted'],
        metadata: ['source' => 'tablet'],
        occurredAt: now()->subMinutes(5)->toImmutable(),
    );
    auditComplianceAppendEvent(
        tenantId: $tenantId,
        action: 'providers.updated',
        objectType: 'provider',
        objectId: 'provider-001',
        occurredAt: now()->subMinutes(3)->toImmutable(),
    );
    auditComplianceAppendEvent(
        tenantId: null,
        action: 'system.started',
        objectType: 'system',
        objectId: 'bootstrap',
        occurredAt: now()->subMinutes(1)->toImmutable(),
    );
    auditComplianceAppendEvent(
        tenantId: (string) Str::uuid(),
        action: 'patients.updated',
        objectType: 'patient',
        objectId: 'patient-001',
        occurredAt: now()->subMinutes(2)->toImmutable(),
    );

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/audit/events?action_prefix=patients.&limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.event_id', $newerEvent->eventId)
        ->assertJsonPath('data.1.event_id', $olderEvent->eventId)
        ->assertJsonPath('meta.filters.action_prefix', 'patients.');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/audit/events/'.$newerEvent->eventId)
        ->assertOk()
        ->assertJsonPath('data.event_id', $newerEvent->eventId)
        ->assertJsonPath('data.metadata.source', 'tablet');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/audit/object/patient/patient-001?action_prefix=patients.&limit=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event_id', $newerEvent->eventId);

    $exportResponse = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/audit/export?object_type=patient&format=csv&limit=1000')
        ->assertOk()
        ->assertJsonPath('status', 'audit_export_created')
        ->assertJsonPath('data.format', 'csv')
        ->assertJsonPath('data.row_count', 2);

    Storage::disk('exports')->assertExists((string) $exportResponse->json('data.path'));

    expect(AuditEventRecord::query()
        ->where('tenant_id', $tenantId)
        ->where('action', 'audit.exported')
        ->exists())->toBeTrue();
});

it('manages tenant retention settings and enforces audit permissions', function (): void {
    [, $viewerToken, $tenantId] = auditComplianceCreateContext($this, 'events-viewer', ['audit.view']);
    [, $managerToken] = auditComplianceAttachUser($this, $tenantId, 'events-manager', ['audit.view', 'audit.manage']);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/audit/retention')
        ->assertOk()
        ->assertJsonPath('data.default_retention_days', 30)
        ->assertJsonPath('data.tenant_retention_days', null)
        ->assertJsonPath('data.pruning_enabled', true);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/audit/export')
        ->assertStatus(403);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/audit/retention', ['retention_days' => 7])
        ->assertStatus(403);

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/audit/retention', ['retention_days' => 7])
        ->assertOk()
        ->assertJsonPath('status', 'audit_retention_updated')
        ->assertJsonPath('data.tenant_retention_days', 7)
        ->assertJsonPath('data.effective_retention_days', 7);

    $this->assertDatabaseHas('audit_retention_settings', [
        'tenant_id' => $tenantId,
        'retention_days' => 7,
    ]);

    expect(AuditEventRecord::query()
        ->where('tenant_id', $tenantId)
        ->where('action', 'audit.retention_updated')
        ->exists())->toBeTrue();
});
