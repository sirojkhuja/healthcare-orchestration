<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\RefundPaymentCommand;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Services\PaymentWorkflowService;

final class RefundPaymentCommandHandler
{
    public function __construct(
        private readonly PaymentWorkflowService $paymentWorkflowService,
    ) {}

    public function handle(RefundPaymentCommand $command): PaymentData
    {
        return $this->paymentWorkflowService->refund(
            paymentId: $command->paymentId,
            supportsRefunds: $command->supportsRefunds,
            reason: $command->reason,
            providerStatus: $command->providerStatus,
        );
    }
}
