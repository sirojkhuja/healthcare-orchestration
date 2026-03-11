<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use App\Modules\Billing\Application\Contracts\PaymentRepository;
use App\Modules\Billing\Application\Services\PaymentAdministrationService;
use App\Modules\Billing\Application\Services\PaymentWorkflowService;
use App\Shared\Infrastructure\Messaging\Persistence\OutboxMessageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

require_once __DIR__.'/BillingTestSupport.php';

uses(RefreshDatabase::class);

it('initiates and advances payments through pending capture and refund via handlers', function (): void {
    $admin = User::factory()->create([
        'email' => 'payment.admin@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = billingIssueBearerToken($this, 'payment.admin@openai.com');
    $tenantId = billingCreateTenant($this, $adminToken, 'Payment Tenant')->json('data.id');

    billingGrantPermissions($admin, $tenantId, ['billing.view', 'billing.manage', 'patients.manage']);

    $patientId = billingCreatePatient($this, $adminToken, $tenantId, [
        'first_name' => 'Nodira',
        'last_name' => 'Usmonova',
    ])->assertCreated()->json('data.id');

    $serviceId = billingCreateService($this, $adminToken, $tenantId, [
        'code' => 'payment-consult',
        'name' => 'Payment Consultation',
        'unit' => 'visit',
    ], 'payment-service-create-1')->assertCreated()->json('data.id');

    $invoiceId = billingCreateInvoice($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'currency' => 'UZS',
        'invoice_date' => '2026-03-11',
    ], 'payment-invoice-create-1')->assertCreated()->json('data.id');

    billingAddInvoiceItem($this, $adminToken, $tenantId, $invoiceId, [
        'service_id' => $serviceId,
        'quantity' => '1',
        'unit_price_amount' => '85000',
    ], 'payment-invoice-item-1')
        ->assertCreated()
        ->assertJsonPath('data.totals.total.amount', '85000.00');

    billingIssueInvoice($this, $adminToken, $tenantId, $invoiceId, 'payment-invoice-issue-1')
        ->assertOk()
        ->assertJsonPath('data.status', 'issued');

    billingInitializeApplicationContext($this, $admin, $tenantId, 'payment-session-1');

    $initiated = app(PaymentAdministrationService::class)->initiate([
        'invoice_id' => $invoiceId,
        'provider_key' => 'manual',
        'amount' => '85000',
        'currency' => 'UZS',
        'description' => 'Reception terminal charge',
    ]);

    $pending = app(PaymentWorkflowService::class)->markPending(
        paymentId: $initiated->paymentId,
        providerPaymentId: 'prov-pay-1',
        providerStatus: 'awaiting_capture',
        checkoutUrl: 'https://payments.example.test/checkout/prov-pay-1',
    );

    $captured = app(PaymentWorkflowService::class)->capture(
        paymentId: $initiated->paymentId,
        providerStatus: 'captured_remote',
    );

    $refunded = app(PaymentWorkflowService::class)->refund(
        paymentId: $initiated->paymentId,
        supportsRefunds: true,
        reason: 'Patient paid twice',
        providerStatus: 'refunded_remote',
    );

    $stored = app(PaymentRepository::class)->findInTenant($tenantId, $initiated->paymentId);

    expect($initiated->status)->toBe('initiated');
    expect($pending->status)->toBe('pending');
    expect($pending->providerPaymentId)->toBe('prov-pay-1');
    expect($captured->status)->toBe('captured');
    expect($refunded->status)->toBe('refunded');
    expect($refunded->refundReason)->toBe('Patient paid twice');
    expect($refunded->providerStatus)->toBe('refunded_remote');
    expect($stored?->status)->toBe('refunded');

    expect(AuditEventRecord::query()->where('action', 'payments.initiated')->where('object_id', $initiated->paymentId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'payments.pending')->where('object_id', $initiated->paymentId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'payments.captured')->where('object_id', $initiated->paymentId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'payments.refunded')->where('object_id', $initiated->paymentId)->exists())->toBeTrue();
    expect(OutboxMessageRecord::query()->where('event_type', 'payment.initiated')->exists())->toBeTrue();
    expect(OutboxMessageRecord::query()->where('event_type', 'payment.pending')->exists())->toBeTrue();
    expect(OutboxMessageRecord::query()->where('event_type', 'payment.captured')->exists())->toBeTrue();
    expect(OutboxMessageRecord::query()->where('event_type', 'payment.refunded')->exists())->toBeTrue();
});

it('enforces invoice-state and payment-lifecycle guards through the application layer', function (): void {
    $admin = User::factory()->create([
        'email' => 'payment.guards@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = billingIssueBearerToken($this, 'payment.guards@openai.com');
    $tenantId = billingCreateTenant($this, $adminToken, 'Payment Guard Tenant')->json('data.id');

    billingGrantPermissions($admin, $tenantId, ['billing.view', 'billing.manage', 'patients.manage']);

    $patientId = billingCreatePatient($this, $adminToken, $tenantId)->assertCreated()->json('data.id');
    $serviceId = billingCreateService($this, $adminToken, $tenantId, [
        'code' => 'guard-service',
        'name' => 'Guard Service',
    ], 'payment-guard-service')->assertCreated()->json('data.id');

    $draftInvoiceId = billingCreateInvoice($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'currency' => 'UZS',
    ], 'payment-draft-invoice')->assertCreated()->json('data.id');

    billingInitializeApplicationContext($this, $admin, $tenantId, 'payment-session-2');

    expect(fn () => app(PaymentAdministrationService::class)->initiate([
        'invoice_id' => $draftInvoiceId,
        'provider_key' => 'manual',
        'amount' => '10000',
    ]))->toThrow(ConflictHttpException::class);

    $issuedInvoiceId = billingCreateInvoice($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'currency' => 'UZS',
    ], 'payment-issued-invoice')->assertCreated()->json('data.id');

    billingAddInvoiceItem($this, $adminToken, $tenantId, $issuedInvoiceId, [
        'service_id' => $serviceId,
        'quantity' => '1',
        'unit_price_amount' => '45000',
    ], 'payment-issued-item')->assertCreated();

    billingIssueInvoice($this, $adminToken, $tenantId, $issuedInvoiceId, 'payment-issued-invoice-issue')
        ->assertOk();

    $payment = app(PaymentAdministrationService::class)->initiate([
        'invoice_id' => $issuedInvoiceId,
        'provider_key' => 'manual',
        'amount' => '45000',
    ]);

    expect(fn () => app(PaymentWorkflowService::class)->cancel(
        paymentId: $payment->paymentId,
        reason: 'Attempt before pending',
    ))->toThrow(ConflictHttpException::class);

    $pending = app(PaymentWorkflowService::class)->markPending(
        paymentId: $payment->paymentId,
        providerPaymentId: 'guard-prov-pay',
    );

    expect($pending->status)->toBe('pending');

    expect(fn () => app(PaymentWorkflowService::class)->refund(
        paymentId: $payment->paymentId,
        supportsRefunds: false,
        reason: 'Unsupported gateway',
    ))->toThrow(ConflictHttpException::class);

    $canceled = app(PaymentWorkflowService::class)->cancel(
        paymentId: $payment->paymentId,
        reason: 'Patient selected cash',
    );

    expect($canceled->status)->toBe('canceled');

    $secondPayment = app(PaymentAdministrationService::class)->initiate([
        'invoice_id' => $issuedInvoiceId,
        'provider_key' => 'manual',
        'amount' => '45000',
    ]);

    app(PaymentWorkflowService::class)->markPending(
        paymentId: $secondPayment->paymentId,
        providerPaymentId: 'guard-prov-pay-2',
    );

    $failed = app(PaymentWorkflowService::class)->fail(
        paymentId: $secondPayment->paymentId,
        failureCode: 'provider_timeout',
        failureMessage: 'Gateway timed out',
    );

    expect($failed->status)->toBe('failed');
    expect(fn () => app(PaymentWorkflowService::class)->capture(
        paymentId: $secondPayment->paymentId,
    ))->toThrow(ConflictHttpException::class);
});
