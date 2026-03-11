<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\ReconcilePaymentsCommand;
use App\Modules\Billing\Application\Data\PaymentReconciliationRunData;
use App\Modules\Billing\Application\Services\PaymentReconciliationService;

final class ReconcilePaymentsCommandHandler
{
    public function __construct(
        private readonly PaymentReconciliationService $paymentReconciliationService,
    ) {}

    public function handle(ReconcilePaymentsCommand $command): PaymentReconciliationRunData
    {
        return $this->paymentReconciliationService->reconcile(
            providerKey: $command->providerKey,
            paymentIds: $command->paymentIds,
            limit: $command->limit,
        );
    }
}
