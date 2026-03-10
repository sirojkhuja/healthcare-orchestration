<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Commands\DeletePrescriptionCommand;
use App\Modules\Pharmacy\Application\Data\PrescriptionData;
use App\Modules\Pharmacy\Application\Services\PrescriptionAdministrationService;

final class DeletePrescriptionCommandHandler
{
    public function __construct(
        private readonly PrescriptionAdministrationService $prescriptionAdministrationService,
    ) {}

    public function handle(DeletePrescriptionCommand $command): PrescriptionData
    {
        return $this->prescriptionAdministrationService->delete($command->prescriptionId);
    }
}
