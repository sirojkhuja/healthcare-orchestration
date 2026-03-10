<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Commands\ReconcileLabOrdersCommand;
use App\Modules\Lab\Application\Data\ReconcileLabOrdersData;
use App\Modules\Lab\Application\Services\LabReconciliationService;

final class ReconcileLabOrdersCommandHandler
{
    public function __construct(
        private readonly LabReconciliationService $labReconciliationService,
    ) {}

    public function handle(ReconcileLabOrdersCommand $command): ReconcileLabOrdersData
    {
        return $this->labReconciliationService->reconcile(
            labProviderKey: $command->labProviderKey,
            orderIds: $command->orderIds,
            limit: $command->limit,
        );
    }
}
