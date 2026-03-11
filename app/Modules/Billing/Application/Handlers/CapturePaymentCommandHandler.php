<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\CapturePaymentCommand;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Services\PaymentGatewayOperationService;

final class CapturePaymentCommandHandler
{
    public function __construct(
        private readonly PaymentGatewayOperationService $paymentGatewayOperationService,
    ) {}

    public function handle(CapturePaymentCommand $command): PaymentData
    {
        return $this->paymentGatewayOperationService->capture($command->paymentId);
    }
}
