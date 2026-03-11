<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\InitiatePaymentCommand;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Services\PaymentGatewayOperationService;

final class InitiatePaymentCommandHandler
{
    public function __construct(
        private readonly PaymentGatewayOperationService $paymentGatewayOperationService,
    ) {}

    public function handle(InitiatePaymentCommand $command): PaymentData
    {
        return $this->paymentGatewayOperationService->initiate($command->attributes);
    }
}
