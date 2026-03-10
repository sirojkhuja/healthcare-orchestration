<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\MarkPaymentPendingCommand;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Services\PaymentWorkflowService;

final class MarkPaymentPendingCommandHandler
{
    public function __construct(
        private readonly PaymentWorkflowService $paymentWorkflowService,
    ) {}

    public function handle(MarkPaymentPendingCommand $command): PaymentData
    {
        return $this->paymentWorkflowService->markPending(
            paymentId: $command->paymentId,
            providerPaymentId: $command->providerPaymentId,
            providerStatus: $command->providerStatus,
            checkoutUrl: $command->checkoutUrl,
        );
    }
}
