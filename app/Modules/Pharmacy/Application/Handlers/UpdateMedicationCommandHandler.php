<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Commands\UpdateMedicationCommand;
use App\Modules\Pharmacy\Application\Data\MedicationData;
use App\Modules\Pharmacy\Application\Services\MedicationCatalogService;

final class UpdateMedicationCommandHandler
{
    public function __construct(
        private readonly MedicationCatalogService $medicationCatalogService,
    ) {}

    public function handle(UpdateMedicationCommand $command): MedicationData
    {
        return $this->medicationCatalogService->update($command->medicationId, $command->attributes);
    }
}
