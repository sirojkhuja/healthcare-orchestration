<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class MarkPaymentPendingCommand
{
    public function __construct(
        public string $paymentId,
        public ?string $providerPaymentId = null,
        public ?string $providerStatus = null,
        public ?string $checkoutUrl = null,
    ) {}
}
