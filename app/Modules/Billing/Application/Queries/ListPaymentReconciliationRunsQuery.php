<?php

namespace App\Modules\Billing\Application\Queries;

final readonly class ListPaymentReconciliationRunsQuery
{
    public function __construct(
        public ?string $providerKey = null,
        public int $limit = 25,
    ) {}
}
