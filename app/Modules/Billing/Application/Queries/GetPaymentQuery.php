<?php

namespace App\Modules\Billing\Application\Queries;

final readonly class GetPaymentQuery
{
    public function __construct(
        public string $paymentId,
    ) {}
}
