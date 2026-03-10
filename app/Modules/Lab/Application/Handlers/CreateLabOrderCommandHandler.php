<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Commands\CreateLabOrderCommand;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Services\LabOrderAdministrationService;

final class CreateLabOrderCommandHandler
{
    public function __construct(
        private readonly LabOrderAdministrationService $labOrderAdministrationService,
    ) {}

    public function handle(CreateLabOrderCommand $command): LabOrderData
    {
        return $this->labOrderAdministrationService->create($command->attributes);
    }
}
