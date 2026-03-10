<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Commands\DeleteMedicationCommand;
use App\Modules\Pharmacy\Application\Data\MedicationData;
use App\Modules\Pharmacy\Application\Services\MedicationCatalogService;

final class DeleteMedicationCommandHandler
{
    public function __construct(
        private readonly MedicationCatalogService $medicationCatalogService,
    ) {}

    public function handle(DeleteMedicationCommand $command): MedicationData
    {
        return $this->medicationCatalogService->delete($command->medicationId);
    }
}
