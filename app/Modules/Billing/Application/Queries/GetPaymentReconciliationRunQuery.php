<?php

namespace App\Modules\Billing\Application\Queries;

final readonly class GetPaymentReconciliationRunQuery
{
    public function __construct(
        public string $runId,
    ) {}
}
