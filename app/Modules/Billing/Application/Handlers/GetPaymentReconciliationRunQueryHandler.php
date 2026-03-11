<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Data\PaymentReconciliationRunData;
use App\Modules\Billing\Application\Queries\GetPaymentReconciliationRunQuery;
use App\Modules\Billing\Application\Services\PaymentReconciliationReadService;

final class GetPaymentReconciliationRunQueryHandler
{
    public function __construct(
        private readonly PaymentReconciliationReadService $paymentReconciliationReadService,
    ) {}

    public function handle(GetPaymentReconciliationRunQuery $query): PaymentReconciliationRunData
    {
        return $this->paymentReconciliationReadService->get($query->runId);
    }
}
