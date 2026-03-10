<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Data\PrescriptionData;
use App\Modules\Pharmacy\Application\Queries\GetPrescriptionQuery;
use App\Modules\Pharmacy\Application\Services\PrescriptionAdministrationService;

final class GetPrescriptionQueryHandler
{
    public function __construct(
        private readonly PrescriptionAdministrationService $prescriptionAdministrationService,
    ) {}

    public function handle(GetPrescriptionQuery $query): PrescriptionData
    {
        return $this->prescriptionAdministrationService->get($query->prescriptionId);
    }
}
