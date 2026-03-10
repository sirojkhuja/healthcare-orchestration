<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Commands\DeleteLabOrderCommand;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Services\LabOrderAdministrationService;

final class DeleteLabOrderCommandHandler
{
    public function __construct(
        private readonly LabOrderAdministrationService $labOrderAdministrationService,
    ) {}

    public function handle(DeleteLabOrderCommand $command): LabOrderData
    {
        return $this->labOrderAdministrationService->delete($command->orderId);
    }
}
