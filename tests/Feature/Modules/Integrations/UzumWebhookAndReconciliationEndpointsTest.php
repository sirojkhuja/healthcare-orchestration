<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../Billing/BillingTestSupport.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('billing.payment_gateways.uzum.service_id', 'uzum-demo-service');
    config()->set('billing.payment_gateways.uzum.merchant_login', 'uzum-demo-login');
    config()->set('billing.payment_gateways.uzum.merchant_password', 'uzum-demo-password');
    config()->set('billing.payment_gateways.uzum.confirmation_timeout_minutes', 30);
});

it('initiates uzum payments and reconciles stale pending transactions', function (): void {
    [, $managerToken, $tenantId, $invoiceId] = uzumPrepareIssuedInvoice($this, 'reconcile');

    $paymentResponse = billingInitiatePayment($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'uzum',
        'amount' => '95000',
        'description' => 'Uzum app payment',
    ], 'uzum-reconcile-initiate-1')
        ->assertCreated()
        ->assertJsonPath('status', 'payment_initiated')
        ->assertJsonPath('data.status', 'initiated')
        ->assertJsonPath('data.provider.key', 'uzum')
        ->assertJsonPath('data.provider.status', 'awaiting_uzum_webhook');

    $paymentId = $paymentResponse->json('data.id');

    $this->withHeader('Authorization', uzumAuthorizationHeader())
        ->postJson('/api/v1/webhooks/uzum?operation=check', uzumPayload([
            'transId' => 'uzum-check-1001',
            'amount' => '95000',
            'params' => [
                'payment_id' => $paymentId,
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('status', 'OK')
        ->assertJsonPath('data.state', 'CHECKED')
        ->assertJsonPath('data.payment_id', $paymentId);

    $createPayload = uzumPayload([
        'transId' => 'uzum-create-1001',
        'amount' => '95000',
        'params' => [
            'payment_id' => $paymentId,
        ],
    ]);

    $this->withHeader('Authorization', uzumAuthorizationHeader())
        ->postJson('/api/v1/webhooks/uzum?operation=create', $createPayload)
        ->assertOk()
        ->assertJsonPath('status', 'OK')
        ->assertJsonPath('data.state', 'CREATED')
        ->assertJsonPath('data.payment_id', $paymentId);

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/webhooks/uzum:verify', [
            'operation' => 'create',
            'authorization' => uzumAuthorizationHeader(),
            'payload' => $createPayload,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'uzum_webhook_verified')
        ->assertJsonPath('data.provider_key', 'uzum')
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.operation', 'create')
        ->assertJsonPath('data.payment_id', $paymentId)
        ->assertJsonPath('data.transaction_id', 'uzum-create-1001');

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/payments/'.$paymentId.'/status')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.provider.payment_id', 'uzum-create-1001')
        ->assertJsonPath('data.provider.status', 'CREATED');

    DB::table('payments')
        ->where('id', $paymentId)
        ->update([
            'pending_at' => now()->subMinutes(31),
            'updated_at' => now(),
        ]);

    $reconcileResponse = billingReconcilePayments($this, $managerToken, $tenantId, [
        'provider_key' => 'uzum',
        'payment_ids' => [$paymentId],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'payments_reconciled')
        ->assertJsonPath('data.provider_key', 'uzum')
        ->assertJsonPath('data.scanned_count', 1)
        ->assertJsonPath('data.changed_count', 1)
        ->assertJsonPath('data.results.0.payment_id', $paymentId)
        ->assertJsonPath('data.results.0.status_before', 'pending')
        ->assertJsonPath('data.results.0.status_after', 'failed');

    $runId = $reconcileResponse->json('data.id');

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/payments/reconciliation-runs?provider_key=uzum')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $runId)
        ->assertJsonPath('data.0.provider_key', 'uzum');

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/payments/reconciliation-runs/'.$runId)
        ->assertOk()
        ->assertJsonPath('data.id', $runId)
        ->assertJsonPath('data.results.0.payment.status', 'failed')
        ->assertJsonPath('data.results.0.payment.failure.code', 'uzum_timeout');

    $this->withHeader('Authorization', uzumAuthorizationHeader())
        ->postJson('/api/v1/webhooks/uzum?operation=status', uzumPayload([
            'transId' => 'uzum-create-1001',
            'params' => [
                'payment_id' => $paymentId,
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('status', 'OK')
        ->assertJsonPath('data.state', 'FAILED')
        ->assertJsonPath('data.payment_id', $paymentId);
});

it('confirms and reverses uzum payments through the webhook flow', function (): void {
    [, $managerToken, $tenantId, $invoiceId] = uzumPrepareIssuedInvoice($this, 'lifecycle');

    $paymentId = billingInitiatePayment($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'uzum',
        'amount' => '95000',
    ], 'uzum-lifecycle-initiate-1')->assertCreated()->json('data.id');

    $createPayload = uzumPayload([
        'transId' => 'uzum-life-1001',
        'amount' => '95000',
        'params' => [
            'payment_id' => $paymentId,
        ],
    ]);

    $this->withHeader('Authorization', uzumAuthorizationHeader())
        ->postJson('/api/v1/webhooks/uzum?operation=create', $createPayload)
        ->assertOk()
        ->assertJsonPath('data.state', 'CREATED');

    $this->withHeader('Authorization', uzumAuthorizationHeader())
        ->postJson('/api/v1/webhooks/uzum?operation=confirm', uzumPayload([
            'transId' => 'uzum-life-1001',
            'params' => [
                'payment_id' => $paymentId,
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('status', 'OK')
        ->assertJsonPath('data.state', 'CONFIRMED');

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/payments/'.$paymentId.'/status')
        ->assertOk()
        ->assertJsonPath('data.status', 'captured')
        ->assertJsonPath('data.provider.payment_id', 'uzum-life-1001')
        ->assertJsonPath('data.provider.status', 'CONFIRMED');

    billingCapturePayment($this, $managerToken, $tenantId, $paymentId, 'uzum-manual-capture-1')
        ->assertStatus(409);

    billingRefundPayment($this, $managerToken, $tenantId, $paymentId, [
        'reason' => 'manual uzum refund',
    ], 'uzum-manual-refund-1')
        ->assertStatus(409);

    $this->withHeader('Authorization', uzumAuthorizationHeader())
        ->postJson('/api/v1/webhooks/uzum?operation=reverse', uzumPayload([
            'transId' => 'uzum-life-1001',
            'params' => [
                'payment_id' => $paymentId,
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('status', 'OK')
        ->assertJsonPath('data.state', 'REFUNDED');

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/payments/'.$paymentId.'/status')
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded')
        ->assertJsonPath('data.provider.status', 'REFUNDED');
});

it('maps uzum authentication validation and lookup failures correctly', function (): void {
    [, $managerToken, $tenantId, $invoiceId] = uzumPrepareIssuedInvoice($this, 'errors');

    $this->withHeader('Authorization', 'Basic '.base64_encode('bad:credentials'))
        ->postJson('/api/v1/webhooks/uzum?operation=create', uzumPayload([
            'transId' => 'uzum-error-1001',
            'amount' => '95000',
            'params' => [
                'payment_id' => 'missing-payment',
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('status', 'ERROR')
        ->assertJsonPath('error.code', 'AUTHENTICATION_FAILED');

    $paymentId = billingInitiatePayment($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'uzum',
        'amount' => '95000',
    ], 'uzum-error-initiate-1')->assertCreated()->json('data.id');

    $this->withHeader('Authorization', uzumAuthorizationHeader())
        ->postJson('/api/v1/webhooks/uzum?operation=create', uzumPayload([
            'serviceId' => 'wrong-service',
            'transId' => 'uzum-error-1002',
            'amount' => '95000',
            'params' => [
                'payment_id' => $paymentId,
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('status', 'ERROR')
        ->assertJsonPath('error.code', 'SERVICE_MISMATCH');

    $this->withHeader('Authorization', uzumAuthorizationHeader())
        ->postJson('/api/v1/webhooks/uzum?operation=create', uzumPayload([
            'transId' => 'uzum-error-1003',
            'amount' => '1',
            'params' => [
                'payment_id' => $paymentId,
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('status', 'ERROR')
        ->assertJsonPath('error.code', 'AMOUNT_MISMATCH');

    $this->withHeader('Authorization', uzumAuthorizationHeader())
        ->postJson('/api/v1/webhooks/uzum?operation=create', uzumPayload([
            'transId' => 'uzum-error-1004',
            'amount' => '95000',
            'params' => [
                'payment_id' => 'missing-payment',
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('status', 'ERROR')
        ->assertJsonPath('error.code', 'PAYMENT_NOT_FOUND');

    billingCancelPayment($this, $managerToken, $tenantId, $paymentId, [
        'reason' => 'manual uzum cancel',
    ], 'uzum-manual-cancel-1')
        ->assertStatus(409);
});

function uzumAuthorizationHeader(): string
{
    return 'Basic '.base64_encode('uzum-demo-login:uzum-demo-password');
}

function uzumPayload(array $attributes): array
{
    return array_merge([
        'serviceId' => 'uzum-demo-service',
    ], $attributes);
}

function uzumPrepareIssuedInvoice($testCase, string $suffix): array
{
    $manager = User::factory()->create([
        'email' => sprintf('uzum.%s.manager@openai.com', $suffix),
        'password' => bcrypt('secret-password'),
    ]);

    $managerToken = billingIssueBearerToken($testCase, sprintf('uzum.%s.manager@openai.com', $suffix));
    $tenantId = billingCreateTenant($testCase, $managerToken, 'Uzum '.$suffix.' Tenant')->json('data.id');

    billingGrantPermissions($manager, $tenantId, [
        'billing.view',
        'billing.manage',
        'integrations.manage',
        'patients.manage',
    ]);

    $patientId = billingCreatePatient($testCase, $managerToken, $tenantId, [
        'first_name' => 'Uzum',
        'last_name' => 'Patient '.$suffix,
    ])->assertCreated()->json('data.id');

    $serviceId = billingCreateService($testCase, $managerToken, $tenantId, [
        'code' => 'UZUM-'.$suffix,
        'name' => 'Uzum Consultation '.$suffix,
    ], 'uzum-'.$suffix.'-service-1')->assertCreated()->json('data.id');

    $invoiceId = billingCreateInvoice($testCase, $managerToken, $tenantId, [
        'patient_id' => $patientId,
        'currency' => 'UZS',
    ], 'uzum-'.$suffix.'-invoice-1')->assertCreated()->json('data.id');

    billingAddInvoiceItem($testCase, $managerToken, $tenantId, $invoiceId, [
        'service_id' => $serviceId,
        'quantity' => '1',
        'unit_price_amount' => '95000',
    ], 'uzum-'.$suffix.'-invoice-item-1')->assertCreated();

    billingIssueInvoice($testCase, $managerToken, $tenantId, $invoiceId, 'uzum-'.$suffix.'-issue-1')->assertOk();

    return [$manager, $managerToken, $tenantId, $invoiceId];
}
