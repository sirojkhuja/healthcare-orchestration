<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\FailPaymentCommand;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Services\PaymentWorkflowService;

final class FailPaymentCommandHandler
{
    public function __construct(
        private readonly PaymentWorkflowService $paymentWorkflowService,
    ) {}

    public function handle(FailPaymentCommand $command): PaymentData
    {
        return $this->paymentWorkflowService->fail(
            paymentId: $command->paymentId,
            failureCode: $command->failureCode,
            failureMessage: $command->failureMessage,
            providerStatus: $command->providerStatus,
        );
    }
}
