<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Commands\DeleteLabTestCommand;
use App\Modules\Lab\Application\Data\LabTestData;
use App\Modules\Lab\Application\Services\LabTestCatalogService;

final class DeleteLabTestCommandHandler
{
    public function __construct(
        private readonly LabTestCatalogService $labTestCatalogService,
    ) {}

    public function handle(DeleteLabTestCommand $command): LabTestData
    {
        return $this->labTestCatalogService->delete($command->testId);
    }
}
