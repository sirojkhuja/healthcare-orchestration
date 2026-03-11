<?php

namespace App\Modules\Billing\Application\Queries;

final readonly class GetPaymentStatusQuery
{
    public function __construct(
        public string $paymentId,
    ) {}
}
