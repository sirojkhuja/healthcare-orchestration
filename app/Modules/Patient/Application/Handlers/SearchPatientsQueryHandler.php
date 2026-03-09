<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Queries\SearchPatientsQuery;
use App\Modules\Patient\Application\Services\PatientReadService;

final class SearchPatientsQueryHandler
{
    public function __construct(
        private readonly PatientReadService $patientReadService,
    ) {}

    /**
     * @return list<\App\Modules\Patient\Application\Data\PatientData>
     */
    public function handle(SearchPatientsQuery $query): array
    {
        return $this->patientReadService->search($query->criteria);
    }
}
