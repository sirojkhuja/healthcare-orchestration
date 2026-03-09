<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Data\PatientContactData;
use App\Modules\Patient\Application\Queries\ListPatientContactsQuery;
use App\Modules\Patient\Application\Services\PatientContactService;

final class ListPatientContactsQueryHandler
{
    public function __construct(
        private readonly PatientContactService $patientContactService,
    ) {}

    /**
     * @return list<PatientContactData>
     */
    public function handle(ListPatientContactsQuery $query): array
    {
        return $this->patientContactService->list($query->patientId);
    }
}
