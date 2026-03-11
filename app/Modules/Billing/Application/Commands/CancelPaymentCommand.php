<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class CancelPaymentCommand
{
    public function __construct(
        public string $paymentId,
        public ?string $reason = null,
    ) {}
}
