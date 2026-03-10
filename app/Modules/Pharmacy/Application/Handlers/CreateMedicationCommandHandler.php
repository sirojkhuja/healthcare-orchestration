<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Commands\CreateMedicationCommand;
use App\Modules\Pharmacy\Application\Data\MedicationData;
use App\Modules\Pharmacy\Application\Services\MedicationCatalogService;

final class CreateMedicationCommandHandler
{
    public function __construct(
        private readonly MedicationCatalogService $medicationCatalogService,
    ) {}

    public function handle(CreateMedicationCommand $command): MedicationData
    {
        return $this->medicationCatalogService->create($command->attributes);
    }
}
