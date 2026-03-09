<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Data\PatientExportData;
use App\Modules\Patient\Application\Queries\ExportPatientsQuery;
use App\Modules\Patient\Application\Services\PatientReadService;

final class ExportPatientsQueryHandler
{
    public function __construct(
        private readonly PatientReadService $patientReadService,
    ) {}

    public function handle(ExportPatientsQuery $query): PatientExportData
    {
        return $this->patientReadService->export($query);
    }
}
