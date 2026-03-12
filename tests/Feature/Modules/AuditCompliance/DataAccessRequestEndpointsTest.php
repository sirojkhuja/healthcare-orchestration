<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/../Patient/PatientTestSupport.php';

uses(RefreshDatabase::class);

it('creates lists approves and denies tenant-scoped data access requests', function (): void {
    $admin = User::factory()->create([
        'email' => 'compliance.requests.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'compliance.requests.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'compliance.requests.admin@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'compliance.requests.viewer@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Compliance Requests')->json('data.id');

    patientGrantPermissions($admin, $tenantId, ['patients.manage', 'compliance.view', 'compliance.manage']);
    patientGrantPermissions($viewer, $tenantId, ['compliance.view']);

    $patientId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Sabina',
            'last_name' => 'Ruzieva',
            'sex' => 'female',
            'birth_date' => '1991-02-11',
        ])
        ->assertCreated()
        ->json('data.id');

    $approvedRequestId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->withHeader('Idempotency-Key', 'dar-create-1')
        ->postJson('/api/v1/data-access-requests', [
            'patient_id' => $patientId,
            'request_type' => 'Full Record Export',
            'requested_by_name' => 'Sabina Ruzieva',
            'requested_by_relationship' => 'self',
            'requested_at' => '2026-03-12T10:00:00+00:00',
            'reason' => 'Needs a specialist second opinion.',
            'notes' => 'Email once approved.',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'data_access_request_created')
        ->assertJsonPath('data.request_type', 'full_record_export')
        ->assertJsonPath('data.status', 'submitted')
        ->json('data.id');

    $deniedRequestId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->withHeader('Idempotency-Key', 'dar-create-2')
        ->postJson('/api/v1/data-access-requests', [
            'patient_id' => $patientId,
            'request_type' => 'Audit Copy',
            'requested_by_name' => 'Representative',
            'requested_by_relationship' => 'guardian',
            'requested_at' => '2026-03-12T09:00:00+00:00',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/data-access-requests?limit=10')
        ->assertOk()
        ->assertJsonPath('data.0.id', $approvedRequestId)
        ->assertJsonPath('data.0.status', 'submitted')
        ->assertJsonPath('data.1.id', $deniedRequestId)
        ->assertJsonPath('data.1.request_type', 'audit_copy');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->withHeader('Idempotency-Key', 'dar-approve-1')
        ->postJson('/api/v1/data-access-requests/'.$approvedRequestId.':approve', [
            'decision_notes' => 'Verified identity and approved export.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'data_access_request_approved')
        ->assertJsonPath('data.id', $approvedRequestId)
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.approved_by.id', (string) $admin->getAuthIdentifier());

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->withHeader('Idempotency-Key', 'dar-deny-1')
        ->postJson('/api/v1/data-access-requests/'.$deniedRequestId.':deny', [
            'reason' => 'Representative authorization is missing.',
            'decision_notes' => 'Request may be resubmitted with proof.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'data_access_request_denied')
        ->assertJsonPath('data.id', $deniedRequestId)
        ->assertJsonPath('data.status', 'denied')
        ->assertJsonPath('data.denial_reason', 'Representative authorization is missing.');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/data-access-requests/'.$approvedRequestId)
        ->assertOk()
        ->assertJsonPath('data.id', $approvedRequestId)
        ->assertJsonPath('data.status', 'approved');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->withHeader('Idempotency-Key', 'dar-approve-2')
        ->postJson('/api/v1/data-access-requests/'.$approvedRequestId.':approve')
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    expect(AuditEventRecord::query()->where('action', 'compliance.data_access_request_created')->count())->toBe(2);
    expect(AuditEventRecord::query()->where('action', 'compliance.data_access_request_approved')->where('object_id', $approvedRequestId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'compliance.data_access_request_denied')->where('object_id', $deniedRequestId)->exists())->toBeTrue();
});

it('enforces compliance request permissions validation and tenant scope', function (): void {
    $admin = User::factory()->create([
        'email' => 'compliance.requests.admin+2@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'compliance.requests.viewer+2@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'compliance.requests.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'compliance.requests.admin+2@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'compliance.requests.viewer+2@openai.com');
    $blockedToken = patientIssueBearerToken($this, 'compliance.requests.blocked@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Compliance Requests Two')->json('data.id');

    patientGrantPermissions($admin, $tenantId, ['patients.manage', 'compliance.view', 'compliance.manage']);
    patientGrantPermissions($viewer, $tenantId, ['compliance.view']);
    patientEnsureMembership($blocked, $tenantId);

    $patientId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Jasur',
            'last_name' => 'Rakhimov',
            'sex' => 'male',
            'birth_date' => '1986-07-09',
        ])
        ->assertCreated()
        ->json('data.id');

    $requestId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->withHeader('Idempotency-Key', 'dar-create-3')
        ->postJson('/api/v1/data-access-requests', [
            'patient_id' => $patientId,
            'request_type' => 'summary_copy',
            'requested_by_name' => 'Jasur Rakhimov',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->withHeader('Idempotency-Key', 'dar-create-blocked')
        ->postJson('/api/v1/data-access-requests', [
            'patient_id' => $patientId,
            'request_type' => 'summary_copy',
            'requested_by_name' => 'Jasur Rakhimov',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->withHeader('Idempotency-Key', 'dar-deny-blocked')
        ->postJson('/api/v1/data-access-requests/'.$requestId.':deny', [
            'reason' => 'No write permission.',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/data-access-requests')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->withHeader('Idempotency-Key', 'dar-deny-invalid')
        ->postJson('/api/v1/data-access-requests/'.$requestId.':deny', [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED');

    $otherTenantId = patientCreateTenant($this, $adminToken, 'Compliance Requests Three')->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->getJson('/api/v1/data-access-requests/'.$requestId)
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});
