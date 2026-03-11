<?php

use App\Modules\Billing\Infrastructure\Integrations\ManualPaymentGateway;
use App\Modules\Billing\Infrastructure\Integrations\PaymePaymentGateway;

return [
    'payment_gateways' => [
        'manual' => [
            'driver' => ManualPaymentGateway::class,
            'supports_refunds' => true,
        ],
        'manual_no_refund' => [
            'driver' => ManualPaymentGateway::class,
            'supports_refunds' => false,
        ],
        'payme' => [
            'driver' => PaymePaymentGateway::class,
            'merchant_id' => env('PAYME_MERCHANT_ID', 'demo-payme-merchant'),
            'merchant_key' => env('PAYME_MERCHANT_KEY', 'demo-payme-key'),
            'merchant_login' => env('PAYME_MERCHANT_LOGIN', 'Paycom'),
            'checkout_base_url' => env('PAYME_CHECKOUT_BASE_URL', 'https://checkout.paycom.uz'),
            'checkout_language' => env('PAYME_CHECKOUT_LANGUAGE', 'uz'),
            'callback' => env('PAYME_CALLBACK_URL'),
            'callback_timeout' => env('PAYME_CALLBACK_TIMEOUT'),
            'currency' => env('PAYME_CURRENCY', 'UZS'),
            'supports_refunds' => true,
        ],
    ],
];
