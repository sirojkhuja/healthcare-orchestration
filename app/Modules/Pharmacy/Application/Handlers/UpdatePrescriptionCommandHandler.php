<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Commands\UpdatePrescriptionCommand;
use App\Modules\Pharmacy\Application\Data\PrescriptionData;
use App\Modules\Pharmacy\Application\Services\PrescriptionAdministrationService;

final class UpdatePrescriptionCommandHandler
{
    public function __construct(
        private readonly PrescriptionAdministrationService $prescriptionAdministrationService,
    ) {}

    public function handle(UpdatePrescriptionCommand $command): PrescriptionData
    {
        return $this->prescriptionAdministrationService->update($command->prescriptionId, $command->attributes);
    }
}
