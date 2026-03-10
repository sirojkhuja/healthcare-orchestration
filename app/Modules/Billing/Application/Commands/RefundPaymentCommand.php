<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class RefundPaymentCommand
{
    public function __construct(
        public string $paymentId,
        public bool $supportsRefunds,
        public ?string $reason = null,
        public ?string $providerStatus = null,
    ) {}
}
