<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use App\Modules\Billing\Application\Services\PaymentAdministrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/BillingTestSupport.php';

uses(RefreshDatabase::class);

it('manages payment initiate read capture refund cancel and idempotent replay flows', function (): void {
    $manager = User::factory()->create([
        'email' => 'payment.manager@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'payment.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = billingIssueBearerToken($this, 'payment.manager@openai.com');
    $viewerToken = billingIssueBearerToken($this, 'payment.viewer@openai.com');
    $tenantId = billingCreateTenant($this, $managerToken, 'Payment Endpoint Tenant')->json('data.id');

    billingGrantPermissions($manager, $tenantId, ['billing.view', 'billing.manage', 'patients.manage']);
    billingGrantPermissions($viewer, $tenantId, ['billing.view']);

    $patientId = billingCreatePatient($this, $managerToken, $tenantId, [
        'first_name' => 'Saida',
        'last_name' => 'Yusupova',
    ])->assertCreated()->json('data.id');
    $serviceId = billingCreateService($this, $managerToken, $tenantId, [
        'code' => 'payment-api-visit',
        'name' => 'Payment API Visit',
        'unit' => 'visit',
    ], 'payment-api-service-1')->assertCreated()->json('data.id');
    $invoiceId = billingCreateInvoice($this, $managerToken, $tenantId, [
        'patient_id' => $patientId,
        'currency' => 'UZS',
        'invoice_date' => '2026-03-11',
    ], 'payment-api-invoice-1')->assertCreated()->json('data.id');

    billingAddInvoiceItem($this, $managerToken, $tenantId, $invoiceId, [
        'service_id' => $serviceId,
        'quantity' => '1',
        'unit_price_amount' => '95000',
    ], 'payment-api-item-1')->assertCreated();

    billingIssueInvoice($this, $managerToken, $tenantId, $invoiceId, 'payment-api-issue-1')->assertOk();

    $createdResponse = billingInitiatePayment($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'manual',
        'amount' => '95000',
        'description' => 'Reception terminal payment',
    ], 'payment-api-initiate-1')
        ->assertCreated()
        ->assertHeader('Idempotency-Key', 'payment-api-initiate-1')
        ->assertJsonPath('status', 'payment_initiated')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.provider.key', 'manual');

    $paymentId = $createdResponse->json('data.id');

    billingInitiatePayment($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'manual',
        'amount' => '95000',
        'description' => 'Reception terminal payment',
    ], 'payment-api-initiate-1')
        ->assertCreated()
        ->assertHeader('X-Idempotent-Replay', 'true')
        ->assertJsonPath('data.id', $paymentId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/payments?status=pending&provider_key=manual&q=reception')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.filters.status', 'pending')
        ->assertJsonPath('meta.filters.provider_key', 'manual')
        ->assertJsonPath('data.0.id', $paymentId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/payments/'.$paymentId)
        ->assertOk()
        ->assertJsonPath('data.id', $paymentId)
        ->assertJsonPath('data.amount.amount', '95000.00')
        ->assertJsonPath('data.invoice.id', $invoiceId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/payments/'.$paymentId.'/status')
        ->assertOk()
        ->assertJsonPath('data.id', $paymentId)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.provider.payment_id', fn (string $providerPaymentId): bool => $providerPaymentId !== '');

    billingCapturePayment($this, $managerToken, $tenantId, $paymentId, 'payment-api-capture-1')
        ->assertOk()
        ->assertHeader('Idempotency-Key', 'payment-api-capture-1')
        ->assertJsonPath('status', 'payment_captured')
        ->assertJsonPath('data.status', 'captured');

    billingCapturePayment($this, $managerToken, $tenantId, $paymentId, 'payment-api-capture-1')
        ->assertOk()
        ->assertHeader('X-Idempotent-Replay', 'true')
        ->assertJsonPath('data.status', 'captured');

    billingRefundPayment($this, $managerToken, $tenantId, $paymentId, [
        'reason' => 'Patient paid twice.',
    ], 'payment-api-refund-1')
        ->assertOk()
        ->assertJsonPath('status', 'payment_refunded')
        ->assertJsonPath('data.status', 'refunded')
        ->assertJsonPath('data.refund_reason', 'Patient paid twice.');

    $cancelPaymentId = billingInitiatePayment($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'manual',
        'amount' => '25000',
        'description' => 'Backup payment attempt',
    ], 'payment-api-initiate-2')->assertCreated()->json('data.id');

    billingCancelPayment($this, $managerToken, $tenantId, $cancelPaymentId, [
        'reason' => 'Switched to bank transfer.',
    ], 'payment-api-cancel-1')
        ->assertOk()
        ->assertJsonPath('status', 'payment_canceled')
        ->assertJsonPath('data.status', 'canceled')
        ->assertJsonPath('data.cancel_reason', 'Switched to bank transfer.');

    expect(AuditEventRecord::query()->where('action', 'payments.initiated')->count())->toBe(2);
    expect(AuditEventRecord::query()->where('action', 'payments.captured')->where('object_id', $paymentId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'payments.refunded')->where('object_id', $paymentId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'payments.canceled')->where('object_id', $cancelPaymentId)->exists())->toBeTrue();
});

it('enforces payment permissions validation idempotency and refund-support guards', function (): void {
    $manager = User::factory()->create([
        'email' => 'payment.guard.manager@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'payment.guard.viewer@openai.com',
        'password' => 'secret-password',
    ]);
    $outsider = User::factory()->create([
        'email' => 'payment.guard.outsider@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = billingIssueBearerToken($this, 'payment.guard.manager@openai.com');
    $viewerToken = billingIssueBearerToken($this, 'payment.guard.viewer@openai.com');
    $outsiderToken = billingIssueBearerToken($this, 'payment.guard.outsider@openai.com');
    $tenantId = billingCreateTenant($this, $managerToken, 'Payment Guard Endpoint Tenant')->json('data.id');

    billingGrantPermissions($manager, $tenantId, ['billing.view', 'billing.manage', 'patients.manage']);
    billingGrantPermissions($viewer, $tenantId, ['billing.view']);
    billingEnsureMembership($outsider, $tenantId);

    $patientId = billingCreatePatient($this, $managerToken, $tenantId)->assertCreated()->json('data.id');
    $serviceId = billingCreateService($this, $managerToken, $tenantId, [
        'code' => 'payment-guard-visit',
        'name' => 'Guard Visit',
    ], 'payment-guard-service-1')->assertCreated()->json('data.id');
    $invoiceId = billingCreateInvoice($this, $managerToken, $tenantId, [
        'patient_id' => $patientId,
        'currency' => 'UZS',
    ], 'payment-guard-invoice-1')->assertCreated()->json('data.id');

    billingAddInvoiceItem($this, $managerToken, $tenantId, $invoiceId, [
        'service_id' => $serviceId,
        'quantity' => '1',
        'unit_price_amount' => '125000',
    ], 'payment-guard-item-1')->assertCreated();

    billingIssueInvoice($this, $managerToken, $tenantId, $invoiceId, 'payment-guard-issue-1')->assertOk();

    billingInitiatePayment($this, $viewerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'manual',
        'amount' => '125000',
    ], 'payment-guard-viewer-initiate')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->withoutHeader('Idempotency-Key')
        ->postJson('/api/v1/payments:initiate', [
            'invoice_id' => $invoiceId,
            'provider_key' => 'manual',
            'amount' => '125000',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonPath('details.errors.Idempotency-Key.0', 'The idempotency header is required for this operation.');

    billingInitiatePayment($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'missing_gateway',
        'amount' => '125000',
    ], 'payment-guard-missing-gateway')
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $noRefundPaymentId = billingInitiatePayment($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'manual_no_refund',
        'amount' => '125000',
    ], 'payment-guard-no-refund-initiate')
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->json('data.id');

    billingCapturePayment($this, $managerToken, $tenantId, $noRefundPaymentId, 'payment-guard-no-refund-capture')
        ->assertOk()
        ->assertJsonPath('data.status', 'captured');

    billingRefundPayment($this, $managerToken, $tenantId, $noRefundPaymentId, [
        'reason' => 'Unsupported refund attempt.',
    ], 'payment-guard-no-refund-refund')
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    billingInitializeApplicationContext($this, $manager, $tenantId, 'payment-endpoint-initiated-state');
    $initiatedOnlyPayment = app(PaymentAdministrationService::class)->initiate([
        'invoice_id' => $invoiceId,
        'provider_key' => 'manual',
        'amount' => '50000',
    ]);

    billingCapturePayment($this, $managerToken, $tenantId, $initiatedOnlyPayment->paymentId, 'payment-guard-capture-before-pending')
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($outsiderToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/payments/'.$noRefundPaymentId)
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
});
