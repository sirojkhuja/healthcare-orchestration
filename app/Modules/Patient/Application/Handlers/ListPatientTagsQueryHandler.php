<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Data\PatientTagListData;
use App\Modules\Patient\Application\Queries\ListPatientTagsQuery;
use App\Modules\Patient\Application\Services\PatientTagService;

final class ListPatientTagsQueryHandler
{
    public function __construct(
        private readonly PatientTagService $patientTagService,
    ) {}

    public function handle(ListPatientTagsQuery $query): PatientTagListData
    {
        return $this->patientTagService->list($query->patientId);
    }
}
