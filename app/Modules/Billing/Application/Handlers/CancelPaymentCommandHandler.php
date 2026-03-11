<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\CancelPaymentCommand;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Services\PaymentGatewayOperationService;

final class CancelPaymentCommandHandler
{
    public function __construct(
        private readonly PaymentGatewayOperationService $paymentGatewayOperationService,
    ) {}

    public function handle(CancelPaymentCommand $command): PaymentData
    {
        return $this->paymentGatewayOperationService->cancel(
            paymentId: $command->paymentId,
            reason: $command->reason,
        );
    }
}
