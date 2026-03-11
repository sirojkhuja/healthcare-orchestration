<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class CapturePaymentCommand
{
    public function __construct(
        public string $paymentId,
    ) {}
}
