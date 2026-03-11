<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/../Billing/BillingTestSupport.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('billing.payment_gateways.payme.merchant_id', 'test-payme-merchant');
    config()->set('billing.payment_gateways.payme.merchant_key', 'test-payme-key');
    config()->set('billing.payment_gateways.payme.merchant_login', 'Paycom');
    config()->set('billing.payment_gateways.payme.checkout_base_url', 'https://checkout.paycom.uz');
    config()->set('billing.payment_gateways.payme.checkout_language', 'uz');
    config()->set('billing.payment_gateways.payme.callback', 'https://example.test/payme-return');
    config()->set('billing.payment_gateways.payme.callback_timeout', 15000);
    config()->set('billing.payment_gateways.payme.currency', 'UZS');
});

it('initiates payme payments with a direct checkout url', function (): void {
    [$manager, $managerToken, $tenantId, $invoiceId] = paymePrepareIssuedInvoice($this, 'checkout');

    $paymentResponse = billingInitiatePayment($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'payme',
        'amount' => '95000',
        'description' => 'Payme checkout request',
    ], 'payme-checkout-initiate-1')
        ->assertCreated()
        ->assertJsonPath('status', 'payment_initiated')
        ->assertJsonPath('data.status', 'initiated')
        ->assertJsonPath('data.provider.key', 'payme')
        ->assertJsonPath('data.provider.status', 'checkout_pending');

    $paymentId = $paymentResponse->json('data.id');
    $checkoutUrl = (string) $paymentResponse->json('data.provider.checkout_url');

    expect($checkoutUrl)->toStartWith('https://checkout.paycom.uz/');

    $encodedPayload = ltrim((string) parse_url($checkoutUrl, PHP_URL_PATH), '/');
    $decodedPayload = base64_decode($encodedPayload, true);

    expect($decodedPayload)->toContain('m=test-payme-merchant');
    expect($decodedPayload)->toContain('ac.payment_id='.$paymentId);
    expect($decodedPayload)->toContain('a=9500000');
    expect($decodedPayload)->toContain('c=https://example.test/payme-return');
    expect($decodedPayload)->toContain('ct=15000');
    expect($decodedPayload)->toContain('cr=UZS');
    expect($decodedPayload)->toContain('l=uz');
});

it('processes payme create perform check statement and verification flows', function (): void {
    [$manager, $managerToken, $tenantId, $invoiceId] = paymePrepareIssuedInvoice($this, 'lifecycle');
    $paymentId = billingInitiatePayment($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'payme',
        'amount' => '95000',
    ], 'payme-lifecycle-initiate-1')->assertCreated()->json('data.id');

    $authorization = paymeAuthorizationHeader();
    $createPayload = [
        'id' => 1001,
        'method' => 'CreateTransaction',
        'params' => [
            'id' => 'payme-transaction-1001',
            'time' => 1710141000000,
            'amount' => 9500000,
            'account' => [
                'payment_id' => $paymentId,
            ],
        ],
    ];

    $this->withHeader('Authorization', $authorization)
        ->postJson('/api/v1/webhooks/payme', [
            'id' => 1000,
            'method' => 'CheckPerformTransaction',
            'params' => [
                'amount' => 9500000,
                'account' => [
                    'payment_id' => $paymentId,
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('result.allow', true);

    $this->withHeader('Authorization', $authorization)
        ->postJson('/api/v1/webhooks/payme', $createPayload)
        ->assertOk()
        ->assertJsonPath('result.state', 1)
        ->assertJsonPath('result.transaction', $paymentId);

    $this->withHeader('Authorization', $authorization)
        ->postJson('/api/v1/webhooks/payme', $createPayload)
        ->assertOk()
        ->assertJsonPath('result.state', 1);

    $this->withHeader('Authorization', $authorization)
        ->postJson('/api/v1/webhooks/payme', [
            'id' => 1002,
            'method' => 'PerformTransaction',
            'params' => [
                'id' => 'payme-transaction-1001',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('result.state', 2)
        ->assertJsonPath('result.transaction', $paymentId);

    $this->withHeader('Authorization', $authorization)
        ->postJson('/api/v1/webhooks/payme', [
            'id' => 1003,
            'method' => 'CheckTransaction',
            'params' => [
                'id' => 'payme-transaction-1001',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('result.state', 2)
        ->assertJsonPath('result.transaction', $paymentId);

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/payments/'.$paymentId.'/status')
        ->assertOk()
        ->assertJsonPath('data.status', 'captured')
        ->assertJsonPath('data.provider.payment_id', 'payme-transaction-1001')
        ->assertJsonPath('data.provider.status', '2');

    $this->withHeader('Authorization', $authorization)
        ->postJson('/api/v1/webhooks/payme', [
            'id' => 1004,
            'method' => 'GetStatement',
            'params' => [
                'from' => 1710140000000,
                'to' => 1710142000000,
            ],
        ])
        ->assertOk()
        ->assertJsonCount(1, 'result.transactions')
        ->assertJsonPath('result.transactions.0.id', 'payme-transaction-1001')
        ->assertJsonPath('result.transactions.0.transaction', $paymentId)
        ->assertJsonPath('result.transactions.0.amount', 9500000)
        ->assertJsonPath('result.transactions.0.state', 2)
        ->assertJsonPath('result.transactions.0.account.payment_id', $paymentId);

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/webhooks/payme:verify', [
            'authorization' => $authorization,
            'payload' => $createPayload,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'payme_webhook_verified')
        ->assertJsonPath('data.provider_key', 'payme')
        ->assertJsonPath('data.valid', true);
});

it('maps payme cancel flows unsupported manual actions and provider errors correctly', function (): void {
    [$manager, $managerToken, $tenantId, $invoiceId] = paymePrepareIssuedInvoice($this, 'errors');
    $authorization = paymeAuthorizationHeader();

    $this->withHeader('Authorization', 'Basic '.base64_encode('Paycom:wrong-key'))
        ->postJson('/api/v1/webhooks/payme', [
            'id' => 2000,
            'method' => 'CheckPerformTransaction',
            'params' => [
                'amount' => 9500000,
                'account' => ['payment_id' => 'missing-payment'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('error.code', -32504);

    $this->withHeader('Authorization', $authorization)
        ->postJson('/api/v1/webhooks/payme', [
            'id' => 2001,
            'method' => 'CreateTransaction',
            'params' => [
                'id' => 'payme-invalid-payment',
                'time' => 1710143000000,
                'amount' => 9500000,
                'account' => ['payment_id' => 'missing-payment'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('error.code', -31050)
        ->assertJsonPath('error.data', 'account.payment_id');

    $mismatchPaymentId = billingInitiatePayment($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'payme',
        'amount' => '95000',
    ], 'payme-errors-initiate-1')->assertCreated()->json('data.id');

    $this->withHeader('Authorization', $authorization)
        ->postJson('/api/v1/webhooks/payme', [
            'id' => 2002,
            'method' => 'CheckPerformTransaction',
            'params' => [
                'amount' => 1,
                'account' => ['payment_id' => $mismatchPaymentId],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('error.code', -31001);

    $cancelPaymentId = billingInitiatePayment($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'payme',
        'amount' => '25000',
    ], 'payme-errors-initiate-2')->assertCreated()->json('data.id');

    $this->withHeader('Authorization', $authorization)
        ->postJson('/api/v1/webhooks/payme', [
            'id' => 2003,
            'method' => 'CreateTransaction',
            'params' => [
                'id' => 'payme-cancel-tx',
                'time' => 1710144000000,
                'amount' => 2500000,
                'account' => ['payment_id' => $cancelPaymentId],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('result.state', 1);

    $this->withHeader('Authorization', $authorization)
        ->postJson('/api/v1/webhooks/payme', [
            'id' => 2004,
            'method' => 'CancelTransaction',
            'params' => [
                'id' => 'payme-cancel-tx',
                'reason' => 3,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('result.state', -1)
        ->assertJsonPath('result.transaction', $cancelPaymentId);

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/payments/'.$cancelPaymentId.'/status')
        ->assertOk()
        ->assertJsonPath('data.status', 'canceled')
        ->assertJsonPath('data.provider.status', '-1');

    $refundPaymentId = billingInitiatePayment($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'provider_key' => 'payme',
        'amount' => '15000',
    ], 'payme-errors-initiate-3')->assertCreated()->json('data.id');

    $this->withHeader('Authorization', $authorization)
        ->postJson('/api/v1/webhooks/payme', [
            'id' => 2005,
            'method' => 'CreateTransaction',
            'params' => [
                'id' => 'payme-refund-tx',
                'time' => 1710145000000,
                'amount' => 1500000,
                'account' => ['payment_id' => $refundPaymentId],
            ],
        ])
        ->assertOk();

    $this->withHeader('Authorization', $authorization)
        ->postJson('/api/v1/webhooks/payme', [
            'id' => 2006,
            'method' => 'PerformTransaction',
            'params' => [
                'id' => 'payme-refund-tx',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('result.state', 2);

    billingRefundPayment($this, $managerToken, $tenantId, $refundPaymentId, [
        'reason' => 'Manual refund should be blocked.',
    ], 'payme-errors-manual-refund')
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    $this->withHeader('Authorization', $authorization)
        ->postJson('/api/v1/webhooks/payme', [
            'id' => 2007,
            'method' => 'CancelTransaction',
            'params' => [
                'id' => 'payme-refund-tx',
                'reason' => 5,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('result.state', -2)
        ->assertJsonPath('result.transaction', $refundPaymentId);

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/payments/'.$refundPaymentId.'/status')
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded')
        ->assertJsonPath('data.provider.status', '-2');
});

function paymeAuthorizationHeader(): string
{
    return 'Basic '.base64_encode('Paycom:test-payme-key');
}

/**
 * @return array{0: User, 1: string, 2: string, 3: string}
 */
function paymePrepareIssuedInvoice($testCase, string $suffix): array
{
    $manager = User::factory()->create([
        'email' => sprintf('payme.%s.manager@openai.com', $suffix),
        'password' => 'secret-password',
    ]);

    $managerToken = billingIssueBearerToken($testCase, sprintf('payme.%s.manager@openai.com', $suffix));
    $tenantId = billingCreateTenant($testCase, $managerToken, 'Payme '.$suffix.' Tenant')->json('data.id');

    billingGrantPermissions($manager, $tenantId, [
        'billing.view',
        'billing.manage',
        'integrations.manage',
        'patients.manage',
    ]);

    $patientId = billingCreatePatient($testCase, $managerToken, $tenantId, [
        'first_name' => 'Dilshod',
        'last_name' => 'Karimov',
    ])->assertCreated()->json('data.id');
    $serviceId = billingCreateService($testCase, $managerToken, $tenantId, [
        'code' => 'payme-'.$suffix.'-visit',
        'name' => 'Payme '.$suffix.' Visit',
    ], 'payme-'.$suffix.'-service-1')->assertCreated()->json('data.id');
    $invoiceId = billingCreateInvoice($testCase, $managerToken, $tenantId, [
        'patient_id' => $patientId,
        'currency' => 'UZS',
        'invoice_date' => '2026-03-11',
    ], 'payme-'.$suffix.'-invoice-1')->assertCreated()->json('data.id');

    billingAddInvoiceItem($testCase, $managerToken, $tenantId, $invoiceId, [
        'service_id' => $serviceId,
        'quantity' => '1',
        'unit_price_amount' => '95000',
    ], 'payme-'.$suffix.'-item-1')->assertCreated();
    billingIssueInvoice($testCase, $managerToken, $tenantId, $invoiceId, 'payme-'.$suffix.'-issue-1')->assertOk();

    return [$manager, $managerToken, $tenantId, $invoiceId];
}
