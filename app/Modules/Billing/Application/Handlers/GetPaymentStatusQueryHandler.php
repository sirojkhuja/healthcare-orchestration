<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Queries\GetPaymentStatusQuery;
use App\Modules\Billing\Application\Services\PaymentReadService;

final class GetPaymentStatusQueryHandler
{
    public function __construct(
        private readonly PaymentReadService $paymentReadService,
    ) {}

    public function handle(GetPaymentStatusQuery $query): PaymentData
    {
        return $this->paymentReadService->get($query->paymentId);
    }
}
