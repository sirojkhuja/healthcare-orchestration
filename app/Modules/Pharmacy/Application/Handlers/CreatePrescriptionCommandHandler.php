<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Commands\CreatePrescriptionCommand;
use App\Modules\Pharmacy\Application\Data\PrescriptionData;
use App\Modules\Pharmacy\Application\Services\PrescriptionAdministrationService;

final class CreatePrescriptionCommandHandler
{
    public function __construct(
        private readonly PrescriptionAdministrationService $prescriptionAdministrationService,
    ) {}

    public function handle(CreatePrescriptionCommand $command): PrescriptionData
    {
        return $this->prescriptionAdministrationService->create($command->attributes);
    }
}
