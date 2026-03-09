<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Queries\ListPatientsQuery;
use App\Modules\Patient\Application\Services\PatientAdministrationService;

final class ListPatientsQueryHandler
{
    public function __construct(
        private readonly PatientAdministrationService $patientAdministrationService,
    ) {}

    /**
     * @return list<\App\Modules\Patient\Application\Data\PatientData>
     */
    public function handle(ListPatientsQuery $query): array
    {
        return $this->patientAdministrationService->list();
    }
}
