<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\InitiatePaymentCommand;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Services\PaymentAdministrationService;

final class InitiatePaymentCommandHandler
{
    public function __construct(
        private readonly PaymentAdministrationService $paymentAdministrationService,
    ) {}

    public function handle(InitiatePaymentCommand $command): PaymentData
    {
        return $this->paymentAdministrationService->initiate($command->attributes);
    }
}
