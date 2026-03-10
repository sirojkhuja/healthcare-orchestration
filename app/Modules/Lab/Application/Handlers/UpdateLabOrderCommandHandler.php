<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Commands\UpdateLabOrderCommand;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Services\LabOrderAdministrationService;

final class UpdateLabOrderCommandHandler
{
    public function __construct(
        private readonly LabOrderAdministrationService $labOrderAdministrationService,
    ) {}

    public function handle(UpdateLabOrderCommand $command): LabOrderData
    {
        return $this->labOrderAdministrationService->update($command->orderId, $command->attributes);
    }
}
