<?php

namespace App\Modules\Billing\Application\Queries;

use App\Modules\Billing\Application\Data\PaymentListCriteria;

final readonly class ListPaymentsQuery
{
    public function __construct(
        public PaymentListCriteria $criteria,
    ) {}
}
