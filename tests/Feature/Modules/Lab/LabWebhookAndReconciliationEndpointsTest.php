<?php

use App\Models\User;
use App\Modules\Lab\Application\Contracts\LabProviderGatewayRegistry;
use App\Modules\Lab\Application\Data\LabProviderOrderPayload;
use App\Modules\Lab\Application\Data\LabProviderResultPayload;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\Lab\FakeLabProviderGateway;
use Tests\Fixtures\Lab\FakeLabProviderGatewayRegistry;

require_once __DIR__.'/LabTestSupport.php';

uses(RefreshDatabase::class);

it('processes webhook deliveries verifies signatures and reconciles in-flight lab orders', function (): void {
    try {
        $admin = User::factory()->create([
            'email' => 'labs.integration.admin@openai.com',
            'password' => 'secret-password',
        ]);
        $token = labIssueBearerToken($this, 'labs.integration.admin@openai.com');
        $tenantId = labCreateTenant($this, $token, 'Lab Integration Tenant')->json('data.id');
        labGrantPermissions($admin, $tenantId, [
            'labs.view',
            'labs.manage',
            'integrations.manage',
            'patients.manage',
            'providers.manage',
        ]);

        $gateway = new FakeLabProviderGateway('mock-lab', 'lab-webhook-secret');
        $registry = new FakeLabProviderGatewayRegistry([$gateway]);
        app()->instance(LabProviderGatewayRegistry::class, $registry);

        $patientId = labCreatePatient($this, $token, $tenantId)->json('data.id');
        $providerId = labCreateProvider($this, $token, $tenantId)->json('data.id');
        $labTestId = labCreateLabTest($this, $token, $tenantId, [
            'lab_provider_key' => 'mock-lab',
        ])->json('data.id');

        $firstOrderId = labCreateLabOrder($this, $token, $tenantId, [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'lab_test_id' => $labTestId,
            'lab_provider_key' => 'mock-lab',
            'ordered_at' => '2026-03-10T11:00:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ], 'lab-webhook-order-create-1')->json('data.id');
        $gateway->queueDispatch($firstOrderId, 'remote-order-001');

        $this->withToken($token)
            ->withHeaders([
                'X-Tenant-Id' => $tenantId,
                'Idempotency-Key' => 'lab-webhook-order-send-1',
            ])
            ->postJson('/api/v1/lab-orders/'.$firstOrderId.':send')
            ->assertOk()
            ->assertJsonPath('data.external_order_id', 'remote-order-001');

        $webhookPayload = [
            'delivery_id' => 'delivery-001',
            'external_order_id' => 'remote-order-001',
            'status' => 'completed',
            'occurred_at' => '2026-03-11T09:15:00+05:00',
            'results' => [
                [
                    'external_result_id' => 'result-001',
                    'status' => 'final',
                    'observed_at' => '2026-03-11T09:00:00+05:00',
                    'received_at' => '2026-03-11T09:10:00+05:00',
                    'value_type' => 'numeric',
                    'value_numeric' => '5.2',
                    'unit' => '10^9/L',
                    'reference_range' => '4.0-11.0',
                    'abnormal_flag' => 'normal',
                ],
            ],
        ];
        $webhookRawPayload = json_encode($webhookPayload, JSON_THROW_ON_ERROR);
        $signature = $gateway->sign($webhookRawPayload);

        $this->withHeader('Idempotency-Key', 'lab-webhook-process-1')
            ->withHeader('X-Lab-Signature', $signature)
            ->postJson('/api/v1/webhooks/lab/mock-lab', $webhookPayload)
            ->assertOk()
            ->assertJsonPath('status', 'lab_webhook_processed')
            ->assertJsonPath('data.already_processed', false)
            ->assertJsonPath('data.order.status', 'completed')
            ->assertJsonPath('data.result_count', 1)
            ->assertJsonPath('data.results.0.external_result_id', 'result-001');

        $this->withHeader('Idempotency-Key', 'lab-webhook-process-2')
            ->withHeader('X-Lab-Signature', $signature)
            ->postJson('/api/v1/webhooks/lab/mock-lab', $webhookPayload)
            ->assertOk()
            ->assertJsonPath('data.already_processed', true)
            ->assertJsonPath('data.order.status', 'completed');

        $this->withHeader('Idempotency-Key', 'lab-webhook-invalid-signature')
            ->withHeader('X-Lab-Signature', 'bad-signature')
            ->postJson('/api/v1/webhooks/lab/mock-lab', [
                'delivery_id' => 'delivery-invalid',
                'external_order_id' => 'remote-order-001',
                'status' => 'completed',
                'occurred_at' => '2026-03-11T09:30:00+05:00',
            ])
            ->assertStatus(401)
            ->assertJsonPath('code', 'WEBHOOK_SIGNATURE_INVALID');

        $this->withToken($token)
            ->withHeader('X-Tenant-Id', $tenantId)
            ->postJson('/api/v1/webhooks/lab/mock-lab:verify', [
                'signature' => $signature,
                'payload' => $webhookPayload,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'lab_webhook_verified')
            ->assertJsonPath('data.valid', true);

        $secondOrderId = labCreateLabOrder($this, $token, $tenantId, [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'lab_test_id' => $labTestId,
            'lab_provider_key' => 'mock-lab',
            'ordered_at' => '2026-03-10T15:00:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ], 'lab-reconcile-order-create-1')->json('data.id');
        $gateway->queueDispatch($secondOrderId, 'remote-order-002');

        $this->withToken($token)
            ->withHeaders([
                'X-Tenant-Id' => $tenantId,
                'Idempotency-Key' => 'lab-reconcile-order-send-1',
            ])
            ->postJson('/api/v1/lab-orders/'.$secondOrderId.':send')
            ->assertOk()
            ->assertJsonPath('data.external_order_id', 'remote-order-002');

        $gateway->queueSnapshot($secondOrderId, new LabProviderOrderPayload(
            externalOrderId: 'remote-order-002',
            status: 'completed',
            occurredAt: CarbonImmutable::parse('2026-03-11T12:00:00+05:00'),
            results: [
                new LabProviderResultPayload(
                    externalResultId: 'result-002',
                    status: 'final',
                    observedAt: CarbonImmutable::parse('2026-03-11T11:50:00+05:00'),
                    receivedAt: CarbonImmutable::parse('2026-03-11T11:55:00+05:00'),
                    valueType: 'text',
                    valueNumeric: null,
                    valueText: 'negative',
                    valueBoolean: null,
                    valueJson: null,
                    unit: null,
                    referenceRange: null,
                    abnormalFlag: 'normal',
                    notes: 'No abnormal finding',
                    rawPayload: ['source' => 'reconciliation'],
                ),
            ],
        ));

        $this->withToken($token)
            ->withHeaders([
                'X-Tenant-Id' => $tenantId,
                'Idempotency-Key' => 'lab-reconcile-1',
            ])
            ->postJson('/api/v1/lab-orders:reconcile', [
                'lab_provider_key' => 'mock-lab',
                'order_ids' => [$secondOrderId],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'lab_orders_reconciled')
            ->assertJsonPath('data.affected_count', 1)
            ->assertJsonPath('data.result_count', 1)
            ->assertJsonPath('data.orders.0.id', $secondOrderId)
            ->assertJsonPath('data.orders.0.status', 'completed');

        $this->withToken($token)
            ->withHeader('X-Tenant-Id', $tenantId)
            ->getJson('/api/v1/lab-orders/'.$secondOrderId.'/results')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_result_id', 'result-002')
            ->assertJsonPath('data.0.value_text', 'negative');
    } finally {
        CarbonImmutable::setTestNow();
    }
});
