<?php

use App\Modules\Billing\Infrastructure\Integrations\ManualPaymentGateway;

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
    ],
];
