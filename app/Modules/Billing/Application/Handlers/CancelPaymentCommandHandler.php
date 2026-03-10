<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\CancelPaymentCommand;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Services\PaymentWorkflowService;

final class CancelPaymentCommandHandler
{
    public function __construct(
        private readonly PaymentWorkflowService $paymentWorkflowService,
    ) {}

    public function handle(CancelPaymentCommand $command): PaymentData
    {
        return $this->paymentWorkflowService->cancel(
            paymentId: $command->paymentId,
            reason: $command->reason,
            providerStatus: $command->providerStatus,
        );
    }
}
