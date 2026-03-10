<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Commands\UpdateLabTestCommand;
use App\Modules\Lab\Application\Data\LabTestData;
use App\Modules\Lab\Application\Services\LabTestCatalogService;

final class UpdateLabTestCommandHandler
{
    public function __construct(
        private readonly LabTestCatalogService $labTestCatalogService,
    ) {}

    public function handle(UpdateLabTestCommand $command): LabTestData
    {
        return $this->labTestCatalogService->update($command->testId, $command->attributes);
    }
}
