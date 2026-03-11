<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Queries\GetPaymentQuery;
use App\Modules\Billing\Application\Services\PaymentReadService;

final class GetPaymentQueryHandler
{
    public function __construct(
        private readonly PaymentReadService $paymentReadService,
    ) {}

    public function handle(GetPaymentQuery $query): PaymentData
    {
        return $this->paymentReadService->get($query->paymentId);
    }
}
