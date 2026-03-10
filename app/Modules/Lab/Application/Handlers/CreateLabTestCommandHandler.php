<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Commands\CreateLabTestCommand;
use App\Modules\Lab\Application\Data\LabTestData;
use App\Modules\Lab\Application\Services\LabTestCatalogService;

final class CreateLabTestCommandHandler
{
    public function __construct(
        private readonly LabTestCatalogService $labTestCatalogService,
    ) {}

    public function handle(CreateLabTestCommand $command): LabTestData
    {
        return $this->labTestCatalogService->create($command->attributes);
    }
}
