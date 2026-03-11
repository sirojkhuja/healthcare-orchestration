<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Queries\ListPaymentsQuery;
use App\Modules\Billing\Application\Services\PaymentReadService;

final class ListPaymentsQueryHandler
{
    public function __construct(
        private readonly PaymentReadService $paymentReadService,
    ) {}

    /**
     * @return list<PaymentData>
     */
    public function handle(ListPaymentsQuery $query): array
    {
        return $this->paymentReadService->list($query->criteria);
    }
}
