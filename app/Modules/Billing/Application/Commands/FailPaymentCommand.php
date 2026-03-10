<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class FailPaymentCommand
{
    public function __construct(
        public string $paymentId,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
        public ?string $providerStatus = null,
    ) {}
}
