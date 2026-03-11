<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/../Billing/BillingTestSupport.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('billing.payment_gateways.click.merchant_id', '46');
    config()->set('billing.payment_gateways.click.service_id', '36');
    config()->set('billing.payment_gateways.click.merchant_user_id', '204');
    config()->set('billing.payment_gateways.click.secret_key', 'test-click-secret');
    config()->set('billing.payment_gateways.click.payment_base_url', 'https://my.click.uz/services/pay');
    config()->set('billing.payment_gateways.click.return_url', 'https://example.test/click-return');
    config()->set('billing.payment_gateways.click.card_type', 'humo');
});

it('initiates click payments with a direct checkout url', function (): void {
    [, $managerToken, $tenantId, $invoiceId] = clickPrepareIssuedInvoice($this, 'checkout');

    $paymentResponse = billingInitiatePayment($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'click',
        'amount' => '95000',
        'description' => 'Click checkout request',
    ], 'click-checkout-initiate-1')
        ->assertCreated()
        ->assertJsonPath('status', 'payment_initiated')
        ->assertJsonPath('data.status', 'initiated')
        ->assertJsonPath('data.provider.key', 'click')
        ->assertJsonPath('data.provider.status', 'checkout_pending');

    $paymentId = $paymentResponse->json('data.id');
    $checkoutUrl = (string) $paymentResponse->json('data.provider.checkout_url');
    $query = [];

    parse_str((string) parse_url($checkoutUrl, PHP_URL_QUERY), $query);

    expect($checkoutUrl)->toStartWith('https://my.click.uz/services/pay?');
    expect($query)->toMatchArray([
        'service_id' => '36',
        'merchant_id' => '46',
        'merchant_user_id' => '204',
        'transaction_param' => $paymentId,
        'amount' => '95000.00',
        'return_url' => 'https://example.test/click-return',
        'card_type' => 'humo',
    ]);
});

it('processes click prepare complete and verification flows', function (): void {
    [, $managerToken, $tenantId, $invoiceId] = clickPrepareIssuedInvoice($this, 'lifecycle');
    $paymentId = billingInitiatePayment($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'click',
        'amount' => '95000',
    ], 'click-lifecycle-initiate-1')->assertCreated()->json('data.id');

    $preparePayload = clickPayload([
        'click_trans_id' => '70001',
        'click_paydoc_id' => '910001',
        'merchant_trans_id' => $paymentId,
        'amount' => '95000.00',
        'action' => '0',
        'error' => '0',
        'error_note' => 'Success',
        'sign_time' => '2026-03-11 10:15:00',
    ]);

    $this->post('/api/v1/webhooks/click', $preparePayload)
        ->assertOk()
        ->assertJson([
            'error' => 0,
            'error_note' => 'Success',
            'click_trans_id' => '70001',
            'merchant_trans_id' => $paymentId,
            'merchant_prepare_id' => '70001',
        ]);

    $this->post('/api/v1/webhooks/click', $preparePayload)
        ->assertOk()
        ->assertJsonPath('merchant_prepare_id', '70001');

    $completePayload = clickPayload([
        'click_trans_id' => '70001',
        'click_paydoc_id' => '910001',
        'merchant_trans_id' => $paymentId,
        'merchant_prepare_id' => '70001',
        'amount' => '95000.00',
        'action' => '1',
        'error' => '0',
        'error_note' => 'Success',
        'sign_time' => '2026-03-11 10:16:00',
    ]);

    $this->post('/api/v1/webhooks/click', $completePayload)
        ->assertOk()
        ->assertJson([
            'error' => 0,
            'error_note' => 'Success',
            'click_trans_id' => '70001',
            'merchant_trans_id' => $paymentId,
            'merchant_confirm_id' => '70001',
        ]);

    $this->post('/api/v1/webhooks/click', $completePayload)
        ->assertOk()
        ->assertJsonPath('merchant_confirm_id', '70001');

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/payments/'.$paymentId.'/status')
        ->assertOk()
        ->assertJsonPath('data.status', 'captured')
        ->assertJsonPath('data.provider.payment_id', '910001')
        ->assertJsonPath('data.provider.status', 'completed');

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/webhooks/click:verify', [
            'payload' => $preparePayload,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'click_webhook_verified')
        ->assertJsonPath('data.provider_key', 'click')
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.action', 0)
        ->assertJsonPath('data.merchant_trans_id', $paymentId);
});

it('maps click cancellation validation failures and unsupported manual actions correctly', function (): void {
    [, $managerToken, $tenantId, $invoiceId] = clickPrepareIssuedInvoice($this, 'errors');

    $this->post('/api/v1/webhooks/click', clickPayload([
        'click_trans_id' => '71001',
        'click_paydoc_id' => '910101',
        'merchant_trans_id' => 'missing-payment',
        'amount' => '95000.00',
        'action' => '0',
        'error' => '0',
        'error_note' => 'Success',
        'sign_time' => '2026-03-11 11:00:00',
        'sign_string' => 'wrong-signature',
    ]))
        ->assertOk()
        ->assertJsonPath('error', -1);

    $this->post('/api/v1/webhooks/click', clickPayload([
        'click_trans_id' => '710011',
        'click_paydoc_id' => '9101011',
        'merchant_trans_id' => 'missing-payment',
        'amount' => '95000.00',
        'action' => '0',
        'error' => '0',
        'error_note' => 'Success',
        'sign_time' => '2026-03-11 11:00:30',
    ]))
        ->assertOk()
        ->assertJsonPath('error', -5);

    $mismatchPaymentId = billingInitiatePayment($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'click',
        'amount' => '95000',
    ], 'click-errors-initiate-1')->assertCreated()->json('data.id');

    $this->post('/api/v1/webhooks/click', clickPayload([
        'click_trans_id' => '71002',
        'click_paydoc_id' => '910102',
        'merchant_trans_id' => $mismatchPaymentId,
        'amount' => '1.00',
        'action' => '0',
        'error' => '0',
        'error_note' => 'Success',
        'sign_time' => '2026-03-11 11:01:00',
    ]))
        ->assertOk()
        ->assertJsonPath('error', -2);

    $cancelPaymentId = billingInitiatePayment($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'click',
        'amount' => '25000',
    ], 'click-errors-initiate-2')->assertCreated()->json('data.id');

    $preparePayload = clickPayload([
        'click_trans_id' => '71003',
        'click_paydoc_id' => '910103',
        'merchant_trans_id' => $cancelPaymentId,
        'amount' => '25000.00',
        'action' => '0',
        'error' => '0',
        'error_note' => 'Success',
        'sign_time' => '2026-03-11 11:02:00',
    ]);

    $this->post('/api/v1/webhooks/click', $preparePayload)
        ->assertOk()
        ->assertJsonPath('error', 0);

    $this->post('/api/v1/webhooks/click', clickPayload([
        'click_trans_id' => '71003',
        'click_paydoc_id' => '910103',
        'merchant_trans_id' => $cancelPaymentId,
        'merchant_prepare_id' => '71003',
        'amount' => '25000.00',
        'action' => '1',
        'error' => '-501',
        'error_note' => 'Canceled by Click',
        'sign_time' => '2026-03-11 11:03:00',
    ]))
        ->assertOk()
        ->assertJsonPath('error', -9)
        ->assertJsonPath('error_note', 'Transaction cancelled');

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/payments/'.$cancelPaymentId.'/status')
        ->assertOk()
        ->assertJsonPath('data.status', 'canceled')
        ->assertJsonPath('data.provider.payment_id', '910103')
        ->assertJsonPath('data.provider.status', 'cancelled');

    billingRefundPayment($this, $managerToken, $tenantId, $cancelPaymentId, [
        'reason' => 'manual click refund',
    ], 'click-errors-refund-1')
        ->assertStatus(409);
});

function clickPayload(array $attributes): array
{
    $payload = array_merge([
        'service_id' => '36',
    ], $attributes);
    $merchantPrepareId = ($payload['action'] ?? '0') === '1'
        ? (string) ($payload['merchant_prepare_id'] ?? '')
        : '';

    $payload['sign_string'] = $payload['sign_string'] ?? md5(
        (string) $payload['click_trans_id']
        .(string) $payload['service_id']
        .'test-click-secret'
        .(string) $payload['merchant_trans_id']
        .$merchantPrepareId
        .(string) $payload['amount']
        .(string) $payload['action']
        .(string) $payload['sign_time'],
    );

    return $payload;
}

function clickPrepareIssuedInvoice($testCase, string $suffix): array
{
    $manager = User::factory()->create([
        'email' => sprintf('click.%s.manager@openai.com', $suffix),
        'password' => bcrypt('secret-password'),
    ]);

    $managerToken = billingIssueBearerToken($testCase, sprintf('click.%s.manager@openai.com', $suffix));
    $tenantId = billingCreateTenant($testCase, $managerToken, 'Click '.$suffix.' Tenant')->json('data.id');

    billingGrantPermissions($manager, $tenantId, [
        'billing.view',
        'billing.manage',
        'integrations.manage',
        'patients.manage',
    ]);

    $patientId = billingCreatePatient($testCase, $managerToken, $tenantId, [
        'first_name' => 'Click',
        'last_name' => 'Patient '.$suffix,
    ])->assertCreated()->json('data.id');

    $serviceId = billingCreateService($testCase, $managerToken, $tenantId, [
        'code' => 'CLICK-'.$suffix,
        'name' => 'Click Consultation '.$suffix,
    ], 'click-'.$suffix.'-service-1')->assertCreated()->json('data.id');

    $invoiceId = billingCreateInvoice($testCase, $managerToken, $tenantId, [
        'patient_id' => $patientId,
        'currency' => 'UZS',
    ], 'click-'.$suffix.'-invoice-1')->assertCreated()->json('data.id');

    billingAddInvoiceItem($testCase, $managerToken, $tenantId, $invoiceId, [
        'service_id' => $serviceId,
        'quantity' => '1',
        'unit_price_amount' => '95000',
    ], 'click-'.$suffix.'-invoice-item-1')->assertCreated();

    billingIssueInvoice($testCase, $managerToken, $tenantId, $invoiceId, 'click-'.$suffix.'-issue-1')->assertOk();

    return [$manager, $managerToken, $tenantId, $invoiceId];
}
