<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/LabTestSupport.php';

uses(RefreshDatabase::class);

it('manages lab catalog records orders bulk updates and workflow transitions', function (): void {
    $admin = User::factory()->create([
        'email' => 'labs.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'labs.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = labIssueBearerToken($this, 'labs.admin@openai.com');
    $viewerToken = labIssueBearerToken($this, 'labs.viewer@openai.com');
    $tenantId = labCreateTenant($this, $adminToken, 'Lab CRUD Tenant')->json('data.id');
    labGrantPermissions($admin, $tenantId, [
        'labs.view',
        'labs.manage',
        'integrations.manage',
        'patients.manage',
        'providers.manage',
        'treatments.view',
        'treatments.manage',
    ]);
    labGrantPermissions($viewer, $tenantId, ['labs.view']);

    $patientId = labCreatePatient($this, $adminToken, $tenantId)->json('data.id');
    $providerId = labCreateProvider($this, $adminToken, $tenantId)->json('data.id');
    $planId = labCreatePlan($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'title' => 'Diagnostics plan',
    ])->json('data.id');
    $itemId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$planId.'/items', [
            'item_type' => 'lab',
            'title' => 'CBC collection',
        ])
        ->assertCreated()
        ->json('data.id');
    $encounterId = labCreateEncounter($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'treatment_plan_id' => $planId,
        'chief_complaint' => 'Routine annual checkup',
    ])->assertCreated()->json('data.id');

    $labTestId = labCreateLabTest($this, $adminToken, $tenantId, [
        'code' => 'cbc-basic',
        'name' => 'CBC Basic',
        'lab_provider_key' => 'mock-lab',
    ])->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/lab-tests')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $labTestId);

    $orderId = labCreateLabOrder($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'encounter_id' => $encounterId,
        'treatment_item_id' => $itemId,
        'lab_test_id' => $labTestId,
        'lab_provider_key' => 'mock-lab',
        'ordered_at' => '2026-03-10T12:00:00+05:00',
        'timezone' => 'Asia/Tashkent',
        'notes' => 'Initial panel',
    ], 'lab-order-create-1')
        ->assertCreated()
        ->assertJsonPath('status', 'lab_order_created')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.requested_test.code', 'cbc-basic')
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/lab-orders')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $orderId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/lab-orders/search?q=cbc-basic&status=draft')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $orderId)
        ->assertJsonPath('meta.filters.q', 'cbc-basic')
        ->assertJsonPath('meta.filters.status', 'draft');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'lab-order-update-1',
        ])
        ->patchJson('/api/v1/lab-orders/'.$orderId, [
            'notes' => 'Updated note',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'lab_order_updated')
        ->assertJsonPath('data.notes', 'Updated note');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'lab-orders-bulk-1',
        ])
        ->postJson('/api/v1/lab-orders/bulk', [
            'order_ids' => [$orderId],
            'changes' => [
                'notes' => 'Bulk updated note',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'lab_orders_bulk_updated')
        ->assertJsonPath('data.affected_count', 1)
        ->assertJsonPath('data.orders.0.notes', 'Bulk updated note');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/lab-orders/'.$orderId.'/results')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $sentResponse = $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'lab-order-send-1',
        ])
        ->postJson('/api/v1/lab-orders/'.$orderId.':send')
        ->assertOk()
        ->assertJsonPath('status', 'lab_order_sent')
        ->assertJsonPath('data.status', 'sent');

    expect($sentResponse->json('data.external_order_id'))->not->toBeNull();

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'lab-order-update-after-send',
        ])
        ->patchJson('/api/v1/lab-orders/'.$orderId, [
            'notes' => 'Should fail',
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'lab-order-cancel-1',
        ])
        ->postJson('/api/v1/lab-orders/'.$orderId.':cancel', [
            'reason' => 'Patient declined the test.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'lab_order_canceled')
        ->assertJsonPath('data.status', 'canceled');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'lab-order-delete-1',
        ])
        ->deleteJson('/api/v1/lab-orders/'.$orderId)
        ->assertOk()
        ->assertJsonPath('status', 'lab_order_deleted')
        ->assertJsonPath('data.deleted_at', fn (string $deletedAt): bool => $deletedAt !== '');

    expect(AuditEventRecord::query()->where('action', 'lab_tests.created')->where('object_id', $labTestId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'lab_orders.bulk_updated')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'lab_orders.sent')->where('object_id', $orderId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'lab_orders.canceled')->where('object_id', $orderId)->exists())->toBeTrue();
});
