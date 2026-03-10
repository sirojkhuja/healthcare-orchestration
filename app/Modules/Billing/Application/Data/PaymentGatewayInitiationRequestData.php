<?php

namespace App\Modules\Billing\Application\Data;

final readonly class PaymentGatewayInitiationRequestData
{
    public function __construct(
        public string $paymentId,
        public string $tenantId,
        public string $invoiceId,
        public string $invoiceNumber,
        public string $providerKey,
        public string $amount,
        public string $currency,
        public ?string $description,
    ) {}
}
