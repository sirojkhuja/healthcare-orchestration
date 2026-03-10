<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\CapturePaymentCommand;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Services\PaymentWorkflowService;

final class CapturePaymentCommandHandler
{
    public function __construct(
        private readonly PaymentWorkflowService $paymentWorkflowService,
    ) {}

    public function handle(CapturePaymentCommand $command): PaymentData
    {
        return $this->paymentWorkflowService->capture(
            paymentId: $command->paymentId,
            providerStatus: $command->providerStatus,
        );
    }
}
