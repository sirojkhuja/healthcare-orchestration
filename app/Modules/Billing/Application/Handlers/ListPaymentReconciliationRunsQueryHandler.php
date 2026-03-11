<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Data\PaymentReconciliationRunData;
use App\Modules\Billing\Application\Queries\ListPaymentReconciliationRunsQuery;
use App\Modules\Billing\Application\Services\PaymentReconciliationReadService;

final class ListPaymentReconciliationRunsQueryHandler
{
    public function __construct(
        private readonly PaymentReconciliationReadService $paymentReconciliationReadService,
    ) {}

    /**
     * @return list<PaymentReconciliationRunData>
     */
    public function handle(ListPaymentReconciliationRunsQuery $query): array
    {
        return $this->paymentReconciliationReadService->list(
            providerKey: $query->providerKey,
            limit: $query->limit,
        );
    }
}
