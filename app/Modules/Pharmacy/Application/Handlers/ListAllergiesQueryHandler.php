<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Data\PatientAllergyData;
use App\Modules\Pharmacy\Application\Queries\ListAllergiesQuery;
use App\Modules\Pharmacy\Application\Services\PatientAllergyService;

final class ListAllergiesQueryHandler
{
    public function __construct(
        private readonly PatientAllergyService $patientAllergyService,
    ) {}

    /**
     * @return list<PatientAllergyData>
     */
    public function handle(ListAllergiesQuery $query): array
    {
        return $this->patientAllergyService->list($query->patientId);
    }
}
