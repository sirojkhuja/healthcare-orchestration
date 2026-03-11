<?php

use App\Modules\Billing\Infrastructure\Integrations\ClickPaymentGateway;
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
        'click' => [
            'driver' => ClickPaymentGateway::class,
            'merchant_id' => env('CLICK_MERCHANT_ID', '46'),
            'service_id' => env('CLICK_SERVICE_ID', '36'),
            'merchant_user_id' => env('CLICK_MERCHANT_USER_ID'),
            'secret_key' => env('CLICK_SECRET_KEY', 'demo-click-secret'),
            'payment_base_url' => env('CLICK_PAYMENT_BASE_URL', 'https://my.click.uz/services/pay'),
            'return_url' => env('CLICK_RETURN_URL'),
            'card_type' => env('CLICK_CARD_TYPE'),
            'supports_refunds' => false,
        ],
    ],
];
