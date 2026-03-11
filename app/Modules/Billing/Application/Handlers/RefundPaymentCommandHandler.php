<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\RefundPaymentCommand;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Services\PaymentGatewayOperationService;

final class RefundPaymentCommandHandler
{
    public function __construct(
        private readonly PaymentGatewayOperationService $paymentGatewayOperationService,
    ) {}

    public function handle(RefundPaymentCommand $command): PaymentData
    {
        return $this->paymentGatewayOperationService->refund(
            paymentId: $command->paymentId,
            reason: $command->reason,
        );
    }
}
